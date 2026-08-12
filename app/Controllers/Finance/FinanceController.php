<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Models\Account;
use App\Models\Transaction;
use Exception;

class FinanceController extends BaseController
{
    // ACCOUNTS (Chart of Accounts)
    public function accounts(): void
    {
        $this->requireAuth();
        $this->requirePermission('view_accounts');
        $accounts = $this->db->select('accounts', '*', ['ORDER' => 'account_code']);
        $this->view('finance/accounts/index', ['accounts' => $accounts]);
    }
    
    public function createAccount(): void
    {
        $this->requireAuth();
        $this->requirePermission('manage_accounts');
        // Fetch existing Groups to be parents
        $groups = $this->db->select('accounts', '*', ['is_group' => 1, 'ORDER' => 'account_name']);
        
        $this->view('finance/accounts/create', ['groups' => $groups]);
    }

    public function storeAccount(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requirePermission('manage_accounts');
        $data = $this->request->all();
        
        try {
            $accModel = new Account();
            $accModel->create([
                'account_code' => $data['account_code'],
                'account_name' => $data['account_name'],
                'type' => $data['type'], // Asset, Liability, etc.
                'is_group' => isset($data['is_group']) ? 1 : 0,
                'parent_id' => !empty($data['parent_id']) ? $data['parent_id'] : null,
                'opening_balance' => !empty($data['opening_balance']) ? $data['opening_balance'] : 0.00,
                'status' => 'active',
                'created_by' => $this->auth->user()['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $this->redirect('finance/accounts');
        } catch (Exception $e) {
            $this->redirect('finance/accounts/create?error=' . urlencode($e->getMessage()));
        }
    }

    // LEDGER / TRANSACTIONS
    public function ledger(): void
    {
        $this->requireAuth();
        $this->requirePermission('view_vouchers');
        
        $transactions = $this->db->select('transactions t', [
            '[>]accounts a' => ['t.account_id' => 'account_id']
        ], [
            't.*',
            'a.account_name',
            'a.account_code'
        ], ['ORDER' => 'transaction_date']);
        
        $this->view('finance/ledger/index', ['transactions' => $transactions]);
    }
    
    public function createTransaction(): void
    {
        $this->requireAuth();
        $this->requirePermission('create_voucher');
        $accounts = $this->db->select('accounts', '*', ['status' => 'active']);
        $this->view('finance/transactions/create', ['accounts' => $accounts]);
    }

    public function storeTransaction(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requirePermission('create_voucher');
        $data = $this->request->all();
        
        try {
             // Basic Double Entry validation could go here
             $transModel = new Transaction();
             
             // Debit Entry
             if (!empty($data['debit_account']) && !empty($data['amount'])) {
                 $transModel->create([
                     'transaction_date' => $data['date'],
                     'account_id' => $data['debit_account'],
                     'debit' => $data['amount'],
                     'credit' => 0,
                     'reference' => $data['reference'] ?? '',
                     'description' => $data['description'] ?? '',
                     'created_by' => $this->auth->user()['id'],
                     'created_at' => date('Y-m-d H:i:s')
                 ]);
             }
             
             // Credit Entry
             if (!empty($data['credit_account']) && !empty($data['amount'])) {
                 $transModel->create([
                     'transaction_date' => $data['date'],
                     'account_id' => $data['credit_account'],
                     'debit' => 0,
                     'credit' => $data['amount'],
                     'reference' => $data['reference'] ?? '',
                     'description' => $data['description'] ?? '',
                     'created_by' => $this->auth->user()['id'],
                     'created_at' => date('Y-m-d H:i:s')
                 ]);
             }
             
             $this->redirect('finance/ledger');
        } catch (Exception $e) {
             $this->redirect('finance/transactions/create');
        }
    }
    // REPORTS
    
    public function trialBalance(): void
    {
        $this->requireAuth();
        $this->requirePermission('view_reports');
        
        // 1. Get Totals per Account
        $totals = $this->db->query("
            SELECT account_id, SUM(debit) as total_debit, SUM(credit) as total_credit 
            FROM transactions 
            GROUP BY account_id
        ")->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);
        
        // 2. Fetch Hierarchy
        $accounts = $this->db->select('accounts', '*', ['ORDER' => 'account_code']);
        
        // 3. Build Tree
        $tree = $this->buildAccountTree($accounts, $totals);
        
        $grandDebit = 0;
        $grandCredit = 0;
        foreach ($tree as $node) {
            if ($node['net_balance'] > 0) {
                $grandDebit += $node['net_balance'];
            } elseif ($node['net_balance'] < 0) {
                $grandCredit += abs($node['net_balance']);
            }
        }
        
        $this->view('finance/reports/trial_balance', [
            'tree' => $tree,
            'grandDebit' => $grandDebit,
            'grandCredit' => $grandCredit
        ]);
    }

    private function buildAccountTree(array $accounts, array $totals, int $parentId = null): array
    {
        $branch = [];
        foreach ($accounts as $account) {
            // Loose comparison for nulls/strings
            if ($account['parent_id'] == $parentId) {
                $children = $this->buildAccountTree($accounts, $totals, (int)$account['account_id']);
                
                // Calculate Self Balance
                $debit = 0; $credit = 0;
                if (isset($totals[$account['account_id']])) {
                    $debit = (float)$totals[$account['account_id']]['total_debit'];
                    $credit = (float)$totals[$account['account_id']]['total_credit'];
                }
                
                // Add Children Balances
                foreach ($children as $child) {
                    $debit += $child['total_debit'];
                    $credit += $child['total_credit'];
                }
                
                $balance = $debit - $credit;
                
                $node = $account;
                $node['children'] = $children;
                $node['total_debit'] = $debit;
                $node['total_credit'] = $credit;
                $node['net_balance'] = $balance;
                
                $branch[] = $node;
            }
        }
        return $branch;
    }
}
