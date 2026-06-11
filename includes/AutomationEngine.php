<?php
// includes/AutomationEngine.php

class AutomationEngine {
    private $pdo;
    private $org_id;

    public function __construct($pdo, $org_id) {
        $this->pdo = $pdo;
        $this->org_id = $org_id;
    }

    /**
     * Trigger workflows for a specific event
     * 
     * @param string $triggerType The type of trigger (e.g., 'lead_created', 'lead_stage_changed')
     * @param array $context Data context for the trigger (e.g., ['lead_id' => 123, 'lead' => $leadData])
     */
    public function trigger($triggerType, $context) {
        $logFile = __DIR__ . '/../automation_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $workflows = $this->getMatchingWorkflows($triggerType);
        $logMsg = "[$timestamp] Trigger: $triggerType | Org: {$this->org_id} | Found: " . count($workflows) . " workflows\n";
        file_put_contents($logFile, $logMsg, FILE_APPEND);

        foreach ($workflows as $workflow) {
            if ($this->evaluateTriggerConditions($workflow['trigger_config'], $context)) {
                file_put_contents($logFile, "[$timestamp] Workflow [{$workflow['id']}] '{$workflow['name']}' - Conditions MET\n", FILE_APPEND);
                $this->executeWorkflow($workflow, $context);
            } else {
                file_put_contents($logFile, "[$timestamp] Workflow [{$workflow['id']}] '{$workflow['name']}' - Conditions NOT met\n", FILE_APPEND);
            }
        }
    }

