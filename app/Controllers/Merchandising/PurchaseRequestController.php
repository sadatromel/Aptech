<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\PurchaseRequestService;
use Core\Container;

class PurchaseRequestController extends BaseController
{
    private PurchaseRequestService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(PurchaseRequestService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        
        // Dynamic Permission Check
        $module = 'Merchandising';
        $page = 'purchase_requests';
        
        // Pass permissions to view
        $permissions = [
            'create' => canCreate($module, $page),
            'edit' => canEdit($module, $page),
            'delete' => canDelete($module, $page),
            'approve' => canApprove($module, $page),
        ];

        $filters = [
            'search' => $this->request->get('search', ''),
            'status' => $this->request->get('status', ''),
            'style_id' => $this->request->get('style_id', ''),
        ];

        $this->view('merchandising/purchase-requests/index', [
            'purchase_requests' => $this->service->listPurchaseRequests($filters),
            'stats' => $this->service->getStats(),
            'filters' => $filters,
            'dropdowns' => $this->service->getDropdownData(),
            'page' => 'purchase_requests',
            'user' => $this->auth->user(),
            'permissions' => $permissions,
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        if (!canCreate('Merchandising', 'purchase_requests')) {
            $this->json(['success' => false, 'message' => 'Permission denied: Create Purchase Request'], 403);
            return;
        }

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'item_name' => 'required',
            'quantity' => 'required',
            'required_date' => 'required|date',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $prId = $this->service->createPurchaseRequest($data, (int) $this->auth->user()['id']);
            $this->json(['success' => true, 'pr_id' => $prId, 'message' => 'Purchase request created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Failed to create purchase request.'], 500);
        }
    }

    public function show(int $prId): void
    {
        $this->requireAuth();
        // View permission is handled by router/index, but good to be safe API-wise
        if (!canView('Merchandising', 'purchase_requests')) {
             $this->json(['error' => 'Permission denied'], 403);
             return;
        }
        
        $pr = $this->service->getPurchaseRequest($prId);
        if (!$pr) {
            $this->json(['error' => 'Purchase request not found'], 404);
            return;
        }

        $this->json(['data' => $pr]);
    }

    public function update(int $prId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        if (!canEdit('Merchandising', 'purchase_requests')) {
            $this->json(['success' => false, 'message' => 'Permission denied: Edit Purchase Request'], 403);
            return;
        }

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'item_name' => 'required',
            'quantity' => 'required',
            'required_date' => 'required|date',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if ($this->service->updatePurchaseRequest($prId, $data)) {
            $this->json(['success' => true, 'message' => 'Purchase request updated successfully.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to update purchase request.'], 500);
    }

    public function updateStatus(int $prId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        if (!canApprove('Merchandising', 'purchase_requests')) {
            $this->json(['success' => false, 'message' => 'Permission denied: Approve/Reject Purchase Request'], 403);
            return;
        }

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'status' => 'required',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if ($this->service->updateStatus($prId, $data['status'])) {
            $this->json(['success' => true, 'message' => 'Status updated successfully.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to update status.'], 500);
    }

    public function destroy(int $prId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        if (!canDelete('Merchandising', 'purchase_requests')) {
            $this->json(['success' => false, 'message' => 'Permission denied: Delete Purchase Request'], 403);
            return;
        }

        if ($this->service->deletePurchaseRequest($prId)) {
            $this->json(['success' => true, 'message' => 'Purchase request deleted.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to delete purchase request.'], 500);
    }
}
