<?php

declare(strict_types=1);

namespace Core;

use PDO;

class Auth
{
    private PDO $db;
    private ?array $user = null;

    /**
     * Fix #2: Permissions cache — loaded once per request to prevent N+1 DB queries.
     * Keyed by user_id, value is array of permission_name/code strings.
     */
    private ?array $permissionsCache = null;

    public function __construct()
    {
        $this->db = Container::get(PDO::class);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function attempt(string $email, string $password): bool
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.role_name 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.role_id 
             WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (isset($user['status']) && $user['status'] !== 'active') {
            return false;
        }

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['full_name'] ?? $user['email'];
        $_SESSION['user_role'] = $user['role_name'] ?? 'user';

        $this->user = $user;

        return true;
    }

    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if (!$this->check()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $this->user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $this->user;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_id() !== '' || isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
        $this->user = null;
    }

    public function hasRole(string $role): bool
    {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }

        // Admin bypass - use the central isAdmin check
        if ($this->isAdmin()) {
            return true;
        }

        $currentRole = strtolower(trim((string)$_SESSION['user_role']));
        $targetRole = strtolower(trim($role));

        if ($currentRole === $targetRole) {
            return true;
        }

        // If user has 'Manager' role, satisfy departmental manager roles (e.g. merchandising_manager, procurement_manager)
        if ($currentRole === 'manager' && (str_ends_with($targetRole, '_manager') || str_starts_with($targetRole, 'manager_'))) {
            return true;
        }

        return false;
    }
    
    public function hasAnyRole(array $roles): bool
    {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }
        
        foreach ($roles as $role) {
            if ($this->hasRole((string)$role)) {
                return true;
            }
        }
        
        return false;
    }

    public function isAdmin(): bool
    {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        $role = strtolower($_SESSION['user_role']);
        return in_array($role, [
            'admin',
            'super admin',
            'supper admin',
            'super_admin',
            'system administrator',
            'system_administrator',
        ], true);
    }

    public function isSuperAdminOrSystemAdmin(): bool
    {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        $role = strtolower(trim($_SESSION['user_role']));
        return in_array($role, [
            'super admin',
            'supper admin',
            'super_admin',
            'system administrator',
            'system_administrator',
        ], true);
    }

    public function isPrivilegedRole(mixed $roleOrId): bool
    {
        if (empty($roleOrId)) {
            return false;
        }

        if (is_numeric($roleOrId)) {
            $roleId = (int) $roleOrId;
            if (in_array($roleId, [1, 2], true)) {
                return true;
            }

            try {
                $stmt = $this->db->prepare('SELECT role_name FROM roles WHERE role_id = ? LIMIT 1');
                $stmt->execute([$roleId]);
                $role = $stmt->fetchColumn();
                if ($role) {
                    return in_array(strtolower(trim((string) $role)), [
                        'super admin',
                        'supper admin',
                        'super_admin',
                        'system administrator',
                        'system_administrator',
                    ], true);
                }
            } catch (\Throwable $e) {
                // Ignore DB error
            }

            return false;
        }

        return in_array(strtolower(trim((string) $roleOrId)), [
            'super admin',
            'supper admin',
            'super_admin',
            'system administrator',
            'system_administrator',
        ], true);
    }

    public function can(string $permissionName): bool
    {
        if (!$this->check()) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if (!function_exists('checkUserPermission')) {
            require_once __DIR__ . '/permission_helper.php';
        }

        $action = 'view';
        if (str_starts_with($permissionName, 'create_') || str_starts_with($permissionName, 'add_')) {
            $action = 'create';
        } elseif (str_starts_with($permissionName, 'edit_') || str_starts_with($permissionName, 'update_')) {
            $action = 'edit';
        } elseif (str_starts_with($permissionName, 'delete_') || str_starts_with($permissionName, 'destroy_')) {
            $action = 'delete';
        } elseif (str_starts_with($permissionName, 'approve_')) {
            $action = 'approve';
        }

        return checkUserPermission($this->db, (int)$_SESSION['user_id'], '', $permissionName, $action);
    }
}
