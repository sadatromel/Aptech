<?php
namespace App\Controllers;

use Core\Controller;

class DynamicModuleController extends Controller {
    public function handle() {
        $module = $_GET['module'] ?? '';
        $file = $_GET['file'] ?? '';

        // Security: sanitize
        $module = preg_replace('/[^a-zA-Z0-9_-]/', '', $module);
        $file = preg_replace('/[^a-zA-Z0-9_-]/', '', $file);

        $path = BASEPATH . "/modules/$module/$file.php";

        if (empty($module) || empty($file) || !file_exists($path)) {
            // Render a 404-ish view
            $this->view('dynamic_module', [
                'module_name' => 'Error',
                'module_content' => '<div class="alert alert-danger">Module file not found. Path: ' . htmlspecialchars($path) . '</div>'
            ]);
            return;
        }

        // Capture Output of the PHP file
        // We start output buffering
        ob_start();
        
        // Expose $conn for legacy scripts that might expect it in global scope if they rely on it being there
        // Note: In MVC, we usually use get_db_connection(), but good to be safe.
        $conn = get_db_connection();
        
        try {
            // Include the file. It executes here.
            include $path;
        } catch (\Exception $e) {
            echo "<div class='alert alert-danger'>Error in module: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        $content = ob_get_clean();

        // Render the content wrapped in the main App Layout
        $this->view('dynamic_module', [
             'module_name' => $module,
             'module_content' => $content
        ]);
    }
}
