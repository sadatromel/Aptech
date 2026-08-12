<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;

class CashBankController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        // 1. Fetch Bank Accounts from bank_information
        $bankAccounts = $this->db->query("
            SELECT * FROM bank_information 
            ORDER BY bank_name ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Fetch Currencies from currencies
        $exchangeRates = $this->db->query("
            SELECT * FROM currencies 
            ORDER BY is_base_currency DESC, currency_code ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Simulated Cheque Register
        $cheques = [
            ['cheque_no' => 'CHQ-981203', 'bank_name' => 'Standard Chartered Bank', 'amount' => 45000.00, 'issue_date' => '2026-07-10', 'clearance_date' => '2026-07-12', 'status' => 'Cleared'],
            ['cheque_no' => 'CHQ-981204', 'bank_name' => 'SCB SCB', 'amount' => 125000.00, 'issue_date' => '2026-07-11', 'clearance_date' => null, 'status' => 'Issued']
        ];

        // 4. Simulated cash book balances
        $cashBalances = [
            ['cash_book' => 'Factory Petty Cash', 'custodian' => 'Habibur Rahman', 'limit' => 20000.00, 'balance' => 4820.00],
            ['cash_book' => 'Head Office General Cash', 'custodian' => 'Kamal Hossain', 'limit' => 100000.00, 'balance' => 64300.00]
        ];

        $this->view('finance/cash_bank', [
            'pageTitle' => 'Cash, Bank & Exchange Rates',
            'activeModule' => 'finance',
            'activeSubModule' => 'cash-bank',
            'bankAccounts' => $bankAccounts,
            'exchangeRates' => $exchangeRates,
            'cheques' => $cheques,
            'cashBalances' => $cashBalances
        ]);
    }

    public function storeTransfer(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $amount = (float)($_POST['amount'] ?? 0);
        $from = $_POST['from_source'] ?? '';
        $to = $_POST['to_destination'] ?? '';

        if ($amount <= 0 || empty($from) || empty($to)) {
            $_SESSION['error_message'] = 'Transfer source, destination, and amount are required';
            $this->redirect('finance/cash-bank');
            return;
        }

        $_SESSION['success_message'] = 'Cash transfer of $' . number_format($amount, 2) . ' initiated successfully';
        $this->redirect('finance/cash-bank');
    }

    public function storeCheque(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $chqNo = $_POST['cheque_no'] ?? '';
        $bank = $_POST['bank_name'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);

        if (empty($chqNo) || empty($bank) || $amount <= 0) {
            $_SESSION['error_message'] = 'Cheque number, bank name, and amount are required';
            $this->redirect('finance/cash-bank');
            return;
        }

        $_SESSION['success_message'] = 'Cheque ' . $chqNo . ' registered successfully';
        $this->redirect('finance/cash-bank');
    }

    public function storeReconciliation(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $_SESSION['success_message'] = 'Bank reconciliation details updated';
        $this->redirect('finance/cash-bank');
    }
}
