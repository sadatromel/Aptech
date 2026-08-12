<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Services\SewingService;
use Core\Container;

class SewingController extends BaseController
{
    private SewingService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(SewingService::class);
    }

    public function index(): void
    {
        $this->lines();
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->view('production/sewing/create_line');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();

        $rules = [
            'line_name' => 'required',
            'line_code' => 'required',
            'line_type' => 'required',
            'capacity_per_day' => 'required|numeric|min:1'
        ];

        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            // $this->view('production/sewing/create_line', ['errors' => $errors, 'old' => $data]);
            $this->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
            return;
        }

        try {
            $data['created_by'] = $this->auth->user()['id'];
            $this->service->createLine($data);
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/production/sewing/lines' : '/Aptech/production/sewing/lines');
        } catch (\Exception $e) {
            // $this->view('production/sewing/create_line', ['error' => $e->getMessage(), 'old' => $data]);
             $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function edit(int $id): void
    {
        $this->requireAuth();
        $line = $this->service->getLine($id);
        if (!$line) {
             header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/production/sewing/lines');
             exit;
        }
        $this->view('production/sewing/edit_line', ['line' => $line]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();
        
        // Similar validation...
         try {
            $this->service->updateLine($id, $data);
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/production/sewing/lines' : '/Aptech/production/sewing/lines');
        } catch (\Exception $e) {
             $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        try {
            $this->service->deleteLine($id);
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/production/sewing/lines' : '/Aptech/production/sewing/lines');
        } catch (\Exception $e) {
             $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function lines(): void
    {
        $this->requireAuth();
        
        $lines = $this->service->getSewingLines();
        
        $this->view('production/sewing/lines', [
            'lines' => $lines
        ]);
    }

    public function plans(): void
    {
        $this->requireAuth();
        
        $filters = [
            'line_id' => $this->request->get('line_id', ''),
            'status' => $this->request->get('status', ''),
            'date_from' => $this->request->get('date_from', ''),
            'date_to' => $this->request->get('date_to', '')
        ];

        $plans = $this->service->getSewingPlans($filters);
        $stats = $this->service->getStats();

        $this->view('production/sewing/plans', [
            'plans' => $plans,
            'stats' => $stats,
            'filters' => $filters
        ]);
    }

    public function createPlan(): void
    {
        $this->requireAuth();
        
        $dropdowns = $this->service->getDropdownData();
        
        $this->view('production/sewing/create_plan', [
            'orders' => $dropdowns['orders'],
            'lines' => $dropdowns['lines']
        ]);
    }

    public function storePlan(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();
        
        $rules = [
            'order_id' => 'required',
            'line_id' => 'required',
            'planned_date' => 'required',
            'quantity' => 'required|numeric|min:1'
        ];

        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }
        
        try {
            $data['created_by'] = $this->auth->user()['id'];
            $planId = $this->service->createSewingPlan($data);
            
            $this->json([
                'success' => true,
                'message' => 'Sewing plan created successfully',
                'plan_id' => $planId
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to create sewing plan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateProgress(int $planId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        $data = $this->request->all();
        
        $rules = [
            'completed_quantity' => 'required|numeric|min:0'
        ];

        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }
        
        try {
            $result = $this->service->updateSewingProgress($planId, (int) $data['completed_quantity'], $data);
            
            if ($result) {
                $this->json([
                    'success' => true,
                    'message' => 'Progress updated successfully'
                ]);
            } else {
                $this->json([
                    'success' => false,
                    'message' => 'Failed to update progress'
                ], 500);
            }
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function performance(int $lineId = null): void
    {
        $this->requireAuth();
        
        if ($lineId) {
            $performance = $this->service->getLineEfficiency($lineId);
        } else {
            $lines = $this->service->getSewingLines();
            $performance = [];
            foreach ($lines as $line) {
                $performance[$line['id']] = $this->service->getLineEfficiency($line['id']);
            }
        }
        
        $this->view('production/sewing/performance', [
            'performance' => $performance,
            'lineId' => $lineId,
            'lines' => $this->service->getSewingLines()
        ]);
    }

    public function activePlans(): void
    {
        $this->requireAuth();
        
        $lineId = $this->request->get('line_id', null);
        $plans = $this->service->getActiveSewingPlans($lineId);
        
        $this->json(['data' => $plans]);
    }
}
