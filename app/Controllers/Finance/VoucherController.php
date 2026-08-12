<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Models\Transaction;
use App\Models\Account;
use Exception;

class VoucherController extends BaseController
{
    /**
     * Daybook / List of Vouchers
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('view_vouchers');
        
        $type = $_GET['type'] ?? null;
        
        $sql = "SELECT voucher_number, voucher_type, transaction_date, narration, SUM(debit) as total_amount 
                FROM transactions 
                WHERE 1=1";
        
        $params = [];
        if ($type) {
            $sql .= " AND voucher_type = :type";
            $params[':type'] = $type;
        }
        
        $sql .= " GROUP BY voucher_number, voucher_type, transaction_date, narration 
                  ORDER BY transaction_date DESC, voucher_number DESC";
        
        // Use prepare/execute for bound params if Database wrapper supports it,
        // The Database class exposes query($sql, $params) which handles safe binding.
        // Note: query() returns PDOStatement, we need fetchAll() on it.
        if ($type) {
            $vouchers = $this->db->query($sql, $params)->fetchAll();
        } else {
             $vouchers = $this->db->query($sql)->fetchAll();
        }
        
        $this->view('finance/vouchers/index', ['vouchers' => $vouchers, 'currentType' => $type]);
    }

    /**
     * Show Create Voucher Form
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission('create_voucher');
        
        // Fetch all leaf accounts (ledgers)
        // Groups (is_group=1) cannot be used in transactions usually, only Ledgers
        $accounts = $this->db->select('accounts', '*', [
            'is_group' => 0, 
            'status' => 'active',
            'ORDER' => 'account_name'
        ]);
        
        $type = $_GET['type'] ?? 'Payment'; // Default type
        
        // Generate a new Voucher Number (Simple auto-increment logic or random for now)
        // In real app, check max ID per type.
        $lastVoucher = $this->db->get('transactions', 'voucher_number', [
            'voucher_type' => $type,
            'ORDER' => ['transaction_id' => 'DESC']
        ]);
        
        // Simple increment logic: PV-1001, RV-1001 etc.
        // For now, let's just use numeric or allow user input. 
        // We'll generate a suggested one.
        $voucherNumber = $lastVoucher ? ((int)$lastVoucher + 1) : 1;

        $this->view('finance/vouchers/create', [
            'accounts' => $accounts, 
            'type' => $type,
            'voucherNumber' => $voucherNumber
        ]);
    }

    /**
     * Store Voucher
     */
    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requirePermission('create_voucher');
        $data = $this->request->all();
        
        // $data should contain:
        // date, voucher_type, voucher_number, narration
        // entries: array of { account_id, debit, credit }
        
        // Validation: Total Debit must equal Total Credit
        $totalDebit = 0;
        $totalCredit = 0;
        
        foreach ($data['entries'] as $entry) {
            $totalDebit += (float)$entry['debit'];
            $totalCredit += (float)$entry['credit'];
        }
        
        if (abs($totalDebit - $totalCredit) > 0.01) {
            // Error: Unbalanced
            // For now, just redirect back or show error.
            // Ideally use Flash messages.
            $this->redirect('finance/vouchers/create?error=unbalanced');
            return;
        }
        
        try {
            // Begin Transaction handled by Database usually, but here we just insert loop
            // In pure PDO: $this->db->pdo->beginTransaction();
            
            foreach ($data['entries'] as $entry) {
                if ((float)$entry['debit'] == 0 && (float)$entry['credit'] == 0) continue;

                (new Transaction())->create([
                    'transaction_date' => $data['date'],
                    'voucher_type' => $data['voucher_type'],
                    'voucher_number' => $data['voucher_number'],
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'narration' => $data['narration'] ?? '',
                    'created_by' => $this->auth->user()['id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->redirect('finance/vouchers');
            
        } catch (Exception $e) {
            $this->redirect('finance/vouchers/create?error=' . urlencode($e->getMessage()));
        }
    }
}
