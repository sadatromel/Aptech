<?php
/**
 * User Class
 * Handles user management operations
 */

class User {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Get all users with pagination
     */
    public function getAll($page = 1, $limit = 20, $search = '') {
        $page = (int)$page;
        $limit = (int)$limit;
        $offset = ($page - 1) * $limit;
        
        $where = '';
        $params = [];
        
        if (!empty($search)) {
        if (!empty($search)) {
            $where = "WHERE (first_name LIKE ? OR last_name LIKE ?) OR email LIKE ?";
            $params = ["%$search%", "%$search%", "%$search%"];
        }
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM users $where";
        $total = $this->db->fetch($countSql, $params)['total'];
        
        // Get users
        $sql = "SELECT u.*, r.role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.role_id 
                $where 
                ORDER BY u.created_at DESC";
        
        $users = $this->db->fetchAllWithLimit($sql, $params, $limit, $offset);
        
        return [
            'users' => $users,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Get user by ID
     */
    public function getById($id) {
        return $this->db->fetch(
            "SELECT u.*, r.role_name 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.role_id 
             WHERE u.user_id = ?", 
            [$id]
        );
    }
    
    /**
     * Create new user
     */
    public function create($data) {
        // Validate data
        $this->validateUserData($data);
        
        // Check if email exists
        if ($this->getByEmail($data['email'])) {
            throw new Exception("Email already exists");
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $sql = "INSERT INTO users (first_name, last_name, email, password_hash, role_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->query($sql, [
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $hashedPassword,
            $data['role_id'],
            $data['status'] ?? 'active'
        ]);
        
        $userId = $this->db->lastInsertId();
        
        Logger::activity($userId, 'User Created', "New user {$data['email']} created");
        
        return $userId;
    }
    
    /**
     * Update user
     */
    public function update($id, $data) {
        // Validate data
        $this->validateUserData($data, $id);
        
        // Build update query
        $fields = [];
        $params = [];
        
        if (isset($data['first_name'])) {
            $fields[] = "first_name = ?";
            $params[] = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $fields[] = "last_name = ?";
            $params[] = $data['last_name'];
        }
        
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['role_id'])) {
            $fields[] = "role_id = ?";
            $params[] = $data['role_id'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            throw new Exception("No data to update");
        }
        
        $params[] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?";
        $this->db->query($sql, $params);
        
        Logger::activity($id, 'User Updated', "User data updated");
        
        return true;
    }
    
    /**
     * Delete user
     */
    public function delete($id) {
        // Don't allow deletion of currently logged in user
        if ($this->auth->isLoggedIn() && $_SESSION['user_id'] == $id) {
            throw new Exception("Cannot delete your own account");
        }
        
        // Soft delete by updating status
        $this->db->query("UPDATE users SET status = 'deleted' WHERE user_id = ?", [$id]);
        
        Logger::activity($id, 'User Deleted', "User marked as deleted");
        
        return true;
    }
    
    /**
     * Get user by email
     */
    public function getByEmail($email) {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }
    
    /**
     * Get user roles
     */
    public function getRoles() {
        return $this->db->fetchAll("SELECT * FROM roles ORDER BY role_name");
    }
    
    /**
     * Change user status
     */
    public function changeStatus($id, $status) {
        $validStatuses = ['active', 'inactive', 'suspended'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status");
        }
        
        $this->db->query("UPDATE users SET status = ? WHERE user_id = ?", [$status, $id]);
        
        Logger::activity($id, 'Status Changed', "User status changed to $status");
        
        return true;
    }
    
    /**
     * Get user statistics
     */
    public function getStats() {
        $stats = [];
        
        // Total users
        $stats['total'] = $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE status != 'deleted'")['count'];
        
        // Active users
        $stats['active'] = $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'];
        
        // Inactive users
        $stats['inactive'] = $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'")['count'];
        
        // New users this month
        $stats['new_this_month'] = $this->db->fetch(
            "SELECT COUNT(*) as count FROM users 
             WHERE MONTH(created_at) = MONTH(CURRENT_DATE) 
             AND YEAR(created_at) = YEAR(CURRENT_DATE)"
        )['count'];
        
        return $stats;
    }
    
    /**
     * Validate user data
     */
    private function validateUserData($data, $excludeId = null) {
        $errors = [];
        
        // Name validation
        // Name validation
        if (empty($data['first_name'])) {
            $errors[] = "First name is required";
        }
        if (empty($data['last_name'])) {
            $errors[] = "Last name is required";
        }
        
        // Email validation
        if (empty($data['email'])) {
            $errors[] = "Email is required";
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Password validation for new users
        if (!isset($excludeId)) {
            if (empty($data['password'])) {
                $errors[] = "Password is required";
            } elseif (strlen($data['password']) < 8) {
                $errors[] = "Password must be at least 8 characters";
            }
        }
        
        // Role validation
        if (isset($data['role_id']) && !is_numeric($data['role_id'])) {
            $errors[] = "Invalid role selected";
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
    }
}

?>
