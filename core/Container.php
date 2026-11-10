<?php

declare(strict_types=1);

namespace Core;

use DI\Container as DIContainer;
use DI\ContainerBuilder;


class Container
{
    private static ?DIContainer $instance = null;

    public static function getInstance(): DIContainer
    {
        if (self::$instance === null) {
            $builder = new ContainerBuilder();
            $builder->addDefinitions([
                // Database
                \PDO::class => function() {
                    $config = require CONFIG_PATH . '/database.php';
                    $driver = $config['driver'] ?? 'mysql';
                    
                    if ($driver === 'sqlsrv') {
                        $port = $config['port'] ?? '1433';
                        $dsn = "sqlsrv:Server={$config['host']},{$port};Database={$config['database']}";
                    } else {
                        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
                    }
                    
                    $pdo = new \PDO($dsn, $config['username'], $config['password'], [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    ]);

                    // Auto-migrate schema and initial data if tables are missing
                    if ($driver === 'mysql') {
                        try {
                            $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
                            if (!$check) {
                                $initSql = dirname(__DIR__) . '/backups/init.sql';
                                if (file_exists($initSql)) {
                                    $sql = file_get_contents($initSql);
                                    if (str_starts_with($sql, "\xFF\xFE")) {
                                        $sql = mb_convert_encoding(substr($sql, 2), 'UTF-8', 'UTF-16LE');
                                    } elseif (str_starts_with($sql, "\xEF\xBB\xBF")) {
                                        $sql = substr($sql, 3);
                                    }
                                    $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, 0);
                                    $pdo->setAttribute(\PDO::MYSQL_ATTR_MULTI_STATEMENTS, true);
                                    $pdo->exec($sql);
                                }
                            }
                        } catch (\Throwable $e) {
                            error_log('Database auto-migration note: ' . $e->getMessage());
                        }
                    }

                    return $pdo;
                },

                // Core Services
                \Core\Database::class   => \DI\autowire(\Core\Database::class),
                \Core\Auth::class       => \DI\autowire(\Core\Auth::class),
                \Core\Request::class    => \DI\autowire(\Core\Request::class),
                \Core\CsrfGuard::class  => \DI\autowire(\Core\CsrfGuard::class),

                // HTTP Services
                \App\Http\Router::class => \DI\autowire(\App\Http\Router::class),
                \App\Http\Response::class => \DI\autowire(\App\Http\Response::class),

                // View Engine
                \Twig\Environment::class => function() {
                    $loader = new \Twig\Loader\FilesystemLoader([
                        BASEPATH . '/app/Views'
                    ]);
                    $twig = new \Twig\Environment($loader, [
                        'cache' => BASEPATH . '/cache/views',
                        'auto_reload' => true,
                    ]);
                    
                    // Add global variables
                    $twig->addGlobal('ASSETS_URL', ASSETS_URL);
                    $twig->addGlobal('BASE_URL', BASE_URL);
                    $twig->addGlobal('base_url', BASE_URL);
                    $twig->addGlobal('BASEPATH', BASEPATH);
                    
                    // Add session data
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $twig->addGlobal('session', $_SESSION);

                    // Add permission helpers
                    $twig->addFunction(new \Twig\TwigFunction('canView', function ($module, $page) {
                        return function_exists('canView') ? canView($module, $page) : true;
                    }));
                    $twig->addFunction(new \Twig\TwigFunction('canCreate', function ($module, $page) {
                        return function_exists('canCreate') ? canCreate($module, $page) : true;
                    }));
                    $twig->addFunction(new \Twig\TwigFunction('canEdit', function ($module, $page) {
                        return function_exists('canEdit') ? canEdit($module, $page) : true;
                    }));
                    $twig->addFunction(new \Twig\TwigFunction('canDelete', function ($module, $page) {
                        return function_exists('canDelete') ? canDelete($module, $page) : true;
                    }));
                    
                    $twig->addFunction(new \Twig\TwigFunction('hasRole', function ($role) {
                         $auth = \Core\Container::get(\Core\Auth::class);
                         return $auth->hasRole($role);
                    }));

                    // Add PHP functions
                    $twig->addFunction(new \Twig\TwigFunction('parse_url', 'parse_url'));

                    // CSRF helpers — usable in any Twig form:
                    //   {{ csrf_field() | raw }}  → hidden input
                    //   {{ csrf_token() }}         → raw token string (for AJAX meta tags)
                    $twig->addFunction(new \Twig\TwigFunction('csrf_token', function () {
                        return \Core\Container::get(\Core\CsrfGuard::class)->getToken();
                    }));
                    $twig->addFunction(new \Twig\TwigFunction('csrf_field', function () {
                        return \Core\Container::get(\Core\CsrfGuard::class)->field();
                    }));

                    return $twig;
                },

                // App Services
                \App\Services\PartsInventoryService::class => \DI\autowire(\App\Services\PartsInventoryService::class),
                \App\Services\BulkCostingService::class => \DI\autowire(\App\Services\BulkCostingService::class),
                \App\Services\BomService::class => \DI\autowire(\App\Services\BomService::class),
                \App\Services\UserService::class => \DI\autowire(\App\Services\UserService::class),
                \App\Services\SamplingService::class => \DI\autowire(\App\Services\SamplingService::class),
                \App\Services\SizeRangeService::class => \DI\autowire(\App\Services\SizeRangeService::class),
                \App\Services\StyleService::class => \DI\autowire(\App\Services\StyleService::class),
                \App\Services\PurchaseRequestService::class => \DI\autowire(\App\Services\PurchaseRequestService::class),
                \App\Services\ProductionOrderService::class => \DI\autowire(\App\Services\ProductionOrderService::class),
                \App\Services\TransferService::class => \DI\autowire(\App\Services\TransferService::class),
                \App\Services\SetupInstallationService::class => \DI\autowire(\App\Services\SetupInstallationService::class),
                \App\Services\TrimsCostingTemplateService::class => \DI\autowire(\App\Services\TrimsCostingTemplateService::class),
                
                // Core System Services
                \App\Services\AuthService::class => \DI\autowire(\App\Services\AuthService::class),
                \App\Services\DashboardService::class => \DI\autowire(\App\Services\DashboardService::class),
                \App\Services\AdminService::class => \DI\autowire(\App\Services\AdminService::class),
                \App\Services\SystemLibraryService::class => \DI\autowire(\App\Services\SystemLibraryService::class),
                \App\Services\VehicleService::class => \DI\autowire(\App\Services\VehicleService::class),
                
                // Production Services
                \App\Services\CapacityService::class => \DI\autowire(\App\Services\CapacityService::class),
                \App\Services\PlanningService::class => \DI\autowire(\App\Services\PlanningService::class),
                \App\Services\SewingService::class => \DI\autowire(\App\Services\SewingService::class),

                // App Models
                \App\Models\PartsInventory::class => \DI\autowire(\App\Models\PartsInventory::class),
            ]);

            if (getenv('APP_ENV') === 'production') {
                $builder->enableCompilation(BASEPATH . '/cache/container');
            }

            self::$instance = $builder->build();
        }

        return self::$instance;
    }

    public static function get(string $id): mixed
    {
        return self::getInstance()->get($id);
    }

    public static function make(string $className, array $params = []): object
    {
        return self::getInstance()->make($className, $params);
    }
}
