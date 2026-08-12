<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class AuthService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Authenticate user with email/username and password
     */
    public function login(string $emailOrUsername, string $password): ?array
    {
        $query = "SELECT u.*, r.role_name 
                  FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.role_id 
                  WHERE (u.email = ? OR u.username = ?) AND u.status = 'active'";
        
        $result = $this->db->query($query, [$emailOrUsername, $emailOrUsername]);
        $user = $result->fetch(\PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Log successful login
            $this->logUserActivity($user['user_id'], 'login', 'User logged in from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            return [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'name' => $user['full_name'] ?? $user['name'] ?? $user['username'] ?? $user['email'] ?? 'Unknown User',
                'role' => $user['role_name']
            ];
        }
        
        return null;
    }

    /**
     * Register a new user
     */
    public function registerUser(array $data): array
    {
        // Check if email already exists
        $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $result = $this->db->query($query, [$data['email']]);
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        
        if (($row['count'] ?? 0) > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Determine First/Last name from Full Name if needed, or just insert appropriately
        // Based on UserService, table has first_name, last_name
        $fullName = $data['full_name'] ?? '';
        $parts = explode(' ', $fullName, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        // Generate username from email (part before @)
        $username = explode('@', $data['email'])[0];
        // Ensure username is unique (simple append if needed, or just hope specific email part is unique enough for now)
        // ideally we should check uniqueness, but for this fix let's rely on email uniqueness or append random
        // Checking if username exists
        $checkUser = $this->db->query("SELECT COUNT(*) as count FROM users WHERE username = ?", [$username]);
        $userRow = $checkUser->fetch(\PDO::FETCH_ASSOC);
        if (($userRow['count'] ?? 0) > 0) {
            $username .= rand(100, 999);
        }

        try {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            $roleId = 3; // Default to User/Employee role (adjust as per roles table)
            
            // Using a raw query to ensure we hit the table correctly
            $insertSql = "INSERT INTO users (username, first_name, last_name, email, password_hash, role_id, status, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
            
            $this->db->query($insertSql, [
                $username,
                $firstName,
                $lastName,
                $data['email'],
                $passwordHash,
                $roleId
            ]);
            
            // Get the new user ID
            $userId = $this->db->lastInsertId();
            
            return ['success' => true, 'user_id' => $userId];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Logout user and log activity
     */
    public function logout(int $userId): void
    {
        $this->logUserActivity($userId, 'logout', 'User logged out from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current user data
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'user_id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'role' => $_SESSION['user_role'] ?? null
        ];
    }

    /**
     * Submit a new access application
     */
    public function submitApplication(array $data): array
    {
        // Check if email already exists in users or applications
        $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $result = $this->db->query($query, [$data['email']]);
        if (($result->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0) > 0) {
            return ['success' => false, 'message' => 'Email already registered as a user'];
        }

        $query = "SELECT COUNT(*) as count FROM access_applications WHERE email = ? AND status = 'pending'";
        $result = $this->db->query($query, [$data['email']]);
        if (($result->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0) > 0) {
            return ['success' => false, 'message' => 'You have a pending application with this email'];
        }

        try {
            $sql = "INSERT INTO access_applications (full_name, email, employee_id, unit, designation, department, reason, profile_photo, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $this->db->query($sql, [
                $data['full_name'],
                $data['email'],
                $data['employee_id'],
                $data['unit'],
                $data['designation'],
                $data['department'],
                $data['reason'],
                $data['profile_photo'] ?? null
            ]);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Application submission failed: ' . $e->getMessage()];
        }
    }

    /**
     * Log user activity
     */
    private function logUserActivity(int $userId, string $activityType, string $description): void
    {
        try {
            $query = "INSERT INTO user_activity_logs (user_id, activity_type, description) VALUES (?, ?, ?)";
            $this->db->query($query, [$userId, $activityType, $description]);
        } catch (\Exception $e) {
            // Log error but don't break authentication
            error_log("Failed to log user activity: " . $e->getMessage());
        }
    }

    /**
     * Check user permission for a module and action
     */
    public function checkPermission(int $userId, string $module, string $action, string $permissionType = 'view'): bool
    {
        $map = [
            'view' => 'can_view',
            'create' => 'can_create',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
            'approve' => 'can_approve'
        ];
        
        $column = $map[$permissionType] ?? 'can_view';
        
        // $action here corresponds to page_name in the db
        $query = "SELECT $column 
                  FROM user_permissions 
                  WHERE user_id = ? AND module = ? AND page_name = ?";
        
        $result = $this->db->query($query, [$userId, $module, $action]);
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        
        return (bool)($row[$column] ?? false);
    }
}
