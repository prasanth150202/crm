<?php
// config/permissions.php
// Permission middleware and role-based access control

class PermissionManager {
    private $pdo;
    private $user;
    private $permissionsCache = null;

    public function __construct($pdo, $user) {
        $this->pdo = $pdo;
        $this->user = $user;
    }

    /**
     * Get all permissions (key => bool) for current user
     */
    public function getAllPermissions() {
        return $this->loadRolePermissions();
    }
    
    /**
     * Load user-specific permissions from database (with caching)
     */
    private function loadRolePermissions() {
        if ($this->permissionsCache !== null) {
            return $this->permissionsCache;
        }
        
        // Load user-specific permissions from user_permissions table
        $stmt = $this->pdo->prepare("
            SELECT knob_key, is_enabled
            FROM user_permissions
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([
            ':user_id' => $this->user['id']
        ]);
        
        $permissions = [];
        while ($row = $stmt->fetch()) {
            $permissions[$row['knob_key']] = (bool)$row['is_enabled'];
        }
        
        $this->permissionsCache = $permissions;
        return $permissions;
    }
    
    /**
     * Check if user has a specific feature enabled (new method)
     */
    public function hasFeature($knob_key) {
        $permissions = $this->loadRolePermissions();
        return isset($permissions[$knob_key]) && $permissions[$knob_key];
    }
    
    /**
     * Get all enabled features for current user
     */
    public function getEnabledFeatures() {
        $permissions = $this->loadRolePermissions();
        return array_keys(array_filter($permissions));
    }
    
    /**
     * Check if user has permission (backward compatibility)
     * Maps old permission names to new feature knobs
     */
    public function hasPermission($permission) {
        // Map old permission names to new knob_keys for backward compatibility
        $permissionMap = [
            'view_all_leads' => 'view_all_assigned_leads',
            'view_assigned_leads' => 'view_own_assigned_leads',
            'create_leads' => 'create_leads',
            'edit_all_leads' => 'edit_all_leads',
            'edit_assigned_leads' => 'edit_own_leads',
            'delete_leads' => 'delete_leads',
            'assign_leads' => 'assign_leads',
            'manage_users' => 'manage_users',
            'manage_teams' => 'manage_users', // Map to manage_users
            'manage_custom_fields' => 'manage_custom_fields',
            'view_reports' => 'view_reports',
            'manage_organization' => 'manage_organization',
            'view_activity' => 'view_activity_log',
        ];
        
        $knob_key = $permissionMap[$permission] ?? $permission;
        return $this->hasFeature($knob_key);
    }
    
    /**
     * Check if user can access a specific lead
     */
    public function canAccessLead($lead_id) {
        // Owner, Admin, Manager can access all leads
        if (in_array($this->user['role'], ['owner', 'admin', 'manager'])) {
            return true;
        }
        
        // Sales rep can only access assigned leads
        $stmt = $this->pdo->prepare("
            SELECT id FROM leads 
            WHERE id = :lead_id 
            AND org_id = :org_id 
            AND (assigned_to = :user_id OR assigned_to IS NULL)
        ");
        $stmt->execute([
            ':lead_id' => $lead_id,
            ':org_id' => $this->user['org_id'],
            ':user_id' => $this->user['id']
        ]);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check if user can edit a specific lead
     */
    public function canEditLead($lead_id) {
        // Owner, Admin, Manager can edit all leads
        if (in_array($this->user['role'], ['owner', 'admin', 'manager'])) {
            return true;
        }
        
        // Sales rep can only edit assigned leads
        $stmt = $this->pdo->prepare("
            SELECT id FROM leads 
            WHERE id = :lead_id 
            AND org_id = :org_id 
            AND assigned_to = :user_id
        ");
        $stmt->execute([
            ':lead_id' => $lead_id,
            ':org_id' => $this->user['org_id'],
            ':user_id' => $this->user['id']
        ]);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Get filtered leads query based on user role and feature knobs
     */
    public function getLeadsFilter($prefix = '') {
        $p = $prefix ? $prefix . '.' : '';
        
        // If user can view all assigned leads, no filtering needed
        if ($this->hasFeature('view_all_assigned_leads')) {
            return [
                'where' => "{$p}org_id = :org_id",
                'params' => [':org_id' => $this->user['org_id']]
            ];
        }
        
        // If user can view unassigned leads + own assigned leads
        if ($this->hasFeature('view_unassigned_leads') && $this->hasFeature('view_own_assigned_leads')) {
            return [
                'where' => "{$p}org_id = :org_id AND ({$p}assigned_to = :user_id OR {$p}assigned_to IS NULL)",
                'params' => [
                    ':org_id' => $this->user['org_id'],
                    ':user_id' => $this->user['id']
                ]
            ];
        }
        
        // If user can only view own assigned leads
        if ($this->hasFeature('view_own_assigned_leads')) {
            return [
                'where' => "{$p}org_id = :org_id AND {$p}assigned_to = :user_id",
                'params' => [
                    ':org_id' => $this->user['org_id'],
                    ':user_id' => $this->user['id']
                ]
            ];
        }
        
        // Default: no leads visible
        return [
            'where' => "1 = 0", // No results
            'params' => []
        ];
    }
    
    /**
     * Require permission or die with error
     */
    public function requirePermission($permission) {
        if (!$this->hasPermission($permission)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Permission denied. Required permission: ' . $permission
            ]);
            exit;
        }
    }
    
    /**
     * Log activity
     */
    public function logActivity($action_type, $lead_id = null, $description = '', $old_value = null, $new_value = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_log 
            (user_id, lead_id, org_id, action_type, description, old_value, new_value, ip_address, user_agent)
            VALUES (:user_id, :lead_id, :org_id, :action_type, :description, :old_value, :new_value, :ip, :ua)
        ");
        
        $stmt->execute([
            ':user_id' => $this->user['id'],
            ':lead_id' => $lead_id,
            ':org_id' => $this->user['org_id'],
            ':action_type' => $action_type,
            ':description' => $description,
            ':old_value' => $old_value ? json_encode($old_value) : null,
            ':new_value' => $new_value ? json_encode($new_value) : null,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

// Helper function to get permission manager
function getPermissionManager($pdo, $user) {
    return new PermissionManager($pdo, $user);
}
