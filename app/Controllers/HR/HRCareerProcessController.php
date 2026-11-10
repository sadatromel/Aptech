<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use App\Models\Employee;
use Core\Container;
use PDO;

class HRCareerProcessController extends BaseController
{
    private Employee $employeeModel;
    private PDO $pdo;

    public function __construct()
    {
        parent::__construct();
        $this->employeeModel = Container::get(Employee::class);
        $this->pdo = Container::get(PDO::class);
    }

    private function getDepartments(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT department_id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getDesignations(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT position_id as designation_id, position_name as designation_name FROM positions WHERE status = 'active' ORDER BY position_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getActiveEmployees(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT employee_id, employee_code, first_name, last_name, basic_salary FROM employees WHERE status = 'active' ORDER BY first_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // 1. Single Increment Entry
    public function incrementEntry(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();
            
            $empId = (int)$data['employee_id'];
            $incAmt = (float)$data['increment_amount'];
            $prevGross = (float)($data['previous_gross'] ?? 0);
            $newGross = $prevGross + $incAmt;

            $stmt = $this->pdo->prepare("INSERT INTO employee_increments (employee_id, increment_type, effective_date, previous_gross, increment_amount, new_gross, status) VALUES (?, ?, ?, ?, ?, ?, 'approved')");
            $stmt->execute([$empId, $data['increment_type'] ?? 'annual', $data['effective_date'], $prevGross, $incAmt, $newGross]);

            // Update employee basic salary if applicable
            $updateEmp = $this->pdo->prepare("UPDATE employees SET basic_salary = ? WHERE employee_id = ?");
            $updateEmp->execute([$newGross, $empId]);

            $_SESSION['flash_success'] = 'Employee increment record saved and salary updated.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/increment-entry' : '/hr/employees/increment-entry');
            return;
        }

        $stmt = $this->pdo->query("SELECT ei.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code FROM employee_increments ei JOIN employees e ON ei.employee_id = e.employee_id ORDER BY ei.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_processes/increment_entry', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 2. Bulk Increment Entry
    public function incrementBulk(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();
            $percentage = (float)($data['increment_percentage'] ?? 0);
            $effectiveDate = $data['effective_date'] ?? date('Y-m-d');
            $deptId = $data['department_id'] ?? null;

            $query = "SELECT employee_id, basic_salary FROM employees WHERE status = 'active'";
            if ($deptId) {
                $query .= " AND department_id = " . (int)$deptId;
            }
            $stmt = $this->pdo->query($query);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $count = 0;
            foreach ($employees as $emp) {
                $prevGross = (float)$emp['basic_salary'];
                $incAmt = ($prevGross * $percentage) / 100;
                $newGross = $prevGross + $incAmt;

                $ins = $this->pdo->prepare("INSERT INTO employee_increments (employee_id, increment_type, effective_date, previous_gross, increment_amount, new_gross, status) VALUES (?, 'annual', ?, ?, ?, ?, 'approved')");
                $ins->execute([$emp['employee_id'], $effectiveDate, $prevGross, $incAmt, $newGross]);

                $up = $this->pdo->prepare("UPDATE employees SET basic_salary = ? WHERE employee_id = ?");
                $up->execute([$newGross, $emp['employee_id']]);
                $count++;
            }

            $_SESSION['flash_success'] = "Bulk increment processed successfully for {$count} employees.";
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/increment-bulk' : '/hr/employees/increment-bulk');
            return;
        }

        $this->view('hr/employee_processes/increment_bulk', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 3. Single Promotion Entry
    public function promotionEntry(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $empId = (int)($data['employee_id'] ?? 0);
            $effectiveDate = $data['effective_date'] ?? date('Y-m-d');
            
            // Resolve previous designation if not provided directly
            $prevDesig = !empty($data['previous_designation']) ? trim((string)$data['previous_designation']) : '';
            if (empty($prevDesig) && $empId > 0) {
                $stmtEmp = $this->pdo->prepare("SELECT COALESCE(p.position_name, 'Operator') FROM employees e LEFT JOIN positions p ON e.position_id = p.position_id WHERE e.employee_id = ?");
                $stmtEmp->execute([$empId]);
                $prevDesig = $stmtEmp->fetchColumn() ?: 'Operator';
            }
            if (empty($prevDesig)) {
                $prevDesig = 'Operator';
            }

            // Resolve new designation if passed as ID or name
            $newDesig = !empty($data['new_designation']) ? trim((string)$data['new_designation']) : '';
            if (empty($newDesig) && !empty($data['new_position_id'])) {
                $stmtPos = $this->pdo->prepare("SELECT position_name FROM positions WHERE position_id = ?");
                $stmtPos->execute([(int)$data['new_position_id']]);
                $newDesig = $stmtPos->fetchColumn() ?: 'Senior Operator';
            }
            if (empty($newDesig)) {
                $newDesig = 'Senior Operator';
            }

            $stmt = $this->pdo->prepare("INSERT INTO employee_promotions (employee_id, effective_date, previous_designation, new_designation, previous_department, new_department, salary_increment, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $empId, $effectiveDate, $prevDesig,
                $newDesig, $data['previous_department'] ?? '', $data['new_department'] ?? '',
                $data['salary_increment'] ?? 0, $data['remarks'] ?? ''
            ]);

            $_SESSION['flash_success'] = 'Employee promotion record saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/promotion-entry' : '/hr/employees/promotion-entry');
            return;
        }

        $stmt = $this->pdo->query("SELECT ep.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code FROM employee_promotions ep JOIN employees e ON ep.employee_id = e.employee_id ORDER BY ep.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_processes/promotion_entry', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'designations' => $this->getDesignations(),
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 4. Bulk Promotion Entry
    public function promotionBulk(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Bulk promotions batch saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/promotion-bulk' : '/hr/employees/promotion-bulk');
            return;
        }

        $this->view('hr/employee_processes/promotion_bulk', [
            'departments' => $this->getDepartments(),
            'designations' => $this->getDesignations(),
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 5. Lay Off Employee
    public function layoffEmployee(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $empId = (int)($data['employee_id'] ?? 0);
            $startDate = $data['start_date'] ?? $data['layoff_start_date'] ?? date('Y-m-d');
            $endDate = !empty($data['end_date']) ? $data['end_date'] : (!empty($data['layoff_end_date']) ? $data['layoff_end_date'] : null);
            $reason = !empty($data['layoff_reason']) ? $data['layoff_reason'] : (!empty($data['reason']) ? $data['reason'] : 'Temporary operational layoff');
            $comp = (float)($data['compensation_percentage'] ?? 50.00);

            $stmt = $this->pdo->prepare("INSERT INTO employee_layoffs (employee_id, start_date, end_date, layoff_reason, compensation_percentage, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([
                $empId, $startDate, $endDate,
                $reason, $comp
            ]);

            $_SESSION['flash_success'] = 'Employee lay off record created successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/lay-off' : '/hr/employees/lay-off');
            return;
        }

        $stmt = $this->pdo->query("SELECT el.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code FROM employee_layoffs el JOIN employees e ON el.employee_id = e.employee_id ORDER BY el.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_processes/layoff_employee', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }
}
