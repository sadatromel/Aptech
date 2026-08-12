<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class SystemLibraryService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Get system configuration
     */
    public function getSystemConfig(): array
    {
        $config = [];
        
        // Get basic system info
        $config['system'] = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'port' => $_SERVER['SERVER_PORT'] ?? '80'
        ];

        // Get database info
        try {
            $result = $this->db->query("SELECT VERSION() as version");
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            $config['database']['version'] = $row['version'] ?? 'Unknown';
            $config['database']['connected'] = true;
        } catch (\Exception $e) {
            $config['database']['connected'] = false;
            $config['database']['error'] = $e->getMessage();
        }

        // Get file system info
        $config['filesystem'] = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'disk_free_space' => $this->formatBytes((int)(disk_free_space(__DIR__) ?: 0)),
            'disk_total_space' => $this->formatBytes((int)(disk_total_space(__DIR__) ?: 0))
        ];

        return $config;
    }

    /**
     * Get system logs
     */
    public function getSystemLogs(int $limit = 100): array
    {
        $logs = [];
        
        // Try to read from database log table
        try {
            $query = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT ?";
            $result = $this->db->query($query);
            $logs = $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            // Fallback to file-based logs
            $logs = $this->getFileLogs($limit);
        }

        return $logs;
    }

    /**
     * Get system statistics
     */
    public function getSystemStats(): array
    {
        $stats = [];

        // Database statistics
        try {
            $tables = $this->db->query("SHOW TABLES");
            $tableCount = count($tables->fetchAll(\PDO::FETCH_ASSOC) ?: []);
            
            $sizeQuery = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size 
                         FROM information_schema.tables 
                         WHERE table_schema = DATABASE()";
            $sizeResult = $this->db->query($sizeQuery);
            $sizeRow = $sizeResult->fetch(\PDO::FETCH_ASSOC);
            
            $stats['database'] = [
                'tables' => $tableCount,
                'size_mb' => $sizeRow['size'] ?? 0
            ];
        } catch (\Exception $e) {
            $stats['database'] = ['error' => $e->getMessage()];
        }

        // User statistics
        try {
            $userQuery = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active 
                         FROM users";
            $userResult = $this->db->query($userQuery);
            $userRow = $userResult->fetch(\PDO::FETCH_ASSOC);
            
            $stats['users'] = [
                'total' => $userRow['total'] ?? 0,
                'active' => $userRow['active'] ?? 0
            ];
        } catch (\Exception $e) {
            $stats['users'] = ['error' => $e->getMessage()];
        }

        // File statistics
        $stats['files'] = [
            'uploads' => $this->countFiles(BASEPATH . '/uploads'),
            'backups' => $this->countFiles(BASEPATH . '/backups'),
            'cache_size' => $this->getDirectorySize(BASEPATH . '/cache')
        ];

        return $stats;
    }

    /**
     * Clear system cache
     */
    public function clearCache(): bool
    {
        try {
            $cacheDir = BASEPATH . '/cache';
            
            // Clear Twig cache
            $twigCacheDir = $cacheDir . '/views';
            if (is_dir($twigCacheDir)) {
                $this->removeDirectory($twigCacheDir);
            }

            // Clear container cache
            $containerCacheDir = $cacheDir . '/container';
            if (is_dir($containerCacheDir)) {
                $this->removeDirectory($containerCacheDir);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to clear cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create system backup
     */
    public function createBackup(): array
    {
        $backup = [
            'success' => false,
            'message' => '',
            'filename' => ''
        ];

        try {
            $backupDir = BASEPATH . '/backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backupDir . '/' . $filename;

            // Get database configuration
            $config = require CONFIG_PATH . '/database.php';
            
            // Create backup using mysqldump
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --single-transaction %s > %s',
                escapeshellarg($config['host']),
                escapeshellarg($config['username']),
                escapeshellarg($config['password']),
                escapeshellarg($config['database']),
                escapeshellarg($filepath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($filepath)) {
                 $backup['success'] = true;
                 $backup['message'] = 'Backup created successfully';
                 $backup['filename'] = $filename;
                 $backup['size'] = $this->formatBytes((int)(filesize($filepath) ?: 0));
             } else {
                 $backup['message'] = 'Failed to create backup';
             }
        } catch (\Exception $e) {
            $backup['message'] = 'Error: ' . $e->getMessage();
        }

        return $backup;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get file-based logs
     */
    private function getFileLogs(int $limit): array
    {
        $logs = [];
        $logFile = BASEPATH . '/logs/app.log';
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -$limit);
            
            foreach ($lines as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (.*)$/', $line, $matches)) {
                    $logs[] = [
                        'created_at' => $matches[1],
                        'message' => $matches[2],
                        'level' => 'INFO'
                    ];
                }
            }
        }
        
        return array_reverse($logs);
    }

    /**
     * Count files in directory
     */
    private function countFiles(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $files = glob($directory . '/*');
        return count($files ?: []);
    }

    /**
     * Get directory size
     */
    private function getDirectorySize(string $directory): string
    {
        if (!is_dir($directory)) {
            return '0 B';
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $this->formatBytes($size);
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
