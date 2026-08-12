<?php

namespace App\Services;

use Core\Database;
use PDO;

class WashBatchService
{
    public function __construct(private Database $db)
    {
    }

    public function listBatches(array $filters = []): array
    {
        $sql = "
            SELECT wb.*, po.po_number as order_number, s.style_code, s.style_name,
                   CONCAT(e.first_name, ' ', e.last_name) as supervisor_name,
                   COUNT(wc.chemical_id) as chemical_count,
                   CASE 
                       WHEN wb.total_quantity > 0 THEN ROUND((wb.completed_quantity / wb.total_quantity) * 100, 1)
                       ELSE 0 
                   END as completion_rate
            FROM wash_batches wb
            LEFT JOIN production_orders po ON wb.order_id = po.po_id
            LEFT JOIN styles s ON wb.style_id = s.style_id
            LEFT JOIN employees e ON wb.supervisor_id = e.employee_id
            LEFT JOIN wash_chemicals wc ON wb.batch_id = wc.batch_id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (wb.batch_number LIKE ? OR po.po_number LIKE ? OR s.style_code LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " GROUP BY wb.batch_id ORDER BY wb.wash_date DESC";

        return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBatch(int $id): ?array
    {
        $batch = $this->db->query("
            SELECT wb.*, po.po_number as order_number, s.style_code, s.style_name,
                   CONCAT(e.first_name, ' ', e.last_name) as supervisor_name
            FROM wash_batches wb
            LEFT JOIN production_orders po ON wb.order_id = po.po_id
            LEFT JOIN styles s ON wb.style_id = s.style_id
            LEFT JOIN employees e ON wb.supervisor_id = e.employee_id
            WHERE wb.batch_id = ?
        ", [$id])->fetch(PDO::FETCH_ASSOC);

        if ($batch) {
            $batch['chemicals'] = $this->db->query("SELECT * FROM wash_chemicals WHERE batch_id = ?", [$id])->fetchAll(PDO::FETCH_ASSOC);
        }

        return $batch ?: null;
    }

    public function createBatch(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $payload = [
                'batch_number' => $data['batch_number'],
                'order_id' => (int)$data['order_id'],
                'style_id' => (int)$data['style_id'],
                'wash_type' => $data['wash_type'],
                'total_quantity' => (int)$data['total_quantity'],
                'wash_date' => $data['wash_date'],
                'washing_line' => $data['washing_line'] ?? '',
                'supervisor_id' => !empty($data['supervisor_id']) ? (int)$data['supervisor_id'] : null,
                'notes' => $data['notes'] ?? '',
                'created_by' => $_SESSION['user_id'] ?? 1,
                'status' => 'pending'
            ];

            $this->db->insert('wash_batches', $payload);
            $batchId = (int)$this->db->lastInsertId();

            if (!empty($data['chemicals']) && is_array($data['chemicals'])) {
                foreach ($data['chemicals'] as $chemical) {
                    if (empty($chemical['chemical_name'])) continue;
                    $this->db->insert('wash_chemicals', [
                        'batch_id' => $batchId,
                        'chemical_name' => $chemical['chemical_name'],
                        'quantity' => (float)$chemical['quantity'],
                        'unit' => $chemical['unit']
                    ]);
                }
            }

            $this->db->commit();
            return $batchId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status, int $completedQty, int $rejectionQty, ?string $date): bool
    {
        return $this->db->update('wash_batches', [
            'status' => $status,
            'completed_quantity' => $completedQty,
            'rejection_quantity' => $rejectionQty,
            'actual_completion_date' => $date,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['batch_id' => $id]);
    }

    public function deleteBatch(int $id): bool
    {
        $this->db->delete('wash_chemicals', ['batch_id' => $id]);
        return (bool)$this->db->delete('wash_batches', ['batch_id' => $id]);
    }

    public function getStats(): array
    {
        return [
            'total_batches' => (int)$this->db->count('wash_batches'),
            'active_batches' => (int)$this->db->count('wash_batches', ['status' => ['pending', 'in_progress']]),
            'completed_batches' => (int)$this->db->count('wash_batches', ['status' => 'completed']),
            'total_washed' => (int)$this->db->sum('wash_batches', 'completed_quantity', ['status' => 'completed'])
        ];
    }
}
