<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Services\CapacityService;
use Core\Container;

class CapacityController extends BaseController
{
    private CapacityService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(CapacityService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $date = $this->request->get('date', date('Y-m-d'));
        $view = $this->request->get('view', 'daily'); // daily, weekly, monthly

        $capacityData = $this->service->getCapacityData($date, $view);

        $this->view('production/capacity/index', [
            'capacityData' => $capacityData,
            'date' => $date,
            'view' => $view
        ]);
    }

    public function lineDetail(int $lineId): void
    {
        $this->requireAuth();

        $date = $this->request->get('date', date('Y-m-d'));
        $view = $this->request->get('view', 'daily');

        $capacityDetail = $this->service->getLineDetail($lineId, $date, $view);
        if (!$capacityDetail) {
            $this->redirect('/production/capacity');
            return;
        }

        $this->view('production/capacity/line-detail', [
            'capacityDetail' => $capacityDetail,
            'date' => $date,
            'view' => $view
        ]);
    }

    public function forecast(): void
    {
        $this->requireAuth();

        $months = $this->request->get('months', 3); // Next 3 months by default

        $forecastData = $this->service->getForecastData($months);

        $this->view('production/capacity/forecast', [
            'forecastData' => $forecastData,
            'months' => $months
        ]);
    }
}
