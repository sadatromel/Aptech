<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\ProductionOrderService;
use Core\Container;

class ProductionOrderController extends BaseController
{
    private ProductionOrderService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(ProductionOrderService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        if (!canView('merchandising', 'production_orders')) {
            $this->renderErrorView(403, ['permission' => 'production_orders']);
            return;
        }

        $filters = [
            'search' => $this->request->get('search', ''),
            'status' => $this->request->get('status', ''),
            'style_id' => $this->request->get('style_id', ''),
            'priority' => $this->request->get('priority', ''),
        ];

        $permissions = [
            'view' => canView('merchandising', 'production_orders'),
            'create' => canCreate('merchandising', 'production_orders'),
            'edit' => canEdit('merchandising', 'production_orders'),
            'delete' => canDelete('merchandising', 'production_orders'),
            'approve' => canApprove('merchandising', 'production_orders'),
        ];

        $this->view('merchandising/production-orders/index', [
            'production_orders' => $this->service->getOrdersWithFilters($filters),
            'stats' => $this->service->getStats(),
            'filters' => $filters,
            'dropdowns' => $this->service->getDropdownData(),
            'permissions' => $permissions,
        ]);
    }

    public function getStyleDetails($styleId): void
    {
        $styleId = (int) $styleId;
        $this->requireAuth();
        if (!canView('merchandising', 'production_orders')) {
            $this->json(['error' => 'Permission denied'], 403);
            return;
        }
        try {
            $styleData = $this->service->getStyleSizes($styleId);
            $this->json($styleData);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getCities($countryId): void
    {
        $countryId = (int) $countryId;
        $this->requireAuth();
        if (!canView('merchandising', 'production_orders')) {
            $this->json(['error' => 'Permission denied'], 403);
            return;
        }
        try {
            $cities = $this->service->getCitiesByCountry($countryId);
            $this->json($cities);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        if (!canCreate('merchandising', 'production_orders')) {
            $this->json(['success' => false, 'message' => 'Permission denied: Create Production Order'], 403);
            return;
        }

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'allocation_number' => 'nullable|string|max:50',
            'factory_id' => 'required|integer',
            'style_id' => 'required|integer',
            'order_date' => 'required|date',
            'required_date' => 'required|date',
            'ex_factory_date' => 'nullable|date',
            'country_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'currency_id' => 'nullable|integer',
            'conversion_rate' => 'nullable|numeric',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $data['created_by'] = (int) ($this->auth->user()['user_id'] ?? 0);
            $poId = $this->service->createOrder($data);
            $this->json(['success' => true, 'po_id' => $poId, 'message' => 'Production order created successfully.']);
        } catch (\Throwable $e) {
            error_log("ProductionOrder Create Error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($poId): void
    {
        $poId = (int) $poId;
        $this->requireAuth();
        if (!canView('merchandising', 'production_orders')) {
            $this->json(['error' => 'Production order not found'], 404);
            return;
        }
        $po = $this->service->getOrderWithDetails($poId);
        if (!$po) {
            $this->json(['error' => 'Production order not found'], 404);
            return;
        }

        $this->json(['data' => $po]);
    }

    public function update($poId): void
    {
        $this->verifyCsrf();
        $poId = (int) $poId;
        $this->requireAuth();
        if (!canEdit('merchandising', 'production_orders')) {
            $this->json(['success' => false, 'message' => 'Permission denied: Edit Production Order'], 403);
            return;
        }

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'factory_id' => 'required|integer',
            'style_id' => 'required|integer',
            'order_date' => 'required|date',
            'required_date' => 'required|date',
            'ex_factory_date' => 'nullable|date',
            'country_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'currency_id' => 'nullable|integer',
            'conversion_rate' => 'nullable|numeric',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->service->updateOrder($poId, $data);
            $this->json(['success' => true, 'message' => 'Production order updated successfully.']);
        } catch (\Throwable $e) {
            error_log("ProductionOrder Update Error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateStatus($poId): void
    {
        $this->verifyCsrf();
        $poId = (int) $poId;
        $this->requireAuth();
        if (!canApprove('merchandising', 'production_orders') && !canEdit('merchandising', 'production_orders')) {
            $this->json(['success' => false, 'message' => 'Permission denied: Update Status'], 403);
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

        try {
            if ($this->service->updateOrderStatus($poId, $data)) {
                $this->json(['success' => true, 'message' => 'Status updated successfully.']);
                return;
            }
            $this->json(['success' => false, 'message' => 'Failed to update status.'], 500);
        } catch (\Throwable $e) {
            error_log("ProductionOrder Status Update Error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($poId): void
    {
        $this->verifyCsrf();
        $poId = (int) $poId;
        $this->requireAuth();
        if (!canDelete('merchandising', 'production_orders')) {
            $this->json(['success' => false, 'message' => 'Permission denied: Delete Production Order'], 403);
            return;
        }

        try {
            $this->service->deleteOrder($poId);
            $this->json(['success' => true, 'message' => 'Production order deleted.']);
        } catch (\Throwable $e) {
            error_log("ProductionOrder Delete Error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
