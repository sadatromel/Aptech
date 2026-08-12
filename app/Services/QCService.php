<?php

namespace App\Services;

use Core\Database;

class QCService
{
    public function __construct(private Database $db)
    {
    }

    public function listInspections(array $filters = []): array
    {
        $sql = "
            SELECT qi.*, po.order_number, s.style_code, s.style_name, pl.line_name, pl.line_code,
                   CONCAT(e.first_name, ' ', e.last_name) as inspector_name,
                   ROUND((qi.passed_quantity / qi.total_inspected) * 100, 1) as pass_rate,
                   ROUND((qi.failed_quantity / qi.total_inspected) * 100, 1) as failure_rate,
                   (SELECT COUNT(*) FROM qc_defects qd WHERE qd.inspection_id = qi.inspection_id) as defect_count
            FROM qc_inspections qi
            LEFT JOIN production_orders po ON qi.order_id = po.order_id
            LEFT JOIN styles s ON qi.style_id = s.style_id
            LEFT JOIN production_lines pl ON qi.line_id = pl.line_id
            LEFT JOIN employees e ON qi.inspector_id = e.employee_id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (qi.inspection_number LIKE ? OR s.style_code LIKE ? OR po.order_number LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY qi.inspection_date DESC";

        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getInspection(int $id)
    {
        $sql = "SELECT qi.*, po.order_number, s.style_code, s.style_name, pl.line_name, pl.line_code,
                       CONCAT(e.first_name, ' ', e.last_name) as inspector_name
                FROM qc_inspections qi
                LEFT JOIN production_orders po ON qi.order_id = po.order_id
                LEFT JOIN styles s ON qi.style_id = s.style_id
                LEFT JOIN production_lines pl ON qi.line_id = pl.line_id
                LEFT JOIN employees e ON qi.inspector_id = e.employee_id
                WHERE qi.inspection_id = ?";
        
        $inspection = $this->db->query($sql, [$id])->fetch();
        
        if ($inspection) {
            $inspection['defects'] = $this->db->query("SELECT * FROM qc_defects WHERE inspection_id = ?", [$id])->fetchAll();
        }

        return $inspection;
    }

    public function createInspection(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $payload = [
                'inspection_number' => $data['inspection_number'],
                'order_id' => (int)$data['order_id'],
                'style_id' => (int)$data['style_id'],
                'line_id' => (int)$data['line_id'],
                'inspection_date' => $data['inspection_date'],
                'inspection_type' => $data['inspection_type'],
                'inspector_id' => (int)$data['inspector_id'],
                'total_inspected' => (int)$data['total_inspected'],
                'passed_quantity' => (int)$data['passed_quantity'],
                'failed_quantity' => (int)$data['failed_quantity'],
                'rework_quantity' => (int)$data['rework_quantity'],
                'remarks' => $data['remarks'] ?? '',
                'created_by' => $_SESSION['user_id'] ?? 1
            ];

            $this->db->insert('qc_inspections', $payload);
            $inspectionId = (int)$this->db->lastInsertId();

            if (!empty($data['defects']) && is_array($data['defects'])) {
                foreach ($data['defects'] as $defect) {
                    if (empty($defect['defect_type'])) continue;
                    
                    $this->db->insert('qc_defects', [
                        'inspection_id' => $inspectionId,
                        'defect_type' => trim($defect['defect_type']),
                        'defect_quantity' => (int)$defect['defect_quantity'],
                        'severity' => trim($defect['severity'])
                    ]);
                }
            }

            $this->db->commit();
            return $inspectionId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteInspection(int $id): bool
    {
        $this->db->delete('qc_defects', ['inspection_id' => $id]);
        return (bool)$this->db->delete('qc_inspections', ['inspection_id' => $id]);
    }

    public function getStats(): array
    {
        return [
            'total_inspections' => (int)$this->db->count('qc_inspections'),
            'avg_pass_rate' => (float)$this->db->query("SELECT AVG((passed_quantity / total_inspected) * 100) FROM qc_inspections WHERE total_inspected > 0")->fetchColumn() ?: 0,
            'today_inspections' => (int)$this->db->query("SELECT COUNT(*) as count FROM qc_inspections WHERE inspection_date = CURDATE()")->fetchColumn(),
            'critical_defects' => (int)$this->db->count('qc_defects', ['severity' => 'Critical'])
        ];
    }
}
