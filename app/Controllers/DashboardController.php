<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DashboardService;
use Core\Container;

class DashboardController extends BaseController
{
    private DashboardService $dashboardService;

    // Response provided by BaseController (Core\Controller)

    public function __construct()
    {
        parent::__construct();
        $this->dashboardService = Container::get(DashboardService::class);
    }

    /**
     * Show main dashboard (legacy compatibility)
     */
    public function index(): void
    {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $dashboardData = $this->dashboardService->getDashboardStats($userId);

        // Restore variables needed for view
        $user = $dashboardData['user'];
        
        // Capture filter parameter
        $factoryId = isset($_GET['factory_id']) ? (int) $_GET['factory_id'] : null;
        
        // Fetch chart data for Buyer Wise Monthly Orders
        $buyerMonthlyOrders = $this->dashboardService->getBuyerMonthlyOrders($factoryId);
        
        // Fetch active factories for the filter dropdown
        $factories = $this->dashboardService->getFactories();

        // Render Twig Template
        $this->response->view('dashboard/index', [
            'user' => $user,
            'buyerMonthlyOrders' => json_encode($buyerMonthlyOrders),
            'factories' => $factories,
            'selectedFactory' => $factoryId,
            'notifications' => $dashboardData['notifications']
        ])->send();
    }

    /**
     * Get dashboard data via AJAX
     */
    public function getStats(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $dashboardData = $this->dashboardService->getDashboardStats($userId);

        header('Content-Type: application/json');
        echo json_encode($dashboardData);
    }

    /**
     * Get module-specific widget data
     */
    public function getWidgetData(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $module = $_GET['module'] ?? '';
        $userId = (int) $_SESSION['user_id'];

        $dashboardData = $this->dashboardService->getDashboardStats($userId);
        
        if (isset($dashboardData['modules'][$module])) {
            header('Content-Type: application/json');
            echo json_encode($dashboardData['modules'][$module]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Module not found or no access']);
        }
    }
}
