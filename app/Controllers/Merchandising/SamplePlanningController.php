<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\SamplePlanningService;
use App\Services\SampleProductionTrackingService;
use Core\Container;

class SamplePlanningController extends BaseController
{
    private SamplePlanningService $planningService;
    private SampleProductionTrackingService $trackingService;

    public function __construct()
    {
        parent::__construct();
        $this->planningService = Container::get(SamplePlanningService::class);
        $this->trackingService = Container::get(SampleProductionTrackingService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('merchandising_manager');
        
        $styleId = $this->request->get('style_id');
        $filters = ['style_id' => $styleId];

        // Active tracking: approved or in progress
        $activeRequisitions = $this->planningService->getRequisitionsForPlanning(array_merge($filters, [
            'statuses' => ['approved', 'in_progress']
        ]));

        // Pending approval: requested
        $pendingRequisitions = $this->planningService->getRequisitionsForPlanning(array_merge($filters, [
            'statuses' => ['requested']
        ]));

        $styles = $this->planningService->getStyles();
        $employees = $this->planningService->getEmployees();
        
        // Get statuses for the approval dropdown
        $samplingService = Container::get(\App\Services\SamplingService::class);
        $samplingData = $samplingService->getDropdownData();

        $this->view('merchandising/sample_planning/index', [
            'requisitions' => $activeRequisitions,
            'pending_requisitions' => $pendingRequisitions,
            'styles' => $styles,
            'employees' => $employees,
            'statuses' => $samplingData['statuses'],
            'filters' => $filters,
        ]);
    }

    /**
     * Assign target and update status
     */
    public function store(): void
    {
        $this->verifyCsrf();
        if ($this->request->isPost()) {
            try {
                $data = $this->request->all();
                if (empty($data)) {
                    $json = $this->request->json();
                    if ($json) $data = $json;
                }
                
                // Basic validation
                if (empty($data['sample_id']) || empty($data['tracking_date'])) {
                    $this->json(['success' => false, 'message' => 'Missing required fields.'], 422);
                    return;
                }

                // If status is provided, update the sampling record
                if (!empty($data['status'])) {
                    $samplingService = Container::get(\App\Services\SamplingService::class);
                    $samplingService->updateStatus((int)$data['sample_id'], $data['status'], $data['notes'] ?? 'Status updated during assignment.');
                }

                // If assignment data is provided (at least one achievement or target > 0 or person assigned)
                $hasAssignment = !empty($data['pattern_master_id']) || !empty($data['sample_man_id']) || 
                                (!empty($data['pattern_master_target']) && $data['pattern_master_target'] > 0) ||
                                (!empty($data['sample_man_target']) && $data['sample_man_target'] > 0);

                if ($hasAssignment) {
                    $this->trackingService->storeRecord($data);
                }

                $this->json(['success' => true, 'message' => 'Requisition updated and targets assigned.']);
            } catch (\Exception $e) {
                $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }
    }
}
