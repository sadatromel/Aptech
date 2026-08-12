<?php

namespace App\Services;

use App\Models\CuttingPlan;
use Core\Database;

class CuttingPlanService
{
    protected CuttingPlan $model;
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->model = new CuttingPlan();
        $this->db = $db;
    }

    public function listPlans(array $filters = []): array
    {
        $sql = "
            SELECT cp.*, po.po_number as order_number, s.style_code, s.style_name, cr.cutting_room_name,
                   CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                   COUNT(cps.plan_size_id) as size_count,
                   SUM(cps.size_quantity) as total_planned
            FROM cutting_plans cp
            LEFT JOIN production_orders po ON cp.order_id = po.po_id
            LEFT JOIN styles s ON cp.style_id = s.style_id
            LEFT JOIN cutting_rooms cr ON cp.cutting_room_id = cr.cutting_room_id
            LEFT JOIN users u ON cp.created_by = u.user_id
            LEFT JOIN cutting_plan_sizes cps ON cp.plan_id = cps.plan_id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (cp.plan_number LIKE ? OR s.style_code LIKE ? OR s.style_name LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " GROUP BY cp.plan_id ORDER BY cp.planned_date ASC";

        return $this->db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function createPlan(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $planData = [
                'plan_number' => $data['plan_number'],
                'order_id' => $data['order_id'],
                'style_id' => $data['style_id'],
                'total_quantity' => $data['total_quantity'],
                'planned_date' => $data['planned_date'],
                'cutting_room_id' => $data['cutting_room_id'],
                'marker_efficiency' => $data['marker_efficiency'] ?? 0,
                'spreading_method' => $data['spreading_method'] ?? '',
                'notes' => $data['notes'] ?? '',
                'created_by' => $_SESSION['user_id'] ?? 1
            ];

            $this->db->query(
                "INSERT INTO cutting_plans (plan_number, order_id, style_id, total_quantity, planned_date, cutting_room_id, marker_efficiency, spreading_method, notes, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array_values($planData)
            );
            
            $planId = (int)$this->db->lastInsertId();

            if (!empty($data['sizes'])) {
                foreach ($data['sizes'] as $size) {
                    $this->db->query(
                        "INSERT INTO cutting_plan_sizes (plan_id, size_name, size_quantity, layers) VALUES (?, ?, ?, ?)",
                        [$planId, $size['size_name'], $size['size_quantity'], $size['layers']]
                    );
                }
            }

            $this->db->commit();
            return $planId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status, int $completedQty, ?string $date = null): void
    {
        $this->db->query(
            "UPDATE cutting_plans SET status=?, completed_quantity=?, actual_completion_date=?, updated_at=NOW() WHERE plan_id=?",
            [$status, $completedQty, $date, $id]
        );
    }

    public function deletePlan(int $id): void
    {
        $this->db->query("DELETE FROM cutting_plans WHERE plan_id=?", [$id]);
    }

    public function getPlan(int $id): ?array
    {
        return $this->db->query("SELECT * FROM cutting_plans WHERE plan_id = ?", [$id])->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function getStats(): array
    {
        return [
            'total_plans' => $this->db->query("SELECT COUNT(*) FROM cutting_plans")->fetchColumn(),
            'active_plans' => $this->db->query("SELECT COUNT(*) FROM cutting_plans WHERE status IN ('planned', 'approved', 'in_progress')")->fetchColumn(),
            'completed_plans' => $this->db->query("SELECT COUNT(*) FROM cutting_plans WHERE status = 'completed'")->fetchColumn(),
            'total_cut_quantity' => $this->db->query("SELECT SUM(completed_quantity) FROM cutting_plans WHERE status = 'completed'")->fetchColumn() ?: 0,
        ];
    }
}
