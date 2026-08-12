<?php
namespace App\Controllers\Admin;

use Core\Controller;
use Core\Request;

class ModuleLoaderController extends Controller {
    public function index() {
        $this->view('admin/module_loader', ['title' => 'Module Loader']);
    }

    public function upload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/admin/module_loader');
            exit;
        }

        $moduleName = trim($_POST['module_name'] ?? '');
        $file = $_FILES['module_file'] ?? null;

        if (empty($moduleName) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Please provide a module name and a valid PHP file.";
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/admin/module_loader');
            exit;
        }

        // Validate file type
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'php') {
            $_SESSION['error'] = "Only PHP files are allowed.";
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/admin/module_loader');
            exit;
        }

        // Create Module Directory
        // Storing in a 'modules' directory in the root
        $modulePath = BASEPATH . '/modules/' . $this->sanitizeFileName($moduleName);
        if (!is_dir($modulePath)) {
            if (!mkdir($modulePath, 0755, true)) {
                $_SESSION['error'] = "Failed to create module directory.";
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/admin/module_loader');
                exit;
            }
        }

        // Move File
        $originalName = basename($file['name']);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize name but keep extension
        $safeName = $this->sanitizeFileName($nameWithoutExt) . '.' . $ext;
        $targetFile = $modulePath . '/' . $safeName;
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $_SESSION['success'] = "Module uploaded successfully! Access it via: " . (defined('BASE_URL') ? BASE_URL : '/Aptech') . "/dynamic/" . urlencode($moduleName) . "/" . urlencode($this->sanitizeFileName($nameWithoutExt));
        } else {
            $_SESSION['error'] = "Failed to move uploaded file.";
        }

        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/admin/module_loader');
        exit;
    }

    private function sanitizeFileName($name) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    }
}
