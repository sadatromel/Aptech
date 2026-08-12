<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\MerchandisingDashboardService;
use Core\Container;

class MerchandisingDashboardController extends BaseController
{
    private MerchandisingDashboardService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(MerchandisingDashboardService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        
        $metrics = $this->service->getDashboardMetrics();

        $this->view('merchandising/dashboard/index', [
            'metrics' => $metrics,
        ]);
    }
}
