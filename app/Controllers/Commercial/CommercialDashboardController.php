<?php

declare(strict_types=1);

namespace App\Controllers\Commercial;

use App\Controllers\BaseController;
use App\Services\CommercialDashboardService;
use Core\Container;

class CommercialDashboardController extends BaseController
{
    private CommercialDashboardService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(CommercialDashboardService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $metrics = $this->service->getDashboardMetrics();

        $this->view('commercial/dashboard/index', [
            'metrics' => $metrics,
        ]);
    }
}
