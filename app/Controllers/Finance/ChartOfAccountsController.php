<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;

class ChartOfAccountsController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        // 1. Fetch Chart of Accounts
        $accounts = $this->db->query("
            SELECT * FROM chart_of_accounts 
            ORDER BY account_code ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Fetch Daybook / Daily Transactions from General Ledger
        $daybook = $this->db->query("
            SELECT gl.*, a.account_name, a.account_code, a.account_type, cc.cost_center_name
            FROM general_ledger gl
            LEFT JOIN chart_of_accounts a ON gl.account_id = a.account_id
            LEFT JOIN cost_centers cc ON gl.cost_center_id = cc.cost_center_id
            ORDER BY gl.transaction_date DESC, gl.ledger_id DESC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Fetch Audit Trail logs
        $auditLogs = [
            ['date' => '2026-07-12 18:22:10', 'user' => 'admin', 'action' => 'Create Voucher', 'details' => 'Created PV-2026-0045, Total $12,400.00'],
            ['date' => '2026-07-12 16:45:02', 'user' => 'admin', 'action' => 'Modify Account', 'details' => 'Set Opening Balance for 1001-Cash on Hand'],
            ['date' => '2026-07-11 11:30:15', 'user' => 'finance_manager', 'action' => 'Approve Voucher', 'details' => 'Released JV-2026-0112'],
            ['date' => '2026-07-10 09:00:00', 'user' => 'system', 'action' => 'Period Lock', 'details' => 'Locked Financial Period: June 2026']
        ];

        // 4. Fiscal years
        $fiscalYears = [
            ['id' => 1, 'year_label' => 'FY 2025-2026', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'status' => 'Closed'],
            ['id' => 2, 'year_label' => 'FY 2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => 'Active']
        ];

        $this->view('finance/accounts', [
            'pageTitle' => 'Chart of Accounts & Daybook',
            'activeModule' => 'finance',
            'activeSubModule' => 'accounts',
            'accounts' => $accounts,
            'daybook' => $daybook,
            'auditLogs' => $auditLogs,
            'fiscalYears' => $fiscalYears
        ]);
    }

    public function storeAccount(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $code = $_POST['account_code'] ?? '';
        $name = $_POST['account_name'] ?? '';
        $type = $_POST['account_type'] ?? 'Asset';
        $status = $_POST['status'] ?? 'active';

        if (empty($code) || empty($name)) {
            $_SESSION['error_message'] = 'Account code and name are required';
            $this->redirect('finance/accounts');
            return;
        }

        try {
            $this->db->insert('chart_of_accounts', [
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'status' => $status
            ]);
            $_SESSION['success_message'] = 'Account ' . $code . ' created successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('finance/accounts');
    }

    public function storeOpeningBalance(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $accountId = (int)($_POST['account_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0.00);
        $balanceType = $_POST['balance_type'] ?? 'debit'; // debit or credit

        if ($accountId <= 0 || $amount <= 0) {
            $_SESSION['error_message'] = 'Valid account and amount are required';
            $this->redirect('finance/accounts');
            return;
        }

        try {
            // Post an Opening entry to general_ledger
            $debit = ($balanceType === 'debit') ? $amount : 0.00;
            $credit = ($balanceType === 'credit') ? $amount : 0.00;

            $this->db->insert('general_ledger', [
                'transaction_date' => date('Y-m-d'),
                'account_id' => $accountId,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'description' => 'Opening Balance posting',
                'reference_type' => 'opening',
                'reference_id' => 1,
                'created_by' => $_SESSION['user_id'] ?? 1
            ]);

            $_SESSION['success_message'] = 'Opening balance set successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error posting opening balance: ' . $e->getMessage();
        }

        $this->redirect('finance/accounts');
    }

    public function storeFiscalYear(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $yearLabel = $_POST['year_label'] ?? '';
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';

        if (empty($yearLabel) || empty($start) || empty($end)) {
            $_SESSION['error_message'] = 'All fiscal year fields are required';
            $this->redirect('finance/accounts');
            return;
        }

        $_SESSION['success_message'] = 'Fiscal year ' . $yearLabel . ' created successfully';
        $this->redirect('finance/accounts');
    }
}
