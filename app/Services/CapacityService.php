<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class CapacityService
{
    public function __construct(private Database $db)
    {
    }

    public function getCapacityData(string $date, string $view): array
    {
        $lines = $this->db->select('production_lines', '*', ['status' => 'active']);
        
        $capacityData = [];
        foreach ($lines as $line) {
            $lineCapacity = [
                'line_id' => $line['production_line_id'],
                'line_name' => $line['line_name'],
                'daily_capacity' => $line['capacity_per_day'],
                'current_load' => 0,
                'available_capacity' => $line['capacity_per_day'],
                'utilization' => 0,
                'orders' => []
            ];

            if ($view === 'daily') {
                $orders = $this->db->select('production_plans pp', [
                    '[>]styles s' => ['pp.style_id' => 'style_id']
                ], [
                    'pp.id',
                    'pp.plan_number',
                    'pp.quantity',
                    'pp.status',
                    's.style_code',
                    'style_id' => 's.style_id'
                ], [
                    'pp.line_id' => $line['production_line_id'],
                    'pp.planned_date' => $date,
                    'pp.status[!]' => 'completed'
                ]);

                $lineCapacity['orders'] = $orders;
                $lineCapacity['current_load'] = array_sum(array_column($orders, 'quantity'));
            } elseif ($view === 'weekly') {
                $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
                $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($date)));

                $orders = $this->db->select('production_plans pp', [
                    '[>]styles s' => ['pp.style_id' => 'style_id']
                ], [
                    'pp.id',
                    'pp.plan_number',
                    'pp.quantity',
                    'pp.status',
                    'pp.planned_date',
                    's.style_code',
                    'style_id' => 's.style_id'
                ], [
                    'pp.line_id' => $line['production_line_id'],
                    'pp.planned_date[>=]' => $weekStart,
                    'pp.planned_date[<=]' => $weekEnd,
                    'pp.status[!]' => 'completed'
                ]);

                $lineCapacity['orders'] = $orders;
                $lineCapacity['current_load'] = array_sum(array_column($orders, 'quantity'));
                $lineCapacity['available_capacity'] = $line['capacity_per_day'] * 5;
            } elseif ($view === 'monthly') {
                $monthStart = date('Y-m-01', strtotime($date));
                $monthEnd = date('Y-m-t', strtotime($date));

                $orders = $this->db->select('production_plans pp', [
                    '[>]styles s' => ['pp.style_id' => 'style_id']
                ], [
                    'pp.id',
                    'pp.plan_number',
                    'pp.quantity',
                    'pp.status',
                    'pp.planned_date',
                    's.style_code',
                    'style_id' => 's.style_id'
                ], [
                    'pp.line_id' => $line['production_line_id'],
                    'pp.planned_date[>=]' => $monthStart,
                    'pp.planned_date[<=]' => $monthEnd,
                    'pp.status[!]' => 'completed'
                ]);

                $lineCapacity['orders'] = $orders;
                $lineCapacity['current_load'] = array_sum(array_column($orders, 'quantity'));
                $workingDays = $this->getWorkingDays($monthStart, $monthEnd);
                $lineCapacity['available_capacity'] = $line['capacity_per_day'] * $workingDays;
            }

            $lineCapacity['utilization'] = $lineCapacity['available_capacity'] > 0 
                ? ($lineCapacity['current_load'] / $lineCapacity['available_capacity']) * 100 
                : 0;

            $capacityData[] = $lineCapacity;
        }

        return $capacityData;
    }

    public function getLineDetail(int $lineId, string $date, string $view): ?array
    {
        $line = $this->db->get('production_lines', '*', ['id' => $lineId]);
        if (!$line) {
            return null;
        }

        $capacityDetail = [
            'line' => $line,
            'daily_capacity' => $line['daily_capacity'],
            'efficiency_target' => $line['efficiency_target'] ?? 85,
            'current_efficiency' => 0,
            'historical_data' => [],
            'upcoming_orders' => []
        ];

        // Get historical efficiency data (last 30 days)
        $historicalStart = date('Y-m-d', strtotime('-30 days'));
        $historicalData = $this->db->select('production_plans', [
            'planned_date',
            'quantity',
            'completed_quantity'
        ], [
            'line_id' => $lineId,
            'planned_date[>=]' => $historicalStart,
            'planned_date[<=]' => date('Y-m-d'),
            'ORDER' => ['planned_date' => 'ASC']
        ]);

        foreach ($historicalData as $data) {
            $efficiency = $data['quantity'] > 0 
                ? ($data['completed_quantity'] / $data['quantity']) * 100 
                : 0;
            
            $capacityDetail['historical_data'][] = [
                'date' => $data['planned_date'],
                'planned' => $data['quantity'],
                'completed' => $data['completed_quantity'],
                'efficiency' => $efficiency
            ];
        }

        // Calculate current efficiency
        if (!empty($capacityDetail['historical_data'])) {
            $recentData = array_slice($capacityDetail['historical_data'], -7);
            $totalEfficiency = array_sum(array_column($recentData, 'efficiency'));
            $capacityDetail['current_efficiency'] = $totalEfficiency / count($recentData);
        }

        // Get upcoming orders
        $upcomingStart = date('Y-m-d', strtotime('+1 day'));
        $upcomingEnd = date('Y-m-d', strtotime('+30 days'));

        $upcomingOrders = $this->db->select('production_plans pp', [
            '[>]styles s' => ['pp.style_id' => 'style_id']
        ], [
            'pp.id',
            'pp.plan_number',
            'pp.planned_date',
            'pp.quantity',
            'pp.status',
            's.style_code',
            's.style_name'
        ], [
            'pp.line_id' => $lineId,
            'pp.planned_date[>=]' => $upcomingStart,
            'pp.planned_date[<=]' => $upcomingEnd,
            'ORDER' => ['pp.planned_date' => 'ASC']
        ]);

        $capacityDetail['upcoming_orders'] = $upcomingOrders;

        return $capacityDetail;
    }

    public function getForecastData(int $months): array
    {
        $lines = $this->db->select('production_lines', '*', ['status' => 'active']);
        
        $forecastData = [];
        for ($i = 0; $i < $months; $i++) {
            $monthDate = date('Y-m-01', strtotime("+$i months"));
            $monthEnd = date('Y-m-t', strtotime($monthDate));
            $workingDays = $this->getWorkingDays($monthDate, $monthEnd);

            $monthForecast = [
                'month' => date('F Y', strtotime($monthDate)),
                'working_days' => $workingDays,
                'lines' => []
            ];

            foreach ($lines as $line) {
                $plannedQty = $this->db->sum('production_plans', 'quantity', [
                    'line_id' => $line['id'],
                    'planned_date[>=]' => $monthDate,
                    'planned_date[<=]' => $monthEnd
                ]);

                $totalCapacity = $line['daily_capacity'] * $workingDays;
                $utilization = $totalCapacity > 0 ? ($plannedQty / $totalCapacity) * 100 : 0;

                $monthForecast['lines'][] = [
                    'line_name' => $line['name'],
                    'daily_capacity' => $line['daily_capacity'],
                    'total_capacity' => $totalCapacity,
                    'planned_quantity' => $plannedQty,
                    'available_capacity' => $totalCapacity - $plannedQty,
                    'utilization' => $utilization
                ];
            }

            $forecastData[] = $monthForecast;
        }

        return $forecastData;
    }

    public function getStats(): array
    {
        $totalLines = (int) ($this->db->count('production_lines', ['status' => 'active']) ?? 0);
        $activePlans = (int) ($this->db->count('production_plans', ['status' => 'active']) ?? 0);
        $totalCapacity = (int) ($this->db->sum('production_lines', 'capacity_per_day', ['status' => 'active']) ?? 0);

        return [
            'total_lines' => $totalLines,
            'active_plans' => $activePlans,
            'total_capacity' => $totalCapacity,
        ];
    }

    private function getWorkingDays(string $startDate, string $endDate): int
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = $start->diff($end);
        $days = $interval->days + 1;

        $weeks = floor($days / 7);
        $remainingDays = $days % 7;
        
        $workingDays = $weeks * 5;
        
        for ($i = 0; $i < $remainingDays; $i++) {
            $dayOfWeek = $start->format('N');
            if ($dayOfWeek < 6) {
                $workingDays++;
            }
            $start->modify('+1 day');
        }
        
        return (int) $workingDays;
    }
}
