<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use App\Models\Employee;
use App\Models\MaternityBenefit;
use PDO;

class RmgComplianceController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        // 1. Grade-wise worker stats
        $gradeStats = $this->db->query("
            SELECT 
                COALESCE(worker_grade, 'Unassigned') as grade,
                COUNT(*) as count,
                AVG(salary) as avg_gross,
                AVG(basic_salary) as avg_basic
            FROM employees 
            WHERE status = 'active'
            GROUP BY worker_grade
            ORDER BY worker_grade ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 2. Maternity stats
        $maternityStats = [
            'total' => $this->db->count('maternity_benefits'),
            'pending' => $this->db->count('maternity_benefits', ['status' => 'Pending']),
            'fully_paid' => $this->db->count('maternity_benefits', ['payment_status' => 'Fully Paid']),
            'partially_paid' => $this->db->count('maternity_benefits', ['payment_status' => 'Partially Paid'])
        ];

        // 3. Current month attendance bonus eligibility count
        $currentMonth = date('Y-m');
        $bonusCount = 0;
        try {
            $sql = "
                SELECT e.employee_id
                FROM employees e
                JOIN attendance a ON e.employee_id = a.employee_id
                WHERE e.status = 'active' AND a.date LIKE ?
                GROUP BY e.employee_id
                HAVING COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) = 0
                   AND COUNT(CASE WHEN a.status = 'Late' OR a.late_minutes > 0 THEN 1 END) <= 1
            ";
            $stmt = $this->db->query($sql, [$currentMonth . '%']);
            $bonusCount = count($stmt->fetchAll());
        } catch (\Exception $e) {
            error_log("Error fetching bonus count: " . $e->getMessage());
        }

        $this->view('hr/compliance/index', [
            'pageTitle' => 'RMG Sector Compliance Dashboard',
            'gradeStats' => $gradeStats,
            'maternityStats' => $maternityStats,
            'bonusCount' => $bonusCount,
            'currentMonth' => $currentMonth
        ]);
    }

    public function wageCompliance(): void
    {
        $this->requireAuth();

        // Standard 2024 Gazette values for RMG sector
        $wageGazette = [
            1 => ['grade' => 1, 'minimum_gross' => 18000.00, 'minimum_basic' => 10200.00, 'roles' => 'Pattern Master, Chief QC'],
            2 => ['grade' => 2, 'minimum_gross' => 14250.00, 'minimum_basic' => 7950.00, 'roles' => 'Senior Operator, Senior Cutter'],
            3 => ['grade' => 3, 'minimum_gross' => 13500.00, 'minimum_basic' => 7400.00, 'roles' => 'Sewing Operator, Cutter'],
            4 => ['grade' => 4, 'minimum_gross' => 13025.00, 'minimum_basic' => 7050.00, 'roles' => 'Junior Operator, Junior Ironer'],
            5 => ['grade' => 5, 'minimum_gross' => 12875.00, 'minimum_basic' => 6950.00, 'roles' => 'Operator Grade B, Folder'],
            6 => ['grade' => 6, 'minimum_gross' => 12750.00, 'minimum_basic' => 6850.00, 'roles' => 'Operator Grade C, Packer'],
            7 => ['grade' => 7, 'minimum_gross' => 12500.00, 'minimum_basic' => 6700.00, 'roles' => 'Helper, Cleaner']
        ];

        // Fetch employee details with their positions/departments
        $employees = $this->db->select('employees', [
            '[>]departments' => ['employees.department_id' => 'departments.department_id'],
            '[>]positions' => ['employees.position_id' => 'positions.position_id']
        ], [
            'employees.employee_id',
            'employees.employee_code',
            'employees.first_name',
            'employees.last_name',
            'employees.worker_grade',
            'employees.salary', // Represents gross salary
            'employees.basic_salary',
            'departments.department_name',
            'positions.position_name'
        ], [
            'employees.status' => 'active',
            'ORDER' => 'employees.worker_grade ASC'
        ]);

        // Evaluate compliance
        $violations = 0;
        foreach ($employees as &$emp) {
            $grade = (int)($emp['worker_grade'] ?? 0);
            $emp['is_compliant'] = true;
            $emp['remarks'] = 'Compliant';
            $emp['required_gross'] = 0.00;
            $emp['required_basic'] = 0.00;

            if ($grade >= 1 && $grade <= 7) {
                $standards = $wageGazette[$grade];
                $emp['required_gross'] = $standards['minimum_gross'];
                $emp['required_basic'] = $standards['minimum_basic'];

                if ($emp['salary'] < $standards['minimum_gross'] || $emp['basic_salary'] < $standards['minimum_basic']) {
                    $emp['is_compliant'] = false;
                    $emp['remarks'] = 'Below Minimum Wage!';
                    $violations++;
                }
            } else {
                $emp['is_compliant'] = true;
                $emp['remarks'] = 'Non-Worker / Excluded';
            }
        }

        $this->view('hr/compliance/wage_compliance', [
            'pageTitle' => 'RMG Minimum Wage Compliance (BLA 2006)',
            'employees' => $employees,
            'wageGazette' => $wageGazette,
            'violations' => $violations
        ]);
    }

    public function attendanceBonus(): void
    {
        $this->requireAuth();

        $month = $this->request->get('month', date('Y-m'));
        $bonusAmountSetting = (float)$this->request->get('bonus_amount', '1000.00');

        // Query employees who had active service during the month and aggregate their monthly attendance details
        $sql = "
            SELECT 
                e.employee_id, e.employee_code, e.first_name, e.last_name, e.salary, e.basic_salary,
                d.department_name, p.position_name,
                COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
                COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN a.status = 'Leave' THEN 1 END) as leave_days,
                COUNT(CASE WHEN a.status = 'Late' OR a.late_minutes > 0 THEN 1 END) as late_days
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN positions p ON e.position_id = p.position_id
            LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.date LIKE ?
            WHERE e.status = 'active'
            GROUP BY e.employee_id
            ORDER BY d.department_name, e.first_name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$month . '%']);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $eligibleCount = 0;
        foreach ($records as &$rec) {
            // Bangladesh RMG standards: 100% attendance = 0 absents, max 1 late
            if ((int)$rec['absent_days'] === 0 && (int)$rec['late_days'] <= 1 && (int)$rec['present_days'] > 0) {
                $rec['is_eligible'] = true;
                $rec['bonus_amount'] = $bonusAmountSetting;
                $eligibleCount++;
            } else {
                $rec['is_eligible'] = false;
                $rec['bonus_amount'] = 0.00;
            }
        }

        $this->view('hr/compliance/attendance_bonus', [
            'pageTitle' => 'Attendance Bonus Calculator',
            'records' => $records,
            'selectedMonth' => $month,
            'bonusAmount' => $bonusAmountSetting,
            'eligibleCount' => $eligibleCount
        ]);
    }

    public function festivalBonus(): void
    {
        $this->requireAuth();

        $festivalDate = $this->request->get('target_date', date('Y-m-d'));
        $bonusPercentage = (float)$this->request->get('percentage', '100'); // 100% of basic is standard

        // Fetch active employees
        $employees = $this->db->select('employees', [
            '[>]departments' => ['employees.department_id' => 'departments.department_id'],
            '[>]positions' => ['employees.position_id' => 'positions.position_id']
        ], [
            'employees.employee_id',
            'employees.employee_code',
            'employees.first_name',
            'employees.last_name',
            'employees.basic_salary',
            'employees.salary',
            'employees.hire_date',
            'departments.department_name',
            'positions.position_name'
        ], [
            'employees.status' => 'active',
            'ORDER' => 'employees.first_name'
        ]);

        $eligibleCount = 0;
        $totalBonusPaid = 0.00;

        foreach ($employees as &$emp) {
            // BLA 2006 compliance: continuous service of at least 1 year
            $hireDate = new \DateTime($emp['hire_date']);
            $targetDate = new \DateTime($festivalDate);
            $interval = $hireDate->diff($targetDate);
            $serviceMonths = ($interval->y * 12) + $interval->m;

            $emp['service_months'] = $serviceMonths;
            $emp['service_years'] = round($serviceMonths / 12, 1);

            if ($serviceMonths >= 12) {
                $emp['is_eligible'] = true;
                $emp['bonus_amount'] = round(((float)$emp['basic_salary'] * $bonusPercentage) / 100, 2);
                $eligibleCount++;
                $totalBonusPaid += $emp['bonus_amount'];
            } else {
                $emp['is_eligible'] = false;
                $emp['bonus_amount'] = 0.00;
            }
        }

        $this->view('hr/compliance/festival_bonus', [
            'pageTitle' => 'Festival Bonus Sheet (BLA 2006)',
            'employees' => $employees,
            'selectedDate' => $festivalDate,
            'percentage' => $bonusPercentage,
            'eligibleCount' => $eligibleCount,
            'totalBonusPaid' => $totalBonusPaid
        ]);
    }

    public function maternity(): void
    {
        $this->requireAuth();

        $maternityModel = new MaternityBenefit();
        $benefits = $maternityModel->getAllWithEmployeeDetails();

        // Get female employees for selection
        $femaleEmployees = $this->db->select('employees', [
            'employee_id',
            'first_name',
            'last_name',
            'employee_code'
        ], [
            'gender' => 'Female',
            'status' => 'active',
            'ORDER' => 'first_name'
        ]);

        $this->view('hr/compliance/maternity', [
            'pageTitle' => 'Maternity Benefits Tracker',
            'benefits' => $benefits,
            'femaleEmployees' => $femaleEmployees
        ]);
    }

    public function createMaternityCase(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('hr/rmg-compliance/maternity');
            return;
        }

        $employeeId = (int)$_POST['employee_id'];
        $expectedDeliveryDate = $_POST['expected_delivery_date'];
        $leaveStartDate = $_POST['leave_start_date'];

        // Under BLA 2006, maternity leave is 16 weeks (112 days)
        $start = new \DateTime($leaveStartDate);
        $end = clone $start;
        $end->modify('+111 days'); // 112 days total
        $leaveEndDate = $end->format('Y-m-d');

        // Calculation of benefit amount under BLA 2006:
        // Average gross wages of preceding 3 calendar months divided by total working days or standard 90 days.
        // We will fetch the employee gross salary and multiply by 112 / 30, or query payroll if available.
        $emp = $this->db->get('employees', '*', ['employee_id' => $employeeId]);
        if (!$emp) {
            $_SESSION['error_message'] = 'Employee not found';
            $this->redirect('hr/rmg-compliance/maternity');
            return;
        }

        $grossSalary = (float)($emp['salary'] ?? 0.00);
        
        // standard daily rate = Gross / 26 working days (RMG standard) or 30 days. Let's use 26 working days.
        $avgDailyWage = round($grossSalary / 26, 2);
        
        // Total benefit amount = 112 days * daily wage
        $totalBenefitAmount = round($avgDailyWage * 112, 2);

        // split installment amounts (1st half paid on start, 2nd half paid on proof of birth)
        $payment1 = round($totalBenefitAmount / 2, 2);
        $payment2 = $totalBenefitAmount - $payment1;

        $maternityModel = new MaternityBenefit();
        $maternityModel->create([
            'employee_id' => $employeeId,
            'expected_delivery_date' => $expectedDeliveryDate,
            'leave_start_date' => $leaveStartDate,
            'leave_end_date' => $leaveEndDate,
            'avg_daily_wage' => $avgDailyWage,
            'total_benefit_amount' => $totalBenefitAmount,
            'payment_status' => 'Pending',
            'payment1_amount' => $payment1,
            'payment2_amount' => $payment2,
            'status' => 'Pending',
            'created_by' => $this->auth->user()['user_id'] ?? $this->auth->user()['id'] ?? $_SESSION['user_id'] ?? 1
        ]);

        $_SESSION['success_message'] = 'Maternity case created successfully! Calculated benefit: BDT ' . number_format($totalBenefitAmount, 2);
        $this->redirect('hr/rmg-compliance/maternity');
    }

    public function approveMaternityCase(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('hr_manager');

        $maternityModel = new MaternityBenefit();
        $maternityModel->update($id, [
            'status' => 'Approved'
        ]);

        $this->json(['success' => true]);
    }

    public function payoutMaternity(int $id): void
    {
        $this->requireAuth();

        $installment = (int)$this->request->get('installment', 1);

        $maternityModel = new MaternityBenefit();
        $case = $maternityModel->find($id);

        if (!$case) {
            $this->json(['success' => false, 'message' => 'Maternity case not found'], 404);
            return;
        }

        $update = [];
        if ($installment === 1) {
            $update['payment1_date'] = date('Y-m-d');
            $update['payment_status'] = $case['payment_status'] === 'Pending' ? 'Partially Paid' : 'Fully Paid';
        } else {
            $update['payment2_date'] = date('Y-m-d');
            $update['payment_status'] = 'Fully Paid';
        }

        $maternityModel->update($id, $update);
        $this->json(['success' => true]);
    }
    
    public function deleteMaternityCase(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('hr_manager');

        $maternityModel = new MaternityBenefit();
        $maternityModel->delete($id);

        $this->json(['success' => true]);
    }
}
