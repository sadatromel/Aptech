<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class PlanningService
{
    public function __construct(private Database $db)
    {
    }

    public function getPlans(array $filters = []): array
    {
        $where = [];
        
        if (!empty($filters['status'])) {
            $where['pp.status'] = $filters['status'];
        }
        
        if (!empty($filters['line_id'])) {
            $where['pp.line_id'] = $filters['line_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where['pp.planned_date[>=]'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where['pp.planned_date[<=]'] = $filters['date_to'];
        }

        $plans = $this->db->select('production_plans pp', [
            '[>]styles s' => ['pp.style_id' => 'style_id'],
            '[>]production_lines pl' => ['pp.line_id' => 'production_line_id']
        ], [
            'pp.id',
            'pp.plan_number',
            'pp.style_id',
            'pp.line_id',
            'pp.planned_date',
            'pp.quantity',
            'pp.completed_quantity',
            'pp.status',
            'pp.remarks',
            's.style_code', // Instead of order_number
            's.style_name',
            'pl.line_name'
        ], [
            'WHERE' => $where,
            'ORDER' => ['pp.planned_date' => 'DESC', 'pp.plan_number' => 'DESC']
        ]);

        return $plans;
    }

    public function createPlan(array $data): int
    {
        $planData = [
            'plan_number' => $this->generatePlanNumber(),
            'style_id' => $data['style_id'],
            'line_id' => $data['line_id'],
            'planned_date' => $data['planned_date'],
            'quantity' => $data['quantity'],
            'completed_quantity' => 0,
            'status' => 'planned',
            'remarks' => $data['remarks'] ?? '',
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert('production_plans', $planData)->rowCount();
    }

    public function updatePlan(int $planId, array $data): bool
    {
        $updateData = [];
        
        if (isset($data['line_id'])) {
            $updateData['line_id'] = $data['line_id'];
        }
        
        if (isset($data['planned_date'])) {
            $updateData['planned_date'] = $data['planned_date'];
        }
        
        if (isset($data['quantity'])) {
            $updateData['quantity'] = $data['quantity'];
        }
        
        if (isset($data['completed_quantity'])) {
            $updateData['completed_quantity'] = $data['completed_quantity'];
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        
        if (isset($data['remarks'])) {
            $updateData['remarks'] = $data['remarks'];
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update('production_plans', $updateData, ['id' => $planId])->rowCount() > 0;
    }

    public function updateProgress(int $planId, int $completedQuantity): bool
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

        return $this->db->update('production_plans', [
            'completed_quantity' => $completedQuantity,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $planId])->rowCount() > 0;
    }

    public function deletePlan(int $planId): bool
    {
        return $this->db->delete('production_plans', ['id' => $planId])->rowCount() > 0;
    }

    public function getPlan(int $planId): ?array
    {
        return $this->db->get('production_plans pp', [
            '[>]styles s' => ['pp.style_id' => 'style_id'],
            '[>]production_lines pl' => ['pp.line_id' => 'production_line_id']
        ], [
            'pp.*',
            's.style_code',
            's.style_name',
            'pl.line_name'
        ], [
            'pp.id' => $planId
        ]);
    }

    public function getDropdownData(): array
    {
        // Fetch styles instead of orders
        $styles = $this->db->select('styles', [
            'style_id',
            'style_code',
            'style_name'
        ], [
            'status[!]' => 'cancelled',
            'ORDER' => ['style_code' => 'DESC']
        ]);

        $lines = $this->db->select('production_lines', [
            'line_id' => 'production_line_id',
            'name' => 'line_name',
            'daily_capacity' => 'capacity_per_day'
        ], [
            'status' => 'active',
            'ORDER' => ['line_name' => 'ASC']
        ]);

        return [
            'styles' => $styles,
            'lines' => $lines
        ];
    }

    public function getStats(): array
    {
        $total = (int) ($this->db->count('production_plans') ?? 0);
        $planned = (int) ($this->db->count('production_plans', ['status' => 'planned']) ?? 0);
        $inProgress = (int) ($this->db->count('production_plans', ['status' => 'in_progress']) ?? 0);
        $completed = (int) ($this->db->count('production_plans', ['status' => 'completed']) ?? 0);

        return [
            'total' => $total,
            'planned' => $planned,
            'in_progress' => $inProgress,
            'completed' => $completed,
        ];
    }

    public function getCapacityReport(string $date): array
    {
        // Using 'production_line_id' from schema 440
        $lines = $this->db->select('production_lines', '*', ['status' => 'active']);
        
        $report = [];
        foreach ($lines as $line) {
            // line uses 'production_line_id' as PK
            $plannedQty = $this->db->sum('production_plans', 'quantity', [
                'line_id' => $line['production_line_id'],
                'planned_date' => $date,
                'status[!]' => 'completed'
            ]);

            $completedQty = $this->db->sum('production_plans', 'completed_quantity', [
                'line_id' => $line['production_line_id'],
                'planned_date' => $date
            ]);

            // line table field 'capacity_per_day' (line 445 in schema) vs 'daily_capacity' in old code. 
            // I should verify column names of production_lines if possible. 
            // Old code used 'daily_capacity'. Schema says 'capacity_per_day'.
            // I'll assume Schema is correct: 'capacity_per_day'.
            $capacity = $line['capacity_per_day'] ?? 0;

            $utilization = $capacity > 0 ? ($plannedQty / $capacity) * 100 : 0;
            $efficiency = $plannedQty > 0 ? ($completedQty / $plannedQty) * 100 : 0;

            $report[] = [
                'line_id' => $line['production_line_id'],
                'line_name' => $line['line_name'],
                'daily_capacity' => $capacity,
                'planned_quantity' => $plannedQty,
                'completed_quantity' => $completedQty,
                'utilization' => round($utilization, 2),
                'efficiency' => round($efficiency, 2)
            ];
        }

        return $report;
    }

    private function generatePlanNumber(): string
    {
        $prefix = 'PP';
        $date = date('Ymd');
        
        $lastPlan = $this->db->get('production_plans', 'plan_number', [
            'plan_number[~]' => $prefix . $date,
            'ORDER' => ['plan_number' => 'DESC']
        ]);

        // lastPlan is likely string or false.
        if ($lastPlan && is_string($lastPlan)) {
            $sequence = (int) substr($lastPlan, -4) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . $date . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }
}
