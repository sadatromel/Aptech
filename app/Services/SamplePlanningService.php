<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class SamplePlanningService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Get requisitions for target assignment and tracking.
     */
    public function getRequisitionsForPlanning(array $filters = []): array
    {
        $statusList = $filters['statuses'] ?? ['approved'];
        $statusPlaceholder = implode(',', array_fill(0, count($statusList), '?'));
        
        $conditions = ["s.status IN ({$statusPlaceholder})"];
        $params = $statusList;

        if (!empty($filters['style_id'])) {
            $conditions[] = "s.style_id = ?";
            $params[] = (int) $filters['style_id'];
        }

        $where = "WHERE " . implode(" AND ", $conditions);

        $sql = "
            SELECT 
                s.sample_id,
                s.requisition_no,
                s.style_id,
                s.sample_type,
                s.sample_size,
                s.size_breakdown,
                s.quantity,
                s.request_date,
                s.due_date,
                s.status,
                s.shell_fabric_available,
                s.pocketing_available,
                s.lining_interlining_available,
                s.sewing_accessories_available,
                s.finishing_accessories_available,
                st.style_code,
                st.style_name,
                b.buyer_id,
                b.buyer_name,
                (SELECT SUM(pattern_master_achievement) FROM sample_production_tracking WHERE sample_id = s.sample_id) as total_pat_achieved,
                (SELECT SUM(pattern_master_target) FROM sample_production_tracking WHERE sample_id = s.sample_id) as total_pat_target,
                (SELECT SUM(sample_man_achievement) FROM sample_production_tracking WHERE sample_id = s.sample_id) as total_sam_achieved,
                (SELECT SUM(sample_man_target) FROM sample_production_tracking WHERE sample_id = s.sample_id) as total_sam_target
            FROM sampling s
            LEFT JOIN styles st ON s.style_id = st.style_id
            LEFT JOIN buyers b ON st.buyer_id = b.buyer_id
            {$where}
            ORDER BY s.request_date ASC
        ";

        $stmt = $this->db->query($sql, $params);
        $requisitions = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(function($req) {
            $req['logs'] = $this->getRequisitionLogs((int)$req['sample_id']);
            $req['breakdown'] = json_decode($req['size_breakdown'] ?? '[]', true);
            return $req;
        }, $requisitions);
    }

    public function getRequisitionLogs(int $sampleId): array
    {
        $sql = "
            SELECT 
                t.*,
                pm.first_name as pm_first_name,
                sm.first_name as sm_first_name
            FROM sample_production_tracking t
            LEFT JOIN employees pm ON t.pattern_master_id = pm.employee_id
            LEFT JOIN employees sm ON t.sample_man_id = sm.employee_id
            WHERE t.sample_id = ?
            ORDER BY t.tracking_date DESC, t.id DESC
        ";
        $stmt = $this->db->query($sql, [$sampleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getStyles(): array
    {
        return $this->db->select('styles', ['style_id', 'style_code'], [
            'status[!]' => 'cancelled',
            'ORDER' => ['style_code' => 'ASC']
        ]);
    }

    public function getEmployees(): array
    {
        return $this->db->select('employees', [
            'employee_id',
            'first_name',
            'last_name',
            'employee_code'
        ], [
            'status' => 'active',
            'ORDER' => ['first_name' => 'ASC']
        ]);
    }
}
