<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Services\ProductionOrderService;
use Core\Container;

class OrderController extends BaseController
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

        $filters = [
            'status' => $this->request->get('status', ''),
            'customer_id' => $this->request->get('customer', ''),
            'date_from' => $this->request->get('date_from', date('Y-m-01')),
            'date_to' => $this->request->get('date_to', date('Y-m-d'))
        ];

        $orders = $this->service->getOrdersWithFilters($filters);
        $dropdowns = $this->service->getDropdownData();
        $totals = $this->service->getOrderTotals($filters);

        $this->view('production/orders/index', [
            'orders' => $orders,
            'customers' => $dropdowns['customers'],
            'styles' => $dropdowns['styles'],
            'totals' => $totals,
            'filters' => $filters
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('production_manager');

        $dropdowns = $this->service->getDropdownData();

        $this->view('production/orders/create', [
            'customers' => $dropdowns['customers'],
            'styles' => $dropdowns['styles']
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('production_manager');

        $data = $this->request->all();
        
        $rules = [
            'customer_id' => 'required',
            'style_id' => 'required',
            'order_date' => 'required',
            'delivery_date' => 'required',
            'quantity' => 'required|numeric|min:1',
            'unit_price' => 'required|numeric|min:0'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $data['created_by'] = $this->auth->user()['id'];
            $orderNumber = $this->service->createOrder($data);

            $this->json([
                'success' => true,
                'message' => 'Production order created successfully',
                'order_number' => $orderNumber
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to create production order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $order = $this->service->getOrderWithDetails($id);
        if (!$order) {
            $this->redirect('/production/orders');
            return;
        }

        $this->view('production/orders/show', [
            'order' => $order['order'],
            'breakdowns' => $order['breakdowns'],
            'plans' => $order['plans']
        ]);
    }

    public function updateStatus(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('production_manager');

        $data = $this->request->all();
        
        $rules = [
            'status' => 'required'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->service->updateOrderStatus($id, $data);

            $this->json([
                'success' => true,
                'message' => 'Order status updated successfully'
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addBreakdown(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('production_manager');

        $data = $this->request->all();
        
        $rules = [
            'size' => 'required',
            'quantity' => 'required|numeric|min:0'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->service->addOrderBreakdown($id, $data);

            $this->json([
                'success' => true,
                'message' => 'Breakdown added successfully'
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to add breakdown: ' . $e->getMessage()
            ], 500);
        }
    }
}
