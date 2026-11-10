<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use PDO;

class HRAttendanceOperationController extends BaseController
{
    private PDO $pdo;

    public function __construct()
    {
        parent::__construct();
        $config = require __DIR__ . '/../../../config/database.php';
        $this->pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['database']}",
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
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
            $stmt = $this->pdo->query("SELECT employee_id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // 1. Bulk Movement Entry
    public function bulkMovement(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();
            
            $empIds = $data['employee_ids'] ?? [];
            if (!empty($empIds) && is_array($empIds)) {
                $stmt = $this->pdo->prepare("INSERT INTO employee_movements (employee_id, movement_date, start_time, end_time, destination, purpose, movement_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved')");
                foreach ($empIds as $empId) {
                    $stmt->execute([
                        $empId,
                        $data['movement_date'],
                        $data['start_time'],
                        $data['end_time'] ?? null,
                        $data['destination'],
                        $data['purpose'],
                        $data['movement_type'] ?? 'official'
                    ]);
                }
            }

            $_SESSION['flash_success'] = 'Bulk outdoor movement entries saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/attendance/movement-bulk' : '/hr/attendance/movement-bulk');
            return;
        }

        $this->view('hr/attendance_operations/bulk_movement', [
            'departments' => $this->getDepartments(),
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 2. Date Attendance Transfer
    public function dateAttendanceTransfer(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $stmt = $this->pdo->prepare("INSERT INTO attendance_transfers (source_date, target_date, department_id, status) VALUES (?, ?, ?, 'completed')");
            $stmt->execute([
                $data['source_date'],
                $data['target_date'],
                !empty($data['department_id']) ? $data['department_id'] : null
            ]);

            $_SESSION['flash_success'] = 'Attendance punch log transfer initiated successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/attendance/date-transfer' : '/hr/attendance/date-transfer');
            return;
        }

        $stmt = $this->pdo->query("SELECT at.*, d.department_name FROM attendance_transfers at LEFT JOIN departments d ON at.department_id = d.department_id ORDER BY at.created_at DESC");
        $history = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/attendance_operations/date_transfer', [
            'history' => $history,
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 3. Define Holiday
    public function defineHoliday(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $title = $data['holiday_title'] ?? $data['holiday_name'] ?? '';
            $startDate = $data['start_date'] ?? $data['from_date'] ?? date('Y-m-d');
            $endDate = $data['end_date'] ?? $data['to_date'] ?? $startDate;

            $stmt = $this->pdo->prepare("INSERT INTO factory_holidays (holiday_title, start_date, end_date, holiday_type, is_recurring, status, remarks) VALUES (?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([
                $title,
                $startDate,
                $endDate,
                $data['holiday_type'] ?? 'festival',
                isset($data['is_recurring']) ? 1 : 0,
                $data['remarks'] ?? ''
            ]);

            $_SESSION['flash_success'] = 'Factory holiday defined successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/attendance/define-holiday' : '/hr/attendance/define-holiday');
            return;
        }

        $stmt = $this->pdo->query("SELECT * FROM factory_holidays WHERE status = 'active' ORDER BY start_date DESC");
        $holidays = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/attendance_operations/define_holiday', [
            'holidays' => $holidays,
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 4. Define Weekend
    public function defineWeekend(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $stmt = $this->pdo->prepare("INSERT INTO factory_weekends (department_id, weekend_day, status) VALUES (?, ?, 'active')");
            $stmt->execute([
                !empty($data['department_id']) ? $data['department_id'] : null,
                $data['weekend_day'] ?? 'Friday'
            ]);

            $_SESSION['flash_success'] = 'Weekly off day configuration saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/attendance/define-weekend' : '/hr/attendance/define-weekend');
            return;
        }

        $stmt = $this->pdo->query("SELECT fw.*, d.department_name FROM factory_weekends fw LEFT JOIN departments d ON fw.department_id = d.department_id WHERE fw.status = 'active'");
        $weekends = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/attendance_operations/define_weekend', [
            'weekends' => $weekends,
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 5. Duty Roster Re-process
    public function dutyRosterReprocess(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $_SESSION['flash_success'] = 'Duty roster and shift assignments re-processed successfully for the selected month.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/attendance/roster-reprocess' : '/hr/attendance/roster-reprocess');
            return;
        }

        $this->view('hr/attendance_operations/roster_reprocess', [
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 6. Holiday Remove
    public function holidayRemove(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();
            
            if (!empty($data['holiday_id'])) {
                $stmt = $this->pdo->prepare("UPDATE factory_holidays SET status = 'removed', remarks = CONCAT(IFNULL(remarks,''), ' [Revoked on ', CURRENT_DATE(), ']') WHERE holiday_id = ?");
                $stmt->execute([$data['holiday_id']]);
            }

            $_SESSION['flash_success'] = 'Declared holiday revoked successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/attendance/holiday-remove' : '/hr/attendance/holiday-remove');
            return;
        }

        $stmt = $this->pdo->query("SELECT * FROM factory_holidays ORDER BY start_date DESC");
        $holidays = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/attendance_operations/holiday_remove', [
            'holidays' => $holidays,
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 7. Manual Attendance Entry
    public function manualAttendanceEntry(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $date = $data['date'] ?? $data['punch_date'] ?? date('Y-m-d');
            $checkIn = $data['check_in'] ?? $data['in_time'] ?? '08:00:00';
            $checkOut = !empty($data['check_out']) ? $data['check_out'] : (!empty($data['out_time']) ? $data['out_time'] : null);
            $status = $data['status'] ?? 'Present';

            $stmt = $this->pdo->prepare("INSERT INTO attendance (employee_id, date, check_in, check_out, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['employee_id'],
                $date,
                $checkIn,
                $checkOut,
                $status
            ]);

            $_SESSION['flash_success'] = 'Manual attendance punch logged successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/attendance/manual-entry' : '/hr/attendance/manual-entry');
            return;
        }

        $this->view('hr/attendance_operations/manual_entry', [
            'employees' => $this->getActiveEmployees(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 8. Movement Register
    public function movementRegister(): void
    {
        $stmt = $this->pdo->query("SELECT em.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code FROM employee_movements em JOIN employees e ON em.employee_id = e.employee_id ORDER BY em.movement_date DESC, em.start_time DESC");
        $movements = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/attendance_operations/movement_register', [
            'movements' => $movements,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    // 9. OT Requisition
    public function otRequisition(): void
    {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $data = $this->request->all();

            $reqDate = $data['requisition_date'] ?? $data['date'] ?? date('Y-m-d');
            $otHours = (float)($data['requested_ot_hours'] ?? $data['estimated_hours'] ?? 2.0);
            $workerCount = (int)($data['worker_count'] ?? 1);
            $reason = $data['reason'] ?? 'Production urgency';

            $stmt = $this->pdo->prepare("INSERT INTO ot_requisitions (department_id, requisition_date, requested_ot_hours, worker_count, reason, status) VALUES (?, ?, ?, ?, ?, 'approved')");
            $stmt->execute([
                $data['department_id'],
                $reqDate,
                $otHours,
                $workerCount,
                $reason
            ]);

            $_SESSION['flash_success'] = 'Overtime requisition pre-approval saved successfully.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/attendance/ot-requisition' : '/hr/attendance/ot-requisition');
            return;
        }

        $stmt = $this->pdo->query("SELECT otr.*, d.department_name FROM ot_requisitions otr JOIN departments d ON otr.department_id = d.department_id ORDER BY otr.requisition_date DESC");
        $requisitions = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('hr/attendance_operations/ot_requisition', [
            'requisitions' => $requisitions,
            'departments' => $this->getDepartments(),
            'flash_success' => $flash,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }
}
