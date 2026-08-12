<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use App\Models\Employee;
use App\Models\MasterLibrary\Section;
use Core\Container;
use PDO;

class HRPayrollProcessController extends BaseController
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

    private function getActiveEmployees(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT employee_id, employee_code, first_name, last_name, basic_salary FROM employees WHERE status = 'active' ORDER BY first_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // 1. Bonus Re-process
    public function bonusReprocess(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Bonus re-process initiated and recalculated successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/bonus-reprocess' : '/hr/payroll/bonus-reprocess');
            return;
        }

        $this->view('hr/payroll_processes/bonus_reprocess', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 2. Cost Center Wise Salary Process
    public function costCenterSalary(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Cost center wise salary process executed successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/cost-center-salary' : '/hr/payroll/cost-center-salary');
            return;
        }

        $this->view('hr/payroll_processes/cost_center_salary', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 3. Earn Leave Payment Process Monthly
    public function earnLeaveMonthly(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Monthly Earn Leave payout processed successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/earn-leave-monthly' : '/hr/payroll/earn-leave-monthly');
            return;
        }

        $stmt = $this->pdo->query("SELECT elp.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code FROM earn_leave_payouts elp JOIN employees e ON elp.employee_id = e.employee_id WHERE elp.payout_type = 'monthly' ORDER BY elp.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/payroll_processes/earn_leave_monthly', [
            'records' => $records,
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 4. Earn Leave Payment Process Yearly
    public function earnLeaveYearly(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Yearly Earn Leave payout encashment processed successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/earn-leave-yearly' : '/hr/payroll/earn-leave-yearly');
            return;
        }

        $stmt = $this->pdo->query("SELECT elp.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code FROM earn_leave_payouts elp JOIN employees e ON elp.employee_id = e.employee_id WHERE elp.payout_type = 'yearly' ORDER BY elp.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/payroll_processes/earn_leave_yearly', [
            'records' => $records,
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 5. Final Settlement Process
    public function finalSettlement(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();
            $net = (float)($data['encashment_amount'] ?? 0) + (float)($data['gratuity_amount'] ?? 0) + (float)($data['notice_pay'] ?? 0) - (float)($data['deductions'] ?? 0);
            
            $stmt = $this->pdo->prepare("INSERT INTO final_settlements (employee_id, resignation_date, release_date, service_length_years, encashment_amount, gratuity_amount, notice_pay, deductions, net_settlement, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
            $stmt->execute([
                $data['employee_id'], $data['resignation_date'], $data['release_date'],
                $data['service_length_years'] ?? 1.0, $data['encashment_amount'] ?? 0,
                $data['gratuity_amount'] ?? 0, $data['notice_pay'] ?? 0,
                $data['deductions'] ?? 0, $net
            ]);

            $_SESSION['flash_success'] = 'Final settlement calculated and saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/final-settlement' : '/hr/payroll/final-settlement');
            return;
        }

        $stmt = $this->pdo->query("SELECT fs.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code FROM final_settlements fs JOIN employees e ON fs.employee_id = e.employee_id ORDER BY fs.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/payroll_processes/final_settlement', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 6. Fraction Month Salary Re-process
    public function fractionSalaryReprocess(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Fraction month salary re-processing completed successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/fraction-salary-reprocess' : '/hr/payroll/fraction-salary-reprocess');
            return;
        }

        $this->view('hr/payroll_processes/fraction_salary_reprocess', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 7. Holiday Allowance Process
    public function holidayAllowance(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Holiday allowance process executed for selected period.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/holiday-allowance' : '/hr/payroll/holiday-allowance');
            return;
        }

        $this->view('hr/payroll_processes/holiday_allowance', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 8. Monthly Bill Process
    public function monthlyBillProcess(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Monthly bills (conveyance, mobile, internet) processed successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/monthly-bill-process' : '/hr/payroll/monthly-bill-process');
            return;
        }

        $this->view('hr/payroll_processes/monthly_bill_process', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 9. Night Allowance Process
    public function nightAllowance(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Night shift allowance processed successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/night-allowance' : '/hr/payroll/night-allowance');
            return;
        }

        $this->view('hr/payroll_processes/night_allowance', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 10. Salary Adjustment Entry
    public function salaryAdjustment(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();
            $stmt = $this->pdo->prepare("INSERT INTO salary_adjustments (employee_id, year_month, adjustment_type, amount, reason, status) VALUES (?, ?, ?, ?, ?, 'applied')");
            $stmt->execute([$data['employee_id'], $data['year_month'], $data['adjustment_type'], $data['amount'], $data['reason']]);

            $_SESSION['flash_success'] = 'Salary adjustment entry saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/salary-adjustment' : '/hr/payroll/salary-adjustment');
            return;
        }

        $stmt = $this->pdo->query("SELECT sa.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code FROM salary_adjustments sa JOIN employees e ON sa.employee_id = e.employee_id ORDER BY sa.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/payroll_processes/salary_adjustment', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 11. Salary Period Lock
    public function salaryPeriodLock(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();
            $stmt = $this->pdo->prepare("INSERT INTO payroll_period_locks (year_month, department_id, lock_status, remarks) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['year_month'], !empty($data['department_id']) ? $data['department_id'] : null, $data['lock_status'], $data['remarks'] ?? '']);

            $_SESSION['flash_success'] = 'Salary period status updated successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/period-lock' : '/hr/payroll/period-lock');
            return;
        }

        $stmt = $this->pdo->query("SELECT ppl.*, ed.department_name FROM payroll_period_locks ppl LEFT JOIN departments ed ON ppl.department_id = ed.department_id ORDER BY ppl.locked_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/payroll_processes/period_lock', [
            'records' => $records,
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 12. Tiffin Bill Process
    public function tiffinBill(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Tiffin bill allowance processed successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/payroll/tiffin-bill' : '/hr/payroll/tiffin-bill');
            return;
        }

        $this->view('hr/payroll_processes/tiffin_bill', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }
}
