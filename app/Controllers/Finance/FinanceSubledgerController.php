<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;

class FinanceSubledgerController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        // 1. Fetch Receivables
        $receivables = $this->db->query("
            SELECT ar.*, b.buyer_name, b.buyer_code 
            FROM accounts_receivable ar
            LEFT JOIN buyers b ON ar.buyer_id = b.buyer_id
            ORDER BY ar.receivable_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Simulated receivables if empty
        if (empty($receivables)) {
            $receivables = [
                ['receivable_id' => 1, 'invoice_number' => 'INV-2026-0092', 'invoice_date' => '2026-07-01', 'due_date' => '2026-07-31', 'buyer_code' => 'BUY-HNM', 'buyer_name' => 'H&M Hennes & Mauritz', 'amount' => 125000.00, 'paid_amount' => 0.00, 'due_amount' => 125000.00, 'status' => 'pending'],
                ['receivable_id' => 2, 'invoice_number' => 'INV-2026-0091', 'invoice_date' => '2026-06-15', 'due_date' => '2026-07-15', 'buyer_code' => 'BUY-ZARA', 'buyer_name' => 'Zara Inditex', 'amount' => 84000.00, 'paid_amount' => 50000.00, 'due_amount' => 34000.00, 'status' => 'partial']
            ];
        }

        // 2. Fetch Payables
        $payables = $this->db->query("
            SELECT ap.*, v.vendor_name, v.vendor_code 
            FROM accounts_payable ap
            LEFT JOIN vendors v ON ap.vendor_id = v.vendor_id
            ORDER BY ap.payable_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Simulated payables if empty
        if (empty($payables)) {
            $payables = [
                ['payable_id' => 1, 'bill_number' => 'BIL-YRN-9812', 'bill_date' => '2026-07-02', 'due_date' => '2026-08-02', 'vendor_code' => 'VEN-TEX', 'vendor_name' => 'Apex Textiles Spinning Ltd.', 'total_amount' => 64000.00, 'paid_amount' => 0.00, 'due_amount' => 64000.00, 'status' => 'pending'],
                ['payable_id' => 2, 'bill_number' => 'BIL-ACC-1102', 'bill_date' => '2026-06-20', 'due_date' => '2026-07-20', 'vendor_code' => 'VEN-TRM', 'vendor_name' => 'Pioneer Accessories & Packaging', 'total_amount' => 18500.00, 'paid_amount' => 18500.00, 'due_amount' => 0.00, 'status' => 'paid']
            ];
        }

        // 3. Fetch Cost Centers from cost_centers
        $costCenters = $this->db->query("
            SELECT * FROM cost_centers 
            ORDER BY cost_center_code ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Simulated profit centers
        $profitCenters = [
            ['profit_center_id' => 1, 'profit_center_name' => 'Woven Denim Division', 'profit_center_code' => 'PC-WD', 'status' => 'active'],
            ['profit_center_id' => 2, 'profit_center_name' => 'Knit & T-Shirt Division', 'profit_center_code' => 'PC-KT', 'status' => 'active']
        ];

        // Fetch Buyers and Vendors for Select lists
        $buyers = $this->db->query("SELECT buyer_id, buyer_name FROM buyers WHERE status='active'")->fetchAll(\PDO::FETCH_ASSOC);
        $vendors = $this->db->query("SELECT vendor_id, vendor_name FROM vendors WHERE status='active'")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('finance/subledgers', [
            'pageTitle' => 'Subledgers, Cost & Profit Centers',
            'activeModule' => 'finance',
            'activeSubModule' => 'subledgers',
            'receivables' => $receivables,
            'payables' => $payables,
            'costCenters' => $costCenters,
            'profitCenters' => $profitCenters,
            'buyers' => $buyers,
            'vendors' => $vendors
        ]);
    }

    public function storeReceipt(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $invoiceNo = $_POST['invoice_number'] ?? '';
        $buyerId = (int)($_POST['buyer_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);

        if (empty($invoiceNo) || $buyerId <= 0 || $amount <= 0) {
            $_SESSION['error_message'] = 'Invoice number, buyer selection, and amount are required';
            $this->redirect('finance/subledgers');
            return;
        }

        try {
            $this->db->insert('accounts_receivable', [
                'buyer_id' => $buyerId,
                'invoice_number' => $invoiceNo,
                'invoice_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'amount' => $amount,
                'total_amount' => $amount,
                'due_amount' => $amount,
                'status' => 'pending'
            ]);
            $_SESSION['success_message'] = 'Sales Invoice ' . $invoiceNo . ' registered successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('finance/subledgers');
    }

    public function storePayment(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $billNo = $_POST['bill_number'] ?? '';
        $vendorId = (int)($_POST['vendor_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);

        if (empty($billNo) || $vendorId <= 0 || $amount <= 0) {
            $_SESSION['error_message'] = 'Bill number, supplier selection, and amount are required';
            $this->redirect('finance/subledgers');
            return;
        }

        try {
            $this->db->insert('accounts_payable', [
                'vendor_id' => $vendorId,
                'bill_number' => $billNo,
                'bill_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'total_amount' => $amount,
                'due_amount' => $amount,
                'status' => 'pending'
            ]);
            $_SESSION['success_message'] = 'Supplier Bill ' . $billNo . ' registered successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('finance/subledgers');
    }

    public function storeCostCenter(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $code = $_POST['cost_center_code'] ?? '';
        $name = $_POST['cost_center_name'] ?? '';

        if (empty($code) || empty($name)) {
            $_SESSION['error_message'] = 'Cost center code and name are required';
            $this->redirect('finance/subledgers');
            return;
        }

        try {
            $this->db->insert('cost_centers', [
                'cost_center_code' => $code,
                'cost_center_name' => $name,
                'status' => 'active'
            ]);
            $_SESSION['success_message'] = 'Cost Center ' . $code . ' created successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('finance/subledgers');
    }
}
