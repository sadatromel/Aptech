<?php
/**
 * Database Connection Class
 * Example class that would be loaded by the autoloader
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // Load configuration
            $config = require __DIR__ . '/../config/database.php';
            
            // Check if config is array (it returns array)
            if (!is_array($config)) {
                // Fallback if file defines constants instead of returning array (legacy support)
                if (defined('DB_HOST')) {
                    $config = [
                        'host' => DB_HOST,
                        'database' => DB_NAME,
                        'username' => DB_USER,
                        'password' => DB_PASS,
                        'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
                    ];
                } else {
                    throw new Exception("Invalid database configuration");
                }
            }

            $host = $config['host'];
            $db_name = $config['database'] ?? $config['dbname'] ?? 'aptech_erp'; // Handle potential key variations
            $username = $config['username'];
            $password = $config['password'];
            $charset = $config['charset'] ?? 'utf8mb4';
            
            $dsn = "mysql:host={$host};dbname={$db_name};charset={$charset}";
            $this->connection = new PDO($dsn, $username, $password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Only log if Logger is available
            if (class_exists('Logger')) {
                Logger::info("Database connection established");
            }
        } catch (PDOException $e) {
            if (class_exists('Logger')) {
                Logger::error("Database connection failed: " . $e->getMessage());
            }
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    // Singleton pattern
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            // Only log if Logger is available
            if (class_exists('Logger')) {
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                Logger::query($sql, $params, $executionTime);
            }
            
            return $stmt;
        } catch (PDOException $e) {
            if (class_exists('Logger')) {
                Logger::error("Database query failed: " . $e->getMessage(), [
                    'sql' => $sql,
                    'params' => $params
                ]);
            }
            throw $e;
        }
    }
    
    /**
     * Execute query with LIMIT and OFFSET parameters
     */
    public function queryWithLimit($sql, $params = [], $limit = null, $offset = null) {
        $startTime = microtime(true);
        
        try {
            // Add LIMIT and OFFSET to SQL if provided
            if ($limit !== null) {
                $sql .= " LIMIT " . (int)$limit;
            }
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            // Only log if Logger is available
            if (class_exists('Logger')) {
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                Logger::query($sql, $params, $executionTime);
            }
            
            return $stmt;
        } catch (PDOException $e) {
            if (class_exists('Logger')) {
                Logger::error("Database query failed: " . $e->getMessage(), [
                    'sql' => $sql,
                    'params' => $params
                ]);
            }
            throw $e;
        }
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchAllWithLimit($sql, $params = [], $limit = null, $offset = null) {
        return $this->queryWithLimit($sql, $params, $limit, $offset)->fetchAll();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}

?>
