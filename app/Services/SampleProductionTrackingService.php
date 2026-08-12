<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class SampleProductionTrackingService
{
    public function __construct(private Database $db)
    {
    }

    public function getRecords(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['buyer_id'])) {
            $conditions[] = 't.buyer_id = ?';
            $params[] = (int) $filters['buyer_id'];
        }

        if (!empty($filters['style_id'])) {
            $conditions[] = 't.style_id = ?';
            $params[] = (int) $filters['style_id'];
        }

        if (!empty($filters['sample_id'])) {
            $conditions[] = 't.sample_id = ?';
            $params[] = (int) $filters['sample_id'];
        }

        if (!empty($filters['from_date'])) {
            $conditions[] = 't.tracking_date >= ?';
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $conditions[] = 't.tracking_date <= ?';
            $params[] = $filters['to_date'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT 
                t.id,
                t.tracking_date,
                t.buyer_id,
                t.style_id,
                t.sample_id,
                t.target_qty,
                t.actual_qty,
                t.created_at,
                t.updated_at,
                b.buyer_name,
                st.style_code,
                st.style_name,
                s.requisition_no,
                s.sample_type,
                s.shell_fabric_available,
                s.pocketing_available,
                s.lining_interlining_available,
                s.sewing_accessories_available,
                s.finishing_accessories_available
            FROM sample_production_tracking t
            LEFT JOIN buyers b ON t.buyer_id = b.buyer_id
            LEFT JOIN styles st ON t.style_id = st.style_id
            LEFT JOIN sampling s ON t.sample_id = s.sample_id
            {$where}
            ORDER BY t.tracking_date DESC, t.id DESC
        ";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getDropdownData(): array
    {
        $buyers = $this->db->select('buyers', [
            'buyer_id',
            'buyer_name',
        ], [
            'status' => 'active',
            'ORDER' => ['buyer_name' => 'ASC']
        ]);

        $styles = $this->db->select('styles', [
            'style_id',
            'style_code',
            'style_name',
            'buyer_id',
        ], [
            'status[!]' => 'cancelled',
            'ORDER' => ['style_code' => 'ASC']
        ]);

        return [
            'buyers' => $buyers,
            'styles'  => $styles,
        ];
    }

    /**
     * Get requisitions (sampling records) for a given buyer and/or style.
     */
    public function getRequisitions(int $buyerId = 0, int $styleId = 0): array
    {
        $conditions = [];
        $params = [];

        if ($styleId > 0) {
            $conditions[] = 's.style_id = ?';
            $params[] = $styleId;
        } elseif ($buyerId > 0) {
            // Filter by styles belonging to the buyer
            $conditions[] = 'st.buyer_id = ?';
            $params[] = $buyerId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT
                s.sample_id,
                s.requisition_no,
                s.sample_type,
                s.quantity,
                s.status,
                st.style_code,
                st.style_name
            FROM sampling s
            LEFT JOIN styles st ON s.style_id = st.style_id
            {$where}
            ORDER BY s.requisition_no DESC
        ";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function storeRecord(array $data): int
    {
        $payload = [
            'tracking_date' => $data['tracking_date'],
            'buyer_id'      => !empty($data['buyer_id'])  ? (int) $data['buyer_id']  : null,
            'style_id'      => !empty($data['style_id'])  ? (int) $data['style_id']  : null,
            'sample_id'     => !empty($data['sample_id']) ? (int) $data['sample_id'] : null,
            'target_qty'    => (int) ($data['target_qty']  ?? 0),
            'actual_qty'    => (int) ($data['actual_qty']  ?? 0),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('sample_production_tracking', $payload);
    }

    public function updateRecord(int $id, array $data): bool
    {
        $payload = [
            'tracking_date' => $data['tracking_date'],
            'buyer_id'      => !empty($data['buyer_id'])  ? (int) $data['buyer_id']  : null,
            'style_id'      => !empty($data['style_id'])  ? (int) $data['style_id']  : null,
            'sample_id'     => !empty($data['sample_id']) ? (int) $data['sample_id'] : null,
            'target_qty'    => (int) ($data['target_qty']  ?? 0),
            'actual_qty'    => (int) ($data['actual_qty']  ?? 0),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        return (bool) $this->db->update('sample_production_tracking', $payload, ['id' => $id]);
    }

    public function deleteRecord(int $id): bool
    {
        return (bool) $this->db->delete('sample_production_tracking', ['id' => $id]);
    }

    public function getStats(): array
    {
        $sql = "
            SELECT 
                SUM(target_qty) as total_target,
                SUM(actual_qty) as total_actual
            FROM sample_production_tracking
            WHERE tracking_date = CURRENT_DATE()
        ";
        $stmt = $this->db->query($sql);
        $res  = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'today_target' => (int) ($res['total_target'] ?? 0),
            'today_actual' => (int) ($res['total_actual'] ?? 0),
        ];
    }
}
