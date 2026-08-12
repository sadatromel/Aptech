<?php

declare(strict_types=1);

namespace App\Controllers\Commercial;

use App\Controllers\BaseController;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Quotation;
use Exception;

class SalesOrderController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $salesOrders = $this->db->select('sales_orders so', [
            '[>]customers c' => ['so.customer_id' => 'customer_id'],
            '[>]quotations q' => ['so.quotation_id' => 'quotation_id']
        ], [
            'so.*',
            'c.customer_name',
            'c.customer_code',
            'q.quotation_no'
        ], [
            'ORDER' => ['so.order_date' => 'DESC']
        ]);

        $this->view('commercial/sales_orders/index', ['salesOrders' => $salesOrders]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('commercial_manager');

        $customerModel = new Customer();
        $customers = $customerModel->all();

        $quotationModel = new Quotation();
        // Get quotations not fully converted? Logic depends on workflow.
        // For now get all active quotations
        $quotations = $this->db->select('quotations', '*', ['status[!]' => 'draft']);

        // Generate next SO number
        $prefix = 'SO' . date('Y');
        $count = $this->db->count('sales_orders', [
            'order_date[~]' => date('Y-m') . '%'
        ]) + 1;
        $next_so_no = $prefix . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);

        $this->view('commercial/sales_orders/create', [
            'customers' => $customers,
            'quotations' => $quotations,
            'next_so_no' => $next_so_no
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('commercial_manager');

        $data = $this->request->all();
        
        // Basic validation
        $rules = [
            'so_no' => 'required',
            'customer_id' => 'required',
            'order_date' => 'required'
        ];
        
        $errors = $this->validate($rules, $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            $soModel = new SalesOrder();
            
            // Calculate totals from lines if provided, similar to Quotation
            // ... (Logic for lines calculation) ...
            $totalAmount = 0;
            if (isset($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $line) {
                     $totalAmount += ($line['quantity'] * $line['unit_price']);
                }
            }

            $soData = [
                'so_no' => $data['so_no'],
                'quotation_id' => !empty($data['quotation_id']) ? $data['quotation_id'] : null,
                'customer_id' => $data['customer_id'],
                'order_date' => $data['order_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'reference_no' => $data['reference_no'] ?? '',
                'attention_to' => $data['attention_to'] ?? '',
                'shipping_address' => $data['shipping_address'] ?? '',
                'billing_address' => $data['billing_address'] ?? '',
                'terms_conditions' => $data['terms_conditions'] ?? '',
                'currency' => $data['currency'] ?? 'USD',
                'status' => $data['status'] ?? 'draft',
                'total_amount' => $totalAmount,
                'grand_total' => $totalAmount,
                'contract_type' => 'single', // Defaulting for now
                'created_by' => $this->auth->user()['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $soId = $soModel->create($soData);

            // Insert lines
            if (isset($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $index => $item) {
                     $this->db->insert('sales_order_lines', [
                        'so_id' => $soId,
                        'line_number' => $index + 1,
                        'style_no' => $item['style_no'],
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        // add other fields
                     ]);
                }
            }
            
            // Update Quotation status if linked
            if (!empty($data['quotation_id'])) {
                $this->db->update('quotations', ['status' => 'converted_to_so'], ['quotation_id' => $data['quotation_id']]);
            }

            $this->db->commit();

            $this->json([
                'success' => true,
                'message' => 'Sales Order created successfully',
                'so_id' => $soId
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->json([
                'success' => false,
                'message' => 'Failed to create sales order: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show(int $id): void
    {
        $this->requireAuth();
        
        $so = $this->db->get('sales_orders so', [
            '[>]customers c' => ['so.customer_id' => 'customer_id'],
             '[>]quotations q' => ['so.quotation_id' => 'quotation_id']
        ], [
            'so.*',
            'c.customer_name', 
            'c.customer_code',
            'c.address as customer_address',
            'q.quotation_no'
        ], ['so.so_id' => $id]);
        
        if (!$so) {
            $this->redirect('/commercial/sales_orders');
            return;
        }
        
        $lines = $this->db->select('sales_order_lines', '*', ['so_id' => $id, 'ORDER' => 'line_number']);
        
        $this->view('commercial/sales_orders/show', [
            'salesOrder' => $so,
            'lines' => $lines
        ]);
    }
    
    // implement edit, update, destroy...
}
