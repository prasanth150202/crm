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
        return $this->getCalculatedPermissions();
    }
    
    /**
     * Load user-specific permissions from database (with caching)
     */
    /**
     * Load permissions from database (with caching)
     * This combines role-based defaults (for the current org) and user-specific overrides
     */
    private function loadRolePermissions() {
        if ($this->permissionsCache !== null) {
            return $this->permissionsCache;
        }
        
        $permissions = [];

        // 1. Load role-based permissions (global defaults where org_id IS NULL, then org-specific overrides)
        if (!empty($this->user['role'])) {
            $stmt = $this->pdo->prepare("
                SELECT knob_key, is_enabled
                FROM role_permissions
                WHERE (org_id = :org_id OR org_id IS NULL) AND role = :role
                ORDER BY (org_id IS NULL) DESC
            ");
            $stmt->execute([
                ':org_id' => $this->user['org_id'] ?? 0,
                ':role' => $this->user['role']
            ]);
            while ($row = $stmt->fetch()) {
                $permissions[$row['knob_key']] = (bool)$row['is_enabled'];
            }
        }
        
        // 2. Load user-specific permissions (overrides)
        $stmt = $this->pdo->prepare("
            SELECT knob_key, is_enabled
            FROM user_permissions
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            ':user_id' => $this->user['id']
        ]);
        while ($row = $stmt->fetch()) {
            // Overrides role permissions
            $permissions[$row['knob_key']] = (bool)$row['is_enabled'];
        }
        
        $this->permissionsCache = $permissions;
        return $permissions;
    }
    
    /**
     * Check if user has a specific feature enabled (new method)
     * This now validates against BOTH the user's permissions and the organization's Plan limits.
     */
    public function hasFeature($knob_key) {
        // 1. Check if the PLAN allows this feature
        if (class_exists('PlanFeatureChecker')) {
            // We use the global helper to get the checker instance
            // It uses the $currentUser from api_common.php or similar global state
            global $pdo;
            $checker = getPlanFeatureChecker($this->pdo, $this->user);
            if (!$checker->hasFeature($knob_key)) {
                return false; // Plan does not support this feature
            }
        }

        // 2. Check if the ROLE/USER has this permission
        $permissions = $this->loadRolePermissions();
        return isset($permissions[$knob_key]) && $permissions[$knob_key];
    }
    
    /**
     * Get all enabled features for current user (respecting Plan limits)
     */
    public function getEnabledFeatures() {
        $stmt = $this->pdo->query("SELECT knob_key FROM feature_knobs");
        $knobs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $enabled = [];
        foreach ($knobs as $knob) {
            if ($this->hasFeature($knob)) {
                $enabled[] = $knob;
            }
        }
        return $enabled;
    }

    /**
     * Get all permissions with their calculated status (User + Plan)
     */
    public function getCalculatedPermissions() {
        $stmt = $this->pdo->query("SELECT knob_key FROM feature_knobs");
        $knobs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $permissions = [];
        foreach ($knobs as $knob) {
            $permissions[$knob] = $this->hasFeature($knob);
        }
        return $permissions;
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
                'where' => "{$p}org_id = :org_id AND ({$p}assigned_to = :user_id OR {$p}owner_id = :owner_user_id)",
                'params' => [
                    ':org_id' => $this->user['org_id'],
                    ':user_id' => $this->user['id'],
                    ':owner_user_id' => $this->user['id']
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
