<?php
/**
 * Logger Class
 * Handles system logging and error tracking
 */

class Logger {
    private static $logFile;
    private static $errorFile;
    private static $initialized = false;
    
    /**
     * Initialize paths if not already done
     */
    private static function initialize() {
        if (!self::$initialized) {
            // Define BASEPATH if not defined
            if (!defined('BASEPATH')) {
                define('BASEPATH', dirname(__DIR__, 2));
            }
            
            self::$logFile = BASEPATH . '/logs/app.log';
            self::$errorFile = BASEPATH . '/logs/error.log';
            
            // Create logs directory if not exists
            $logDir = dirname(self::$logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            self::$initialized = true;
        }
    }
    
    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::initialize();
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::initialize();
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error($message, $context = []) {
        self::initialize();
        self::log('ERROR', $message, $context);
        
        // Also log to error file
        self::logToFile(self::$errorFile, 'ERROR', $message, $context);
    }
    
    /**
     * Log debug message
     */
    public static function debug($message, $context = []) {
        self::initialize();
        if (Config::get('app.debug', false)) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Log database query
     */
    public static function query($sql, $params = [], $executionTime = null) {
        self::initialize();
        if (Config::get('app.debug', false)) {
            $message = "Query: $sql";
            if (!empty($params)) {
                $message .= " | Params: " . json_encode($params);
            }
            if ($executionTime !== null) {
                $message .= " | Time: {$executionTime}ms";
            }
            self::debug($message);
        }
    }
    
    /**
     * Log user activity
     */
    public static function activity($userId, $action, $details = '') {
        self::initialize();
        $message = "User ID: $userId | Action: $action";
        if ($details) {
            $message .= " | Details: $details";
        }
        self::info($message, ['type' => 'activity', 'user_id' => $userId]);
    }
    
    /**
     * General log method
     */
    private static function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        // Write to main log file
        self::logToFile(self::$logFile, $level, $message, $context);
    }
    
    /**
     * Write to log file
     */
    private static function logToFile($file, $level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        // Rotate log if too large
        if (file_exists($file) && filesize($file) > 10485760) { // 10MB
            $backupFile = str_replace('.log', '_' . date('Y-m-d_H-i-s') . '.log', $file);
            rename($file, $backupFile);
        }
        
        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get recent logs
     */
    public static function getRecent($lines = 100, $type = 'app') {
        self::initialize();
        $file = $type === 'error' ? self::$errorFile : self::$logFile;
        
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        $logs = explode(PHP_EOL, trim($content));
        $logs = array_slice($logs, -$lines);
        
        return array_map(function($log) {
            if (empty($log)) return null;
            
            // Parse log entry
            if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] (.+)$/', $log, $matches)) {
                return [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3]
                ];
            }
            
            return ['message' => $log];
        }, $logs);
    }
    
    /**
     * Clear logs
     */
    public static function clear($type = 'app') {
        self::initialize();
        $file = $type === 'error' ? self::$errorFile : self::$logFile;
        if (file_exists($file)) {
            file_put_contents($file, '');
        }
    }
}

?>
