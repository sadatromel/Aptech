<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SetupInstallation;
use Core\Database;
use PDO;

class SetupInstallationService
{
    private Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    /**
     * Get system statistics
     */
    public function getSystemStats()
    {
        $model = new SetupInstallation();
        
        return [
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->getMySQLVersion(),
            'server_os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'disk_space' => $this->getDiskSpace(),
            'database_size' => $this->getDatabaseSize(),
            'config_count' => $model->count()
        ];
    }
    
    /**
     * Get MySQL version
     */
    private function getMySQLVersion()
    {
        try {
            $statement = $this->db->query('SELECT VERSION() as version');
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            return $result['version'] ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
    
    /**
     * Get disk space information
     */
    private function getDiskSpace()
    {
        // Use appropriate path for Windows
        $root = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'C:' : '/';
        $free = disk_free_space($root);
        $total = disk_total_space($root);
        
        if ($free === false || $total === false) {
            return [
                'free' => 'Unknown',
                'total' => 'Unknown',
                'used_percentage' => 'Unknown'
            ];
        }
        
        return [
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'used_percentage' => round((($total - $free) / $total) * 100, 2)
        ];
    }
    
    /**
     * Get database size
     */
    private function getDatabaseSize()
    {
        try {
            $dbName = 'aptech_erp';
            $statement = $this->db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'size' 
                                 FROM information_schema.tables 
                                 WHERE table_schema = '$dbName'");
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            return $result['size'] . ' MB';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get all configurations grouped by category
     */
    public function getAllConfigurations()
    {
        $model = new SetupInstallation();
        $configurations = [];
        $categories = $this->getCategories();
        
        foreach ($categories as $category) {
            $configs = $model->where('category', $category)->orderBy('config_key')->all();
            $configurations[$category] = $configs;
        }
        
        return $configurations;
    }
    
    /**
     * Get configuration categories
     */
    private function getCategories()
    {
        $model = new SetupInstallation();
        $configs = $model->all();
        $categories = [];
        
        foreach ($configs as $config) {
            if (!in_array($config['category'], $categories)) {
                $categories[] = $config['category'];
            }
        }
        
        sort($categories);
        return $categories;
    }
    
    /**
     * Save configuration
     */
    public function saveConfiguration($key, $value, $type, $description, $category, $isEncrypted)
    {
        return SetupInstallation::setValue($key, $value, $type, $description, $category, $isEncrypted);
    }
    
    /**
     * Delete configuration
     */
    public function deleteConfiguration($key)
    {
        return SetupInstallation::deleteByKey($key);
    }
    
    /**
     * Check system requirements
     */
    public function checkSystemRequirements()
    {
        $requirements = [
            'php_version' => [
                'required' => '>= 7.4',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'pass' : 'fail'
            ],
            'pdo_mysql' => [
                'required' => 'Enabled',
                'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('pdo_mysql') ? 'pass' : 'fail'
            ],
            'gd' => [
                'required' => 'Enabled',
                'current' => extension_loaded('gd') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('gd') ? 'pass' : 'fail'
            ],
            'curl' => [
                'required' => 'Enabled',
                'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('curl') ? 'pass' : 'fail'
            ],
            'fileinfo' => [
                'required' => 'Enabled',
                'current' => extension_loaded('fileinfo') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('fileinfo') ? 'pass' : 'fail'
            ],
            'memory_limit' => [
                'required' => '>= 64M',
                'current' => ini_get('memory_limit'),
                'status' => $this->compareBytes(ini_get('memory_limit'), '64M') ? 'pass' : 'warning'
            ],
            'max_execution_time' => [
                'required' => '>= 30',
                'current' => ini_get('max_execution_time'),
                'status' => (int)ini_get('max_execution_time') >= 30 ? 'pass' : 'warning'
            ]
        ];
        
        return $requirements;
    }
    
    /**
     * Compare byte values
     */
    private function compareBytes($current, $required)
    {
        $currentBytes = $this->returnBytes($current);
        $requiredBytes = $this->returnBytes($required);
        
        return $currentBytes >= $requiredBytes;
    }
    
    /**
     * Convert PHP ini value to bytes
     */
    private function returnBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int)$val;
        
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Backup database
     */
    public function backupDatabase($backupPath = null)
    {
        if (!$backupPath) {
            $backupPath = dirname(__DIR__, 2) . '/backups/';
        }
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupPath . $filename;
        
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $db = 'aptech_erp';
        
        $command = "mysqldump --host={$host} --user={$user} --password={$pass} {$db} > {$filepath}";
        
        exec($command, $output, $return);
        
        if ($return === 0) {
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath)
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to create backup'
        ];
    }
    
    /**
     * Get backup files list
     */
    public function getBackupFiles()
    {
        $backupPath = dirname(__DIR__, 2) . '/backups/';
        $files = [];
        
        if (is_dir($backupPath)) {
            $items = scandir($backupPath);
            foreach ($items as $item) {
                if ($item[0] !== '.' && pathinfo($item, PATHINFO_EXTENSION) === 'sql') {
                    $filepath = $backupPath . $item;
                    $files[] = [
                        'filename' => $item,
                        'size' => $this->formatBytes(filesize($filepath)),
                        'created' => date('Y-m-d H:i:s', filemtime($filepath))
                    ];
                }
            }
        }
        
        return array_reverse($files);
    }
}
