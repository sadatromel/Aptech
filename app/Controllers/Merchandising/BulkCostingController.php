<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\BulkCostingService;
use Core\Container;

class BulkCostingController extends BaseController
{
    private BulkCostingService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(BulkCostingService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $filters = [
            'style_id' => $this->request->get('style_id', ''),
            'status' => $this->request->get('status', ''),
            'season' => $this->request->get('season', ''),
            'search' => $this->request->get('search', ''),
        ];

        $this->view('merchandising/bulk-costing/index', [
            'costings' => $this->service->listCostings($filters),
            'stats' => $this->service->getStats(),
            'filters' => $filters,
            'dropdowns' => $this->service->getDropdownData(),
        ]);
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

        $errors = $this->validate([
            'style_id' => 'nullable|integer',
            'factory_id' => 'required|integer',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $costingId = $this->service->createCosting($data, (int) $this->auth->user()['id']);
            $this->json(['success' => true, 'costing_id' => $costingId, 'message' => 'Bulk costing created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Failed to create bulk costing: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $costingId): void
    {
        $this->requireAuth();
        $costing = $this->service->getCosting($costingId);
        
        if (!$costing) {
            if ($this->request->isJson() || $this->request->isAjax()) {
                 $this->json(['error' => 'Costing not found'], 404);
            } else {
                 $_SESSION['error_message'] = "Bulk Costing not found.";
                 $this->redirect('merchandising/bulk-costing');
            }
            return;
        }

        if ($this->request->isJson() || $this->request->isAjax()) {
            $this->json(['data' => $costing]);
            return;
        }

        $this->view('merchandising/bulk-costing/show', [
            'costing' => $costing
        ]);
    }

    public function report(int $costingId): void
    {
        $this->requireAuth();
        $costing = $this->service->getCosting($costingId);
        
        if (!$costing) {
             $_SESSION['error_message'] = "Bulk Costing not found.";
             $this->redirect('merchandising/bulk-costing');
             return;
        }

        $this->view('merchandising/bulk-costing/report', [
            'costing' => $costing
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('merchandising_manager');
        
        $this->view('merchandising/bulk-costing/create', [
            'dropdowns' => $this->service->getDropdownData()
        ]);
    }

    public function edit(int $costingId): void
    {
        $this->requireAuth();
        $this->requireRole('merchandising_manager');
        
        $costing = $this->service->getCosting($costingId);
        if (!$costing) {
             $this->redirect('merchandising/bulk-costing');
             return;
        }

        $this->view('merchandising/bulk-costing/edit', [
            'costing' => $costing,
            'dropdowns' => $this->service->getDropdownData()
        ]);
    }

    public function update(int $costingId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
             'style_id' => 'nullable|integer',
             'factory_id' => 'required|integer',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            if ($this->service->updateCosting($costingId, $data, (int) $this->auth->user()['id'])) {
                $this->json(['success' => true, 'message' => 'Bulk costing updated successfully.']);
                return;
            }
            $this->json(['success' => false, 'message' => 'Failed to update bulk costing.'], 500);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(int $costingId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');

        // Allow POST or DELETE
        if (!$this->request->isPost() && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
             $this->json(['error' => 'Invalid method'], 405);
             return;
        }

        if ($this->service->deleteCosting($costingId)) {
            $this->json(['success' => true, 'message' => 'Bulk costing deleted.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to delete bulk costing.'], 500);
    }
}
