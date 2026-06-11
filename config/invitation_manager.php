<?php
/**
 * Invitation Manager
 * Handles secure token generation and invitation lifecycle management
 */

class InvitationManager {
    
    /**
     * Generate a cryptographically secure random token
     * @return string 64-character hex string
     */
    public static function generateToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Hash a token using SHA-256
     * @param string $token Plain text token
     * @return string SHA-256 hash
     */
    public static function hashToken(string $token): string {
        return hash('sha256', $token);
    }
    
    /**
     * Create a new invitation
     * @param PDO $pdo Database connection
     * @param int $org_id Organization ID
     * @param string $email User email
     * @param string $role User role
     * @param array $features Array of feature knob_keys
     * @param int $created_by Creator user ID
     * @param int $expiryDays Days until expiration (default 7)
     * @return array ['success' => bool, 'token' => string, 'invitation_id' => int]
     */
    public static function createInvitation(
        PDO $pdo,
        int $org_id,
        string $email,
        string $role,
        array $features,
        int $created_by,
        int $expiryDays = 7
    ): array {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => 'Invalid email address'];
            }
            
            // Check if email already exists in this organization
            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE email = :email AND org_id = :org_id
            ");
            $stmt->execute([':email' => $email, ':org_id' => $org_id]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'User with this email already exists'];
            }
            
            // Check for pending invitations
            $stmt = $pdo->prepare("
                SELECT id FROM invitation_tokens 
                WHERE email = :email 
                AND org_id = :org_id 
                AND used_at IS NULL 
                AND revoked_at IS NULL 
                AND expires_at > NOW()
            ");
            $stmt->execute([':email' => $email, ':org_id' => $org_id]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Pending invitation already exists for this email'];
            }
            
            // Generate token
            $token = self::generateToken();
            $tokenHash = self::hashToken($token);
            
            // Calculate expiry
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
            
            // Insert invitation
            $stmt = $pdo->prepare("
                INSERT INTO invitation_tokens 
                (token_hash, org_id, email, role, assigned_features, created_by, expires_at)
                VALUES (:token_hash, :org_id, :email, :role, :features, :created_by, :expires_at)
            ");
            
            $stmt->execute([
                ':token_hash' => $tokenHash,
                ':org_id' => $org_id,
                ':email' => $email,
                ':role' => $role,
                ':features' => json_encode($features),
                ':created_by' => $created_by,
                ':expires_at' => $expiresAt
            ]);
            
            $invitationId = $pdo->lastInsertId();
            
            return [
                'success' => true,
                'token' => $token,
                'invitation_id' => $invitationId,
                'expires_at' => $expiresAt
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate a token and return invitation details
     * @param PDO $pdo Database connection
     * @param string $token Plain text token
     * @return array|false Invitation details or false if invalid
     */
    public static function validateToken(PDO $pdo, string $token) {
        try {
            $tokenHash = self::hashToken($token);
            
            $stmt = $pdo->prepare("
                SELECT 
                    it.*,
                    o.name as org_name
                FROM invitation_tokens it
                JOIN organizations o ON it.org_id = o.id
                WHERE it.token_hash = :token_hash
            ");
            
            $stmt->execute([':token_hash' => $tokenHash]);
            $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invitation) {
                return false;
            }
            
            // Check if already used
            if ($invitation['used_at'] !== null) {
                return ['error' => 'Invitation has already been used'];
            }
            
            // Check if revoked
            if ($invitation['revoked_at'] !== null) {
                return ['error' => 'Invitation has been revoked'];
            }
            
            // Check if expired
            if (strtotime($invitation['expires_at']) < time()) {
                return ['error' => 'Invitation has expired'];
            }
            
            // Decode features
            $invitation['assigned_features'] = json_decode($invitation['assigned_features'], true);
            
            return $invitation;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Activate an invitation and create user account
     * @param PDO $pdo Database connection
     * @param string $token Plain text token
     * @param string $password User password
     * @param string $fullName User full name
     * @return array ['success' => bool, 'user_id' => int]
     */
    public static function activateInvitation(
        PDO $pdo,
        string $token,
        string $password,
        string $fullName
    ): array {
        try {
            $pdo->beginTransaction();
            
            // Validate token
            $invitation = self::validateToken($pdo, $token);
            
            if (!$invitation || isset($invitation['error'])) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'error' => $invitation['error'] ?? 'Invalid invitation token'
                ];
            }
            
            // Validate password
            require_once __DIR__ . '/security.php';
            $passwordErrors = Security::validatePassword($password);
            
            if (!empty($passwordErrors)) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'error' => 'Password validation failed',
                    'password_errors' => $passwordErrors
                ];
            }
            
            // Create user account
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (org_id, email, full_name, password_hash, role, is_active)
                VALUES (:org_id, :email, :full_name, :password_hash, :role, 1)
            ");
            
            $stmt->execute([
                ':org_id' => $invitation['org_id'],
                ':email' => $invitation['email'],
                ':full_name' => $fullName,
                ':password_hash' => $passwordHash,
                ':role' => $invitation['role']
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Assign features to user
            if (!empty($invitation['assigned_features'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_permissions (user_id, knob_key, is_enabled, granted_by)
                    VALUES (:user_id, :knob_key, 1, :granted_by)
                ");
                
                foreach ($invitation['assigned_features'] as $featureKey) {
                    $stmt->execute([
                        ':user_id' => $userId,
                        ':knob_key' => $featureKey,
                        ':granted_by' => $invitation['created_by']
                    ]);
                }
            }
            
            // Mark invitation as used
            $stmt = $pdo->prepare("
                UPDATE invitation_tokens 
                SET used_at = NOW(), used_by = :user_id
                WHERE id = :invitation_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':invitation_id' => $invitation['id']
            ]);
            
            // Log activation
            $stmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, org_id, action_type, description, ip_address, user_agent)
                VALUES (:user_id, :org_id, 'invitation_activated', :description, :ip, :ua)
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':org_id' => $invitation['org_id'],
                ':description' => "User account activated via invitation: {$invitation['email']}",
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            $pdo->commit();
            
            return [
                'success' => true,
                'user_id' => $userId
            ];
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Revoke an invitation
     * @param PDO $pdo Database connection
     * @param int $invitationId Invitation ID
     * @param int $revokedBy User ID who revoked
     * @return bool Success status
     */
    public static function revokeInvitation(PDO $pdo, int $invitationId, int $revokedBy): bool {
        try {
            $stmt = $pdo->prepare("
                UPDATE invitation_tokens 
                SET revoked_at = NOW(), revoked_by = :revoked_by
                WHERE id = :invitation_id 
                AND used_at IS NULL 
                AND revoked_at IS NULL
            ");
            
            $stmt->execute([
                ':invitation_id' => $invitationId,
                ':revoked_by' => $revokedBy
            ]);
            
            if ($stmt->rowCount() > 0) {
                // Log revocation
                $stmt = $pdo->prepare("
                    SELECT org_id, email FROM invitation_tokens WHERE id = :id
                ");
                $stmt->execute([':id' => $invitationId]);
                $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($invitation) {
                    $stmt = $pdo->prepare("
                        INSERT INTO activity_log 
                        (user_id, org_id, action_type, description, ip_address, user_agent)
                        VALUES (:user_id, :org_id, 'invitation_revoked', :description, :ip, :ua)
                    ");
                    
                    $stmt->execute([
                        ':user_id' => $revokedBy,
                        ':org_id' => $invitation['org_id'],
                        ':description' => "Invitation revoked for: {$invitation['email']}",
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                        ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);
                }
                
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            return false;
        }
    }
}
