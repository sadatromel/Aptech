<?php
/**
 * Configuration Class
 * Manages application configuration settings
 */

class Config {
    private static $config = [];
    private static $loaded = false;
    
    /**
     * Load configuration from file
     */
    private static function load() {
        if (self::$loaded) return;
        
        // Load database configuration
        $dbConfig = require CONFIG_PATH . '/database.php';
        
        // Handle case where config might be returned as an array or constants defined
        if (!is_array($dbConfig) && defined('DB_HOST')) {
            $dbConfig = [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'username' => DB_USER,
                'password' => DB_PASS,
                'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
            ];
        }

        // Default configuration
        self::$config = [
            'app' => [
                'name' => 'Aptech ERP System',
                'version' => '2.0',
                'timezone' => 'Asia/Dhaka',
                'debug' => false,
                'maintenance' => false
            ],
            'database' => [
                'host' => $dbConfig['host'],
                'name' => $dbConfig['database'] ?? $dbConfig['dbname'] ?? 'aptech_erp',
                'user' => $dbConfig['username'],
                'pass' => $dbConfig['password'],
                'charset' => $dbConfig['charset'] ?? 'utf8mb4'
            ],
            'security' => [
                'session_lifetime' => 3600, // 1 hour
                'password_min_length' => 8,
                'max_login_attempts' => 5,
                'lockout_duration' => 900 // 15 minutes
            ],
            'upload' => [
                'max_file_size' => 5242880, // 5MB
                'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
                'upload_path' => UPLOADS_PATH
            ],
            'email' => [
                'from_name' => 'Aptech ERP',
                'from_email' => 'noreply@aptech.com',
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => ''
            ],
            'pagination' => [
                'default_limit' => 20,
                'max_limit' => 100
            ]
        ];
        
        // Load custom config if exists
        $configFile = CONFIG_PATH . '/app.config.php';
        if (file_exists($configFile)) {
            $customConfig = include $configFile;
            if (is_array($customConfig)) {
                self::$config = array_merge_recursive(self::$config, $customConfig);
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        self::load();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set configuration value
     */
    public static function set($key, $value) {
        self::load();
        
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * Get all configuration
     */
    public static function all() {
        self::load();
        return self::$config;
    }
    
    /**
     * Check if configuration exists
     */
    public static function has($key) {
        return self::get($key) !== null;
    }
}

?>
