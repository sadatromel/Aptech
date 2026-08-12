<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\SystemLibraryService;
use Core\Container;

class SystemController
{
    private SystemLibraryService $systemService;

    public function __construct()
    {
        $this->systemService = Container::get(SystemLibraryService::class);
    }

    /**
     * Show system library (legacy compatibility)
     */
    public function index(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $config = $this->systemService->getSystemConfig();
        $stats = $this->systemService->getSystemStats();

        include __DIR__ . '/../../views/system/library.php';
    }

    /**
     * Show system logs
     */
    public function logs(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $logs = $this->systemService->getSystemLogs();

        include __DIR__ . '/../../views/system/logs.php';
    }

    /**
     * Show system configuration
     */
    public function config(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $config = $this->systemService->getSystemConfig();

        include __DIR__ . '/../../views/system/config.php';
    }

    /**
     * Clear system cache
     */
    public function clearCache(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->systemService->clearCache();
            
            if ($result) {
                $_SESSION['success_message'] = 'Cache cleared successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to clear cache';
            }
        }

        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/system');
        exit;
    }

    /**
     * Create system backup
     */
    public function createBackup(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $backup = $this->systemService->createBackup();
            
            if ($backup['success']) {
                $_SESSION['success_message'] = $backup['message'] . ' (' . $backup['filename'] . ' - ' . $backup['size'] . ')';
            } else {
                $_SESSION['error_message'] = $backup['message'];
            }
        }

        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/system');
        exit;
    }

    /**
     * Get system statistics via AJAX
     */
    public function getStats(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $stats = $this->systemService->getSystemStats();

        header('Content-Type: application/json');
        echo json_encode($stats);
    }

    /**
     * Show backups page
     */
    public function backups(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $backupDir = __DIR__ . '/../../backups';
        $backups = [];
        
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*.sql');
            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'size' => $this->formatBytes(filesize($file)),
                    'created_at' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }

        include __DIR__ . '/../../views/system/backups.php';
    }

    /**
     * Show theme demo page
     */
    public function themeDemo(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        include __DIR__ . '/../../views/system/theme_demo.php';
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
}
