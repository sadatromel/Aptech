<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;

class FinanceDashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        // 1. Pending Approvals Count from Vouchers & Requisitions
        $pendingRequisitionsCount = (int) ($this->db->query("SELECT COUNT(*) FROM store_requisitions WHERE status = 'pending'")->fetchColumn() ?? 0);
        $pendingVouchersCount = (int) ($this->db->query("SELECT COUNT(*) FROM vouchers WHERE status IN ('pending_approval', 'pending_release')")->fetchColumn() ?? 0);
        $pendingApprovalsCount = $pendingRequisitionsCount + $pendingVouchersCount;

        // 2. Accounts Payable stats (dynamic from DB)
        $apStats = $this->db->query("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount - paid_amount), 0) as total 
            FROM accounts_payable 
            WHERE status != 'paid'
        ")->fetch(\PDO::FETCH_ASSOC) ?? ['count' => 0, 'total' => 0.00];

        // 3. Accounts Receivable stats (dynamic from DB)
        $arStats = $this->db->query("
            SELECT COUNT(*) as count, COALESCE(SUM(amount - paid_amount), 0) as total 
            FROM accounts_receivable 
            WHERE status != 'paid'
        ")->fetch(\PDO::FETCH_ASSOC) ?? ['count' => 0, 'total' => 0.00];

        // 4. Dynamic Cash & Bank Account Balances from General Ledger
        $cashAccountsRaw = $this->db->query("
            SELECT 
                a.account_id,
                a.account_name,
                a.account_code,
                COALESCE(SUM(gl.debit_amount - gl.credit_amount), 0) as current_balance
            FROM chart_of_accounts a
            LEFT JOIN general_ledger gl ON a.account_id = gl.account_id
            WHERE a.account_type IN ('Asset', 'Current Asset') 
              AND (a.account_name LIKE '%Cash%' OR a.account_name LIKE '%Bank%' OR a.account_code LIKE '10%')
              AND a.status = 'active'
            GROUP BY a.account_id, a.account_name, a.account_code
            ORDER BY a.account_code ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $cashAccounts = [];
        $totalCashOnHand = 0.00;
        foreach ($cashAccountsRaw as $ca) {
            $bal = (float)$ca['current_balance'];
            $totalCashOnHand += $bal;
            $cashAccounts[] = [
                'account_name' => $ca['account_name'],
                'opening_balance' => $bal
            ];
        }

        // 5. Total Income & Total Expense calculations from General Ledger
        $totalIncome = (float)($this->db->query("
            SELECT COALESCE(SUM(gl.credit_amount - gl.debit_amount), 0)
            FROM general_ledger gl
            JOIN chart_of_accounts a ON gl.account_id = a.account_id
            WHERE a.account_type IN ('Income', 'Revenue')
        ")->fetchColumn() ?? 0.00);

        $totalExpense = (float)($this->db->query("
            SELECT COALESCE(SUM(gl.debit_amount - gl.credit_amount), 0)
            FROM general_ledger gl
            JOIN chart_of_accounts a ON gl.account_id = a.account_id
            WHERE a.account_type = 'Expense'
        ")->fetchColumn() ?? 0.00);

        $netProfit = $totalIncome - $totalExpense;
        $netMargin = ($totalIncome > 0) ? number_format(($netProfit / $totalIncome) * 100, 1) . '%' : '0.0%';

        $workingCapital = ($totalCashOnHand + (float)$arStats['total']) - (float)$apStats['total'];

        $stats = [
            'net_profit_margin' => $netMargin,
            'working_capital' => $workingCapital,
            'cash_on_hand' => $totalCashOnHand,
            'pending_approvals' => $pendingApprovalsCount,
            'due_payable_count' => (int)$apStats['count'],
            'due_payable_total' => (float)$apStats['total'],
            'due_receivable_count' => (int)$arStats['count'],
            'due_receivable_total' => (float)$arStats['total']
        ];

        // 6. Monthly Cash Flow Trend from General Ledger
        $monthlyCashflow = $this->db->query("
            SELECT 
                DATE_FORMAT(transaction_date, '%b %Y') as month_label, 
                DATE_FORMAT(transaction_date, '%Y-%m') as year_month_key,
                COALESCE(SUM(debit_amount), 0) as inflow, 
                COALESCE(SUM(credit_amount), 0) as outflow 
            FROM general_ledger 
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), DATE_FORMAT(transaction_date, '%b %Y')
            ORDER BY DATE_FORMAT(transaction_date, '%Y-%m') ASC
            LIMIT 6
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 7. Recent Transactions Feed
        $recentTransactions = $this->db->query("
            SELECT gl.*, a.account_name, a.account_code 
            FROM general_ledger gl
            JOIN chart_of_accounts a ON gl.account_id = a.account_id
            ORDER BY gl.transaction_date DESC, gl.ledger_id DESC
            LIMIT 6
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('finance/dashboard', [
            'pageTitle' => 'Finance Dashboard & KPIs',
            'activeModule' => 'finance',
            'activeSubModule' => 'dashboard',
            'stats' => $stats,
            'cashAccounts' => $cashAccounts,
            'monthlyCashflow' => $monthlyCashflow,
            'recentTransactions' => $recentTransactions
        ]);
    }
}
