<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\Inventory\StockService;
use Core\Container;

class StockController extends BaseController
{
    private StockService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(StockService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('inventory_manager');

        $filters = [
            'warehouse_id' => $this->request->get('warehouse_id', ''),
            'category' => $this->request->get('category', ''),
            'low_stock' => $this->request->get('low_stock', ''),
            'search' => $this->request->get('search', ''),
        ];

        $this->view('inventory/stock/index', [
            'stock_items' => $this->service->listStock($filters),
            'stats' => $this->service->getStats(),
            'filters' => $filters,
            'dropdowns' => $this->service->getDropdownData(),
        ]);
    }

    public function show(int $stockId): void
    {
        $this->requireAuth();
        $stock = $this->service->getStock($stockId);
        if (!$stock) {
            $this->json(['error' => 'Stock record not found'], 404);
            return;
        }

        $this->json(['data' => $stock]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('inventory_manager');

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'warehouse_id' => 'required|integer',
            'item_id' => 'required|integer',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $stockId = $this->service->createStock($data);
            $this->json(['success' => true, 'stock_id' => $stockId, 'message' => 'Stock record created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function adjust(int $stockId): void
    {
        $this->requireAuth();
        $this->requireRole('inventory_manager');

        $data = $this->request->json();
        if (empty($data)) {
            $data = $this->request->all();
        }

        $errors = $this->validate([
            'quantity_on_hand' => 'required|numeric',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->service->adjustStock($stockId, $data, (int) $this->auth->user()['id']);
            $this->json(['success' => true, 'message' => 'Stock adjusted successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $stockId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('inventory_manager');

        try {
            $this->service->deleteStock($stockId);
            $this->json(['success' => true, 'message' => 'Stock record deleted successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
