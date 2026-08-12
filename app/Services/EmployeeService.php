<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use Core\Database;
use Core\Logger;

class EmployeeService
{
    private Database $db;
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function createEmployee(array $data): array
    {
        try {
            // Validate unique email
            $existing = $this->db->get('employees', 'employee_id', ['email' => $data['email']]);
            if ($existing) {
                return ['success' => false, 'message' => 'Email already exists'];
            }

            // Generate employee ID
            // $data['employee_id'] = $this->generateEmployeeId(); // Logic handled by controller/form
            $data['status'] = 'active';
            $data['created_at'] = date('Y-m-d H:i:s');

            $employee = new Employee();
            $id = $employee->create($data);

            // Logger removed

            return ['success' => true, 'employee_id' => $id];
        } catch (\Exception $e) {
            // Logger removed

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateEmployee(int $id, array $data): array
    {
        try {
            $employee = new Employee();
            $exists = $employee->find($id);

            if (!$exists) {
                return ['success' => false, 'message' => 'Employee not found'];
            }

            $data['updated_at'] = date('Y-m-d H:i:s');
            $success = $employee->update($id, $data);

            if ($success) {
                // Logger removed

                return ['success' => true];
            }

            return ['success' => false, 'message' => 'No changes made'];
        } catch (\Exception $e) {
            // Logger removed

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteEmployee(int $id): array
    {
        try {
            $employee = new Employee();
            $exists = $employee->find($id);

            if (!$exists) {
                return ['success' => false, 'message' => 'Employee not found'];
            }

            // Check for dependencies
            $dependencies = $this->checkDependencies($id);
            if (!empty($dependencies)) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete employee with existing records',
                    'dependencies' => $dependencies
                ];
            }

            $success = $employee->delete($id);

            if ($success) {
                // Logger removed
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'Failed to delete employee'];
        } catch (\Exception $e) {
            // Logger removed
            // error_log("Failed to delete employee: " . $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getEmployeeReport(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['department'])) {
            $where[] = "department = ?";
            $params[] = $filters['department'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                department,
                COUNT(*) as total_employees,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_employees,
                AVG(salary) as average_salary,
                MAX(salary) as max_salary,
                MIN(salary) as min_salary
            FROM employees 
            {$whereClause}
            GROUP BY department
            ORDER BY department
        ";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function generateEmployeeId(): string
    {
        $prefix = 'EMP';
        $year = date('Y');
        $sequence = $this->getNextSequence();
        return sprintf("%s%s%04d", $prefix, $year, $sequence);
    }

    private function getNextSequence(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM employees WHERE YEAR(created_at) = ?", [date('Y')]);
        return (int) $stmt->fetchColumn() + 1;
    }

    private function checkDependencies(int $employeeId): array
    {
        $dependencies = [];

        $tables = [
            'attendance' => 'attendance records',
            'payroll' => 'payroll records',
            'performance_reviews' => 'performance reviews',
            'user_accounts' => 'user accounts'
        ];

        foreach ($tables as $table => $description) {
            $count = $this->db->count($table, ['employee_id' => $employeeId]);
            if ($count > 0) {
                $dependencies[] = "{$description} ({$count})";
            }
        }

        return $dependencies;
    }
}
