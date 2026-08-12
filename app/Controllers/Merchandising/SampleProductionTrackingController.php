<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\SampleProductionTrackingService;
use Core\Container;

class SampleProductionTrackingController extends BaseController
{
    private SampleProductionTrackingService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(SampleProductionTrackingService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $filters = [
            'buyer_id'  => $this->request->get('buyer_id', ''),
            'style_id'  => $this->request->get('style_id', ''),
            'sample_id' => $this->request->get('sample_id', ''),
            'from_date' => $this->request->get('from_date', ''),
            'to_date'   => $this->request->get('to_date', ''),
        ];

        $dropdowns = $this->service->getDropdownData();

        $this->view('merchandising/sample_production/index', [
            'records'      => $this->service->getRecords($filters),
            'stats'        => $this->service->getStats(),
            'filters'      => $filters,
            'buyers'       => $dropdowns['buyers'],
            'styles'       => $dropdowns['styles'],
        ]);
    }

    /** AJAX: return requisitions filtered by buyer_id and/or style_id */
    public function requisitions(): void
    {
        $this->requireAuth();

        $buyerId = (int) $this->request->get('buyer_id', '0');
        $styleId = (int) $this->request->get('style_id', '0');

        $items = $this->service->getRequisitions($buyerId, $styleId);
        $this->json($items);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $rules = [
            'tracking_date' => 'required|date',
            'buyer_id'      => 'required|integer',
            'style_id'      => 'required|integer',
            'sample_id'     => 'nullable|integer',
            'target_qty'    => 'required|integer',
            'actual_qty'    => 'required|integer',
        ];

        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $id = $this->service->storeRecord($data);
            $this->json(['success' => true, 'id' => $id, 'message' => 'Record created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Failed to create record.'], 500);
        }
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $rules = [
            'tracking_date' => 'required|date',
            'buyer_id'      => 'required|integer',
            'style_id'      => 'required|integer',
            'sample_id'     => 'nullable|integer',
            'target_qty'    => 'required|integer',
            'actual_qty'    => 'required|integer',
        ];

        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if ($this->service->updateRecord($id, $data)) {
            $this->json(['success' => true, 'message' => 'Record updated successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update record.'], 500);
        }
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        if ($this->service->deleteRecord($id)) {
            $this->json(['success' => true, 'message' => 'Record deleted.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to delete record.'], 500);
        }
    }
}
