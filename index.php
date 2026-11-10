<?php
/**
 * ERP System - Pure MVC Main Entry Point
 * 100% MVC Architecture - No Legacy Dependencies
 */

use Core\Container;
use App\Controllers\HR\LeaveController;

// Load path configuration
require_once __DIR__ . '/config/paths.php';

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load permission helpers
require_once __DIR__ . '/core/permission_helper.php';

// Start session
session_start();

// Intercept clean URLs and dispatch via modern Router
$container = Container::getInstance();
$router = $container->get(\App\Http\Router::class);
$httpMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
if (($pos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $pos);
}
foreach (['/Aptech/public', '/Aptech'] as $base) {
    if (strpos($requestUri, $base) === 0) {
        $requestUri = substr($requestUri, strlen($base));
        break;
    }
}
$requestUri = '/' . ltrim($requestUri, '/');
$routeResult = $router->dispatch($httpMethod, $requestUri);
if ($routeResult[0] === 200) {
    $handler = $routeResult[1];
    $vars = array_values($routeResult[2] ?? []);
    try {
        if ($handler instanceof \Closure) {
            call_user_func_array($handler, $vars);
        } elseif (is_array($handler)) {
            [$controller, $method] = $handler;
            $controllerInstance = $container->make($controller);
            call_user_func_array([$controllerInstance, $method], $vars);
        }
        exit;
    } catch (\Throwable $e) {
        // Fallback to switch block if dispatching fails
    }
}

// Redirect explicit ?page= legacy URLs to clean modern URLs
if (isset($_GET['page']) && strpos($_SERVER['REQUEST_URI'], 'page=') !== false) {
    $page_param = $_GET['page'];
    
    // Map legacy alias pages to clean paths
    $legacy_mappings = [
        'chart_of_accounts' => 'finance/accounts',
        'general_ledger' => 'finance/general-ledger',
        'finance/ledger' => 'finance/ledger',
        'production/cutting' => 'cutting/plans',
        'cutting_plans' => 'cutting/plans',
        'bundle_generation' => 'cutting/bundles',
        'production/sewing' => 'production/sewing/lines',
        'sewing_lines' => 'production/sewing/lines',
        'sewing_progress' => 'sewing/performance',
        'sewing/performance' => 'sewing/performance',
        'incentives' => 'sewing/incentives',
        'sewing/incentives' => 'sewing/incentives',
        'qc_checks' => 'sewing/qc',
        'defects' => 'sewing/qc',
        'product_natures' => 'master_library/product_natures',
        'product_types' => 'master_library/product_types',
        'garments_items' => 'master_library/garments_items',
    ];
    
    $clean_path = $legacy_mappings[$page_param] ?? null;
    
    // If not in mapping, check if it starts with 'finance/' or is a finance alias
    if ($clean_path === null && (strpos($page_param, 'finance/') === 0 || $page_param === 'chart_of_accounts' || $page_param === 'general_ledger')) {
        $clean_path = $page_param;
    }
    
    if ($clean_path !== null) {
        // Handle specific actions for finance/general-ledger to clean endpoints
        $action_param = $_GET['action'] ?? null;
        if ($clean_path === 'finance/general-ledger' && in_array($action_param, ['trial-balance', 'profit-and-loss', 'balance-sheet', 'cash-book'])) {
            $clean_path .= '/' . $action_param;
            unset($_GET['action']);
        }
        
        // Handle sewing lines action endpoints
        if ($clean_path === 'production/sewing/lines') {
            if ($action_param === 'create') {
                $clean_path = 'production/sewing/lines/create';
                unset($_GET['action']);
            } elseif ($action_param === 'edit' && isset($_GET['id'])) {
                $clean_path = 'production/sewing/lines/' . $_GET['id'] . '/edit';
                unset($_GET['action']);
                unset($_GET['id']);
            }
        }
        
        $query_params = $_GET;
        unset($query_params['page']);
        
        $redirect_url = BASE_URL . '/' . $clean_path;
        if (!empty($query_params)) {
            $redirect_url .= '?' . http_build_query($query_params);
        }
        
        header('Location: ' . $redirect_url, true, 301);
        exit;
    }
}

// Get the requested page and action
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : 'index';

// Clean URL Routing Helper
$path_segments = explode('/', $page);
if (count($path_segments) >= 3) {
    $three_segment_page = $path_segments[0] . '/' . $path_segments[1] . '/' . $path_segments[2];
    $known_3_segment_routes = [
        'production/sewing/lines',
        'hr/payroll/bonus-reprocess',
        'hr/payroll/cost-center-salary',
        'hr/payroll/earn-leave-monthly',
        'hr/payroll/earn-leave-yearly',
        'hr/payroll/final-settlement',
        'hr/payroll/fraction-salary-reprocess',
        'hr/payroll/holiday-allowance',
        'hr/payroll/monthly-bill-process',
        'hr/payroll/night-allowance',
        'hr/payroll/salary-adjustment',
        'hr/payroll/period-lock',
        'hr/payroll/tiffin-bill',
        'hr/employees/increment-entry',
        'hr/employees/increment-bulk',
        'hr/employees/promotion-entry',
        'hr/employees/promotion-bulk',
        'hr/employees/lay-off',
        'master_library/districts',
        'master_library/sub_districts',
        'hr/attendance/movement-bulk',
        'hr/attendance/date-transfer',
        'hr/attendance/define-holiday',
        'hr/attendance/define-weekend',
        'hr/attendance/roster-reprocess',
        'hr/attendance/holiday-remove',
        'hr/attendance/manual-entry',
        'hr/attendance/movement-register',
        'hr/attendance/ot-requisition',
    ];

    if (in_array($three_segment_page, $known_3_segment_routes)) {
        $page = $three_segment_page;
        if (isset($path_segments[3])) {
            if (is_numeric($path_segments[3])) {
                $_GET['id'] = $path_segments[3];
                $action = isset($path_segments[4]) ? $path_segments[4] : 'show';
            } else {
                $action = $path_segments[3];
                if (isset($path_segments[4])) {
                    $_GET['id'] = $path_segments[4];
                }
            }
        } else {
            $action = 'index';
        }
    } else {
        // General structure: module/controller/action or module/controller/id/action
        $page = $path_segments[0] . '/' . $path_segments[1];
        
        if (is_numeric($path_segments[2])) {
            $_GET['id'] = $path_segments[2];
            $action = isset($path_segments[3]) ? $path_segments[3] : 'show';
        } else {
            $action = $path_segments[2];
            if (isset($path_segments[3])) {
                $_GET['id'] = $path_segments[3];
            }
        }
    }
} elseif (count($path_segments) == 2) {
    // Check for specific modules that support 2-segment routing (module/action)
    $allowed_modules = ['finance', 'admin', 'vehicle_requisition', 'vehicle_requisitions', 'store_requisition', 'store_requisitions'];
    
    if (in_array($path_segments[0], $allowed_modules)) {
        // For admin and finance, we might want to keep the legacy behavior or standardise
        // The original code kept "finance/accounts" as "finance/accounts".
        // But for "vehicle_requisition/create", we want page="vehicle_requisition", action="create".
        
        if (in_array($path_segments[0], ['vehicle_requisition', 'vehicle_requisitions', 'store_requisition', 'store_requisitions'])) {
            $page = $path_segments[0];
            $action = $path_segments[1];
        } else {
            // Keep original behavior for admin/finance for now to avoid regressions
             // (e.g. admin/users is a valid page case in switch)
        }
    }
}

