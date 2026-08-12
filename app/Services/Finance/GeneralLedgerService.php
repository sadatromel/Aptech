<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Core\Database;

class GeneralLedgerService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        $totalEntries = (int) ($this->db->count('general_ledger') ?? 0);
        
        $totalDebitsSql = "SELECT SUM(debit_amount) as total FROM general_ledger";
        $result = $this->db->query($totalDebitsSql);
        $totalDebits = (float) ($result->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
        
        $totalCreditsSql = "SELECT SUM(credit_amount) as total FROM general_ledger";
        $result = $this->db->query($totalCreditsSql);
        $totalCredits = (float) ($result->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
        
        $todayEntriesSql = "SELECT COUNT(*) as count FROM general_ledger WHERE transaction_date = CURDATE()";
        $result = $this->db->query($todayEntriesSql);
        $todayEntries = (int) ($result->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);

        return [
            'total_entries' => $totalEntries,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'balance_difference' => $totalDebits - $totalCredits,
            'today_entries' => $todayEntries,
        ];
    }

    public function listEntries(array $filters = []): array
    {
        $where = ['ORDER' => ['gl.transaction_date' => 'DESC', 'gl.ledger_id' => 'DESC']];

        if (!empty($filters['account_id'])) {
            $where['gl.account_id'] = (int) $filters['account_id'];
        }

        if (!empty($filters['cost_center_id'])) {
            $where['gl.cost_center_id'] = (int) $filters['cost_center_id'];
        }

        if (!empty($filters['transaction_type'])) {
            if ($filters['transaction_type'] === 'debit') {
                $where['gl.debit_amount[>]'] = 0;
            } elseif ($filters['transaction_type'] === 'credit') {
                $where['gl.credit_amount[>]'] = 0;
            }
        }

        if (!empty($filters['date_from'])) {
            $where['gl.transaction_date[>=]'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where['gl.transaction_date[<=]'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'gl.description[~]' => $term,
                'a.account_name[~]' => $term,
                'a.account_code[~]' => $term,
            ];
        }

        return $this->db->select('general_ledger gl', [
            '[>]chart_of_accounts a' => ['gl.account_id' => 'account_id'],
            '[>]cost_centers cc' => ['gl.cost_center_id' => 'cost_center_id'],
            '[>]users u' => ['gl.created_by' => 'user_id'],
        ], [
            'gl.*',
            'a.account_name',
            'a.account_code',
            'a.account_type',
            'cc.cost_center_name',
            'u.username AS created_by_name',
        ], $where) ?? [];
    }

    public function getEntry(int $ledgerId): ?array
    {
        return $this->db->get('general_ledger gl', [
            '[>]chart_of_accounts a' => ['gl.account_id' => 'account_id'],
            '[>]cost_centers cc' => ['gl.cost_center_id' => 'cost_center_id'],
            '[>]users u' => ['gl.created_by' => 'user_id'],
        ], [
            'gl.*',
            'a.account_name',
            'a.account_code',
            'a.account_type',
            'cc.cost_center_name',
            'u.username AS created_by_name',
        ], [
            'gl.ledger_id' => $ledgerId,
        ]);
    }

    public function createEntry(array $data, int $userId): int
    {
        $debitAmount = (float) ($data['debit_amount'] ?? 0);
        $creditAmount = (float) ($data['credit_amount'] ?? 0);

        if ($debitAmount > 0 && $creditAmount > 0) {
            throw new \RuntimeException('Entry cannot have both debit and credit amounts.');
        }

        if ($debitAmount == 0 && $creditAmount == 0) {
            throw new \RuntimeException('Entry must have either a debit or credit amount.');
        }

        $payload = [
            'transaction_date' => $data['transaction_date'],
            'account_id' => (int) $data['account_id'],
            'debit_amount' => $debitAmount,
            'credit_amount' => $creditAmount,
            'reference_type' => $data['reference_type'] ?? '',
            'reference_id' => !empty($data['reference_id']) ? (int) $data['reference_id'] : null,
            'description' => $data['description'] ?? '',
            'cost_center_id' => !empty($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int) $this->db->insert('general_ledger', $payload);
    }

    public function createJournalEntry(array $data, int $userId): bool
    {
        $this->db->beginTransaction();

        try {
            $entries = $data['entries'] ?? [];
            if (empty($entries)) {
                throw new \RuntimeException('Journal entry must have at least one line.');
            }

            $totalDebits = 0;
            $totalCredits = 0;

            foreach ($entries as $entry) {
                $debitAmount = (float) ($entry['debit_amount'] ?? 0);
                $creditAmount = (float) ($entry['credit_amount'] ?? 0);

                $totalDebits += $debitAmount;
                $totalCredits += $creditAmount;

                $this->db->insert('general_ledger', [
                    'transaction_date' => $data['transaction_date'],
                    'account_id' => (int) $entry['account_id'],
                    'debit_amount' => $debitAmount,
                    'credit_amount' => $creditAmount,
                    'reference_type' => $data['reference_type'] ?? 'journal',
                    'reference_id' => !empty($data['reference_id']) ? (int) $data['reference_id'] : null,
                    'description' => $data['description'] ?? '',
                    'cost_center_id' => !empty($entry['cost_center_id']) ? (int) $entry['cost_center_id'] : null,
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if (abs($totalDebits - $totalCredits) > 0.01) {
                throw new \RuntimeException('Journal entry is not balanced. Debits must equal credits.');
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteEntry(int $ledgerId): bool
    {
        return (bool) $this->db->delete('general_ledger', ['ledger_id' => $ledgerId]);
    }

    public function getTrialBalance(array|string $filters = []): array
    {
        if (is_string($filters)) {
            $filters = ['as_of_date' => $filters];
        }

        $params = [];
        $glJoinCondition = "gl.account_id = a.account_id";

        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? ($filters['as_of_date'] ?? date('Y-m-d'));

        if (!empty($dateFrom) && !empty($dateTo)) {
            $glJoinCondition .= " AND gl.transaction_date >= ? AND gl.transaction_date <= ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif (!empty($dateFrom)) {
            $glJoinCondition .= " AND gl.transaction_date >= ?";
            $params[] = $dateFrom;
        } elseif (!empty($dateTo)) {
            $glJoinCondition .= " AND gl.transaction_date <= ?";
            $params[] = $dateTo;
        } else {
            $glJoinCondition .= " AND gl.transaction_date <= ?";
            $params[] = date('Y-m-d');
        }

        $whereClause = "WHERE a.status = 'active'";

        if (!empty($filters['main_group'])) {
            $whereClause .= " AND (a.account_type = ? OR a.account_type LIKE ?)";
            $params[] = $filters['main_group'];
            $params[] = '%' . $filters['main_group'] . '%';
        }

        if (!empty($filters['account_code'])) {
            $whereClause .= " AND a.account_code LIKE ?";
            $params[] = '%' . $filters['account_code'] . '%';
        }

        if (!empty($filters['sub_group'])) {
            $whereClause .= " AND (a.account_name LIKE ? OR a.account_code LIKE ?)";
            $params[] = '%' . $filters['sub_group'] . '%';
            $params[] = '%' . $filters['sub_group'] . '%';
        }

        $openingParam = !empty($dateFrom) ? $dateFrom : '1970-01-01';
        $paramsWithOpening = array_merge([$openingParam], $params);

        $sql = "SELECT 
                    a.account_id,
                    a.account_code,
                    a.account_name,
                    a.account_type,
                    COALESCE((
                        SELECT SUM(gl_prev.debit_amount - gl_prev.credit_amount)
                        FROM general_ledger gl_prev
                        WHERE gl_prev.account_id = a.account_id
                          AND gl_prev.transaction_date < ?
                    ), 0) as opening_balance,
                    COALESCE(SUM(gl.debit_amount), 0) as total_debits,
                    COALESCE(SUM(gl.credit_amount), 0) as total_credits,
                    COALESCE(SUM(gl.debit_amount), 0) - COALESCE(SUM(gl.credit_amount), 0) as balance
                FROM chart_of_accounts a
                LEFT JOIN general_ledger gl ON {$glJoinCondition}
                {$whereClause}
                GROUP BY a.account_id, a.account_code, a.account_name, a.account_type";

        $balanceType = $filters['balance_type'] ?? 'without_zero';

        if ($balanceType === 'debit_only') {
            $sql .= " HAVING (total_debits - total_credits) > 0";
        } elseif ($balanceType === 'credit_only') {
            $sql .= " HAVING (total_credits - total_debits) > 0";
        } elseif ($balanceType === 'without_zero') {
            $sql .= " HAVING total_debits > 0 OR total_credits > 0 OR opening_balance != 0";
        }

        $sql .= " ORDER BY a.account_code ASC";
        
        $stmt = $this->db->query($sql, $paramsWithOpening);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDropdownData(): array
    {
        $accounts = $this->db->select('chart_of_accounts', [
            'account_id',
            'account_code',
            'account_name',
            'account_type',
        ], [
            'status' => 'active',
            'ORDER' => ['account_code' => 'ASC'],
        ]) ?? [];

        $costCenters = $this->db->select('cost_centers', [
            'cost_center_id',
            'cost_center_name',
            'cost_center_code',
        ], [
            'status' => 'active',
            'ORDER' => ['cost_center_name' => 'ASC'],
        ]) ?? [];

        $transactionTypes = [
            ['value' => 'debit', 'label' => 'Debit'],
            ['value' => 'credit', 'label' => 'Credit'],
        ];

        $referenceTypes = [
            ['value' => 'journal', 'label' => 'Journal Entry'],
            ['value' => 'invoice', 'label' => 'Invoice'],
            ['value' => 'payment', 'label' => 'Payment'],
            ['value' => 'receipt', 'label' => 'Receipt'],
            ['value' => 'adjustment', 'label' => 'Adjustment'],
        ];

        return [
            'accounts' => $accounts,
            'cost_centers' => $costCenters,
            'transaction_types' => $transactionTypes,
            'reference_types' => $referenceTypes,
        ];
    }

    public function getProfitAndLoss(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT 
                    a.account_type,
                    a.account_name,
                    a.account_code,
                    COALESCE(SUM(gl.debit_amount), 0) as total_debit,
                    COALESCE(SUM(gl.credit_amount), 0) as total_credit,
                    CASE 
                        WHEN a.account_type IN ('Income', 'Revenue') THEN COALESCE(SUM(gl.credit_amount - gl.debit_amount), 0)
                        ELSE COALESCE(SUM(gl.debit_amount - gl.credit_amount), 0)
                    END as net_amount
                FROM chart_of_accounts a
                LEFT JOIN general_ledger gl ON a.account_id = gl.account_id 
                    AND gl.transaction_date >= ? AND gl.transaction_date <= ?
                WHERE (a.account_type IN ('Income', 'Expense', 'Revenue') OR a.account_type LIKE '%Expense%' OR a.account_type LIKE '%Income%') AND a.status = 'active'
                GROUP BY a.account_id, a.account_type, a.account_name, a.account_code
                HAVING total_debit > 0 OR total_credit > 0
                ORDER BY a.account_type DESC, a.account_code ASC";
        
        $stmt = $this->db->query($sql, [$dateFrom, $dateTo]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getBalanceSheet(string $asOfDate): array
    {
        $sql = "SELECT 
                    a.account_type,
                    a.account_name,
                    a.account_code,
                    COALESCE(SUM(gl.debit_amount), 0) as total_debit,
                    COALESCE(SUM(gl.credit_amount), 0) as total_credit,
                    CASE 
                        WHEN a.account_type LIKE '%Asset%' THEN COALESCE(SUM(gl.debit_amount - gl.credit_amount), 0)
                        ELSE COALESCE(SUM(gl.credit_amount - gl.debit_amount), 0)
                    END as net_amount
                FROM chart_of_accounts a
                LEFT JOIN general_ledger gl ON a.account_id = gl.account_id 
                    AND gl.transaction_date <= ?
                WHERE (a.account_type LIKE '%Asset%' OR a.account_type LIKE '%Liabilit%' OR a.account_type = 'Equity') AND a.status = 'active'
                GROUP BY a.account_id, a.account_type, a.account_name, a.account_code
                HAVING total_debit > 0 OR total_credit > 0
                ORDER BY a.account_type ASC, a.account_code ASC";
        
        $stmt = $this->db->query($sql, [$asOfDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Standard Accounting: Calculate Current Period Net Income up to $asOfDate
        $netIncomeSql = "SELECT 
                            COALESCE(SUM(CASE WHEN a.account_type IN ('Income', 'Revenue') THEN gl.credit_amount - gl.debit_amount ELSE 0 END), 0) -
                            COALESCE(SUM(CASE WHEN a.account_type = 'Expense' OR a.account_type LIKE '%Expense%' THEN gl.debit_amount - gl.credit_amount ELSE 0 END), 0) as net_profit
                        FROM chart_of_accounts a
                        JOIN general_ledger gl ON a.account_id = gl.account_id AND gl.transaction_date <= ?";
        $stmtNet = $this->db->query($netIncomeSql, [$asOfDate]);
        $netProfit = (float)($stmtNet->fetchColumn() ?? 0);

        if ($netProfit != 0.0) {
            $rows[] = [
                'account_type' => 'Equity',
                'account_name' => 'Retained Earnings / Current Period Net Profit',
                'account_code' => '3999',
                'total_debit' => 0.00,
                'total_credit' => $netProfit,
                'net_amount' => $netProfit
            ];
        }

        return $rows;
    }

    public function getCashBook(string $dateFrom, string $dateTo, int $accountId = 0): array
    {
        $sql = "SELECT 
                    gl.transaction_date,
                    gl.description,
                    gl.reference_type,
                    gl.reference_id,
                    a.account_name,
                    gl.debit_amount,
                    gl.credit_amount
                FROM general_ledger gl
                JOIN chart_of_accounts a ON gl.account_id = a.account_id
                WHERE gl.transaction_date >= ? AND gl.transaction_date <= ?";
                
        $params = [$dateFrom, $dateTo];

        if ($accountId > 0) {
            $sql .= " AND gl.account_id = ?";
            $params[] = $accountId;
        } else {
            // Include all Cash or Bank accounts
            $sql .= " AND a.account_type IN ('Asset', 'Current Asset') AND (a.account_name LIKE '%Cash%' OR a.account_name LIKE '%Bank%')";
        }
        
        $sql .= " ORDER BY gl.transaction_date ASC, gl.ledger_id ASC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCashBankAccounts(): array
    {
        $sql = "SELECT account_id, account_name FROM chart_of_accounts 
                WHERE account_type IN ('Asset', 'Current Asset') 
                AND (account_name LIKE '%Cash%' OR account_name LIKE '%Bank%')
                AND status = 'active'
                ORDER BY account_name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