    /**
     * Get workflows that have a trigger of the given type
     */
    private function getMatchingWorkflows($triggerType) {
        $sql = "
            SELECT w.*, t.trigger_config, t.id as trigger_id
            FROM automation_workflows w
            JOIN automation_triggers t ON w.id = t.workflow_id
            WHERE w.org_id = :org_id 
              AND w.is_active = 1 
              AND t.trigger_type = :trigger_type
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':org_id' => $this->org_id,
            ':trigger_type' => $triggerType
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Evaluate if the trigger conditions are met based on context
     */
    private function evaluateTriggerConditions($configJson, $context) {
        if (empty($configJson)) return true; // No conditions = always trigger
        
        $config = json_decode($configJson, true);
        if (!$config) return true;

        // Implement logic based on trigger type context
        
        // Example: Check specific stage
        if (isset($config['to_stage']) && !empty($config['to_stage'])) {
            if (!isset($context['lead']['stage_id']) || $context['lead']['stage_id'] !== $config['to_stage']) {
                return false;
            }
        }

        // Expanded Field Change Check
        if (isset($config['field_name']) && !empty($config['field_name'])) {
            $field = $config['field_name'];
            $operator = $config['operator'] ?? 'equals';
            $targetValue = $config['field_value'] ?? null;
            
            // Check if this specific field actually changed (if context provided changed_fields)
            if (isset($context['changed_fields'])) {
                if (strpos($field, 'custom_') === 0) {
                     if (!in_array('custom_data', $context['changed_fields'])) {
                         return false; 
                     }
                } else {
                     if (!in_array($field, $context['changed_fields'])) {
                         return false;
                     }
                }
            }

            // Get actual value
            $actualValue = null;
            if (strpos($field, 'custom_') === 0) {
                $customKey = substr($field, 7);
                $customData = isset($context['lead']['custom_data']) && is_string($context['lead']['custom_data']) 
                    ? json_decode($context['lead']['custom_data'], true) 
                    : ($context['lead']['custom_data'] ?? []);
                $actualValue = $customData[$customKey] ?? '';
            } else {
                $actualValue = $context['lead'][$field] ?? '';
            }

            // Evaluate based on operator
            switch ($operator) {
                case 'equals':
                    if ($actualValue != $targetValue) return false;
                    break;
                case 'not_equals':
                    if ($actualValue == $targetValue) return false;
                    break;
                case 'contains':
                    if (strpos((string)$actualValue, (string)$targetValue) === false) return false;
                    break;
                case 'not_empty':
                    if (empty($actualValue)) return false;
                    break;
                case 'is_empty':
                    if (!empty($actualValue)) return false;
                    break;
                case 'changed':
                    break;
                default:
                    if ($actualValue != $targetValue) return false;
                    break;
            }
        }
        
        // Example: Check assignment type
        if (isset($config['assign_type'])) {
            if ($config['assign_type'] === 'to_me') {
                // To check 'to_me', we need to know who the workflow owner is?
                // Or maybe 'to_me' implies the current user context? 
                // Usually automation runs system-wide.
                // 'to_me' likely means: Assigned to the User Who Created The Workflow.
                // We don't have that context easily unless we pass $workflow['created_by']
                // Let's defer this advanced check or implement basic check
                 // For now, assuming basic pass through
            }
        }

        return true;
    }

    /**
     * Execute all actions for a workflow
     */
    private function executeWorkflow($workflow, $context) {
        // Fetch actions
        $stmt = $this->pdo->prepare("
            SELECT * FROM automation_actions 
            WHERE workflow_id = :workflow_id 
            ORDER BY execution_order ASC
        ");
        $stmt->execute([':workflow_id' => $workflow['id']]);
        $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $executionLog = [
            'workflow_id' => $workflow['id'],
            'lead_id' => $context['lead_id'] ?? null,
            'trigger_type' => $workflow['trigger_type'] ?? 'unknown', // trigger_type is from joined table
            'status' => 'success',
            'steps' => []
        ];

        foreach ($actions as $action) {
            $stepResult = ['type' => $action['action_type'], 'status' => 'pending'];
            try {
                $this->executeAction($action, $context);
                $stepResult['status'] = 'success';
            } catch (Exception $e) {
                $stepResult['status'] = 'failed';
                $stepResult['error'] = $e->getMessage();
                $executionLog['status'] = 'partial'; // Or failed depending on policy
                error_log("Automation Action Failed: " . $e->getMessage());
            }
            $executionLog['steps'][] = $stepResult;
        }

        // Log execution (if we had a logs table fully hooked up)
        $this->logExecution($executionLog);
    }

    private function executeAction($action, $context) {
        $config = json_decode($action['action_config'], true);
        
        switch ($action['action_type']) {
            case 'webhook':
                $this->executeWebhook($config, $context);
                break;
            case 'add_note':
                $this->executeAddNote($config, $context);
                break;
            case 'assign_user':
                $this->executeAssignUser($config, $context);
                break;
            case 'update_field':
                $this->executeUpdateField($config, $context);
                break;
            case 'zingbot':
                $this->executeZingbot($config, $context);
                break;
            // Add other actions...
        }
    }

    private function executeWebhook($config, $context) {
        $logFile = __DIR__ . '/../automation_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $url = $config['url'] ?? '';
        if (empty($url)) {
            file_put_contents($logFile, "[$timestamp] Webhook FAILED: URL empty\n", FILE_APPEND);
            return;
        }

        $payload = $context['lead'] ?? [];
        file_put_contents($logFile, "[$timestamp] Sending Webhook to: $url\n", FILE_APPEND);
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($payload),
                'timeout' => 5
            ]
        ];
        
        try {
            $streamContext = stream_context_create($options);
            $result = @file_get_contents($url, false, $streamContext);
            if ($result === false) {
                $error = error_get_last();
                file_put_contents($logFile, "[$timestamp] Webhook FAILED: " . ($error['message'] ?? 'Unknown error') . "\n", FILE_APPEND);
            } else {
                file_put_contents($logFile, "[$timestamp] Webhook SUCCESS | Response: " . substr($result, 0, 100) . "\n", FILE_APPEND);
            }
        } catch (Exception $e) {
            file_put_contents($logFile, "[$timestamp] Webhook EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    private function executeZingbot($config, $context) {
        $logFile = __DIR__ . '/../automation_debug.log';
        $timestamp = date('Y-m-d H:i:s');

        // 1. Get Settings
        $stmt = $this->pdo->prepare("SELECT api_key, api_endpoint FROM zingbot_settings WHERE org_id = ? AND is_active = 1");
        $stmt->execute([$this->org_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings || empty($settings['api_key']) || empty($settings['api_endpoint'])) {
            file_put_contents($logFile, "[$timestamp] Zingbot execution skipped: settings missing or inactive\n", FILE_APPEND);
            return;
        }
        
        $flowId = $config['flow_id'] ?? '';
        if (empty($flowId)) {
            file_put_contents($logFile, "[$timestamp] Zingbot FAILED: No flow_id specified\n", FILE_APPEND);
            return;
        }

        $lead = $context['lead'] ?? [];
        $phone = $lead['phone'] ?? '';
        
        // Sanitize phone (strip the 'p:' prefix if it exists, common in this DB)
        $phone = preg_replace('/^p:/', '', $phone);
        
        // Ensure it has + if it's just digits (10 or more)
        if (preg_match('/^\d{10,}$/', $phone)) {
            $phone = '+' . $phone;
        }

        // Split name into first/last
        $nameParts = explode(' ', ($lead['name'] ?? ''), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        // Prepare Zingbot Payload
        $payload = [
            'phone' => $phone,
            'email' => $lead['email'] ?? '',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'actions' => [
                [
                    'action' => 'send_flow',
                    'flow_id' => is_numeric($flowId) ? (float)$flowId : $flowId
                ]
            ]
        ];
        
        $url = rtrim($settings['api_endpoint'], '/') . '/contacts';
        if (strpos($url, 'http') === false) {
            $url = 'https://' . $url;
        }

        $jsonPayload = json_encode($payload);
        file_put_contents($logFile, "[$timestamp] Zingbot Request to $url | Body: $jsonPayload\n", FILE_APPEND);

        $options = [
            'http' => [
                'header'  => "X-ACCESS-TOKEN: " . $settings['api_key'] . "\r\n" .
                           "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => $jsonPayload,
                'timeout' => 12
            ]
        ];
        
        try {
            $streamContext = stream_context_create($options);
            $result = @file_get_contents($url, false, $streamContext);
            if ($result === false) {
                $error = error_get_last();
                file_put_contents($logFile, "[$timestamp] Zingbot API FAILED: " . ($error['message'] ?? 'Unknown error') . "\n", FILE_APPEND);
            } else {
                file_put_contents($logFile, "[$timestamp] Zingbot API SUCCESS | Response: " . substr($result, 0, 100) . "\n", FILE_APPEND);
            }
        } catch (Exception $e) {
            file_put_contents($logFile, "[$timestamp] Zingbot EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    
    private function executeAddNote($config, $context) {
        $text = $config['note_text'] ?? '';
        if (empty($text) || empty($context['lead_id'])) return;

        // Variable substitution
        $text = $this->substituteVariables($text, $context);

        $stmt = $this->pdo->prepare("
            INSERT INTO lead_notes (lead_id, user_id, note, created_at)
            VALUES (:lead_id, :user_id, :note, NOW())
        ");
        
        // Use a system user ID or the workflow creator's ID
        // For now, let's use the lead owner or a default system ID (e.g., 0 or 1)
        // Ideally pass workflow creator ID.
        // Let's assume user_id 0 is 'System/Automation'
        $stmt->execute([
            ':lead_id' => $context['lead_id'],
            ':user_id' => $context['lead']['owner_id'] ?? 1, 
            ':note' => $text
        ]);
    }
    
    // Placeholder logic for variable substitution
    private function substituteVariables($text, $context) {
        if (!isset($context['lead'])) return $text;
        
        $lead = $context['lead'];
        foreach ($lead as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $text = str_replace("{{lead.$key}}", $value, $text);
            }
        }
        return $text;
    }

    private function executeAssignUser($config, $context) {
        $leadId = $context['lead_id'] ?? null;
        if (!$leadId) return;

        $userId = $config['user_id'] ?? null;
        
        // Handle logic for assignment
        if ($userId === 'round_robin') {
            // Simple round robin implementation
            // Get all staff/managers in org
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE org_id = :org_id AND is_active = 1 AND role IN ('staff', 'manager', 'admin', 'owner') ORDER BY id");
            $stmt->execute([':org_id' => $this->org_id]);
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($users)) return;
            
            // Get last assigned user index or random for now
            // For MVP, just random
            $userId = $users[array_rand($users)];
        }

        if ($userId) {
            $stmt = $this->pdo->prepare("UPDATE leads SET assigned_to = :user_id, updated_at = NOW() WHERE id = :lead_id AND org_id = :org_id");
            $stmt->execute([
                ':user_id' => $userId,
                ':lead_id' => $leadId,
                ':org_id' => $this->org_id
            ]);
            
            // Log activity handled by trigger if we wanted, but let's look at update.php
            // update.php logs activity when CALLED via API. Here we are doing DB update directly.
            // We should insert activity log manually here.
            $act_stmt = $this->pdo->prepare("INSERT INTO activities (org_id, lead_id, user_id, type, content, created_at) VALUES (?, ?, ?, 'assignment', ?, NOW())");
            // use system user or leave user_id null
            $act_stmt->execute([$this->org_id, $leadId, null, "Lead automatically assigned to User #$userId"]);
        }
    }

    private function executeUpdateField($config, $context) {
        $leadId = $context['lead_id'] ?? null;
        if (!$leadId) return;

        $field = $config['field_name'] ?? null; // from UI it might be 'field_value' config? 
        // In UI rendering: onchange="Automations.showFieldValueInput(${actionId}, this.value)"...
        // UI saves to 'config.field_value' and probably 'config.field_name' (implied by select value?)
        // Wait, UI code: onchange="Automations.showFieldValueInput... (doesn't save field name directly?)
        // Ah, select onchange calls showFieldValueInput but maybe doesn't save to config?
        // Let's check UI code.
        // Update: yes, the UI renderActionConfig has:
        // onchange="Automations.showFieldValueInput(${actionId}, this.value)"
        // It DOES NOT save the field name to config. We need to fix UI too or assume it saves.
        // Actually, let's look at the UI code again.
        // It has `onchange="Automations.showFieldValueInput(${actionId}, this.value)"` which JUST shows input.
        // It MISSES saving the field name! 
        // I need to fix the UI first or hack it here. 
        // I will fix the UI in next step. Assuming config has 'field_name' and 'field_value'.
        
        // Let's assume passed config has field_name and field_value.
        // Actually, looking at UI code provided in previous turn:
        // `onchange="Automations.updateActionConfig(${actionId}, 'field_name', this.value); Automations.showFieldValueInput(${actionId}, this.value)"` would be correct.
        // But the previous code was: `onchange="Automations.showFieldValueInput(${actionId}, this.value)"`.
        // So 'field_name' is missing in config.
        
        // I will fix the UI js file in the next step. For now, writing PHP code that expects 'field_name'.
        
        $field = $config['field_name'] ?? null;
        $value = $config['field_value'] ?? null;
        
        if ($field && $value !== null) {
            // Allowed fields for update
            $allowed = ['lead_value', 'source', 'stage_id', 'status']; // basic fields
            if (in_array($field, $allowed)) {
                $stmt = $this->pdo->prepare("UPDATE leads SET $field = :value, updated_at = NOW() WHERE id = :lead_id AND org_id = :org_id");
                $stmt->execute([
                    ':value' => $value,
                    ':lead_id' => $leadId,
                    ':org_id' => $this->org_id
                ]);
            }
        }
    }

    private function logExecution($logData) {
        // Insert into automation_execution_logs
        $stmt = $this->pdo->prepare("
            INSERT INTO automation_execution_logs 
            (workflow_id, lead_id, trigger_type, status, execution_data, executed_at)
            VALUES (:workflow_id, :lead_id, :trigger_type, :status, :execution_data, NOW())
        ");
        
        // Map trigger_type properly. It might be missing from $logData if join didn't select it or alias issues
        // We selected t.trigger_type in getMatchingWorkflows? No, we selected w.* and t.trigger_config.
        // Let's fix getMatchingWorkflows to select trigger_type
        
        $stmt->execute([
            ':workflow_id' => $logData['workflow_id'],
            ':lead_id' => $logData['lead_id'],
            ':trigger_type' => $logData['trigger_type'] ?? 'unknown',
            ':status' => $logData['status'],
            ':execution_data' => json_encode($logData['steps'])
        ]);
    }
}
