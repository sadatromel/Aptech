<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;

class FinanceVoucherController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        // 1. Fetch Posted Vouchers from `vouchers` table or fallback to General Ledger
        $vouchers = $this->db->query("
            SELECT v.id, v.voucher_number as reference_id, v.voucher_type as reference_type, 
                   v.transaction_date, v.description, v.amount as total_amount, 2 as line_count,
                   da.account_code as debit_code, da.account_name as debit_name,
                   ca.account_code as credit_code, ca.account_name as credit_name
            FROM vouchers v
            LEFT JOIN chart_of_accounts da ON v.debit_account_id = da.account_id
            LEFT JOIN chart_of_accounts ca ON v.credit_account_id = ca.account_id
            WHERE v.status = 'posted'
            ORDER BY v.transaction_date DESC, v.id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($vouchers)) {
            $vouchers = $this->db->query("
                SELECT gl.transaction_date, gl.reference_type, gl.reference_id, gl.description, 
                       SUM(gl.debit_amount) as total_amount, COUNT(*) as line_count
                FROM general_ledger gl
                GROUP BY gl.transaction_date, gl.reference_type, gl.reference_id, gl.description
                ORDER BY gl.transaction_date DESC, gl.reference_id DESC
            ")->fetchAll(\PDO::FETCH_ASSOC);
        }

        // 2. Fetch Pending Approvals & Releases from `vouchers` table
        $pendingVouchersRaw = $this->db->query("
            SELECT v.id, v.voucher_number as number, v.voucher_type as type, 
                   v.transaction_date as date, v.description, v.amount, v.status,
                   da.account_code as debit_code, ca.account_code as credit_code
            FROM vouchers v
            LEFT JOIN chart_of_accounts da ON v.debit_account_id = da.account_id
            LEFT JOIN chart_of_accounts ca ON v.credit_account_id = ca.account_id
            WHERE v.status IN ('pending_approval', 'pending_release')
            ORDER BY v.id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $pendingVouchers = [];
        foreach ($pendingVouchersRaw as $pv) {
            $pendingVouchers[] = [
                'id' => $pv['id'],
                'date' => $pv['date'],
                'type' => $pv['type'],
                'number' => $pv['number'],
                'amount' => (float)$pv['amount'],
                'description' => $pv['description'],
                'status' => ($pv['status'] === 'pending_release') ? 'Pending Release' : 'Pending Approval'
            ];
        }

        // 3. Fetch Accounts for form selects
        $accounts = $this->db->query("
            SELECT account_id, account_code, account_name, account_type 
            FROM chart_of_accounts 
            WHERE status = 'active'
            ORDER BY account_code ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('finance/vouchers', [
            'pageTitle' => 'Vouchers & Approvals',
            'activeModule' => 'finance',
            'activeSubModule' => 'vouchers',
            'vouchers' => $vouchers,
            'pendingVouchers' => $pendingVouchers,
            'accounts' => $accounts
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $date = $_POST['transaction_date'] ?? date('Y-m-d');
        $voucherType = $_POST['voucher_type'] ?? 'Journal';
        $desc = $_POST['description'] ?? 'Voucher transaction';
        $status = $_POST['status'] ?? 'posted';
        
        $debitAccount = (int)($_POST['debit_account'] ?? 0);
        $creditAccount = (int)($_POST['credit_account'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0.00);

        if ($debitAccount <= 0 || $creditAccount <= 0 || $amount <= 0) {
            $_SESSION['error_message'] = 'Debit account, credit account, and positive amount are required';
            $this->redirect('finance/vouchers');
            return;
        }

        try {
            $this->db->beginTransaction();

            $prefix = match($voucherType) {
                'Payment' => 'PV',
                'Receipt' => 'RV',
                'Contra' => 'CV',
                'Debit Note' => 'DN',
                'Credit Note' => 'CN',
                default => 'JV'
            };
            $refNum = $prefix . '-' . date('Y') . '-' . rand(1000, 9999);
            $isPosted = ($status === 'posted') ? 1 : 0;

            // Insert into `vouchers` table
            $this->db->insert('vouchers', [
                'voucher_number' => $refNum,
                'voucher_type' => $voucherType,
                'transaction_date' => $date,
                'debit_account_id' => $debitAccount,
                'credit_account_id' => $creditAccount,
                'amount' => $amount,
                'description' => $desc,
                'status' => $status,
                'posted_to_gl' => $isPosted,
                'created_by' => $_SESSION['user_id'] ?? 1
            ]);

            // If posted immediately, insert Debit & Credit entries into `general_ledger`
            if ($isPosted) {
                $refId = (int)filter_var($refNum, FILTER_SANITIZE_NUMBER_INT);
                if ($refId <= 0) $refId = rand(1000, 9999);

                // Post Debit side
                $this->db->insert('general_ledger', [
                    'transaction_date' => $date,
                    'account_id' => $debitAccount,
                    'debit_amount' => $amount,
                    'credit_amount' => 0.00,
                    'description' => $desc,
                    'reference_type' => $voucherType,
                    'reference_id' => $refId,
                    'created_by' => $_SESSION['user_id'] ?? 1
                ]);

                // Post Credit side
                $this->db->insert('general_ledger', [
                    'transaction_date' => $date,
                    'account_id' => $creditAccount,
                    'debit_amount' => 0.00,
                    'credit_amount' => $amount,
                    'description' => $desc,
                    'reference_type' => $voucherType,
                    'reference_id' => $refId,
                    'created_by' => $_SESSION['user_id'] ?? 1
                ]);
            }

            $this->db->commit();
            $_SESSION['success_message'] = $voucherType . ' Voucher ' . $refNum . ' created successfully (' . ucfirst(str_replace('_', ' ', $status)) . ')';
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('finance/vouchers');
    }

    public function approve(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? 'approve';

        if ($id <= 0) {
            $_SESSION['error_message'] = 'Invalid voucher ID';
            $this->redirect('finance/vouchers');
            return;
        }

        try {
            $voucher = $this->db->query("SELECT * FROM vouchers WHERE id = {$id}")->fetch(\PDO::FETCH_ASSOC);

            if (!$voucher) {
                $_SESSION['error_message'] = 'Voucher record not found';
                $this->redirect('finance/vouchers');
                return;
            }

            if ($action === 'approve') {
                $this->db->beginTransaction();

                // Update voucher status to posted
                $this->db->query("UPDATE vouchers SET status = 'posted', posted_to_gl = 1, updated_at = NOW() WHERE id = {$id}");

                // Post entries to General Ledger if not posted yet
                if (!$voucher['posted_to_gl']) {
                    $refId = (int)filter_var($voucher['voucher_number'], FILTER_SANITIZE_NUMBER_INT);
                    if ($refId <= 0) $refId = $id;

                    // Debit Entry
                    $this->db->insert('general_ledger', [
                        'transaction_date' => $voucher['transaction_date'],
                        'account_id' => $voucher['debit_account_id'],
                        'debit_amount' => $voucher['amount'],
                        'credit_amount' => 0.00,
                        'description' => $voucher['description'],
                        'reference_type' => $voucher['voucher_type'],
                        'reference_id' => $refId,
                        'created_by' => $_SESSION['user_id'] ?? 1
                    ]);

                    // Credit Entry
                    $this->db->insert('general_ledger', [
                        'transaction_date' => $voucher['transaction_date'],
                        'account_id' => $voucher['credit_account_id'],
                        'debit_amount' => 0.00,
                        'credit_amount' => $voucher['amount'],
                        'description' => $voucher['description'],
                        'reference_type' => $voucher['voucher_type'],
                        'reference_id' => $refId,
                        'created_by' => $_SESSION['user_id'] ?? 1
                    ]);
                }

                $this->db->commit();
                $_SESSION['success_message'] = 'Voucher ' . $voucher['voucher_number'] . ' released and posted to General Ledger successfully!';
            } else {
                // Reject
                $this->db->query("UPDATE vouchers SET status = 'rejected', updated_at = NOW() WHERE id = {$id}");
                $_SESSION['success_message'] = 'Voucher ' . $voucher['voucher_number'] . ' rejected successfully.';
            }
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $_SESSION['error_message'] = 'Error processing approval: ' . $e->getMessage();
        }

        $this->redirect('finance/vouchers');
    }
}
