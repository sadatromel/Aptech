<?php
/**
 * Simple Autoloader for ERP System
 * 
 * An autoloader automatically loads PHP class files when they are needed,
 * without requiring manual include/require statements.
 * 
 * Benefits:
 * 1. Better Performance - Only loads files that are actually used
 * 2. Cleaner Code - No need for multiple include/require statements
 * 3. Easier Maintenance - Classes are loaded automatically
 * 4. Follows PSR-4 Standards - Modern PHP standard
 */

spl_autoload_register(function ($class_name) {
    // Define BASEPATH if not defined
    if (!defined('BASEPATH')) {
        define('BASEPATH', dirname(__DIR__));
    }
    
    // Convert namespace to file path
    $class_file = str_replace('\\', DIRECTORY_SEPARATOR, $class_name) . '.php';
    
    // Possible directories to look for classes
    $directories = [
        BASEPATH . '/classes',        // Main classes directory
        BASEPATH . '/library',        // Library classes
        BASEPATH . '/core/classes',   // Core specific classes
        BASEPATH . '/admin/classes',  // Admin module classes
        // Add more module class directories as needed
    ];
    
    // Search for the class file
    foreach ($directories as $directory) {
        $file = $directory . DIRECTORY_SEPARATOR . $class_file;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    // If class not found, you could log an error
    error_log("Autoloader: Class '$class_name' not found in any directory");
});

/**
 * Usage Example:
 * 
 * Instead of:
 * require_once 'classes/User.php';
 * require_once 'classes/Database.php';
 * require_once 'classes/Auth.php';
 * 
 * You can just use:
 * $user = new User();
 * $db = new Database();
 * $auth = new Auth();
 * 
 * The autoloader will automatically load the required files when you instantiate the classes.
 */

/**
 * Example Class Structure:
 * 
 * classes/
 * ├── User.php           (contains class User)
 * ├── Database.php       (contains class Database)
 * ├── Auth.php          (contains class Auth)
 * └── modules/
 *     ├── Merchandising/
 *         │   └── BOM.php    (contains class Merchandising\BOM)
 *     └── Production/
 *         │   └── Plan.php   (contains class Production\Plan)
 */

?>
