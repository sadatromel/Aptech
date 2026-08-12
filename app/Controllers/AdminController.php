<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminService;
use Core\Container;
use App\Http\Response;

class AdminController extends BaseController
{
    private AdminService $adminService;

    public function __construct()
    {
        parent::__construct();
        $this->adminService = Container::get(AdminService::class);
    }

    /**
     * Main entry point for admin pages
     */
    public function index(): void
    {
        $this->dashboard();
    }

    /**
     * Show admin dashboard
     */
    public function dashboard(): void
    {
        // Redirect to main dashboard
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/dashboard');
        exit;
    }

    /**
     * Show module loader (legacy compatibility)
     */
    public function moduleLoader(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $modules = $this->adminService->getModuleList();
        $stats = $this->adminService->getSystemStats();

        // Use Twig view if available, or just render message
        // We know admin/module_loader.twig exists in app/Views/admin/module_loader.twig
        $this->view('admin/module_loader', [
            'modules' => $modules,
            'stats' => $stats
        ]);
    }

    /**
     * Handle module upload
     */
    public function uploadModule(): void
    {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        // Handle module upload logic here
        $_SESSION['success_message'] = 'Module upload functionality coming soon';
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/admin/modules');
        exit;
    }
    /**
     * Show admin setup page
     */
    public function setup(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $tab = $_GET['tab'] ?? 'setup';
        $viewData = [
            'title' => 'Admin Setup',
            'active_tab' => $tab
        ];

        switch ($tab) {
            case 'backups':
                // Load backup-specific data if needed (mocked for now)
                $viewData['backups'] = []; // Would come from service
                $this->view('admin/setup/backups', $viewData);
                break;

            case 'database':
                $this->view('admin/setup/database', $viewData);
                break;

            case 'configuration':
                $configFile = __DIR__ . '/../../config/paths.php';
                $configContent = file_exists($configFile) ? file_get_contents($configFile) : 'Configuration file not found.';
                $viewData['config_content'] = $configContent;
                // Basic env detection for display
                $viewData['environment'] = getenv('APP_ENV') ?: 'development';
                $viewData['site_url'] = defined('BASE_URL') ? BASE_URL : 'http://localhost/Aptech';
                
                $this->view('admin/setup/configuration', $viewData);
                break;

            case 'tools':
                $stats = $this->adminService->getSystemStats();
                $viewData['system_info'] = [
                    'php_version' => phpversion(),
                    'mysql_version' => $stats['system']['mysql_version'] ?? 'Unknown',
                    'server_os' => PHP_OS,
                    'web_server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'memory_limit' => ini_get('memory_limit'),
                    'max_upload' => ini_get('upload_max_filesize'),
                ];
                $this->view('admin/setup/tools', $viewData);
                break;

            case 'management':
                // Check if system is configured (e.g. database.php exists)
                $isConfigured = file_exists(__DIR__ . '/../../config/database.php');
                $viewData['is_configured'] = $isConfigured;
                
                if ($isConfigured) {
                    // Fetch admins/users for management view
                    $viewData['users'] = $this->adminService->getAllUsers();
                }
                
                $this->view('admin/setup/management', $viewData);
                break;
                
            case 'setup':
            default:
                $viewData['current_config'] = [
                    'db_host' => '127.0.0.1',
                    'db_name' => 'transport_erp',
                    'db_user' => 'root',
                    'app_url' => 'http://localhost/Aptech',
                ];
                $this->view('admin/setup/setup', $viewData);
                break;
        }
    }
}
