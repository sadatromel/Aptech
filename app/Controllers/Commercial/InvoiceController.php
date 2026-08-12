<?php

declare(strict_types=1);

namespace App\Controllers\Commercial;

use App\Controllers\BaseController;
use App\Models\CommercialInvoice;
use App\Models\Customer;
use Exception;

class InvoiceController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $status = $this->request->get('status', '');
        $customer = $this->request->get('customer', '');
        $date_from = $this->request->get('date_from', date('Y-m-01'));
        $date_to = $this->request->get('date_to', date('Y-m-d'));

        // Build where conditions
        $where = [
            'invoice_date[>=]' => $date_from,
            'invoice_date[<=]' => $date_to
        ];
        
        if (!empty($status)) {
            $where['status'] = $status;
        }
        if (!empty($customer)) {
            $where['customer_id'] = $customer;
        }

        $invoices = $this->db->select('commercial_invoices ci', [
            '[>]customers c' => ['ci.customer_id' => 'customer_id'],
            '[>]production_orders po' => ['ci.order_id' => 'po_id']
        ], [
            'ci.id',
            'ci.invoice_number',
            'ci.invoice_date',
            'ci.due_date',
            'ci.total_amount',
            'ci.paid_amount',
            'ci.balance',
            'ci.status',
            'c.customer_name',
            'po.order_number'
        ], [
            'WHERE' => $where,
            'ORDER' => ['ci.invoice_date' => 'DESC']
        ]);

        // Get filters data
        $customers = $this->db->select('customers', '*', ['ORDER' => 'customer_name']);

        // Calculate totals
        $totals = [
            'total_amount' => array_sum(array_column($invoices, 'total_amount')),
            'total_paid' => array_sum(array_column($invoices, 'paid_amount')),
            'total_balance' => array_sum(array_column($invoices, 'balance'))
        ];

        $this->view('commercial/invoices/index', [
            'invoices' => $invoices,
            'customers' => $customers,
            'totals' => $totals,
            'filters' => [
                'status' => $status,
                'customer' => $customer,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'commercial_manager', 'manager']);

        $customers = $this->db->select('customers', '*', ['status' => 'active', 'ORDER' => 'customer_name']);
        $orders = $this->db->select('production_orders', [
            '[>]customers' => ['buyer_id' => 'customer_id']
        ], [
            'production_orders.po_id',
            'production_orders.buyer_id',
            'production_orders.order_number',
            'production_orders.quantity',
            'production_orders.unit_price',
            'customers.customer_name'
        ], [
            'production_orders.status' => 'completed',
            'production_orders.invoiced' => 0,
            'ORDER' => ['production_orders.order_date' => 'DESC']
        ]);

        $this->view('commercial/invoices/create', [
            'customers' => $customers,
            'orders' => $orders
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'commercial_manager', 'manager']);

        $data = $this->request->all();
        
        $rules = [
            'customer_id' => 'required',
            'invoice_date' => 'required',
            'due_date' => 'required',
            'items' => 'required|array'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber();

            // Calculate total amount
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            // Add tax if applicable
            $taxAmount = $data['tax_rate'] ? ($totalAmount * $data['tax_rate'] / 100) : 0;
            $grandTotal = $totalAmount + $taxAmount - ($data['discount'] ?? 0);

            // Insert invoice
            $invoiceId = $this->db->insert('commercial_invoices', [
                'invoice_number' => $invoiceNumber,
                'customer_id' => $data['customer_id'],
                'order_id' => $data['order_id'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'total_amount' => $grandTotal,
                'paid_amount' => 0,
                'balance' => $grandTotal,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'tax_amount' => $taxAmount,
                'discount' => $data['discount'] ?? 0,
                'status' => 'pending',
                'notes' => $data['notes'] ?? '',
                'created_by' => $this->auth->user()['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Insert invoice items
            foreach ($data['items'] as $item) {
                $this->db->insert('invoice_items', [
                    'invoice_id' => $invoiceId,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['quantity'] * $item['unit_price'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Update order as invoiced if order_id is provided
            if (!empty($data['order_id'])) {
                $this->db->update('production_orders', [
                    'invoiced' => 1,
                    'invoice_id' => $invoiceId,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['po_id' => $data['order_id']]);
            }

            // Create accounts receivable entry
            $this->db->insert('accounts_receivable', [
                'buyer_id' => $data['customer_id'],
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'amount' => $grandTotal,
                'total_amount' => $grandTotal,
                'due_amount' => $grandTotal,
                'status' => 'pending',
                'description' => "Invoice #$invoiceNumber",
                'created_by' => $this->auth->user()['id'] ?? 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();

            $this->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->json([
                'success' => false,
                'message' => 'Failed to create invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $invoice = $this->db->get('commercial_invoices ci', [
            '[>]customers c' => ['ci.customer_id' => 'customer_id'],
            '[>]production_orders po' => ['ci.order_id' => 'po_id']
        ], [
            'ci.*',
            'c.customer_name',
            'c.address',
            'c.phone',
            'c.email',
            'po.order_number'
        ], ['ci.id' => $id]);

        if (!$invoice) {
            $this->redirect('/commercial/invoices');
            return;
        }

        // Get invoice items
        $items = $this->db->select('invoice_items', '*', [
            'invoice_id' => $id,
            'ORDER' => ['id' => 'ASC']
        ]);

        // Get payment history
        $payments = $this->db->select('invoice_payments', '*', [
            'invoice_id' => $id,
            'ORDER' => ['payment_date' => 'DESC']
        ]);

        $this->view('commercial/invoices/show', [
            'invoice' => $invoice,
            'items' => $items,
            'payments' => $payments
        ]);
    }

    public function receivePayment(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'commercial_manager', 'manager']);

        $data = $this->request->all();
        
        $rules = [
            'payment_amount' => 'required|numeric',
            'payment_date' => 'required',
            'payment_method' => 'required'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Get current invoice
            $invoice = $this->db->get('commercial_invoices', '*', ['id' => $id]);
            if (!$invoice) {
                throw new \Exception('Invoice not found');
            }

            $paymentAmount = floatval($data['payment_amount']);
            if ($paymentAmount > $invoice['balance']) {
                throw new \Exception('Payment amount exceeds balance');
            }

            // Add payment record
            $this->db->insert('invoice_payments', [
                'invoice_id' => $id,
                'payment_amount' => $paymentAmount,
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? '',
                'notes' => $data['notes'] ?? '',
                'created_by' => $this->auth->user()['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Update invoice
            $newPaidAmount = $invoice['paid_amount'] + $paymentAmount;
            $newBalance = $invoice['balance'] - $paymentAmount;
            $newStatus = $newBalance <= 0 ? 'paid' : 'partial';

            $this->db->update('commercial_invoices', [
                'paid_amount' => $newPaidAmount,
                'balance' => $newBalance,
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $id]);

            // Update accounts receivable
            $ar = $this->db->get('accounts_receivable', '*', [
                'invoice_number' => $invoice['invoice_number']
            ]);

            if ($ar) {
                $this->db->update('accounts_receivable', [
                    'paid_amount' => $newPaidAmount,
                    'due_amount' => $newBalance,
                    'status' => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['receivable_id' => $ar['receivable_id']]);
            }

            $this->db->commit();

            $this->json([
                'success' => true,
                'message' => 'Payment received successfully'
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = date('Ymd');
        
        // Get today's count
        $count = $this->db->count('commercial_invoices', [
            'invoice_number[~]' => $prefix . $date
        ]) + 1;
        
        return sprintf("%s%s%04d", $prefix, $date, $count);
    }

    public function printInvoice(int $id): void
    {
        $this->requireAuth();

        $invoice = $this->db->get('commercial_invoices ci', [
            '[>]customers c' => ['ci.customer_id' => 'customer_id'],
            '[>]production_orders po' => ['ci.order_id' => 'po_id']
        ], [
            'ci.*',
            'c.customer_name',
            'c.address',
            'c.phone',
            'c.email',
            'po.order_number'
        ], ['ci.id' => $id]);

        if (!$invoice) {
            $this->redirect('/commercial/invoices');
            return;
        }

        $items = $this->db->select('invoice_items', '*', [
            'invoice_id' => $id,
            'ORDER' => ['id' => 'ASC']
        ]);

        $this->view('commercial/invoices/print', [
            'invoice' => $invoice,
            'items' => $items
        ]);
    }
}
