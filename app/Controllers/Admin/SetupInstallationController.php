<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\SetupInstallationService;
use PDO;
use PDOException;

class SetupInstallationController extends BaseController
{
    private $setupService;
    
    public function __construct(SetupInstallationService $setupService)
    {
        parent::__construct();
        $this->setupService = $setupService;
    }
    
    /**
     * Display main setup & installation dashboard
     */
    public function index()
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'super_admin']);
        
        $systemStats = $this->setupService->getSystemStats();
        $requirements = $this->setupService->checkSystemRequirements();
        $configurations = $this->setupService->getAllConfigurations();
        $backups = $this->setupService->getBackupFiles();
        
        $this->view('admin/setup/index', [
            'pageTitle' => 'Setup & Installation',
            'systemStats' => $systemStats,
            'requirements' => $requirements,
            'configurations' => $configurations,
            'backups' => $backups,
            'activeModule' => 'admin',
            'activeSubModule' => 'setup_installation',
            'base_url' => BASE_URL
        ]);
    }
    
    /**
     * System information page
     */
    public function systemInfo()
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'super_admin']);
        
        $systemStats = $this->setupService->getSystemStats();
        $requirements = $this->setupService->checkSystemRequirements();
        
        $this->view('admin/setup/system_info', [
            'pageTitle' => 'System Information',
            'systemStats' => $systemStats,
            'requirements' => $requirements,
            'activeModule' => 'admin',
            'activeSubModule' => 'setup_installation',
            'base_url' => BASE_URL
        ]);
    }
    
    /**
     * Configuration management page
     */
    public function configuration()
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'super_admin']);
        
        $configurations = $this->setupService->getAllConfigurations();
        
        $this->view('admin/setup/configuration', [
            'pageTitle' => 'System Configuration',
            'configurations' => $configurations,
            'activeModule' => 'admin',
            'activeSubModule' => 'setup_installation',
            'base_url' => BASE_URL
        ]);
    }
    
    /**
     * Save configuration
     */
    public function saveConfiguration()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
            return;
        }
        
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        $type = $_POST['type'] ?? 'string';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? 'general';
        $isEncrypted = isset($_POST['is_encrypted']) && $_POST['is_encrypted'] === '1';
        
        if (empty($key)) {
            $this->json(['success' => false, 'message' => 'Configuration key is required'], 400);
            return;
        }
        
        try {
            $config = $this->setupService->saveConfiguration($key, $value, $type, $description, $category, $isEncrypted);
            $this->json([
                'success' => true,
                'message' => 'Configuration saved successfully',
                'config' => $config->toArray()
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Delete configuration
     */
    public function deleteConfiguration()
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
            return;
        }
        
        $key = $_POST['key'] ?? '';
        
        if (empty($key)) {
            $this->json(['success' => false, 'message' => 'Configuration key is required'], 400);
            return;
        }
        
        try {
            $deleted = $this->setupService->deleteConfiguration($key);
            
            if ($deleted) {
                $this->json(['success' => true, 'message' => 'Configuration deleted successfully']);
            } else {
                $this->json(['success' => false, 'message' => 'Configuration not found'], 404);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Database backup page
     */
    public function backup()
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'super_admin']);
        
        $backups = $this->setupService->getBackupFiles();
        
        $this->view('admin/setup/backup', [
            'pageTitle' => 'Database Backup',
            'backups' => $backups,
            'activeModule' => 'admin',
            'activeSubModule' => 'setup_installation',
            'base_url' => BASE_URL
        ]);
    }
    
    /**
     * Create database backup
     */
    public function createBackup()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
            return;
        }
        
        try {
            $result = $this->setupService->backupDatabase();
            
            if ($result['success']) {
                $this->json([
                    'success' => true,
                    'message' => 'Backup created successfully',
                    'backup' => $result
                ]);
            } else {
                $this->json(['success' => false, 'message' => $result['error']], 500);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Download backup file
     */
    public function downloadBackup($filename)
    {
        $backupPath = dirname(__DIR__, 3) . '/backups/' . $filename;
        
        if (!file_exists($backupPath)) {
            $this->redirect('admin/setup/backup');
            exit;
        }
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backupPath));
        
        readfile($backupPath);
        exit;
    }
    
    /**
     * Delete backup file
     */
    public function deleteBackup()
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
            return;
        }
        
        $filename = $_POST['filename'] ?? '';
        
        if (empty($filename)) {
            $this->json(['success' => false, 'message' => 'Filename is required'], 400);
            return;
        }
        
        $backupPath = dirname(__DIR__, 3) . '/backups/' . $filename;
        
        if (!file_exists($backupPath)) {
            $this->json(['success' => false, 'message' => 'Backup file not found'], 404);
            return;
        }
        
        try {
            if (unlink($backupPath)) {
                $this->json(['success' => true, 'message' => 'Backup deleted successfully']);
            } else {
                $this->json(['success' => false, 'message' => 'Failed to delete backup'], 500);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Installation wizard
     */
    public function wizard()
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'super_admin']);
        
        // Check if already installed
        if (file_exists(dirname(__DIR__, 3) . '/config/config.php')) {
            $this->redirect('dashboard');
            exit;
        }
        
        $step = $_GET['step'] ?? 1;
        $requirements = $this->setupService->checkSystemRequirements();
        
        $this->view('admin/setup/wizard', [
            'pageTitle' => 'Installation Wizard',
            'step' => $step,
            'requirements' => $requirements,
            'activeModule' => 'admin',
            'activeSubModule' => 'setup_installation',
            'base_url' => BASE_URL
        ]);
    }
    
    /**
     * Process installation
     */
    public function install()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
            return;
        }
        
        $step = $_POST['step'] ?? 1;
        
        switch ($step) {
            case 1:
                // Verify requirements
                $requirements = $this->setupService->checkSystemRequirements();
                $hasErrors = false;
                
                foreach ($requirements as $requirement) {
                    if ($requirement['status'] === 'fail') {
                        $hasErrors = true;
                        break;
                    }
                }
                
                if ($hasErrors) {
                    $this->json([
                        'success' => false,
                        'message' => 'Please fix all failed requirements before proceeding',
                        'requirements' => $requirements
                    ]);
                } else {
                    $this->json(['success' => true, 'next_step' => 2]);
                }
                break;
                
            case 2:
                // Database configuration
                $host = $_POST['db_host'] ?? 'localhost';
                $name = $_POST['db_name'] ?? '';
                $user = $_POST['db_user'] ?? '';
                $pass = $_POST['db_pass'] ?? '';
                
                // Test database connection
                try {
                    $pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Save config
                    $configContent = "<?php\n";
                    $configContent .= "define('DB_HOST', '$host');\n";
                    $configContent .= "define('DB_NAME', '$name');\n";
                    $configContent .= "define('DB_USER', '$user');\n";
                    $configContent .= "define('DB_PASS', '$pass');\n";
                    
                    $configPath = dirname(__DIR__, 3) . '/config/config.php';
                    file_put_contents($configPath, $configContent);
                    
                    $this->json(['success' => true, 'next_step' => 3]);
                } catch (PDOException $e) {
                    $this->json(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
                }
                break;
                
            case 3:
                // Admin account creation
                $name = $_POST['admin_name'] ?? '';
                $email = $_POST['admin_email'] ?? '';
                $password = $_POST['admin_password'] ?? '';
                
                if (empty($name) || empty($email) || empty($password)) {
                    $this->json(['success' => false, 'message' => 'All fields are required']);
                    return;
                }
                
                // Create admin user
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Create users table if not exists
                    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        email VARCHAR(255) UNIQUE NOT NULL,
                        password VARCHAR(255) NOT NULL,
                        role VARCHAR(50) DEFAULT 'user',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
                    $stmt->execute([$name, $email, $hash]);
                    
                    $this->json(['success' => true, 'message' => 'Installation completed successfully']);
                } catch (PDOException $e) {
                    $this->json(['success' => false, 'message' => 'Error creating admin account: ' . $e->getMessage()]);
                }
                break;
                
            default:
                $this->json(['success' => false, 'message' => 'Invalid step'], 400);
        }
    }
}
