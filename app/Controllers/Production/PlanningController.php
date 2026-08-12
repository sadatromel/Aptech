<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Services\PlanningService;
use Core\Container;

class PlanningController extends BaseController
{
    private PlanningService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(PlanningService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'status' => $this->request->get('status', ''),
            'line_id' => $this->request->get('line_id', ''),
            'date_from' => $this->request->get('date_from', ''),
            'date_to' => $this->request->get('date_to', '')
        ];

        $plans = $this->service->getPlans($filters);
        $stats = $this->service->getStats();

        $this->view('production/planning/index', [
            'plans' => $plans,
            'stats' => $stats,
            'filters' => $filters
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        
        $dropdowns = $this->service->getDropdownData();

        $this->view('production/planning/create', [
            'orders' => $dropdowns['orders'],
            'lines' => $dropdowns['lines']
        ]);
    }

    public function store(): void
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
        if ($errors) {
            $this->json(['errors' => $errors], 422);
            return;
        }
        
        try {
            $data['created_by'] = $this->auth->user()['id'];
            $planId = $this->service->createPlan($data);
            
            $this->json(['success' => true, 'message' => 'Production Plan created successfully', 'plan_id' => $planId]);
            
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
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
        if ($errors) {
            $this->json(['errors' => $errors], 422);
            return;
        }
        
        try {
            $result = $this->service->updateProgress($planId, (int) $data['completed_quantity']);
            
            if ($result) {
                $this->json(['success' => true, 'message' => 'Progress updated successfully']);
            } else {
                $this->json(['success' => false, 'message' => 'Failed to update progress'], 500);
            }
            
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function capacityReport(): void
    {
        $this->requireAuth();
        
        $date = $this->request->get('date', date('Y-m-d'));
        $report = $this->service->getCapacityReport($date);
        
        $this->view('production/planning/capacity-report', [
            'report' => $report,
            'date' => $date
        ]);
    }
    
    public function ganttData(): void
    {
         $this->requireAuth();
         
         $plans = $this->service->getPlans();
         
         $tasks = [];
         foreach ($plans as $plan) {
             // Fake duration/end date for visual since DB only has start
             $startDate = $plan['planned_date'];
             $endDate = date('Y-m-d', strtotime($startDate . ' + 5 days'));
             
             $tasks[] = [
                 'id' => $plan['id'],
                 'text' => $plan['style_name'] . ' (' . $plan['plan_number'] . ')',
                 'start_date' => $startDate,
                 'duration' => 5,
                 'parent' => $plan['line_id'],
                 'type' => 'task'
             ];
         }
         
         $this->json(['data' => $tasks]);
    }
    public function lineLoading(): void
    {
        $this->requireAuth();
        
        $date = $this->request->get('date', date('Y-m-d'));
        
        // Reuse capacity report logic for line loading view
        $linesStatus = $this->service->getCapacityReport($date);
        $dropdowns = $this->service->getDropdownData();
        
        $this->view('production/planning/line_loading', [
            'lines_status' => $linesStatus,
            'date' => $date,
            'lines' => $dropdowns['lines']
        ]);
    }
}
