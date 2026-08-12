<?php

namespace App\Services;

use Core\Database;
use PDO;

class PackingPlanService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function listPlans(string $offset = '0', string $limit = '20', string $search = ''): array
    {
        $query = "
            SELECT pp.*, po.po_number as order_number, s.style_code, s.style_name,
                   CONCAT(e.first_name, ' ', e.last_name) as supervisor_name,
                   COUNT(ps.id) as size_count,
                   CASE 
                       WHEN pp.target_quantity > 0 THEN ROUND((pp.actual_quantity / pp.target_quantity) * 100, 1)
                       ELSE 0 
                   END as completion_rate
            FROM packing_plans pp
            LEFT JOIN production_orders po ON pp.order_id = po.po_id
            LEFT JOIN styles s ON pp.style_id = s.style_id
            LEFT JOIN employees e ON pp.supervisor_id = e.employee_id
            LEFT JOIN packing_sizes ps ON pp.plan_id = ps.plan_id
        ";
        
        $params = [];
        if ($search) {
            $query .= " WHERE pp.plan_number LIKE ? OR po.po_number LIKE ? OR s.style_code LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $query .= " GROUP BY pp.plan_id ORDER BY pp.packing_date DESC LIMIT $limit OFFSET $offset";

        return $this->db->query($query, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlan(int $id): ?array
    {
        $plan = $this->db->query("
            SELECT pp.*, po.po_number as order_number, s.style_code, s.style_name,
                   CONCAT(e.first_name, ' ', e.last_name) as supervisor_name
            FROM packing_plans pp
            LEFT JOIN production_orders po ON pp.order_id = po.po_id
            LEFT JOIN styles s ON pp.style_id = s.style_id
            LEFT JOIN employees e ON pp.supervisor_id = e.employee_id
            WHERE pp.plan_id = ?
        ", [$id])->fetch(PDO::FETCH_ASSOC);

        if ($plan) {
            $plan['sizes'] = $this->db->query("SELECT * FROM packing_sizes WHERE plan_id = ?", [$id])->fetchAll(PDO::FETCH_ASSOC);
        }

        return $plan ?: null;
    }

    public function createPlan(array $data, array $sizes, int $userId): int
    {
        try {
            $this->db->beginTransaction();

            $this->db->query(
                "INSERT INTO packing_plans (plan_number, order_id, style_id, packing_date, target_quantity, packing_line, supervisor_id, notes, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['plan_number'],
                    $data['order_id'],
                    $data['style_id'],
                    $data['packing_date'],
                    $data['target_quantity'],
                    $data['packing_line'] ?? null,
                    $data['supervisor_id'] ?? null,
                    $data['notes'] ?? '',
                    $userId
                ]
            );

            $planId = (int)$this->db->lastInsertId();

            foreach ($sizes as $size) {
                if (!empty($size['size_name']) && isset($size['quantity'])) {
                    $this->db->query(
                        "INSERT INTO packing_sizes (plan_id, size_name, quantity) VALUES (?, ?, ?)",
                        [$planId, $size['size_name'], $size['quantity']]
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

    public function updateStatus(int $id, string $status, int $actualQuantity, ?string $completionDate): bool
    {
        return (bool)$this->db->query(
            "UPDATE packing_plans SET status=?, actual_quantity=?, completion_date=?, updated_at=NOW() WHERE plan_id=?",
            [$status, $actualQuantity, $completionDate, $id]
        );
    }

    public function deletePlan(int $id): bool
    {
        return (bool)$this->db->query("DELETE FROM packing_plans WHERE plan_id = ?", [$id]);
    }

    public function getStats(): array
    {
        return [
            'total_plans' => $this->db->query("SELECT COUNT(*) FROM packing_plans")->fetchColumn(),
            'active_plans' => $this->db->query("SELECT COUNT(*) FROM packing_plans WHERE status IN ('planned', 'in_progress')")->fetchColumn(),
            'completed_plans' => $this->db->query("SELECT COUNT(*) FROM packing_plans WHERE status = 'completed'")->fetchColumn(),
            'total_packed' => $this->db->query("SELECT SUM(actual_quantity) FROM packing_plans WHERE status = 'completed'")->fetchColumn() ?: 0
        ];
    }
}
