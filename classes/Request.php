<?php
/**
 * Request Class
 * Handles HTTP requests, input validation, and security
 */

class Request {
    private $get;
    private $post;
    private $files;
    private $server;
    
    public function __construct() {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
    }
    
    /**
     * Get GET parameter
     */
    public function get($key, $default = null) {
        return $this->get[$key] ?? $default;
    }
    
    /**
     * Get POST parameter
     */
    public function post($key, $default = null) {
        return $this->post[$key] ?? $default;
    }
    
    /**
     * Get either GET or POST parameter
     */
    public function input($key, $default = null) {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }
    
    /**
     * Get all input data
     */
    public function all() {
        return array_merge($this->get, $this->post);
    }
    
    /**
     * Check if request method is POST
     */
    public function isPost() {
        return $this->server['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * Check if request method is GET
     */
    public function isGet() {
        return $this->server['REQUEST_METHOD'] === 'GET';
    }
    
    /**
     * Check if request is AJAX
     */
    public function isAjax() {
        return isset($this->server['HTTP_X_REQUESTED_WITH']) && 
               strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get uploaded file
     */
    public function file($key) {
        return $this->files[$key] ?? null;
    }
    
    /**
     * Validate required fields
     */
    public function validate($rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $this->input($field);
            
            // Required validation
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = $rule['label'] ?? $field . ' is required';
                continue;
            }
            
            // Skip other validations if field is empty and not required
            if (empty($value) && !(isset($rule['required']) && $rule['required'])) {
                continue;
            }
            
            // Email validation
            if (isset($rule['email']) && $rule['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = $rule['label'] ?? $field . ' must be a valid email';
            }
            
            // Min length validation
            if (isset($rule['min']) && strlen($value) < $rule['min']) {
                $errors[$field] = ($rule['label'] ?? $field) . ' must be at least ' . $rule['min'] . ' characters';
            }
            
            // Max length validation
            if (isset($rule['max']) && strlen($value) > $rule['max']) {
                $errors[$field] = ($rule['label'] ?? $field) . ' must not exceed ' . $rule['max'] . ' characters';
            }
            
            // Numeric validation
            if (isset($rule['numeric']) && $rule['numeric'] && !is_numeric($value)) {
                $errors[$field] = ($rule['label'] ?? $field) . ' must be a number';
            }
            
            // Custom validation
            if (isset($rule['custom']) && is_callable($rule['custom'])) {
                $customError = $rule['custom']($value);
                if ($customError) {
                    $errors[$field] = $customError;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitize input data
     */
    public function sanitize($data = null) {
        if ($data === null) {
            $data = $this->all();
        }
        
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get client IP address
     */
    public function getClientIp() {
        $ipaddress = '';
        
        if (isset($this->server['HTTP_CLIENT_IP']))
            $ipaddress = $this->server['HTTP_CLIENT_IP'];
        else if(isset($this->server['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $this->server['HTTP_X_FORWARDED_FOR'];
        else if(isset($this->server['HTTP_X_FORWARDED']))
            $ipaddress = $this->server['HTTP_X_FORWARDED'];
        else if(isset($this->server['HTTP_FORWARDED_FOR']))
            $ipaddress = $this->server['HTTP_FORWARDED_FOR'];
        else if(isset($this->server['HTTP_FORWARDED']))
            $ipaddress = $this->server['HTTP_FORWARDED'];
        else if(isset($this->server['REMOTE_ADDR']))
            $ipaddress = $this->server['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        
        return $ipaddress;
    }
    
    /**
     * Get current URL
     */
    public function getCurrentUrl() {
        $protocol = (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? "https" : "http";
        return $protocol . "://" . $this->server['HTTP_HOST'] . $this->server['REQUEST_URI'];
    }
    
    /**
     * Get referer URL
     */
    public function getReferer() {
        return $this->server['HTTP_REFERER'] ?? '';
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

?>
