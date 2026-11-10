<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use App\Models\Employee;
use Core\Container;
use PDO;

class HREmployeeInfoController extends BaseController
{
    private Employee $employeeModel;
    private PDO $pdo;

    public function __construct()
    {
        parent::__construct();
        $this->employeeModel = Container::get(Employee::class);
        $this->pdo = Container::get(PDO::class);
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        try {
            // 1. employee_weekends
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS employee_weekends (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                weekend_days VARCHAR(100) NOT NULL DEFAULT 'Friday',
                effective_date DATE NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 2. employee_policy_tags
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS employee_policy_tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                policy_name VARCHAR(150) NOT NULL,
                policy_type VARCHAR(100) NOT NULL DEFAULT 'Operational',
                effective_date DATE NULL,
                expiry_date DATE NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                remarks TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 3. employee_submitted_documents
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS employee_submitted_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                document_type VARCHAR(100) NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                document_number VARCHAR(100) NULL,
                submission_date DATE NULL,
                verification_status VARCHAR(50) NOT NULL DEFAULT 'Submitted',
                remarks TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 4. employee_vaccinations
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS employee_vaccinations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                vaccine_name VARCHAR(150) NOT NULL,
                dose_number VARCHAR(50) NOT NULL DEFAULT '1st Dose',
                vaccination_date DATE NULL,
                certificate_no VARCHAR(100) NULL,
                dose_status VARCHAR(50) NOT NULL DEFAULT 'Fully Vaccinated',
                center_name VARCHAR(200) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 5. employee_health_records
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS employee_health_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                blood_group VARCHAR(10) NULL,
                height_cm DECIMAL(5,2) NULL,
                weight_kg DECIMAL(5,2) NULL,
                medical_conditions TEXT NULL,
                allergies TEXT NULL,
                doctor_clearance_date DATE NULL,
                checkup_date DATE NULL,
                insurance_no VARCHAR(100) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 6. employee_tax_certificates
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS employee_tax_certificates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                tin_number VARCHAR(50) NOT NULL,
                tax_circle VARCHAR(100) NULL,
                tax_zone VARCHAR(100) NULL,
                assessment_year VARCHAR(20) NOT NULL DEFAULT '2025-2026',
                tax_amount DECIMAL(12,2) DEFAULT 0.00,
                tds_deducted DECIMAL(12,2) DEFAULT 0.00,
                submission_status VARCHAR(50) NOT NULL DEFAULT 'Submitted',
                submission_date DATE NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        } catch (\Exception $e) {
            // Log table creation exception if needed
        }
    }

    private function getActiveEmployees(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.gender, e.phone, e.email, e.hire_date, e.basic_salary, e.status, d.department_name, p.position_name FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id LEFT JOIN positions p ON e.position_id = p.position_id ORDER BY e.first_name ASC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getDepartments(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    // 1. Employee Weekend
    public function weekendInfo(): void
    {
        $this->requireAuth();
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $empId = (int)$data['employee_id'];
            $weekendDays = is_array($data['weekend_days'] ?? null) ? implode(', ', $data['weekend_days']) : ($data['weekend_days'] ?? 'Friday');
            $effectiveDate = $data['effective_date'] ?? date('Y-m-d');
            $status = $data['status'] ?? 'active';

            $stmt = $this->pdo->prepare("INSERT INTO employee_weekends (employee_id, weekend_days, effective_date, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$empId, $weekendDays, $effectiveDate, $status]);

            $_SESSION['flash_success'] = 'Employee weekend schedule saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/weekend' : '/hr/employees/weekend');
            return;
        }

        $stmt = $this->pdo->query("SELECT ew.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code, d.department_name FROM employee_weekends ew JOIN employees e ON ew.employee_id = e.employee_id LEFT JOIN departments d ON e.department_id = d.department_id ORDER BY ew.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_info/weekend_info', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 2. Employee Basic
    public function basicInfo(): void
    {
        $this->requireAuth();
        $employees = $this->getActiveEmployees();

        $this->view('hr/employee_info/basic_info', [
            'employees' => $employees,
            'departments' => $this->getDepartments(),
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 3. Employee Details
    public function detailsInfo(): void
    {
        $this->requireAuth();
        
        $search = $this->request->get('q', '');
        $employees = $this->getActiveEmployees();

        $selectedId = (int)($this->request->get('id', $employees[0]['employee_id'] ?? 0));

        $selectedEmp = null;
        if ($selectedId > 0) {
            $stmt = $this->pdo->prepare("SELECT e.*, d.department_name, p.position_name FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id LEFT JOIN positions p ON e.position_id = p.position_id WHERE e.employee_id = ?");
            $stmt->execute([$selectedId]);
            $selectedEmp = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $this->view('hr/employee_info/details_info', [
            'employees' => $employees,
            'selectedEmployee' => $selectedEmp,
            'searchQuery' => $search,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 4. Employee Policy Tagging Information
    public function policyTagging(): void
    {
        $this->requireAuth();
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $policyName = $data['policy_name'] ?? $data['attendance_policy'] ?? $data['leave_policy'] ?? $data['holiday_policy'] ?? 'Standard Company Policy';

            $stmt = $this->pdo->prepare("INSERT INTO employee_policy_tags (employee_id, policy_name, policy_type, effective_date, expiry_date, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['employee_id'],
                $policyName,
                $data['policy_type'] ?? 'Operational',
                $data['effective_date'] ?? date('Y-m-d'),
                $data['expiry_date'] ?? null,
                $data['status'] ?? 'active',
                $data['remarks'] ?? ''
            ]);

            $_SESSION['flash_success'] = 'Policy tagged to employee successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/policy-tagging' : '/hr/employees/policy-tagging');
            return;
        }

        $stmt = $this->pdo->query("SELECT ept.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code, d.department_name FROM employee_policy_tags ept JOIN employees e ON ept.employee_id = e.employee_id LEFT JOIN departments d ON e.department_id = d.department_id ORDER BY ept.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_info/policy_tagging', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 5. Employee Salary Information
    public function salaryInfo(): void
    {
        $this->requireAuth();
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();
            $empId = (int)$data['employee_id'];

            $basic = (float)($data['basic_salary'] ?? 0);
            $house = (float)($data['house_rent'] ?? 0);
            $medical = (float)($data['medical_allowance'] ?? 0);
            $transport = (float)($data['transport_allowance'] ?? 0);
            $food = (float)($data['food_allowance'] ?? 0);

            $stmt = $this->pdo->prepare("UPDATE employees SET basic_salary = ?, house_rent = ?, medical_allowance = ?, transport_allowance = ?, food_allowance = ?, bank_name = ?, bank_account = ?, bank_branch = ? WHERE employee_id = ?");
            $stmt->execute([
                $basic, $house, $medical, $transport, $food,
                $data['bank_name'] ?? '', $data['bank_account'] ?? '', $data['bank_branch'] ?? '',
                $empId
            ]);

            $_SESSION['flash_success'] = 'Employee salary & breakdown information updated successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/salary-info' : '/hr/employees/salary-info');
            return;
        }

        $employees = $this->getActiveEmployees();

        $this->view('hr/employee_info/salary_info', [
            'employees' => $employees,
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 6. Employee Submitted Documents
    public function submittedDocuments(): void
    {
        $this->requireAuth();
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $stmt = $this->pdo->prepare("INSERT INTO employee_submitted_documents (employee_id, document_type, document_name, document_number, submission_date, verification_status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['employee_id'],
                $data['document_type'],
                $data['document_name'],
                $data['document_number'] ?? '',
                $data['submission_date'] ?? date('Y-m-d'),
                $data['verification_status'] ?? 'Submitted',
                $data['remarks'] ?? ''
            ]);

            $_SESSION['flash_success'] = 'Submitted document record logged successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/submitted-documents' : '/hr/employees/submitted-documents');
            return;
        }

        $stmt = $this->pdo->query("SELECT esd.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code, d.department_name FROM employee_submitted_documents esd JOIN employees e ON esd.employee_id = e.employee_id LEFT JOIN departments d ON e.department_id = d.department_id ORDER BY esd.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_info/submitted_documents', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 7. Employee Vaccination Information
    public function vaccinationInfo(): void
    {
        $this->requireAuth();
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $stmt = $this->pdo->prepare("INSERT INTO employee_vaccinations (employee_id, vaccine_name, dose_number, vaccination_date, certificate_no, dose_status, center_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['employee_id'],
                $data['vaccine_name'],
                $data['dose_number'] ?? '1st Dose',
                $data['vaccination_date'] ?? date('Y-m-d'),
                $data['certificate_no'] ?? '',
                $data['dose_status'] ?? 'Fully Vaccinated',
                $data['center_name'] ?? ''
            ]);

            $_SESSION['flash_success'] = 'Vaccination record logged successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/vaccination-info' : '/hr/employees/vaccination-info');
            return;
        }

        $stmt = $this->pdo->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code, d.department_name FROM employee_vaccinations ev JOIN employees e ON ev.employee_id = e.employee_id LEFT JOIN departments d ON e.department_id = d.department_id ORDER BY ev.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_info/vaccination_info', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 8. Health and Medical Information
    public function healthMedicalInfo(): void
    {
        $this->requireAuth();
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $stmt = $this->pdo->prepare("INSERT INTO employee_health_records (employee_id, blood_group, height_cm, weight_kg, medical_conditions, allergies, doctor_clearance_date, checkup_date, insurance_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['employee_id'],
                $data['blood_group'] ?? 'O+',
                $data['height_cm'] ?? null,
                $data['weight_kg'] ?? null,
                $data['medical_conditions'] ?? $data['medical_fitness'] ?? '',
                $data['allergies'] ?? $data['doctor_remarks'] ?? '',
                $data['doctor_clearance_date'] ?? null,
                $data['checkup_date'] ?? date('Y-m-d'),
                $data['insurance_no'] ?? ''
            ]);

            $_SESSION['flash_success'] = 'Employee health & medical record saved.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/health-medical' : '/hr/employees/health-medical');
            return;
        }

        $stmt = $this->pdo->query("SELECT ehr.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code, d.department_name FROM employee_health_records ehr JOIN employees e ON ehr.employee_id = e.employee_id LEFT JOIN departments d ON e.department_id = d.department_id ORDER BY ehr.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_info/health_medical', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 9. Tax Certificate Information
    public function taxCertificateInfo(): void
    {
        $this->requireAuth();
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $stmt = $this->pdo->prepare("INSERT INTO employee_tax_certificates (employee_id, tin_number, tax_circle, tax_zone, assessment_year, tax_amount, tds_deducted, submission_status, submission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['employee_id'],
                $data['tin_number'],
                $data['tax_circle'] ?? '',
                $data['tax_zone'] ?? '',
                $data['assessment_year'] ?? $data['tax_year'] ?? '2025-2026',
                $data['tax_amount'] ?? 0.00,
                $data['tds_deducted'] ?? 0.00,
                $data['submission_status'] ?? 'Submitted',
                $data['submission_date'] ?? date('Y-m-d')
            ]);

            $_SESSION['flash_success'] = 'Tax certificate information saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/employees/tax-certificate' : '/hr/employees/tax-certificate');
            return;
        }

        $stmt = $this->pdo->query("SELECT etc.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code, d.department_name FROM employee_tax_certificates etc JOIN employees e ON etc.employee_id = e.employee_id LEFT JOIN departments d ON e.department_id = d.department_id ORDER BY etc.created_at DESC");
        $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/employee_info/tax_certificate', [
            'records' => $records,
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }
}