// Security check - ensure user is logged in for most pages
$public_pages = ['login', 'register', 'logout'];
if (!in_array($page, $public_pages) && !isset($_SESSION['user_id'])) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
    exit;
}

// Initialize Container
$container = Container::getInstance();

// Helper for Master Library CRUD
function handleCrud(object $controller, string $action): void {
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->store();
    } elseif ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->store();
    } elseif ($action === 'edit' && isset($_GET['id'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update((int)$_GET['id']);
        } else {
            $controller->edit((int)$_GET['id']);
        }
    } elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : null);
        if ($id) {
            $controller->update($id);
        }
    } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->destroy((int)$_POST['id']);
    } elseif ($action === 'show' && isset($_GET['id'])) {
         if (method_exists($controller, 'show')) {
             $controller->show((int)$_GET['id']);
         }
    } elseif ($action === 'create') {
        $controller->create();
    } else {
        $controller->index();
    }
}

// Route to appropriate MVC controller
switch ($page) {
    // Authentication
    case 'login':
        $controller = $container->get(\App\Controllers\AuthController::class);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->login();
        } else {
            $controller->showLogin();
        }
        break;

    case 'logout':
        $controller = $container->get(\App\Controllers\AuthController::class);
        $controller->logout();
        break;

    case 'register':
        $controller = $container->get(\App\Controllers\AuthController::class);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->register();
        } else {
            $controller->showRegister();
        }
        break;

    // Dashboard
    case 'dashboard':
    case 'home':
        $controller = $container->get(\App\Controllers\DashboardController::class);
        $controller->index();
        break;

    case 'users':
    case 'user_management':
    case 'admin/users':
        $controller = $container->get(\App\Controllers\Admin\UserController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'reset_password' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->resetPassword((int)$_GET['id']);
        } elseif ($action === 'toggle_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->toggleStatus((int)$_POST['id']);
        } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // JS deleteUser uses fetch to .../users/destroy with body id=...
            // But Controller method is destroy($id).
            // JS: fetch('.../users/destroy', {body: 'id='+userId})
            // Wait, the action in JS is 'destroy'. The block had 'delete'.
            // I should handle 'destroy' or 'delete'.
            $controller->destroy((int)$_POST['id']);
        } elseif ($action === 'destroy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->destroy((int)$_POST['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    case 'profile':
        $controller = $container->get(\App\Controllers\ProfileController::class);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update();
        } else {
            $controller->index();
        }
        break;

        break;

    // Admin Functions
    case 'admin':
    case 'admin/setup':
        $controller = $container->get(\App\Controllers\AdminController::class);
        if ($action === 'module_loader') {
            $controller->moduleLoader();
        } elseif ($page === 'admin/setup') {
            $controller->setup();
        } else {
            $controller->index();
        }
        break;

    case 'admin/roles':
    case 'role_permissions': // Legacy alias
        $controller = $container->get(\App\Controllers\Admin\RoleController::class);
        if ($action === 'create') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->store();
            } else {
                $controller->create();
            }
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->destroy((int)$_POST['role_id']);
        } elseif ($action === 'permissions' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->updatePermissions((int)$_GET['id']);
            } else {
                $controller->permissions((int)$_GET['id']);
            }
        } else {
            $controller->index();
        }
        break;

    case 'admin/permissions':
    case 'permissions':
        $controller = $container->get(\App\Controllers\Admin\PermissionController::class);
        if ($action === 'update' || ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'reset')) {
            $controller->update();
        } elseif ($action === 'reset') {
            $controller->reset();
        } else {
            $controller->index();
        }
        break;

    case 'admin/settings':
    case 'system_settings':
        $controller = $container->get(\App\Controllers\Admin\SettingsController::class);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update();
        } else {
            $controller->index();
        }
        break;

    case 'admin/module_loader':
        $controller = $container->get(\App\Controllers\Admin\ModuleLoaderController::class);
        if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->upload();
        } else {
            $controller->index();
        }
        break;

    case 'admin/access_applications':
        $controller = $container->get(\App\Controllers\Admin\AccessApplicationController::class);
        if ($action === 'approve') {
            $controller->approve();
        } elseif ($action === 'reject') {
            $controller->reject();
        } else {
             // Default or list view if it exists, otherwise 404 or redirect
             // For now, redirect to users as applications are listed there
             header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/admin/users'); 
             exit;
        }
        break;

    // ==========================================
    // Master Library Module
    // ==========================================
    case 'master_library':
        $controller = $container->get(\App\Controllers\MasterLibrary\MasterLibraryController::class);
        $controller->index();
        break;

    case 'master_library/countries':
    case 'countries':
        $controller = $container->get(\App\Controllers\MasterLibrary\CountryController::class);
        handleCrud($controller, $action);
        break;

    case 'master_library/cities':
    case 'cities':
        $controller = $container->get(\App\Controllers\MasterLibrary\CityController::class);
        handleCrud($controller, $action);
        break;

    case 'master_library/districts':
    case 'districts':
        $controller = $container->get(\App\Controllers\MasterLibrary\DistrictController::class);
        handleCrud($controller, $action);
        break;

    case 'master_library/sub_districts':
    case 'sub_districts':
        $controller = $container->get(\App\Controllers\MasterLibrary\SubDistrictController::class);
        if ($action === 'get-by-district' && isset($_GET['district_id'])) {
            $controller->getSubDistrictsByDistrict((int)$_GET['district_id']);
        } else {
            handleCrud($controller, $action);
        }
        break;

    case 'master_library/banks':
    case 'banks':
        $controller = $container->get(\App\Controllers\MasterLibrary\BankController::class);
        handleCrud($controller, $action);
        break;
    
    case 'master_library/currencies':
    case 'currencies':
        $controller = $container->get(\App\Controllers\MasterLibrary\CurrencyController::class);
        handleCrud($controller, $action);
        break;

    case 'master_library/factories':
    case 'factories':
        $controller = $container->get(\App\Controllers\MasterLibrary\FactoryController::class);
        handleCrud($controller, $action);
        break;

    case 'master_library/stores':
    case 'stores':
        $controller = $container->get(\App\Controllers\MasterLibrary\StoreController::class);
        handleCrud($controller, $action);
        break;

    case 'master_library/store_locations':
    case 'store_locations':
         $controller = $container->get(\App\Controllers\MasterLibrary\StoreLocationController::class);
         if ($action === 'get-stores') {
             $controller->getStoresByFactory();
         } elseif ($action === 'get-locations') {
             $controller->getLocationsByStore();
         } else {
             handleCrud($controller, $action);
         }
         break;

    case 'master_library/item_categories':
    case 'item_categories':
         $controller = $container->get(\App\Controllers\MasterLibrary\ItemCategoryController::class);
         handleCrud($controller, $action);
         break;

    case 'master_library/production_floors':
    case 'production_floors':
         $controller = $container->get(\App\Controllers\MasterLibrary\ProductionFloorController::class);
         if ($action === 'get-floors') {
             $controller->getFloorsByLocation();
         } else {
             handleCrud($controller, $action);
         }
         break;

    case 'master_library/store_rooms':
    case 'store_rooms':
         $controller = $container->get(\App\Controllers\MasterLibrary\StoreRoomController::class);
         if ($action === 'get-rooms') {
             $controller->getRoomsByFloor();
         } else {
             handleCrud($controller, $action);
         }
         break;

    case 'master_library/store_racks':
    case 'store_racks':
         $controller = $container->get(\App\Controllers\MasterLibrary\StoreRackController::class);
         handleCrud($controller, $action);
         break;

    case 'master_library/production_lines':
    case 'production_lines':
         $controller = $container->get(\App\Controllers\MasterLibrary\ProductionLineController::class);
         handleCrud($controller, $action);
         break;

    case 'master_library/buyers':
    case 'buyers':
          $controller = $container->get(\App\Controllers\MasterLibrary\BuyerController::class);
          handleCrud($controller, $action);
          break;

    case 'master_library/buyer_brands':
    case 'buyer_brands':
          $controller = $container->get(\App\Controllers\MasterLibrary\BuyerBrandController::class);
          handleCrud($controller, $action);
          break;

    case 'master_library/product_categories':
    case 'product_categories':
          $controller = $container->get(\App\Controllers\MasterLibrary\ProductCategoryController::class);
          handleCrud($controller, $action);
          break;

    case 'master_library/product_departments':
    case 'product_departments':
          $controller = $container->get(\App\Controllers\MasterLibrary\ProductDepartmentController::class);
          handleCrud($controller, $action);
          break;

    case 'master_library/garment_sample_types':
    case 'garment_sample_types':
          $controller = $container->get(\App\Controllers\MasterLibrary\GarmentSampleTypeController::class);
          handleCrud($controller, $action);
          break;

    case 'master_library/product_natures':
    case 'product_natures':
          $controller = $container->get(\App\Controllers\MasterLibrary\ProductNatureController::class);
          handleCrud($controller, $action);
          break;

    case 'master_library/product_types':
    case 'product_types':
          $controller = $container->get(\App\Controllers\MasterLibrary\ProductTypeController::class);
          handleCrud($controller, $action);
          break;

    case 'master_library/garments_items':
    case 'garments_items':
          $controller = $container->get(\App\Controllers\MasterLibrary\GarmentsItemController::class);
          handleCrud($controller, $action);
          break;

    case 'master_library/size-ranges':
    case 'size-ranges':
        $controller = $container->get(\App\Controllers\MasterLibrary\SizeRangeController::class);
        // Handle POST to /create OR / (index)
        if (($action === 'create' || $action === 'index' || empty($action)) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'update' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->update((int)$_GET['id']);
        } elseif ($action === 'delete' && isset($_GET['id'])) {
             $controller->destroy((int)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;
    
    case 'master_library/trims_costing_template':
    case 'trims_costing_template':
        $controller = $container->get(\App\Controllers\MasterLibrary\TrimsCostingTemplateController::class);
        if ($action === 'templates-by-buyer') {
            $controller->getTemplatesByBuyer();
        } elseif ($action === 'copy') {
            $controller->copyTemplate();
        } else {
            handleCrud($controller, $action);
        }
        break;

    case 'master_library/terms_conditions':
    case 'terms_conditions':
        $controller = $container->get(\App\Controllers\MasterLibrary\TermsConditionController::class);
        handleCrud($controller, $action);
        break;

    // System Library
    case 'system_library':
        $controller = $container->get(\App\Controllers\Admin\SystemController::class);
        if ($action === 'logs') {
            $controller->logs();
        } elseif ($action === 'config') {
            $controller->config();
        } elseif ($action === 'clear_cache' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->clearCache();
        } elseif ($action === 'backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->createBackup();
        } else {
            $controller->index();
        }
        break;

    // Vehicle Management
    case 'vehicles':
    case 'vehicle_management':
        $controller = $container->get(\App\Controllers\Transport\VehicleController::class);
        if ($action === 'show' && isset($_GET['id'])) {
            $controller->show();
        } elseif ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update();
            } else {
                $controller->edit();
            }
        } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->destroy();
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    case 'vehicle_requisition':
    case 'vehicle_requisitions':
        $controller = $container->get(\App\Controllers\Transport\VehicleController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->createRequisition();
        } elseif ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->updateRequisitionStatus();
        } else {
            $controller->requisitions();
        }
        break;

    // ==========================================
    // HR Module
    // ==========================================
    // ==========================================
    // Visitor Management Module
    // ==========================================
    case 'visitor_log':
    case 'add_visitor':
        $controller = $container->get(\App\Controllers\Visitor\VisitorController::class);
        if ($page === 'add_visitor') {
             if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                 $controller->store();
             } else {
                 $controller->create();
             }
        } elseif ($action === 'check_out' && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->checkOut();
        } else {
             $controller->index();
        }
        break;

    case 'hr':
    case 'hr/dashboard':
        $controller = $container->get(\App\Controllers\HR\HRDashboardController::class);
        $controller->index();
        break;

    case 'hr/showcase':
        $controller = $container->get(\App\Controllers\HR\HRDashboardController::class);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $section = $action;
            $pageName = $_GET['id'] ?? '';
            $controller->storeRecord($section, $pageName);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $id = (int)($_GET['id'] ?? 0);
            $controller->deleteRecord($id);
        } else {
            $section = $action;
            $pageName = $_GET['id'] ?? '';
            $controller->showcase($section, $pageName);
        }
        break;

    case 'hr/employees':
        $controller = $container->get(\App\Controllers\HR\EmployeeController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->destroy((int)$_POST['id']);
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } elseif ($action === 'export') {
            $controller->export();
        } else {
            $controller->index();
        }
        break;

    case 'hr/leave':
        $controller = $container->get(LeaveController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->updateStatus();
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    case 'hr/leave-entry':
    case 'hr/leave/entry':
        $controller = $container->get(LeaveController::class);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->storeEntry();
        } else {
            $controller->entryPage();
        }
        break;

    case 'api/hr/leave-balance':
        $controller = $container->get(LeaveController::class);
        $controller->getLeaveBalanceApi();
        break;

    case 'hr/leave-delete':
    case 'hr/leave/delete':
        $controller = $container->get(LeaveController::class);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->deletePerform();
        } else {
            $controller->deletePage();
        }
        break;

    case 'api/hr/employee-details':
        $controller = $container->get(LeaveController::class);
        $controller->getEmployeeDetailsApi();
        break;

    case 'hr/reports/job-card':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->jobCardReport();
        break;

    case 'hr/reports/job-card-four-hours':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->jobCardFourHoursReport();
        break;

    case 'hr/reports/extra-ot-payment-sheet':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->extraOtPaymentSheetReport();
        break;

    case 'hr/reports/extra-ot-payment-sheet/export-excel':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->exportExtraOtPaymentExcel();
        break;

    case 'hr/reports/salary-sheet-staff':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->salarySheetStaffReport();
        break;

    case 'hr/reports/salary-sheet-worker':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->salarySheetWorkerReport();
        break;

    case 'hr/reports/ot-payment-sheet':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->otPaymentSheetReport();
        break;

    case 'hr/reports/ot-payment-sheet/export-excel':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->exportOtPaymentExcel();
        break;

    case 'hr/reports/payslip-bangla':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->payslipBanglaReport();
        break;

    case 'hr/reports/payslip-english':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->payslipEnglishReport();
        break;

    case 'hr/reports/leave-status':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->leaveStatusReport();
        break;

    case 'hr/reports/employee-leave-record':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->employeeLeaveRecordReport();
        break;

    case 'hr/reports/annual-leave-statement':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->annualLeaveStatementReport();
        break;

    case 'hr/reports/yearly-leave-balance':
        $controller = $container->get(\App\Controllers\HR\HRReportController::class);
        $controller->yearlyLeaveBalanceReport();
        break;

    case 'hr/attendance':
        $controller = $container->get(\App\Controllers\HR\AttendanceController::class);
        if ($action === 'check_in' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->checkIn();
        } elseif ($action === 'check_out' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->checkOut();
        } else {
            $controller->index();
        }
        break;

    case 'hr/payroll':
        $controller = $container->get(\App\Controllers\HR\PayrollController::class);
        if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->generate();
        } elseif ($action === 'payslip' && isset($_GET['id'])) {
            $controller->payslip((int)$_GET['id']);
        } else {
            $controller->index();
        }
        break;

    // HR Payroll Processes
    case 'hr/payroll/bonus-reprocess':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->bonusReprocess();
        break;

    case 'hr/payroll/cost-center-salary':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->costCenterSalary();
        break;

    case 'hr/payroll/earn-leave-monthly':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->earnLeaveMonthly();
        break;

    case 'hr/payroll/earn-leave-yearly':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->earnLeaveYearly();
        break;

    case 'hr/payroll/final-settlement':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->finalSettlement();
        break;

    case 'hr/payroll/fraction-salary-reprocess':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->fractionSalaryReprocess();
        break;

    case 'hr/payroll/holiday-allowance':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->holidayAllowance();
        break;

    case 'hr/payroll/monthly-bill-process':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->monthlyBillProcess();
        break;

    case 'hr/payroll/night-allowance':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->nightAllowance();
        break;

    case 'hr/payroll/salary-adjustment':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->salaryAdjustment();
        break;

    case 'hr/payroll/period-lock':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->salaryPeriodLock();
        break;

    case 'hr/payroll/tiffin-bill':
        $controller = $container->get(\App\Controllers\HR\HRPayrollProcessController::class);
        $controller->tiffinBill();
        break;

    // HR Employee Career & Lifecycle Processes
    case 'hr/employees/increment-entry':
        $controller = $container->get(\App\Controllers\HR\HRCareerProcessController::class);
        $controller->incrementEntry();
        break;

    case 'hr/employees/increment-bulk':
        $controller = $container->get(\App\Controllers\HR\HRCareerProcessController::class);
        $controller->incrementBulk();
        break;

    case 'hr/employees/promotion-entry':
        $controller = $container->get(\App\Controllers\HR\HRCareerProcessController::class);
        $controller->promotionEntry();
        break;

    case 'hr/employees/promotion-bulk':
        $controller = $container->get(\App\Controllers\HR\HRCareerProcessController::class);
        $controller->promotionBulk();
        break;

    case 'hr/employees/lay-off':
        $controller = $container->get(\App\Controllers\HR\HRCareerProcessController::class);
        $controller->layoffEmployee();
        break;

    // Extended HR Attendance & Roster Operations
    case 'hr/attendance/movement-bulk':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->bulkMovement();
        break;

    case 'hr/attendance/date-transfer':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->dateAttendanceTransfer();
        break;

    case 'hr/attendance/define-holiday':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->defineHoliday();
        break;

    case 'hr/attendance/define-weekend':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->defineWeekend();
        break;

    case 'hr/attendance/roster-reprocess':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->dutyRosterReprocess();
        break;

    case 'hr/attendance/holiday-remove':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->holidayRemove();
        break;

    case 'hr/attendance/manual-entry':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->manualAttendanceEntry();
        break;

    case 'hr/attendance/movement-register':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->movementRegister();
        break;

    case 'hr/attendance/ot-requisition':
        $controller = $container->get(\App\Controllers\HR\HRAttendanceOperationController::class);
        $controller->otRequisition();
        break;

    case 'hr/rmg-compliance':
    case 'hr/rmg-compliance/wage-compliance':
    case 'hr/rmg-compliance/attendance-bonus':
    case 'hr/rmg-compliance/festival-bonus':
    case 'hr/rmg-compliance/maternity':
        $controller = $container->get(\App\Controllers\HR\RmgComplianceController::class);
        if ($page === 'hr/rmg-compliance/wage-compliance') {
            $controller->wageCompliance();
        } elseif ($page === 'hr/rmg-compliance/attendance-bonus') {
            $controller->attendanceBonus();
        } elseif ($page === 'hr/rmg-compliance/festival-bonus') {
            $controller->festivalBonus();
        } elseif ($page === 'hr/rmg-compliance/maternity') {
            if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->createMaternityCase();
            } elseif ($action === 'approve') {
                $controller->approveMaternityCase((int)$_GET['id']);
            } elseif ($action === 'payout') {
                $controller->payoutMaternity((int)$_GET['id']);
            } elseif ($action === 'delete') {
                $controller->deleteMaternityCase((int)$_GET['id']);
            } else {
                $controller->maternity();
            }
        } else {
            $controller->index();
        }
        break;

    // ==========================================
    // Commercial Module
    // ==========================================
    case 'commercial':
    case 'commercial/dashboard':
        $controller = $container->get(\App\Controllers\Commercial\CommercialDashboardController::class);
        $controller->index();
        break;

    case 'commercial/export-documents':
    case 'commercial/export':
        $controller = $container->get(\App\Controllers\Commercial\ExportController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif (($action === 'show' || $action === 'view') && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } else {
            $controller->index();
        }
        break;

    case 'commercial/letters-of-credit':
    case 'commercial/lc':
        $controller = $container->get(\App\Controllers\Commercial\LetterOfCreditController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } else {
            $controller->index();
        }
        break;

    case 'commercial/orders':
    case 'commercial/sales_orders':
        $controller = $container->get(\App\Controllers\Commercial\SalesOrderController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    case 'commercial/invoices':
        $controller = $container->get(\App\Controllers\Commercial\InvoiceController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    case 'commercial/quotations':
        $controller = $container->get(\App\Controllers\Commercial\QuotationController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    case 'commercial/sales-contracts':
        $controller = $container->get(\App\Controllers\Commercial\SalesContractController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'allocation' && isset($_GET['id'])) {
            $controller->getOrdersByAllocation((string)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    // ==========================================
    // Production Module
    // ==========================================
    case 'production':
    case 'production/dashboard':
        $controller = $container->get(\App\Controllers\Production\ProductionController::class);
        $controller->index();
        break;

    case 'production/planning':
        $controller = $container->get(\App\Controllers\Production\PlanningController::class);
        if ($action === 'create') {
            $controller->create();
        } elseif ($action === 'line-loading') {
            $controller->lineLoading();
        } elseif ($action === 'capacity-report') {
            $controller->capacityReport();
        } else {
            $controller->index();
        }
        break;

    case 'production/capacity':
        $controller = $container->get(\App\Controllers\Production\CapacityController::class);
        $controller->index();
        break;

    case 'production/cutting':
        $controller = $container->get(\App\Controllers\Production\CuttingController::class);
        if ($action === 'create') {
            $controller->create();
        } elseif ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } else {
            $controller->index();
        }
        break;

    case 'production/sewing':
        $controller = $container->get(\App\Controllers\Production\SewingController::class);
        if ($action === 'create') {
            $controller->create();
        } elseif ($action === 'performance') {
            $controller->performance();
        } else {
            $controller->index();
        }
        break;

    case 'production/finishing':
        $controller = $container->get(\App\Controllers\Production\FinishingController::class);
        if (!empty($action) && $action !== 'index') {
            $controller->showTab($action);
        } else {
            $controller->index();
        }
        break;

    case 'production/quality':
        $controller = $container->get(\App\Controllers\Production\QualityController::class);
        if (!empty($action) && $action !== 'index') {
            $controller->showTab($action);
        } else {
            $controller->index();
        }
        break;

    case 'production/packing':
        $controller = $container->get(\App\Controllers\Production\PackingController::class);
        if ($action === 'cartons') {
            $controller->cartons();
        } elseif ($action === 'create') {
            $controller->createPlan();
        } else {
            $controller->plans();
        }
        break;

    case 'production/washing':
        $controller = $container->get(\App\Controllers\Production\WashingController::class);
        if ($action === 'create') {
            $controller->createBatch();
        } else {
            $controller->batches();
        }
        break;

    // ==========================================
    // Merchandising Module
    // ==========================================
    case 'merchandising':
    case 'merchandising/dashboard':
        $controller = $container->get(\App\Controllers\Merchandising\MerchandisingDashboardController::class);
        $controller->index();
        break;

    case 'merchandising/tna':
        $controller = $container->get(\App\Controllers\Merchandising\TnaController::class);
        if (is_numeric($action)) {
            $controller->show((int)$action);
        } else {
            $controller->index();
        }
        break;

    case 'merchandising/tech-packs':
        $controller = $container->get(\App\Controllers\Merchandising\StyleController::class);
        $controller->techPacksIndex();
        break;

    case 'merchandising/costing':
    case 'bulk-costing': // Sidebar alias
        $controller = $container->get(\App\Controllers\Merchandising\BulkCostingController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'delete' || ($action === 'destroy' && $_SERVER['REQUEST_METHOD'] === 'DELETE')) {
             // Handle both POST via 'delete' action or REST DELETE
             $id = isset($_POST['id']) ? (int)$_POST['id'] : (int)$_GET['id'];
             $controller->destroy($id);
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    case 'merchandising/purchase-requests':
    case 'purchase_requests':
        $controller = $container->get(\App\Controllers\Merchandising\PurchaseRequestController::class);
        
        // Handle REST /id logic (e.g. /123, /123/status)
        if (is_numeric($action)) {
            $id = (int)$action;
            $subResource = $_GET['id'] ?? null; // segment 3
            $method = $_SERVER['REQUEST_METHOD'];
            
            if ($subResource === 'status' && $method === 'POST') {
                $controller->updateStatus($id);
            } elseif ($method === 'PUT') {
                 $controller->update($id);
            } elseif ($method === 'DELETE') {
                 $controller->destroy($id);
            } else {
                 $controller->show($id);
            }
        } elseif ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->store();
        } elseif (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
             // Handle POST to root (index) as store
             $controller->store();
        } else {
             $controller->index();
        }
        break;



    case 'merchandising/styles':
    case 'styles': // Legacy alias
        $controller = $container->get(\App\Controllers\Merchandising\StyleController::class);
        if (is_numeric($action)) {
            $id = (int)$action;
            $method = $_SERVER['REQUEST_METHOD'];
            $inputMethod = $_POST['_method'] ?? (json_decode(file_get_contents('php://input'), true)['_method'] ?? '');
            
            if ($method === 'PUT' || ($method === 'POST' && $inputMethod === 'PUT')) {
                 $controller->update($id); 
            } elseif ($method === 'DELETE' || ($method === 'POST' && $inputMethod === 'DELETE')) {
                 $controller->delete($id);
            } else {
                 $controller->show($id);
            }
        } elseif (($action === 'create' || $action === 'index' || empty($action)) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            $controller->edit((int)$_GET['id']);
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;

    case 'merchandising/bom':
    case 'bom':
        $controller = $container->get(\App\Controllers\Merchandising\BomController::class);
        
        // Component API Routing
        if ($action === 'components') {
             $method = $_SERVER['REQUEST_METHOD'];
             if ($method === 'POST') {
                 $controller->storeComponent();
             } elseif ($method === 'PUT' && isset($_GET['id'])) {
                 $controller->updateComponent((int)$_GET['id']);
             } elseif ($method === 'DELETE' && isset($_GET['id'])) {
                 $controller->destroyComponent((int)$_GET['id']);
             }
             exit; // API response sent
        }

        // BOM Header Routing
        if (($action === 'create' || $action === 'index' || empty($action)) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'update' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->update((int)$_GET['id']);
        } elseif ($action === 'delete' && isset($_GET['id'])) {
             $controller->destroy((int)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } elseif ($action === 'show' || (is_numeric($action) && empty($_GET['id']))) {
            // Handle /bom/123 or /bom/show/123
            $id = is_numeric($action) ? (int)$action : (int)$_GET['id'];
            $controller->show($id);
        } else {
            $controller->index();
        }
        break;

    case 'merchandising/production_orders': // Clean URL used in sidebar? 
    case 'production-orders': // Likely sidebar alias if URL rewriting is off
    case 'merchandising/production-orders': 
    case 'production_orders': // Underscore legacy alias
        $controller = $container->get(\App\Controllers\Merchandising\ProductionOrderController::class);
        
        if (is_numeric($action)) {
            $id = (int)$action;
            $subAction = $_GET['id'] ?? ''; // 4th segment if present, e.g. /123/status
            
            if ($subAction === 'status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->updateStatus($id);
            } else {
                $method = $_SERVER['REQUEST_METHOD'];
                if ($method === 'PUT') {
                    $controller->update($id);
                } elseif ($method === 'DELETE') {
                    $controller->destroy($id);
                } else {
                    $controller->show($id);
                }
            }
        } elseif ($action === 'style-details' && isset($_GET['id'])) {
            $controller->getStyleDetails((int)$_GET['id']);
        } elseif ($action === 'get-cities' && isset($_GET['id'])) {
            $controller->getCities((int)$_GET['id']);
        } elseif (($action === 'create' || $action === 'store') && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'update' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update((int)$_GET['id']);
        } elseif ($action === 'delete' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->destroy((int)$_GET['id']);
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } else {
            $controller->index();
        }
        break;

    case 'merchandising/sampling':
    case 'sampling':
        $controller = $container->get(\App\Controllers\Merchandising\SamplingController::class);
        
        if (is_numeric($action)) {
            $id = (int)$action;
            $subResource = $_GET['id'] ?? null;
            $method = $_SERVER['REQUEST_METHOD'];

            if ($subResource === 'status' && $method === 'POST') {
                $controller->updateStatus($id);
            } elseif ($method === 'PUT') {
                 $controller->update($id);
            } elseif ($method === 'DELETE') {
                 $controller->destroy($id);
            } else {
                 $controller->show($id);
            }
        } elseif (($action === 'create' || $action === 'index' || empty($action)) && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->store();
        } else {
             $controller->index();
        }
        break;

    case 'merchandising/sample-planning':
        $controller = $container->get(\App\Controllers\Merchandising\SamplePlanningController::class);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } else {
            $controller->index();
        }
        break;

    case 'merchandising/sample-production':
    case 'sample-production':
        $controller = $container->get(\App\Controllers\Merchandising\SampleProductionTrackingController::class);
        if (is_numeric($action)) {
            $id = (int)$action;
            $method = $_SERVER['REQUEST_METHOD'];
            if ($method === 'PUT') {
                 $controller->update($id);
            } elseif ($method === 'DELETE') {
                 $controller->destroy($id);
            }
        } elseif ($action === 'requisitions') {
            // AJAX: GET requisitions by buyer_id / style_id
            $controller->requisitions();
        } elseif (($action === 'create' || $action === 'index' || empty($action)) && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->store();
        } else {
             $controller->index();
        }
        break;
         
    case 'transfer':
    case 'merchandising/transfer':
        $controller = $container->get(\App\Controllers\Merchandising\TransferController::class);
        
        if ($action === 'history') {
            $controller->history();
        } elseif ($action === 'get-po-skus') {
            $controller->getPOSKUs();
        } elseif ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif (is_numeric($action)) {
            $controller->show((int)$action);
        } elseif ($action === 'create') {
             $controller->index(); // Use index as create page
        } else {
             $controller->index();
        }
        break;
         


    // ==========================================
    // Production Module
    // ==========================================
    case 'production':
    case 'production/dashboard':
    case 'production/planning':
    case 'production/capacity':
    case 'production/line-planning':

    case 'production/mrp':
    case 'production/wip':
    case 'production/monitoring':
    case 'production/reports':
    case 'production_planning':
    case 'planning':
    case 'line_loading':
    case 'capacity_planning':
        $tab = 'dashboard';
        if ($page === 'production/planning' || $page === 'production_planning' || $page === 'planning') {
            $tab = 'planning';
        } elseif ($page === 'capacity_planning' || $page === 'production/capacity') {
            $tab = 'capacity';
        } elseif ($page === 'line_loading') {
            $tab = 'line-planning';
        } else {
            $parts = explode('/', $page);
            if (isset($parts[1])) {
                $tab = $parts[1];
            }
        }
        $controller = $container->get(\App\Controllers\Production\ProductionController::class);
        $controller->showTab($tab);
        break;

    case 'cutting':
    case 'production/cutting':
    case 'cutting_plans':
    case 'cutting/dashboard':
    case 'cutting/plans':
    case 'cutting/markers':
    case 'cutting/marker':
    case 'cutting/lay':
    case 'cutting/fabric':
    case 'cutting/execution':
    case 'cutting/bundles':
    case 'cutting/numbering':
    case 'cutting/ticketing':
    case 'cutting/qc':
    case 'cutting/reports':
    case 'bundle_generation':
        $tab = 'dashboard';
        if ($page === 'cutting_plans' || $page === 'cutting/plans' || $page === 'production/cutting') {
            $tab = 'plans';
        } elseif ($page === 'bundle_generation' || $page === 'cutting/bundles') {
            $tab = 'bundles';
        } else {
            $parts = explode('/', $page);
            if (isset($parts[1])) {
                $tab = $parts[1];
            }
        }
        $controller = $container->get(\App\Controllers\Cutting\CuttingController::class);
        $controller->showTab($tab);
        break;

    case 'sewing':
    case 'production/sewing':
    case 'sewing_lines':
    case 'sewing/dashboard':
    case 'sewing/lines':
    case 'sewing/input':
    case 'sewing/production':
    case 'sewing/output':
    case 'sewing/wip':
    case 'sewing/quality':
    case 'sewing/machine':
    case 'sewing/operator':
    case 'sewing/incentives':
    case 'sewing/productivity':
    case 'sewing/reports':
    case 'production/sewing/lines':
    case 'sewing_progress':
    case 'sewing/performance':
    case 'incentives':
        $tab = 'dashboard';
        if ($page === 'sewing_lines' || $page === 'sewing/lines' || $page === 'production/sewing/lines') {
            $tab = 'lines';
        } elseif ($page === 'sewing_progress' || $page === 'sewing/performance') {
            $tab = 'production';
        } elseif ($page === 'incentives') {
            $tab = 'incentives';
        } else {
            $parts = explode('/', $page);
            if (isset($parts[1])) {
                $tab = $parts[1];
            }
        }
        $controller = $container->get(\App\Controllers\Sewing\SewingController::class);
        $controller->showTab($tab);
        break;

    case 'washing':
    case 'production/washing':
    case 'washing/dashboard':
    case 'washing/plan':
    case 'washing/recipe':
    case 'washing/progress':
    case 'washing/qc':
    case 'washing/reports':
        $tab = 'dashboard';
        $parts = explode('/', $page);
        if (isset($parts[1])) {
            $tab = $parts[1];
        }
        $controller = $container->get(\App\Controllers\Washing\WashingController::class);
        $controller->showTab($tab);
        break;

    case 'packing':
    case 'production/packing':
    case 'packing/dashboard':
    case 'packing/list':
    case 'packing/cartons':
    case 'packing/scan':
    case 'packing/shipment':
    case 'packing/reports':
        $tab = 'dashboard';
        $parts = explode('/', $page);
        if (isset($parts[1])) {
            $tab = $parts[1];
        }
        $controller = $container->get(\App\Controllers\Packing\PackingController::class);
        $controller->showTab($tab);
        break;

    case 'finishing':
    case 'finishing/dashboard':
    case 'finishing/receive':
    case 'finishing/ironing':
    case 'finishing/qc':
    case 'finishing/packing':
    case 'finishing/reports':
        $tab = 'dashboard';
        $parts = explode('/', $page);
        if (isset($parts[1])) {
            $tab = $parts[1];
        }
        $controller = $container->get(\App\Controllers\Production\FinishingController::class);
        $controller->showTab($tab);
        break;

     // ==========================================
     // Inventory Module
     // ==========================================

     case 'inventory/dashboard':
          $controller = $container->get(\App\Controllers\Inventory\StoreDashboardController::class);
          $controller->index();
          break;

     case 'inventory/items':
          $controller = $container->get(\App\Controllers\Inventory\ItemMasterController::class);
          if ($action === 'store') {
              $controller->store();
          } elseif ($action === 'category') {
              $controller->storeCategory();
          } elseif ($action === 'opening-balance') {
              $controller->updateOpeningBalance();
          } else {
              $controller->index();
          }
          break;

     case 'inventory/warehouses':
          $controller = $container->get(\App\Controllers\Inventory\WarehouseController::class);
          if ($action === 'store') {
              $controller->storeWarehouse();
          } elseif ($action === 'room') {
              $controller->storeRoom();
          } elseif ($action === 'rack') {
              $controller->storeRack();
          } else {
              $controller->index();
          }
          break;

     case 'inventory/transactions':
          $controller = $container->get(\App\Controllers\Inventory\StockTransactionController::class);
          if ($action === 'grn') {
              $controller->storeGRN();
          } elseif ($action === 'requisition') {
              $controller->storeRequisition();
          } elseif ($action === 'issue') {
              $controller->storeIssue();
          } elseif ($action === 'transfer') {
              $controller->storeTransfer();
          } elseif ($action === 'adjustment') {
              $controller->storeAdjustment();
          } else {
              $controller->index();
          }
          break;

     case 'inventory/production':
          $controller = $container->get(\App\Controllers\Inventory\ProductionStoreController::class);
          if ($action === 'inspection') {
              $controller->storeInspection();
          } else {
              $controller->index();
          }
          break;

     case 'inventory/control':
          $controller = $container->get(\App\Controllers\Inventory\InventoryControlController::class);
          if ($action === 'approval') {
              $controller->processApproval();
          } elseif ($action === 'barcode') {
              $controller->generateBarcode();
          } else {
              $controller->index();
          }
          break;

     case 'inventory/stock':
     case 'stock_transfers':
     case 'stock': // Legacy alias
          $controller = $container->get(\App\Controllers\Inventory\StockController::class);
          $controller->index();
          break;

     case 'inventory/gate-passes':
          $controller = $container->get(\App\Controllers\Inventory\GatePassController::class);
          if (($action === 'create' || $action === 'store') && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->store();
          } elseif ($action === 'delete') {
              $id = isset($_POST['id']) ? (int)$_POST['id'] : (int)$_GET['id'];
              $controller->destroy($id);
          } elseif ($action === 'show' && isset($_GET['id'])) {
              $controller->show((int)$_GET['id']);
          } elseif ($action === 'create') {
              $controller->create();
          } else {
              $controller->index();
          }
          break;

     case 'store_requisitions':
     case 'store_requisition':
          $controller = $container->get(\App\Controllers\Inventory\StoreRequisitionController::class);
          handleCrud($controller, $action);
          break;


    // ==========================================
    // Transport Module
    // ==========================================
    case 'transport':
        $controller = $container->get(\App\Controllers\Transport\TransportController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->storeVehicle(); // Explicitly call storeVehicle
        } elseif ($action === 'create') {
             $controller->createVehicle();
        } else {
            $controller->vehicles();
        }
        break;
    
    case 'transport/maintenance':
    case 'vehicle_management/maintenance': 
    case 'maintenance': // Legacy alias
        $controller = $container->get(\App\Controllers\Transport\MaintenanceController::class);
        $controller->index();
        break;

    case 'parts_inventory':
        $controller = $container->get(\App\Controllers\Transport\PartsInventoryController::class);
        $controller->index();
        break;

    case 'vehicle_routes':
         $controller = $container->get(\App\Controllers\Transport\TransportController::class);
         if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->storeRoute();
         } elseif ($action === 'edit' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->updateRoute();
         } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->deleteRoute();
         } else {
             $controller->routes();
         }
         break;

    case 'employee_seat_allotment':
         $controller = $container->get(\App\Controllers\Transport\TransportController::class);
         if ($action === 'create') {
             $controller->createSeatAllotment();
         } elseif ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->storeSeatAllotment();
         } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->deleteSeatAllotment();
         } elseif ($action === 'search_employees') {
             $controller->searchEmployees();
         } else {
             $controller->seatAllotment();
         }
         break;

    // ==========================================
    // HR Module Routes (Sidebar Aliases)
    // ==========================================
    case 'employees': // Sidebar uses ?page=employees
        $controller = $container->get(\App\Controllers\HR\EmployeeController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'edit' && isset($_GET['id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update((int)$_GET['id']);
            } else {
                $controller->edit((int)$_GET['id']);
            }
        } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->destroy((int)$_POST['id']);
        } elseif ($action === 'show' && isset($_GET['id'])) {
            $controller->show((int)$_GET['id']);
        } elseif ($action === 'create') {
            $controller->create();
        } elseif ($action === 'export') {
            $controller->export();
        } else {
            $controller->index();
        }
        break;

    case 'attendance':
        $controller = $container->get(\App\Controllers\HR\AttendanceController::class);
        if ($action === 'check_in' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->checkIn();
        } elseif ($action === 'check_out' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->checkOut();
        } else {
            $controller->index();
        }
        break;

    case 'payroll':
        $controller = $container->get(\App\Controllers\HR\PayrollController::class);
        if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->generate();
        } elseif ($action === 'payslip' && isset($_GET['id'])) {
            $controller->payslip((int)$_GET['id']);
        } else {
            $controller->index();
        }
        break;

    case 'leave_management':
        $controller = $container->get(LeaveController::class);
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } elseif ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->updateStatus();
        } elseif ($action === 'create') {
            $controller->create();
        } else {
            $controller->index();
        }
        break;
        
    // ==========================================
    // Finance Module Routes (Sidebar Aliases)
    // ==========================================
    // Finance Module Routes
    case 'finance/dashboard':
          $controller = $container->get(\App\Controllers\Finance\FinanceDashboardController::class);
          $controller->index();
          break;

    case 'finance/accounts':
    case 'chart_of_accounts':
          $controller = $container->get(\App\Controllers\Finance\ChartOfAccountsController::class);
          if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeAccount();
          } elseif ($action === 'opening-balance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeOpeningBalance();
          } elseif ($action === 'fiscal-year' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeFiscalYear();
          } else {
              $controller->index();
          }
          break;

    case 'finance/vouchers':
          $controller = $container->get(\App\Controllers\Finance\FinanceVoucherController::class);
          if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->store();
          } elseif ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->approve();
          } else {
              $controller->index();
          }
          break;

    case 'finance/cash-bank':
          $controller = $container->get(\App\Controllers\Finance\CashBankController::class);
          if ($action === 'transfer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeTransfer();
          } elseif ($action === 'cheque' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeCheque();
          } elseif ($action === 'reconciliation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeReconciliation();
          } else {
              $controller->index();
          }
          break;

    case 'finance/subledgers':
          $controller = $container->get(\App\Controllers\Finance\FinanceSubledgerController::class);
          if ($action === 'receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeReceipt();
          } elseif ($action === 'payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storePayment();
          } elseif ($action === 'cost-center' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeCostCenter();
          } else {
              $controller->index();
          }
          break;

    case 'finance/reports':
    case 'finance/ledger':
    case 'general_ledger':
    case 'finance/general-ledger':
          $controller = $container->get(\App\Controllers\Finance\FinancialReportsController::class);
          if ($action === 'depreciation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->postDepreciation();
          } elseif ($action === 'budget' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeBudget();
          } elseif ($action === 'vat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->storeVAT();
          } else {
              $controller->index();
          }
          break;

    // ==========================================
    // Procurement (Sidebar Aliases)
    // ==========================================
    case 'purchase_orders':
         $controller = $container->get(\App\Controllers\Procurement\PurchaseOrderController::class);
         if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->store();
         } elseif ($action === 'edit' && isset($_GET['id'])) {
             if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                 $controller->update((int)$_GET['id']);
             } else {
                 $controller->edit((int)$_GET['id']);
             }
         } elseif ($action === 'show' && isset($_GET['id'])) {
             $controller->show((int)$_GET['id']);
         } elseif ($action === 'create') {
             $controller->create();
         } elseif (($action === 'delete' && isset($_POST['id'])) || (is_numeric($action) && $_SERVER['REQUEST_METHOD'] === 'DELETE')) {
             $id = isset($_POST['id']) ? (int)$_POST['id'] : (int)$action;
             $controller->destroy($id);
         } else {
             $controller->index();
         }
         break;


    case 'master_library/uoms':
    case 'uoms':
         $controller = $container->get(\App\Controllers\MasterLibrary\UomController::class);
         handleCrud($controller, $action);
         break;

     case 'master_library/employee_departments':
     case 'employee_departments':
          $controller = $container->get(\App\Controllers\MasterLibrary\EmployeeDepartmentController::class);
          handleCrud($controller, $action);
          break;

     case 'master_library/employee_sections':
     case 'employee_sections':
          $controller = $container->get(\App\Controllers\MasterLibrary\EmployeeSectionController::class);
          handleCrud($controller, $action);
          break;

      case 'master_library/employee_designations':
      case 'employee_designations':
           $controller = $container->get(\App\Controllers\MasterLibrary\EmployeeDesignationController::class);
           handleCrud($controller, $action);
           break;

          case 'master_library/vendors':
     case 'procurement/vendors':
     case 'vendors':
          $controller = $container->get(\App\Controllers\Procurement\VendorController::class);
          if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
              $controller->import();
          } elseif ($action === 'download_template') {
              $controller->downloadTemplate();
          } else {
              handleCrud($controller, $action);
          }
          break;

    case 'carton_booking':
    case 'procurement/carton-booking':
         $controller = $container->get(\App\Controllers\Procurement\CartonBookingController::class);
         
         if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
             $controller->store();
         } elseif (is_numeric($action) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
             $controller->destroy((int)$action);
         } else {
             $controller->index();
         }
         break;
         
    // ==========================================
    // Quality
    // ==========================================
    case 'quality':
    case 'quality/dashboard':
    case 'quality/inline':
    case 'quality/audit':
    case 'quality/library':
    case 'quality/capa':
    case 'quality/reports':
    case 'qc_checks':
    case 'defects':
    case 'sewing/qc':
        $tab = 'dashboard';
        if ($page === 'qc_checks' || $page === 'defects' || $page === 'sewing/qc') {
            $tab = 'inline';
        } else {
            $parts = explode('/', $page);
            if (isset($parts[1])) {
                $tab = $parts[1];
            }
        }
        $controller = $container->get(\App\Controllers\Production\QualityController::class);
        $controller->showTab($tab);
        break;



    // Special pages - Convert to MVC
    case 'backups':
        $controller = $container->get(\App\Controllers\Admin\SystemController::class);
        $controller->backups();
        break;

    case 'theme_demo':
        $controller = $container->get(\App\Controllers\Admin\SystemController::class);
        $controller->themeDemo();
        break;

    default:
        // 404 - Page not found
        http_response_code(404);
        echo '<h1>Page Not Found</h1>';
        echo '<p>The requested page "' . htmlspecialchars($page) . '" was not found.</p>';
        break;
}
