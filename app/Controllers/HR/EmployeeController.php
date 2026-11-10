<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use App\Models\Employee;
use App\Services\EmployeeService;
use Core\Container;

class EmployeeController extends BaseController
{
    private EmployeeService $employeeService;

    public function __construct()
    {
        parent::__construct();
        $this->employeeService = Container::get(EmployeeService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $page = max(1, (int) ($this->request->get('p', 1)));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $search = $this->request->get('q', '');

        // Build where conditions
        $where = [];
        if (!empty($search)) {
            $where['OR'] = [
                'employees.first_name[~]' => $search,
                'employees.last_name[~]' => $search,
                'employees.email[~]' => $search,
                'employees.employee_code[~]' => $search
            ];
        }

        // Get total count
        $total = $this->db->count('employees', $where);

        // Add limit and offset
        $where['LIMIT'] = [$offset, $limit];
        // Add sorting
        $where['ORDER'] = ['employees.created_at' => 'DESC'];

        // Get paginated data with joins
        $employees = $this->db->select('employees', [
            '[>]departments' => ['employees.department_id' => 'departments.department_id'],
            '[>]positions' => ['employees.position_id' => 'positions.position_id']
        ], [
            'employees.*',
            'department_name' => 'departments.department_name',
            'position_title' => 'positions.position_name'
        ], $where);

        $this->view('hr/employees/index', [
            'employees' => $employees,
            'pagination' => [
                'current' => $page,
                'total' => ceil($total / $limit),
                'limit' => $limit,
                'totalItems' => $total
            ],
            'request' => ['q' => $search]
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('hr_manager');

        // Get dropdown data
        $departments = $this->db->select('departments', '*', ['ORDER' => 'department_name']);
        $positions = $this->db->select('positions', '*', ['ORDER' => 'position_name']);
        $sections = $this->db->select('sections', '*', ['ORDER' => 'section_name']);
        $lines = $this->db->select('production_lines', '*', ['ORDER' => 'line_name']);
        $shifts = $this->db->select('shifts', '*', ['ORDER' => 'shift_name']);

        $this->view('hr/employees/create', [
            'departments' => $departments,
            'positions' => $positions,
            'sections' => $sections,
            'lines' => $lines,
            'shifts' => $shifts
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('hr_manager');

        $data = $this->request->all();
        
        $rules = [
            'employee_code' => 'required|max:20',
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email',
            'phone' => 'required|max:20',
            'date_of_birth' => 'required',
            'gender' => 'required',
            'department_id' => 'required',
            'position_id' => 'required',
            'employment_type' => 'required',
            'joining_date' => 'required',
            'basic_salary' => 'required'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        // Map form fields to database structure
        $employeeData = [
            'employee_code' => $data['employee_code'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'alternate_phone' => $data['alternate_phone'] ?? '',
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
            'blood_group' => $data['blood_group'] ?? '',
            'marital_status' => $data['marital_status'] ?? '',
            'nationality' => $data['nationality'] ?? '',
            'religion' => $data['religion'] ?? '',
            'nid_number' => $data['nid_number'] ?? '',
            'passport_number' => $data['passport_number'] ?? '',
            'present_address' => $data['present_address'] ?? '',
            'permanent_address' => $data['permanent_address'] ?? '',
            'city' => $data['city'] ?? '',
            'postal_code' => $data['postal_code'] ?? '',
            'country' => $data['country'] ?? '',
            'department_id' => $data['department_id'],
            'position_id' => $data['position_id'],
            'section_id' => $data['section_id'] ?? 0,
            'line_id' => $data['line_id'] ?? 0,
            'shift_id' => $data['shift_id'] ?? 0,
            'employment_type' => $data['employment_type'],
            'hire_date' => $data['joining_date'],
            'confirmation_date' => $data['confirmation_date'] ?? null,
            'salary' => $data['basic_salary'],
            'basic_salary' => $data['basic_salary'],
            'house_rent' => $data['house_rent'] ?? 0,
            'medical_allowance' => $data['medical_allowance'] ?? 0,
            'transport_allowance' => $data['transport_allowance'] ?? 0,
            'food_allowance' => $data['food_allowance'] ?? 0,
            'other_allowances' => $data['other_allowances'] ?? 0,
            'bank_name' => $data['bank_name'] ?? '',
            'bank_account' => $data['bank_account'] ?? '',
            'bank_branch' => $data['bank_branch'] ?? '',
            'emergency_contact_name' => $data['emergency_contact_name'] ?? '',
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? '',
            'emergency_contact_relation' => $data['emergency_contact_relation'] ?? '',
            'education_qualification' => $data['education_qualification'] ?? '',
            'experience_years' => $data['experience_years'] ?? 0,
            'previous_company' => $data['previous_company'] ?? '',
            'skills' => $data['skills'] ?? '',
            'notes' => $data['notes'] ?? '',
            'created_by' => $this->auth->user()['user_id'] ?? $this->auth->user()['id'] ?? $_SESSION['user_id'] ?? 1,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->employeeService->createEmployee($employeeData);

        if ($result['success']) {
            $this->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'employee_id' => $result['employee_id']
            ]);
        } else {
            $this->json([
                'success' => false,
                'message' => $result['message']
            ], 500);
        }
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $employee = new Employee();
        $employeeData = $employee->find($id);

        if (!$employeeData) {
            $this->json(['error' => 'Employee not found'], 404);
            return;
        }

        // Get related data
        $attendance = $this->db->select('attendance', '*', [
            'employee_id' => $id,
            'ORDER' => ['date' => 'DESC'],
            'LIMIT' => 30
        ]);

        $payroll = $this->db->select('payroll', '*', [
            'employee_id' => $id,
            'ORDER' => ['pay_date' => 'DESC'],
            'LIMIT' => 12
        ]);

        $this->view('hr/employees/show', [
            'employee' => $employeeData,
            'attendance' => $attendance,
            'payroll' => $payroll
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('hr_manager');

        $employee = new Employee();
        $employeeData = $employee->find($id);

        if (!$employeeData) {
            $this->redirect('hr/employees');
            return;
        }

        // Get dropdown data
        $departments = $this->db->select('departments', '*', ['ORDER' => 'department_name']);
        $positions = $this->db->select('positions', '*', ['ORDER' => 'position_name']);
        $sections = $this->db->select('sections', '*', ['ORDER' => 'section_name']);
        $lines = $this->db->select('production_lines', '*', ['ORDER' => 'line_name']);
        $shifts = $this->db->select('shifts', '*', ['ORDER' => 'shift_name']);

        $this->view('hr/employees/edit', [
            'employee' => $employeeData,
            'departments' => $departments,
            'positions' => $positions,
            'sections' => $sections,
            'lines' => $lines,
            'shifts' => $shifts
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('hr_manager');

        $data = $this->request->all();
        
        $rules = [
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email',
            'phone' => 'required|max:20',
            'department_id' => 'required',
            'position_id' => 'required'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        // Map form fields to database structure
        $employeeData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'alternate_phone' => $data['alternate_phone'] ?? '',
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
            'blood_group' => $data['blood_group'] ?? '',
            'marital_status' => $data['marital_status'] ?? '',
            'nationality' => $data['nationality'] ?? '',
            'religion' => $data['religion'] ?? '',
            'nid_number' => $data['nid_number'] ?? '',
            'passport_number' => $data['passport_number'] ?? '',
            'present_address' => $data['present_address'] ?? '',
            'permanent_address' => $data['permanent_address'] ?? '',
            'city' => $data['city'] ?? '',
            'postal_code' => $data['postal_code'] ?? '',
            'country' => $data['country'] ?? '',
            'department_id' => $data['department_id'],
            'position_id' => $data['position_id'],
            'section_id' => $data['section_id'] ?? 0,
            'line_id' => $data['line_id'] ?? 0,
            'shift_id' => $data['shift_id'] ?? 0,
            'employment_type' => $data['employment_type'],
            'hire_date' => $data['joining_date'],
            'confirmation_date' => $data['confirmation_date'] ?? null,
            'salary' => $data['basic_salary'],
            'basic_salary' => $data['basic_salary'],
            'house_rent' => $data['house_rent'] ?? 0,
            'medical_allowance' => $data['medical_allowance'] ?? 0,
            'transport_allowance' => $data['transport_allowance'] ?? 0,
            'food_allowance' => $data['food_allowance'] ?? 0,
            'other_allowances' => $data['other_allowances'] ?? 0,
            'bank_name' => $data['bank_name'] ?? '',
            'bank_account' => $data['bank_account'] ?? '',
            'bank_branch' => $data['bank_branch'] ?? '',
            'emergency_contact_name' => $data['emergency_contact_name'] ?? '',
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? '',
            'emergency_contact_relation' => $data['emergency_contact_relation'] ?? '',
            'education_qualification' => $data['education_qualification'] ?? '',
            'experience_years' => $data['experience_years'] ?? 0,
            'previous_company' => $data['previous_company'] ?? '',
            'skills' => $data['skills'] ?? '',
            'notes' => $data['notes'] ?? '',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->employeeService->updateEmployee($id, $employeeData);

        if ($result['success']) {
            $this->json([
                'success' => true,
                'message' => 'Employee updated successfully'
            ]);
        } else {
            $this->json([
                'success' => false,
                'message' => $result['message']
            ], 500);
        }
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('hr_manager');

        $result = $this->employeeService->deleteEmployee($id);

        if ($result['success']) {
            $this->json([
                'success' => true,
                'message' => 'Employee deleted successfully'
            ]);
        } else {
            $this->json([
                'success' => false,
                'message' => $result['message'],
                'dependencies' => $result['dependencies'] ?? []
            ], 400);
        }
    }

    public function search(): void
    {
        // Redirect to index which handles search now
        $query = $this->request->get('q', '');
        $this->redirect('hr/employees?q=' . urlencode($query));
    }

    public function export(): void
    {
        $this->requireAuth();
        $this->requireRole('hr_manager');

        $employee = new Employee();
        $employees = $employee->orderBy('last_name')->get();

        // CSV export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="employees.csv"');

        $output = fopen('php://output', 'w');

        // Header
        fputcsv($output, [
            'Employee Code', 'First Name', 'Last Name', 'Email', 'Phone',
            'Department', 'Position', 'Employment Type', 'Status', 'Joining Date'
        ]);

        // Data
        foreach ($employees as $emp) {
            fputcsv($output, [
                $emp['employee_code'],
                $emp['first_name'],
                $emp['last_name'],
                $emp['email'],
                $emp['phone'],
                $emp['department_id'],
                $emp['position_id'],
                $emp['employment_type'],
                $emp['status'],
                $emp['hire_date']
            ]);
        }

        fclose($output);
    }
}
