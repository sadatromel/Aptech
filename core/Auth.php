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

        return strtolower($_SESSION['user_role']) === strtolower($role);
    }
    
    public function hasAnyRole(array $roles): bool
    {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }

        // Admin bypass - use the central isAdmin check
        if ($this->isAdmin()) {
            return true;
        }
        
        $currentRole = strtolower($_SESSION['user_role']);
        $roles = array_map('strtolower', $roles);
        
        return in_array($currentRole, $roles);
    }

    public function isAdmin(): bool
    {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        $role = strtolower($_SESSION['user_role']);
        // Fix #7: Removed 'supper admin' typo — normalize DB roles to 'admin' or 'super_admin
        return in_array($role, [
            'admin',
            'super admin',
            'super_admin',
            'system administrator',
        ]);
    }

    public function can(string $permissionName): bool
    {
        if (!$this->check()) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        // Fix #2: Load all permissions for this user once per request and cache them.
        // This avoids N+1 DB queries when multiple requirePermission() calls are made
        // in a single request (e.g. checking hr.view, hr.create, hr.delete).
        if ($this->permissionsCache === null) {
            $stmt = $this->db->prepare("
                SELECT p.permission_name, p.permission_code
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.permission_id
                JOIN users u ON rp.role_id = u.role_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->permissionsCache = [];
            foreach ($rows as $row) {
                $this->permissionsCache[] = $row['permission_name'];
                if (!empty($row['permission_code'])) {
                    $this->permissionsCache[] = $row['permission_code'];
                }
            }
        }

        return in_array($permissionName, $this->permissionsCache, true);
    }
}
