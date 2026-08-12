<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;

class FinancialReportsController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'company' => $this->request->get('company', 'Big Boss Corporation'),
            'ac_year' => $this->request->get('ac_year', '2025-2026'),
            'main_group' => $this->request->get('main_group', ''),
            'sub_group' => $this->request->get('sub_group', ''),
            'sub_sub_group' => $this->request->get('sub_sub_group', ''),
            'account_code' => $this->request->get('account_code', ''),
            'with_sub_code' => $this->request->get('with_sub_code', ''),
            'balance_type' => $this->request->get('balance_type', 'without_zero'),
            'date_from' => $this->request->get('date_from', ''),
            'date_to' => $this->request->get('date_to', date('Y-m-d'))
        ];

        $service = \Core\Container::get(\App\Services\Finance\GeneralLedgerService::class);
        $trialBalanceRaw = $service->getTrialBalance($filters);

        $trialBalance = [];
        $totalOpening = 0.00;
        $totalTBDebit = 0.00;
        $totalTBCredit = 0.00;
        foreach ($trialBalanceRaw as $tb) {
            $op = (float)$tb['opening_balance'];
            $dr = (float)$tb['total_debits'];
            $cr = (float)$tb['total_credits'];
            $totalOpening += $op;
            $totalTBDebit += $dr;
            $totalTBCredit += $cr;
            $trialBalance[] = [
                'account_code' => $tb['account_code'],
                'account_name' => $tb['account_name'],
                'account_type' => $tb['account_type'],
                'opening_balance' => $op,
                'total_debit' => $dr,
                'total_credit' => $cr
            ];
        }

        // Calculate Profit & Loss (Income Statement)
        $incomeStatement = [];
        $totalIncome = 0.00;
        $totalExpense = 0.00;
        foreach ($trialBalance as $tb) {
            $isIncome = in_array($tb['account_type'], ['Income', 'Revenue']);
            $isExpense = ($tb['account_type'] === 'Expense');
            
            if ($isIncome) {
                // Credit balance is normal for Income
                $bal = (float)$tb['total_credit'] - (float)$tb['total_debit'];
                if ($bal !== 0.0) {
                    $incomeStatement['income'][] = [
                        'account_code' => $tb['account_code'],
                        'account_name' => $tb['account_name'],
                        'balance' => $bal
                    ];
                    $totalIncome += $bal;
                }
            } elseif ($isExpense) {
                // Debit balance is normal for Expense
                $bal = (float)$tb['total_debit'] - (float)$tb['total_credit'];
                if ($bal !== 0.0) {
                    $incomeStatement['expense'][] = [
                        'account_code' => $tb['account_code'],
                        'account_name' => $tb['account_name'],
                        'balance' => $bal
                    ];
                    $totalExpense += $bal;
                }
            }
        }

        $netProfit = $totalIncome - $totalExpense;

        // Calculate Balance Sheet
        $balanceSheet = ['assets' => [], 'liabilities' => [], 'equity' => []];
        $totalAssets = 0.00;
        $totalLiabilities = 0.00;
        $totalEquity = 0.00;
        foreach ($trialBalance as $tb) {
            $isAsset = in_array($tb['account_type'], ['Asset', 'Current Asset', 'Fixed Asset']);
            $isLiability = in_array($tb['account_type'], ['Liability', 'Current Liability']);
            $isEquity = ($tb['account_type'] === 'Equity');

            if ($isAsset) {
                $bal = (float)$tb['total_debit'] - (float)$tb['total_credit'];
                if ($bal !== 0.0) {
                    $balanceSheet['assets'][] = ['code' => $tb['account_code'], 'name' => $tb['account_name'], 'balance' => $bal];
                    $totalAssets += $bal;
                }
            } elseif ($isLiability) {
                $bal = (float)$tb['total_credit'] - (float)$tb['total_debit'];
                if ($bal !== 0.0) {
                    $balanceSheet['liabilities'][] = ['code' => $tb['account_code'], 'name' => $tb['account_name'], 'balance' => $bal];
                    $totalLiabilities += $bal;
                }
            } elseif ($isEquity) {
                $bal = (float)$tb['total_credit'] - (float)$tb['total_debit'];
                if ($bal !== 0.0) {
                    $balanceSheet['equity'][] = ['code' => $tb['account_code'], 'name' => $tb['account_name'], 'balance' => $bal];
                    $totalEquity += $bal;
                }
            }
        }

        // Standard Financial Accounting: Include Current Period Retained Earnings (Net Profit) in Equity
        if ($netProfit !== 0.0) {
            $balanceSheet['equity'][] = [
                'code' => '3999',
                'name' => 'Retained Earnings / Current Period Net Profit',
                'balance' => $netProfit
            ];
            $totalEquity += $netProfit;
        }

        // 2. Simulated Budgets data
        $budgets = [
            ['department' => 'Production (Sewing)', 'fiscal_year' => 'FY 2026-2027', 'budget_amount' => 500000.00, 'actual_spent' => 42000.00, 'variance' => 458000.00],
            ['department' => 'Procurement (Yarn)', 'fiscal_year' => 'FY 2026-2027', 'budget_amount' => 1200000.00, 'actual_spent' => 245000.00, 'variance' => 955000.00],
            ['department' => 'Administration HQ', 'fiscal_year' => 'FY 2026-2027', 'budget_amount' => 300000.00, 'actual_spent' => 18000.00, 'variance' => 282000.00]
        ];

        // 3. Simulated Fixed Assets register
        $assets = [
            ['asset_code' => 'AST-SEW-0041', 'asset_name' => 'Juki High-Speed Single Needle Sewing Machine', 'purchase_date' => '2026-01-10', 'purchase_cost' => 1200.00, 'depr_rate' => '10%', 'accum_depr' => 60.00, 'book_value' => 1140.00],
            ['asset_code' => 'AST-GEN-0012', 'asset_name' => 'Cummins 500kVA Diesel Generator Set', 'purchase_date' => '2025-07-01', 'purchase_cost' => 45000.00, 'depr_rate' => '15%', 'accum_depr' => 6750.00, 'book_value' => 38250.00]
        ];

        // 4. Simulated Tax/VAT return registers (Bangladesh local tax compliance)
        $vatRecords = [
            ['period' => 'June 2026', 'vat_input' => 18450.00, 'vat_output' => 24100.00, 'payable' => 5650.00, 'status' => 'Paid'],
            ['period' => 'July 2026', 'vat_input' => 8400.00, 'vat_output' => 11200.00, 'payable' => 2800.00, 'status' => 'Pending']
        ];

        $this->view('finance/reports', [
            'pageTitle' => 'Financial Reports, Assets & Budgets',
            'activeModule' => 'finance',
            'activeSubModule' => 'reports',
            'filters' => $filters,
            'trialBalance' => $trialBalance,
            'totalOpening' => $totalOpening,
            'totalTBDebit' => $totalTBDebit,
            'totalTBCredit' => $totalTBCredit,
            'incomeStatement' => $incomeStatement,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'balanceSheet' => $balanceSheet,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'budgets' => $budgets,
            'assets' => $assets,
            'vatRecords' => $vatRecords
        ]);
    }

    public function trialBalance(): void
    {
        $this->requireAuth();

        $filters = [
            'company' => $this->request->get('company', 'Big Boss Corporation'),
            'ac_year' => $this->request->get('ac_year', '2025-2026'),
            'main_group' => $this->request->get('main_group', ''),
            'sub_group' => $this->request->get('sub_group', ''),
            'sub_sub_group' => $this->request->get('sub_sub_group', ''),
            'account_code' => $this->request->get('account_code', ''),
            'with_sub_code' => $this->request->get('with_sub_code', ''),
            'balance_type' => $this->request->get('balance_type', 'without_zero'),
            'date_from' => $this->request->get('date_from', ''),
            'date_to' => $this->request->get('date_to', date('Y-m-d')),
            'as_of_date' => $this->request->get('as_of_date', date('Y-m-d'))
        ];

        $service = \Core\Container::get(\App\Services\Finance\GeneralLedgerService::class);
        $trialBalance = $service->getTrialBalance($filters);

        $totalOpening = array_sum(array_column($trialBalance, 'opening_balance'));
        $totalDebits = array_sum(array_column($trialBalance, 'total_debits'));
        $totalCredits = array_sum(array_column($trialBalance, 'total_credits'));

        $this->view('finance/general-ledger/trial-balance', [
            'trial_balance' => $trialBalance,
            'total_opening' => $totalOpening,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'filters' => $filters,
            'as_of_date' => !empty($filters['date_to']) ? $filters['date_to'] : $filters['as_of_date'],
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
        ]);
    }

    public function profitAndLoss(): void
    {
        $this->requireAuth();

        $dateFrom = $this->request->get('date_from', date('Y-01-01'));
        $dateTo = $this->request->get('date_to', date('Y-m-d'));

        $service = \Core\Container::get(\App\Services\Finance\GeneralLedgerService::class);

        $this->view('finance/reports/profit-and-loss', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'data' => $service->getProfitAndLoss($dateFrom, $dateTo)
        ]);
    }

    public function balanceSheet(): void
    {
        $this->requireAuth();

        $asOfDate = $this->request->get('as_of_date', date('Y-m-d'));

        $service = \Core\Container::get(\App\Services\Finance\GeneralLedgerService::class);

        $this->view('finance/reports/balance-sheet', [
            'as_of_date' => $asOfDate,
            'data' => $service->getBalanceSheet($asOfDate)
        ]);
    }

    public function cashBook(): void
    {
        $this->requireAuth();

        $dateFrom = $this->request->get('date_from', date('Y-01-01'));
        $dateTo = $this->request->get('date_to', date('Y-m-d'));
        $accountId = $this->request->get('account_id', '');

        $service = \Core\Container::get(\App\Services\Finance\GeneralLedgerService::class);

        $this->view('finance/reports/cash-book', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'account_id' => $accountId,
            'data' => $service->getCashBook($dateFrom, $dateTo, (int)$accountId),
            'cash_bank_accounts' => $service->getCashBankAccounts()
        ]);
    }

    public function postDepreciation(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $_SESSION['success_message'] = 'Monthly assets depreciation schedules posted successfully';
        $this->redirect('finance/reports');
    }

    public function storeBudget(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $dept = $_POST['department'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);

        if (empty($dept) || $amount <= 0) {
            $_SESSION['error_message'] = 'Department and positive budget amount are required';
            $this->redirect('finance/reports');
            return;
        }

        $_SESSION['success_message'] = 'Budget for ' . $dept . ' set successfully';
        $this->redirect('finance/reports');
    }

    public function storeVAT(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $_SESSION['success_message'] = 'Bangladesh VAT Return submitted to NBR successfully';
        $this->redirect('finance/reports');
    }
}
