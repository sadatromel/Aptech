<?php
/**
 * ERP System Path Configuration
 * Defines all base paths for the application
 */

// Define base path
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/..');
}

// Define directory paths
if (!defined('CORE_PATH')) define('CORE_PATH', BASEPATH . '/core');
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', BASEPATH . '/config');
if (!defined('ASSETS_PATH')) define('ASSETS_PATH', BASEPATH . '/assets');
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', BASEPATH . '/uploads');
if (!defined('SETUP_PATH')) define('SETUP_PATH', BASEPATH . '/setup');
if (!defined('TESTS_PATH')) define('TESTS_PATH', BASEPATH . '/tests');
if (!defined('DOCS_PATH')) define('DOCS_PATH', BASEPATH . '/docs');
if (!defined('SQL_PATH')) define('SQL_PATH', BASEPATH . '/sql');
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', BASEPATH . '/public');

// Module paths
if (!defined('ADMIN_PATH')) define('ADMIN_PATH', BASEPATH . '/admin');
if (!defined('API_PATH')) define('API_PATH', BASEPATH . '/api');
if (!defined('COMMERCIAL_PATH')) define('COMMERCIAL_PATH', BASEPATH . '/commercial');
if (!defined('CUTTING_PATH')) define('CUTTING_PATH', BASEPATH . '/cutting');
if (!defined('FINANCE_PATH')) define('FINANCE_PATH', BASEPATH . '/finance');
if (!defined('HR_PATH')) define('HR_PATH', BASEPATH . '/hr');
if (!defined('MERCHANDISING_PATH')) define('MERCHANDISING_PATH', BASEPATH . '/merchandising');
if (!defined('PACKING_PATH')) define('PACKING_PATH', BASEPATH . '/packing');
if (!defined('PROCUREMENT_PATH')) define('PROCUREMENT_PATH', BASEPATH . '/procurement');
if (!defined('PRODUCTION_PATH')) define('PRODUCTION_PATH', BASEPATH . '/production');
if (!defined('SEWING_PATH')) define('SEWING_PATH', BASEPATH . '/sewing');
if (!defined('STORE_INVENTORY_PATH')) define('STORE_INVENTORY_PATH', BASEPATH . '/store_inventory');
if (!defined('VEHICLE_MANAGEMENT_PATH')) define('VEHICLE_MANAGEMENT_PATH', BASEPATH . '/vehicle_management');
if (!defined('WASHING_PATH')) define('WASHING_PATH', BASEPATH . '/washing');

// URL paths (for web access)
// Detect if we're running from public directory or root
    // For Aptech project, BASE_URL should consistently be /Aptech
    // and correctly handle if we are running from /public
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $publicPos = strpos($scriptName, '/public');
    
    if ($publicPos !== false) {
        define('BASE_URL', '/Aptech');
    } else {
        define('BASE_URL', '/Aptech');
    }

if (!defined('CORE_URL')) define('CORE_URL', BASE_URL . '/core');
if (!defined('ASSETS_URL')) define('ASSETS_URL', '/Aptech/assets'); // Assets are in root, not public
if (!defined('UPLOADS_URL')) define('UPLOADS_URL', '/Aptech/uploads'); // Uploads are in root, not public

// Ensure include path is set
set_include_path(get_include_path() . PATH_SEPARATOR . CORE_PATH . PATH_SEPARATOR . CONFIG_PATH);

?>
