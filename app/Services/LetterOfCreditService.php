<?php

declare(strict_types=1);

namespace App\Services;

use Core\Container;
use Core\Database;
use PDO;

class LetterOfCreditService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Container::get(Database::class);
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT lc.*, b.buyer_name
            FROM letters_of_credit lc
            LEFT JOIN buyers b ON lc.buyer_id = b.buyer_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND lc.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (lc.lc_number LIKE ? OR lc.opening_bank LIKE ? OR b.buyer_name LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY lc.lc_id DESC";

        return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummaryStats(): array
    {
        $totalLcSum = (float) $this->db->query("SELECT COALESCE(SUM(lc_amount), 0) FROM letters_of_credit WHERE status IN ('active', 'amendment')")->fetchColumn();
        $utilizedSum = (float) $this->db->query("SELECT COALESCE(SUM(utilized_amount), 0) FROM letters_of_credit WHERE status IN ('active', 'amendment')")->fetchColumn();
        $availableSum = max(0.0, $totalLcSum - $utilizedSum);
        $totalCount = (int) $this->db->query("SELECT COUNT(*) FROM letters_of_credit")->fetchColumn();

        return [
            'total_lc_sum'  => $totalLcSum,
            'utilized_sum'  => $utilizedSum,
            'available_sum' => $availableSum,
            'total_count'   => $totalCount,
        ];
    }

    public function create(array $data, int $userId): int
    {
        $this->db->query(
            "INSERT INTO letters_of_credit (lc_number, lc_type, buyer_id, opening_bank, advising_bank, lc_amount, utilized_amount, currency, issue_date, expiry_date, shipment_date, tenure_days, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['lc_number'],
                $data['lc_type'] ?? 'master_lc',
                $data['buyer_id'] ?: null,
                $data['opening_bank'] ?? '',
                $data['advising_bank'] ?? '',
                $data['lc_amount'] ?? 0.0,
                $data['utilized_amount'] ?? 0.0,
                $data['currency'] ?? 'USD',
                $data['issue_date'] ?: null,
                $data['expiry_date'] ?: null,
                $data['shipment_date'] ?: null,
                $data['tenure_days'] ?? 60,
                $data['status'] ?? 'active',
                $data['notes'] ?? '',
                $userId
            ]
        );

        return (int)$this->db->lastInsertId();
    }
}
