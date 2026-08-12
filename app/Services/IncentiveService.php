<?php

namespace App\Services;

use App\Models\Incentive;
use Core\Database;

class IncentiveService
{
    public function __construct(private Database $db)
    {
    }

    public function listIncentives(array $filters = []): array
    {
        $sql = "
            SELECT ei.*, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) as employee_name, 
                   d.department_name, p.position_name,
                   CASE 
                       WHEN (ei.actual_production / ei.production_target) * 100 >= 110 THEN 'Excellent'
                       WHEN (ei.actual_production / ei.production_target) * 100 >= 100 THEN 'Target Met'
                       WHEN (ei.actual_production / ei.production_target) * 100 >= 90 THEN 'Good'
                       ELSE 'Below Target'
                   END as performance_rating
            FROM employee_incentives ei
            LEFT JOIN employees e ON ei.employee_id = e.employee_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN positions p ON e.position_id = p.position_id
            WHERE 1=1
        ";

        // Note: The legacy query joined `users` aliased as `u` on `e.user_id` probably (implied by CONCAT(u.first_name...)), 
        // but the legacy code had `LEFT JOIN employees e ... LEFT JOIN departments d ...` then `ORDER BY ... u.first_name`.
        // The legacy code actually didn't show the join to `users` in the `incentives.php` snippet provided (line 78-80), 
        // but it used `u.first_name` in the SELECT. This implies `employees` might be joined to `users` internally or I missed a join line.
        // Looking at `get_incentive.php`, it does `LEFT JOIN employees e ...` and `CONCAT(e.first_name...` assuming fields are on employees table directly?
        // Wait, `incentives.php` line 19 fetches `$user` from `users`. 
        // Line 69 query: `LEFT JOIN employees e ... CONCAT(u.first_name...` uses `u` but `u` is NOT joined. 
        // ERROR IN LEGACY CODE or `employees` view? 
        // However, `get_incentive.php` line 9 uses `CONCAT(e.first_name...`. 
        // I will assume `employees` table has name columns OR we need to join users. 
        // Let's assume standard structure: `employees` links to `users` via `user_id` OR `employees` has `first_name`.
        // Given `get_incentive.php` uses `e.first_name`, I will use `e.first_name` here too if `employees` has it.
        // IF `employees` table does not have name, I'll need to join users. 
        // Let's try to join users as `u` on `e.user_id = u.user_id` just in case, or check column names.
        // Actually, `get_incentive.php` (Line 9) says `CONCAT(e.first_name...`. 
        // But `incentives.php` (Line 70) says `CONCAT(u.first_name...` and `ORDER BY ... full_name`.
        // I'll add the join to `users` to be safe and standard.

        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (ei.incentive_type LIKE ? OR e.employee_code LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY ei.month DESC";

        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getIncentive(int $id)
    {
        $sql = "SELECT ei.*, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) as employee_name, 
                       d.department_name, p.position_name
                FROM employee_incentives ei
                LEFT JOIN employees e ON ei.employee_id = e.employee_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                LEFT JOIN positions p ON e.position_id = p.position_id
                WHERE ei.incentive_id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }

    public function createIncentive(array $data): int
    {
        $payload = [
            'employee_id' => (int)$data['employee_id'],
            'month' => $data['month'],
            'incentive_type' => $data['incentive_type'],
            'base_salary' => (float)$data['base_salary'],
            'attendance_rate' => (float)$data['attendance_rate'],
            'production_target' => (int)$data['production_target'],
            'actual_production' => (int)$data['actual_production'],
            'quality_rate' => (float)$data['quality_rate'],
            'efficiency_rate' => (float)$data['efficiency_rate'],
            'incentive_amount' => (float)$data['incentive_amount'],
            'total_earnings' => (float)$data['total_earnings'],
            'remarks' => $data['remarks'] ?? '',
            'created_by' => $_SESSION['user_id'] ?? 1
        ];

        $this->db->insert('employee_incentives', $payload);
        return (int)$this->db->lastInsertId();
    }

    public function deleteIncentive(int $id): bool
    {
        return (bool)$this->db->delete('employee_incentives', ['incentive_id' => $id]);
    }

    public function getStats(): array
    {
        $currentMonth = date('Y-m');
        return [
            'total_records' => (int)$this->db->count('employee_incentives'),
            'total_amount' => (float)$this->db->sum('employee_incentives', 'incentive_amount'),
            'avg_incentive' => (float)$this->db->query("SELECT AVG(incentive_amount) FROM employee_incentives")->fetchColumn() ?: 0,
            'this_month' => (int)$this->db->count('employee_incentives', ['month' => $currentMonth])
        ];
    }
}
