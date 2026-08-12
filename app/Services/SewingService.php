<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class SewingService
{
    public function __construct(private Database $db)
    {
    }

    public function getSewingLines(): array
    {
        return $this->db->select('production_lines pl', [
            '[>]users u' => ['pl.manager_id' => 'user_id']
        ], [
            'pl.production_line_id',
            'pl.line_name',
            'pl.line_code',
            'pl.line_type',
            'pl.capacity_per_day',
            'pl.manager_id',
            "CONCAT(u.first_name, ' ', u.last_name) as supervisor_name",
            'pl.status'
        ], [
            'pl.status' => 'active',
            'ORDER' => ['pl.line_name' => 'ASC']
        ]);
    }

    public function getLine(int $id)
    {
        return $this->db->get('production_lines', '*', ['production_line_id' => $id]);
    }

    public function createLine(array $data): int
    {
        $payload = [
            'line_name' => $data['line_name'],
            'line_code' => $data['line_code'],
            'line_type' => $data['line_type'],
            'capacity_per_day' => (int)$data['capacity_per_day'],
            'manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
            'status' => 'active'
        ];
        
        $this->db->insert('production_lines', $payload);
        return (int)$this->db->lastInsertId();
    }

    public function updateLine(int $id, array $data): bool
    {
        $payload = [
            'line_name' => $data['line_name'],
            'line_code' => $data['line_code'],
            'line_type' => $data['line_type'],
            'capacity_per_day' => (int)$data['capacity_per_day'],
            'manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return (bool)$this->db->update('production_lines', $payload, ['production_line_id' => $id]);
    }

    public function deleteLine(int $id): bool
    {
        // specific requirements: soft delete or hard delete? Default soft delete usually safer but let's do soft
        // Legacy system often used "inactive" status
        return (bool)$this->db->update('production_lines', ['status' => 'inactive'], ['production_line_id' => $id]);
    }

    public function getSewingPlans(array $filters = []): array
    {
        $where = [];
        
        if (!empty($filters['line_id'])) {
            $where['pp.line_id'] = $filters['line_id'];
        }
        
        if (!empty($filters['status'])) {
            $where['pp.status'] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where['pp.planned_date[>=]'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where['pp.planned_date[<=]'] = $filters['date_to'];
        }

        $plans = $this->db->select('production_plans pp', [
            '[>]production_orders po' => ['pp.order_id' => 'po_id'],
            '[>]styles s' => ['po.style_id' => 'style_id'],
            '[>]production_lines pl' => ['pp.line_id' => 'production_line_id']
        ], [
            'pp.id',
            'pp.plan_number',
            'pp.order_id',
            'pp.line_id',
            'pp.planned_date',
            'pp.quantity',
            'pp.completed_quantity',
            'pp.status',
            'pp.remarks',
            'po.order_number',
            's.style_number',
            's.style_name',
            'pl.line_name'
        ], [
            'WHERE' => $where,
            'ORDER' => ['pp.planned_date' => 'DESC', 'pp.plan_number' => 'DESC']
        ]);

        return $plans;
    }

    public function createSewingPlan(array $data): int
    {
        $planData = [
            'plan_number' => $this->generatePlanNumber('SEW'),
            'order_id' => $data['order_id'],
            'line_id' => $data['line_id'],
            'planned_date' => $data['planned_date'],
            'quantity' => $data['quantity'],
            'completed_quantity' => 0,
            'status' => 'planned',
            'remarks' => $data['remarks'] ?? '',
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert('production_plans', $planData);
    }

    public function updateSewingProgress(int $planId, int $completedQuantity, array $data = []): bool
    {
        $plan = $this->db->get('production_plans', '*', ['id' => $planId]);
        if (!$plan) {
            return false;
        }

        $status = 'in_progress';
        if ($completedQuantity >= $plan['quantity']) {
            $status = 'completed';
            $completedQuantity = $plan['quantity'];
        }

        $updateData = [
            'completed_quantity' => $completedQuantity,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if (!empty($data['remarks'])) {
            $updateData['remarks'] = $data['remarks'];
        }

        if (!empty($data['defects'])) {
            $updateData['remarks'] = ($updateData['remarks'] ?? '') . ' | Defects: ' . $data['defects'];
        }

        return $this->db->update('production_plans', $updateData, ['id' => $planId]) > 0;
    }

    public function getSewingPerformance(int $lineId, string $startDate, string $endDate): array
    {
        $plans = $this->db->select('production_plans', [
            'planned_date',
            'quantity',
            'completed_quantity',
            'status'
        ], [
            'line_id' => $lineId,
            'planned_date[>=]' => $startDate,
            'planned_date[<=]' => $endDate,
            'ORDER' => ['planned_date' => 'ASC']
        ]);

        $performance = [];
        $totalPlanned = 0;
        $totalCompleted = 0;

        foreach ($plans as $plan) {
            $efficiency = $plan['quantity'] > 0 ? ($plan['completed_quantity'] / $plan['quantity']) * 100 : 0;
            
            $performance[] = [
                'date' => $plan['planned_date'],
                'planned' => $plan['quantity'],
                'completed' => $plan['completed_quantity'],
                'efficiency' => round($efficiency, 2),
                'status' => $plan['status']
            ];

            $totalPlanned += $plan['quantity'];
            $totalCompleted += $plan['completed_quantity'];
        }

        $overallEfficiency = $totalPlanned > 0 ? ($totalCompleted / $totalPlanned) * 100 : 0;

        return [
            'daily_performance' => $performance,
            'summary' => [
                'total_planned' => $totalPlanned,
                'total_completed' => $totalCompleted,
                'overall_efficiency' => round($overallEfficiency, 2),
                'total_days' => count($performance)
            ]
        ];
    }

    public function getLineEfficiency(int $lineId, int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-$days days"));
        $endDate = date('Y-m-d');

        return $this->getSewingPerformance($lineId, $startDate, $endDate);
    }

    public function getActiveSewingPlans(int $lineId = null): array
    {
        $where = [
            'status[!]' => ['completed', 'cancelled']
        ];

        if ($lineId) {
            $where['line_id'] = $lineId;
        }

        return $this->db->select('production_plans pp', [
            '[>]production_orders po' => ['pp.order_id' => 'id'],
            '[>]styles s' => ['po.style_id' => 'id'],
            '[>]production_lines pl' => ['pp.line_id' => 'id']
        ], [
            'pp.id',
            'pp.plan_number',
            'pp.order_id',
            'pp.line_id',
            'pp.planned_date',
            'pp.quantity',
            'pp.completed_quantity',
            'pp.status',
            'po.order_number',
            's.style_number',
            's.style_name',
            'pl.name as line_name'
        ], [
            'WHERE' => $where,
            'ORDER' => ['pp.planned_date' => 'ASC']
        ]);
    }

    public function getStats(): array
    {
        $totalPlans = (int) ($this->db->count('production_plans') ?? 0);
        $activePlans = (int) ($this->db->count('production_plans', ['status' => 'in_progress']) ?? 0);
        $completedPlans = (int) ($this->db->count('production_plans', ['status' => 'completed']) ?? 0);
        $totalLines = (int) ($this->db->count('production_lines', ['status' => 'active']) ?? 0);

        // Calculate average efficiency for completed plans in last 30 days
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $completedPlansData = $this->db->select('production_plans', [
            'quantity',
            'completed_quantity'
        ], [
            'status' => 'completed',
            'updated_at[>=]' => $startDate
        ]);

        $totalPlanned = array_sum(array_column($completedPlansData, 'quantity'));
        $totalCompleted = array_sum(array_column($completedPlansData, 'completed_quantity'));
        $avgEfficiency = $totalPlanned > 0 ? ($totalCompleted / $totalPlanned) * 100 : 0;

        return [
            'total_plans' => $totalPlans,
            'active_plans' => $activePlans,
            'completed_plans' => $completedPlans,
            'total_lines' => $totalLines,
            'avg_efficiency' => round($avgEfficiency, 2)
        ];
    }

    public function getDropdownData(): array
    {
        $orders = $this->db->select('production_orders po', [
            '[>]styles s' => ['po.style_id' => 'id']
        ], [
            'po.id as order_id',
            'po.order_number',
            's.style_number',
            's.style_name'
        ], [
            'po.status[!]' => 'cancelled',
            'ORDER' => ['po.order_number' => 'DESC']
        ]);

        $lines = $this->db->select('production_lines', [
            'production_line_id as line_id',
            'line_name',
            'capacity_per_day'
        ], [
            'status' => 'active',
            'ORDER' => ['line_name' => 'ASC']
        ]);

        $supervisors = $this->getSupervisors();

        return [
            'orders' => $orders,
            'lines' => $lines,
            'supervisors' => $supervisors
        ];
    }
    
    // Helper to get supervisors
    public function getSupervisors(): array
    {
         return $this->db->select('users', [
             'user_id',
             "CONCAT(first_name, ' ', last_name) as full_name",
             'username'
         ], [
             'role_id' => [3, 4, 5], // Assuming Production related roles, or check by department? using simpler role id assumption for now or fetch all users
             'status' => 'active'
         ]);
    }

    private function generatePlanNumber(string $prefix = 'SEW'): string
    {
        $date = date('Ymd');
        
        $lastPlan = $this->db->get('production_plans', 'plan_number', [
            'plan_number[~]' => $prefix . $date,
            'ORDER' => ['plan_number' => 'DESC']
        ]);

        if ($lastPlan) {
            $sequence = (int) substr($lastPlan['plan_number'], -4) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . $date . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }
}
