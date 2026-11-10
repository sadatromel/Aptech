<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use App\Models\Attendance;
use App\Models\Employee;

class AttendanceController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $date = $this->request->get('date', date('Y-m-d'));
        $department = $this->request->get('department', '');

        $attendanceModel = new Attendance();
        $attendance = $attendanceModel->getDailyAttendance($date, $department);

        // Get departments for filter
        $departments = $this->db->select('departments', '*', ['ORDER' => 'department_name']);
        
        // Get active employees for the modal
        $employeeModel = new Employee();
        $employees = $employeeModel->activeEmployees();

        $this->view('hr/attendance/index', [
            'attendance' => $attendance,
            'departments' => $departments,
            'employees' => $employees,
            'selectedDate' => $date,
            'selectedDepartment' => $department
        ]);
    }

    public function get(int $id): void
    {
        $this->requireAuth();
        
        $attendanceModel = new Attendance();
        $record = $attendanceModel->find($id);

        if ($record) {
            $this->json($record);
        } else {
            $this->json(['error' => 'Attendance record not found'], 404);
        }
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('hr_manager');

        $data = $this->request->all();
        
        $rules = [
            'employee_id' => 'required',
            'date' => 'required',
            'status' => 'required'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            // If AJAX, return JSON. If Form submit, redirect.
            // For now, assuming hybrid.
            if ($this->request->isAjax()) {
                $this->json(['errors' => $errors], 422);
            } else {
                // Flash error and redirect
                // $_SESSION['error'] = 'Validation failed';
                 header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/attendance');
            }
            return;
        }

        try {
            // Fetch employee's department
            $employeeModel = new Employee();
            $employee = $employeeModel->find((int)$data['employee_id']);
            
            $attendanceData = [
                'employee_id' => $data['employee_id'],
                'department_id' => $employee['department_id'] ?? 0,
                'date' => $data['date'],
                'check_in' => $data['check_in'] ?? date('H:i:s'),
                'check_out' => !empty($data['check_out']) ? $data['check_out'] : null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? '',
                'created_by' => $this->auth->user()['user_id'] ?? $this->auth->user()['id'] ?? $_SESSION['user_id'] ?? 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('attendance', $attendanceData);

            if ($this->request->isAjax()) {
                $this->json([
                    'success' => true,
                    'message' => 'Attendance recorded successfully'
                ]);
            } else {
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/attendance?success=true');
            }
        } catch (\Exception $e) {
            if ($this->request->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => 'Failed to record attendance: ' . $e->getMessage()
                ], 500);
            } else {
                 header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/attendance?error=' . urlencode($e->getMessage()));
            }
        }
    }
}
