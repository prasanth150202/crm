<?php
// config/feature_knobs.php
// Feature Knob Manager - Admin-only feature permission management

class FeatureKnobManager {
    private $pdo;
    private $user;
    
    public function __construct($pdo, $user) {
        $this->pdo = $pdo;
        $this->user = $user;
    }
    
    /**
     * Check if current user is Admin or Owner
     */
    private function requireAdmin() {
        if (!in_array($this->user['role'], ['owner', 'admin'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Only Admin and Owner can manage feature permissions'
            ]);
            exit;
        }
    }
    
    /**
     * Get all feature knobs with their current role permissions
     */
    public function getAllKnobs($org_id = null) {
        $this->requireAdmin();
        
        // Get all feature knobs
        $stmt = $this->pdo->query("
            SELECT 
                knob_key,
                knob_name,
                description,
                category,
                is_system
            FROM feature_knobs
            ORDER BY category, knob_name
        ");
        $knobs = $stmt->fetchAll();
        
        // Get permissions for each role
        $roles = ['owner', 'admin', 'manager', 'staff'];
        $result = [];
        
        foreach ($knobs as $knob) {
            $permissions = [];
            
            foreach ($roles as $role) {
                $stmt = $this->pdo->prepare("
                    SELECT is_enabled 
                    FROM role_permissions 
                    WHERE role = :role 
                    AND knob_key = :knob_key 
                    AND (org_id IS NULL OR org_id = :org_id)
                    ORDER BY org_id DESC
                    LIMIT 1
                ");
                $stmt->execute([
                    ':role' => $role,
                    ':knob_key' => $knob['knob_key'],
                    ':org_id' => $org_id
                ]);
                $perm = $stmt->fetch();
                $permissions[$role] = $perm ? (bool)$perm['is_enabled'] : false;
            }
            
            $result[] = [
                'knob_key' => $knob['knob_key'],
                'knob_name' => $knob['knob_name'],
                'description' => $knob['description'],
                'category' => $knob['category'],
                'is_system' => (bool)$knob['is_system'],
                'permissions' => $permissions
            ];
        }
        
        return $result;
    }
    
    /**
     * Update role permission for a specific knob
     */
    public function updateRolePermission($role, $knob_key, $is_enabled, $org_id = null) {
        $this->requireAdmin();
        
        // Validate role
        if (!in_array($role, ['owner', 'admin', 'manager', 'staff'])) {
            throw new Exception('Invalid role');
        }
        
        // Check if knob exists
        $stmt = $this->pdo->prepare("SELECT knob_key FROM feature_knobs WHERE knob_key = :knob_key");
        $stmt->execute([':knob_key' => $knob_key]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid knob_key');
        }
        
        // Get old value for logging
        $stmt = $this->pdo->prepare("
            SELECT is_enabled 
            FROM role_permissions 
            WHERE role = :role AND knob_key = :knob_key AND (org_id IS NULL OR org_id = :org_id)
            LIMIT 1
        ");
        $stmt->execute([':role' => $role, ':knob_key' => $knob_key, ':org_id' => $org_id]);
        $old = $stmt->fetch();
        $old_value = $old ? $old['is_enabled'] : null;
        
        // Insert or update permission
        $stmt = $this->pdo->prepare("
            INSERT INTO role_permissions (role, knob_key, is_enabled, org_id, updated_by)
            VALUES (:role, :knob_key, :is_enabled, :org_id, :updated_by)
            ON DUPLICATE KEY UPDATE 
                is_enabled = :is_enabled,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            ':role' => $role,
            ':knob_key' => $knob_key,
            ':is_enabled' => $is_enabled ? 1 : 0,
            ':org_id' => $org_id,
            ':updated_by' => $this->user['id']
        ]);
        
        // Log the change
        $this->logPermissionChange($role, $knob_key, $old_value, $is_enabled);
        
        return true;
    }
    
    /**
     * Get permissions for a specific role
     */
    public function getRolePermissions($role, $org_id = null) {
        $this->requireAdmin();
        
        $stmt = $this->pdo->prepare("
            SELECT 
                fk.knob_key,
                fk.knob_name,
                fk.category,
                COALESCE(rp.is_enabled, 0) as is_enabled
            FROM feature_knobs fk
            LEFT JOIN role_permissions rp ON rp.knob_key = fk.knob_key 
                AND rp.role = :role 
                AND (rp.org_id IS NULL OR rp.org_id = :org_id)
            ORDER BY fk.category, fk.knob_name
        ");
        
        $stmt->execute([':role' => $role, ':org_id' => $org_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Bulk update permissions for a role
     */
    public function bulkUpdateRolePermissions($role, $permissions, $org_id = null) {
        $this->requireAdmin();
        
        $this->pdo->beginTransaction();
        
        try {
            foreach ($permissions as $knob_key => $is_enabled) {
                $this->updateRolePermission($role, $knob_key, $is_enabled, $org_id);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Audit log for permission changes
     */
    private function logPermissionChange($role, $knob_key, $old_value, $new_value) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_log 
            (user_id, org_id, action_type, entity_type, description, old_value, new_value, ip_address, user_agent)
            VALUES (:user_id, :org_id, :action_type, :entity_type, :description, :old_value, :new_value, :ip, :ua)
        ");
        
        $stmt->execute([
            ':user_id' => $this->user['id'],
            ':org_id' => $this->user['org_id'],
            ':action_type' => 'permission_changed',
            ':entity_type' => 'feature_knob',
            ':description' => "Changed permission for role '$role' on feature '$knob_key'",
            ':old_value' => json_encode(['role' => $role, 'knob_key' => $knob_key, 'enabled' => $old_value]),
            ':new_value' => json_encode(['role' => $role, 'knob_key' => $knob_key, 'enabled' => $new_value]),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

// Helper function to get feature knob manager
function getFeatureKnobManager($pdo, $user) {
    return new FeatureKnobManager($pdo, $user);
}
