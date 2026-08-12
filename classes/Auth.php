<?php
/**
 * Authentication Class
 * Handles user authentication, sessions, and permissions
 */

class Auth {
    private $db;
    private $user = null;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login user with email and password
     */
    public function login($email, $password) {
        $query = "SELECT u.user_id, u.email, u.password, u.full_name, u.status, r.role_name 
                  FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.role_id 
                  WHERE u.email = ?";
        
        $user = $this->db->fetch($query, [$email]);
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                throw new Exception("Account is not active");
            }
            
            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role_name'];
            
            // Log the login
            $this->logActivity('Login', 'User logged in from IP: ' . $_SERVER['REMOTE_ADDR']);
            
            $this->user = $user;
            return true;
        }
        
        return false;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logActivity('Logout', 'User logged out');
        }
        
        // Destroy session
        session_destroy();
        $this->user = null;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Get current user data
     */
    public function getUser() {
        if (!$this->user && $this->isLoggedIn()) {
            $this->user = $this->db->fetch(
                "SELECT * FROM users WHERE user_id = ?", 
                [$_SESSION['user_id']]
            );
        }
        return $this->user;
    }
    
    /**
     * Get user role
     */
    public function getRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return $this->getRole() === $role;
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin() {
        return in_array($this->getRole(), ['Super Admin', 'Admin', 'System Administrator']);
    }
    
    /**
     * Require login to access page
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: index.php?page=login');
            exit();
        }
    }
    
    /**
     * Require specific role to access page
     */
    public function requireRole($role) {
        $this->requireLogin();
        
        if (!$this->hasRole($role) && !$this->isAdmin()) {
            http_response_code(403);
            echo "Access Denied";
            exit();
        }
    }
    
    /**
     * Log user activity
     */
    private function logActivity($action, $details = '') {
        $query = "INSERT INTO user_activity_logs (user_id, action, details, created_at) 
                  VALUES (?, ?, ?, NOW())";
        $this->db->query($query, [$_SESSION['user_id'], $action, $details]);
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        // Check if email exists
        $existing = $this->db->fetch("SELECT user_id FROM users WHERE email = ?", [$data['email']]);
        if ($existing) {
            throw new Exception("Email already exists");
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $query = "INSERT INTO users (full_name, email, password, role_id, status, created_at) 
                  VALUES (?, ?, ?, ?, 'active', NOW())";
        
        $this->db->query($query, [
            $data['full_name'],
            $data['email'],
            $hashedPassword,
            $data['role_id'] ?? 2 // Default to regular user role
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Change password
     */
    public function changePassword($oldPassword, $newPassword) {
        $user = $this->getUser();
        
        if (!password_verify($oldPassword, $user['password'])) {
            throw new Exception("Current password is incorrect");
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->query(
            "UPDATE users SET password = ? WHERE user_id = ?",
            [$newHash, $user['user_id']]
        );
        
        $this->logActivity('Password Change', 'User changed their password');
    }
    /**
     * Check if user is authenticated (Alias for isLoggedIn)
     */
    public function check() {
        return $this->isLoggedIn();
    }
    
    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles) {
        if ($this->isAdmin()) {
            return true;
        }
        return in_array($this->getRole(), $roles);
    }

    /**
     * Get user (Alias for getUser)
     */
    public function user() {
        return $this->getUser();
    }
}

?>
