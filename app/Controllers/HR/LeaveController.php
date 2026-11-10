<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;

class LeaveController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $status = $this->request->get('status', '');

        // Base where clause
        $where = [];
        if (!empty($status)) {
            $where['leaves.status'] = $status;
        }

        // Get leave records with employee details
        $leaves = $this->db->select('leaves', [
            '[>]employees' => ['leaves.employee_id' => 'employees.employee_id'],
            '[>]departments' => ['employees.department_id' => 'departments.department_id']
        ], [
            'leaves.id',
            'leaves.leave_type',
            'leaves.start_date',
            'leaves.end_date',
            'leaves.reason',
            'leaves.status',
            'leaves.created_at',
            'employees.first_name',
            'employees.last_name',
            'employees.employee_code',
            'departments.department_name'
        ], [
            'WHERE' => $where,
            'ORDER' => ['leaves.created_at' => 'DESC']
        ]);

        // Calculate stats
        $stats = [
            'pending' => $this->db->count('leaves', ['status' => 'Pending']),
            'approved' => $this->db->count('leaves', ['status' => 'Approved']),
            'rejected' => $this->db->count('leaves', ['status' => 'Rejected']),
            'total' => $this->db->count('leaves')
        ];

        // Get employees list for the modal
        $employees = $this->db->select('employees', [
            'employee_id',
            'first_name',
            'last_name',
            'employee_code'
        ], [
            'status' => 'active',
            'ORDER' => 'first_name'
        ]);

        $this->view('hr/leaves/index', [
            'leaves' => $leaves,
            'employees' => $employees,
            'stats' => $stats,
            'selectedStatus' => $status
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        // Basic validation could be improved
        $data = $this->request->all();
        
        if (empty($data['employee_id']) || empty($data['leave_type']) || empty($data['start_date']) || empty($data['end_date'])) {
             // Handle error - for now redirect back
             header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/leaves');
             exit;
        }

        try {
            $this->db->insert('leaves', [
                'employee_id' => $data['employee_id'],
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'] ?? '',
                'status' => 'Pending',
                'created_by' => $this->auth->user()['user_id'] ?? $this->auth->user()['id'] ?? $_SESSION['user_id'] ?? 1
            ]);

            // Success redirect
             header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/leaves?success=true');
        } catch (\Exception $e) {
             // Error redirect
             header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/leaves?error=' . urlencode($e->getMessage()));
        }
    }

    public function create(): void
    {
        $this->entryPage();
    }

    public function updateStatus(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $leaveId = (int)$this->request->get('leave_id');
        $status = $this->request->get('status');

        if ($leaveId && in_array($status, ['Approved', 'Rejected', 'Pending'])) {
            $this->db->update('leaves', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                'id' => $leaveId
            ]);
            $this->json(['success' => true, 'message' => "Leave application #{$leaveId} updated to {$status}."]);
            return;
        }

        $this->json(['success' => false, 'message' => 'Invalid leave ID or status.'], 400);
    }

    public function deletePage(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->deletePerform();
            return;
        }

        $this->requireAuth();

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        // Filter inputs
        $empCode = trim($this->request->get('emp_code', ''));
        $empId = trim($this->request->get('emp_id', ''));
        $departmentId = $this->request->get('department_id', '');
        $sectionId = $this->request->get('section_id', '');
        $positionId = $this->request->get('position_id', '');
        $leaveType = trim($this->request->get('leave_type', ''));
        $appFrom = trim($this->request->get('app_from', ''));
        $appTo = trim($this->request->get('app_to', ''));
        $delFrom = trim($this->request->get('del_from', ''));
        $delTo = trim($this->request->get('del_to', ''));

        // Build WHERE clause
        $where = [];
        if (!empty($empCode)) {
            $where['employees.employee_code[~]'] = $empCode;
        }
        if (!empty($empId)) {
            $where['leaves.employee_id'] = (int)$empId;
        }
        if (!empty($departmentId)) {
            $where['employees.department_id'] = (int)$departmentId;
        }
        if (!empty($sectionId)) {
            $where['employees.section_id'] = (int)$sectionId;
        }
        if (!empty($positionId)) {
            $where['employees.position_id'] = (int)$positionId;
        }
        if (!empty($leaveType) && $leaveType !== '--Select--') {
            $where['leaves.leave_type'] = $leaveType;
        }
        if (!empty($appFrom)) {
            $where['leaves.created_at[>=]'] = $appFrom . ' 00:00:00';
        }
        if (!empty($appTo)) {
            $where['leaves.created_at[<=]'] = $appTo . ' 23:59:59';
        }
        if (!empty($delFrom)) {
            $where['leaves.start_date[>=]'] = $delFrom;
        }
        if (!empty($delTo)) {
            $where['leaves.end_date[<=]'] = $delTo;
        }

        // Get leaves with joined employee details
        $leavesWhere = $where;
        $leavesWhere['ORDER'] = ['leaves.id' => 'DESC'];

        $leaves = $this->db->select('leaves', [
            '[>]employees' => ['leaves.employee_id' => 'employees.employee_id'],
            '[>]departments' => ['employees.department_id' => 'departments.department_id'],
            '[>]sections' => ['employees.section_id' => 'sections.section_id'],
            '[>]positions' => ['employees.position_id' => 'positions.position_id']
        ], [
            'leaves.id',
            'leaves.employee_id',
            'leaves.leave_type',
            'leaves.start_date',
            'leaves.end_date',
            'leaves.reason',
            'leaves.status',
            'leaves.created_at',
            'employees.first_name',
            'employees.last_name',
            'employees.employee_code',
            'departments.department_name',
            'sections.section_name',
            'positions.position_name'
        ], $leavesWhere);

        // Standard Lookup datasets with explicit table qualifications
        $employees = $this->db->select('employees', [
            '[>]departments' => ['employees.department_id' => 'departments.department_id'],
            '[>]sections' => ['employees.section_id' => 'sections.section_id'],
            '[>]positions' => ['employees.position_id' => 'positions.position_id']
        ], [
            'employees.employee_id',
            'employees.employee_code',
            'employees.first_name',
            'employees.last_name',
            'employees.department_id',
            'employees.section_id',
            'employees.position_id',
            'departments.department_name',
            'sections.section_name',
            'positions.position_name'
        ], [
            'employees.status' => 'active',
            'ORDER' => ['employees.first_name' => 'ASC']
        ]);

        $departments = $this->db->select('departments', ['department_id', 'department_name'], [
            'departments.status' => 'active',
            'ORDER' => ['department_name' => 'ASC']
        ]);

        $sections = $this->db->select('sections', ['section_id', 'section_name', 'department_id'], [
            'sections.status' => 'active',
            'ORDER' => ['section_name' => 'ASC']
        ]);

        $positions = $this->db->select('positions', ['position_id', 'position_name'], [
            'positions.status' => 'active',
            'ORDER' => ['position_name' => 'ASC']
        ]);

        $companies = $this->db->select('factories', ['factory_id', 'factory_name', 'factory_code'], [
            'factories.status' => 'active',
            'ORDER' => ['factory_name' => 'ASC']
        ]);

        // Defined reference arrays matching factory specs
        $categories = ['Worker', 'Staff', 'Executive', 'Daily Rated', 'Monthly Rated'];
        $locations = [
            '30 Sarabo, Kashimpur, Gazipur',
            'Head Office, Dhaka',
            'Factory Unit 1 - Kashimpur',
            'Factory Unit 2 - Sarabo',
            'Washing Plant - Gazipur'
        ];
        $divisions = ['Apparel Division', 'Textile Division', 'Operations Division', 'Admin & HR Division'];
        $subsections = ['Subsection A', 'Subsection B', 'Subsection C', 'General Line'];
        
        $leaveTypes = [
            'Casual Leave',
            'Sick Leave',
            'Earned Leave',
            'Leave Without Pay',
            'Maternity Leave',
            'Miscarriage Leave',
            'WPIL',
            'Paternity Leave',
            'Special Leave',
            'Suspended Leave',
            'Short Leave',
            'Lay Off Leave',
            'Lay Off Weekend',
            'Lay Off Holiday',
            'National Holiday',
            'GFT',
            'BFT'
        ];

        // Stats summary
        $stats = [
            'total_all' => $this->db->count('leaves'),
            'filtered' => count($leaves),
            'approved' => $this->db->count('leaves', ['status' => 'Approved']),
            'pending' => $this->db->count('leaves', ['status' => 'Pending'])
        ];

        $this->view('hr/leaves/delete', [
            'leaves' => $leaves,
            'employees' => $employees,
            'departments' => $departments,
            'sections' => $sections,
            'positions' => $positions,
            'companies' => $companies,
            'categories' => $categories,
            'locations' => $locations,
            'divisions' => $divisions,
            'subsections' => $subsections,
            'leaveTypes' => $leaveTypes,
            'stats' => $stats,
            'filters' => [
                'emp_code' => $empCode,
                'emp_id' => $empId,
                'department_id' => $departmentId,
                'section_id' => $sectionId,
                'position_id' => $positionId,
                'leave_type' => $leaveType,
                'app_from' => $appFrom,
                'app_to' => $appTo,
                'del_from' => $delFrom,
                'del_to' => $delTo
            ],
            'flash_success' => $flashSuccess,
            'flash_error' => $flashError
        ]);
    }

    public function deletePerform(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $leaveId = $this->request->input('leave_id');
        $leaveIds = $this->request->input('leave_ids');
        $remarks = trim((string)$this->request->input('remarks', ''));

        $idsToDelete = [];
        if (!empty($leaveId)) {
            $idsToDelete[] = (int)$leaveId;
        }
        if (!empty($leaveIds) && is_array($leaveIds)) {
            foreach ($leaveIds as $id) {
                $idsToDelete[] = (int)$id;
            }
        }

        $idsToDelete = array_unique(array_filter($idsToDelete));

        if (empty($idsToDelete)) {
            $_SESSION['flash_error'] = 'No valid leave record selected for deletion.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/leave-delete' : '/hr/leave-delete');
            return;
        }

        try {
            $count = 0;
            foreach ($idsToDelete as $id) {
                $result = $this->db->delete('leaves', ['id' => $id]);
                if ($result) {
                    $count++;
                }
            }

            $msg = ($count === 1) 
                ? "Leave record #{$idsToDelete[0]} was successfully deleted." 
                : "Successfully deleted {$count} leave record(s).";

            if (!empty($remarks)) {
                $msg .= " Remarks logged: " . htmlspecialchars($remarks);
            }

            $_SESSION['flash_success'] = $msg;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error deleting leave record(s): ' . $e->getMessage();
        }

        $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/leave-delete' : '/hr/leave-delete');
    }

    public function getEmployeeDetailsApi(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $empCode = trim($this->request->get('code', ''));
        $empId = $this->request->get('id', '');

        $where = [];
        if (!empty($empCode)) {
            $where['employees.employee_code'] = $empCode;
        } elseif (!empty($empId)) {
            $where['employees.employee_id'] = (int)$empId;
        } else {
            echo json_encode(['success' => false, 'message' => 'No parameters provided']);
            exit;
        }

        $whereClause = !empty($empCode) ? "employees.employee_code = ?" : "employees.employee_id = ?";
        $param = !empty($empCode) ? $empCode : (int)$empId;

        $stmt = $this->db->query("
            SELECT 
                employees.employee_id,
                employees.employee_code,
                employees.first_name,
                employees.last_name,
                employees.department_id,
                employees.section_id,
                employees.position_id,
                departments.department_name,
                sections.section_name,
                positions.position_name
            FROM employees
            LEFT JOIN departments ON employees.department_id = departments.department_id
            LEFT JOIN sections ON employees.section_id = sections.section_id
            LEFT JOIN positions ON employees.position_id = positions.position_id
            WHERE {$whereClause}
            LIMIT 1
        ", [$param]);

        $emp = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;

        if ($emp) {
            echo json_encode([
                'success' => true,
                'employee' => [
                    'id' => $emp['employee_id'],
                    'code' => $emp['employee_code'],
                    'name' => trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')),
                    'department_id' => $emp['department_id'],
                    'department_name' => $emp['department_name'] ?? 'N/A',
                    'section_id' => $emp['section_id'],
                    'section_name' => $emp['section_name'] ?? 'N/A',
                    'position_id' => $emp['position_id'],
                    'position_name' => $emp['position_name'] ?? 'N/A'
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
        }
        exit;
    }

    public function entryPage(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->storeEntry();
            return;
        }

        $this->requireAuth();

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        // Standard Lookup datasets
        $employees = $this->db->select('employees', [
            '[>]departments' => ['employees.department_id' => 'departments.department_id'],
            '[>]sections' => ['employees.section_id' => 'sections.section_id'],
            '[>]positions' => ['employees.position_id' => 'positions.position_id']
        ], [
            'employees.employee_id',
            'employees.employee_code',
            'employees.first_name',
            'employees.last_name',
            'employees.department_id',
            'employees.section_id',
            'employees.position_id',
            'departments.department_name',
            'sections.section_name',
            'positions.position_name'
        ], [
            'employees.status' => 'active',
            'ORDER' => ['employees.first_name' => 'ASC']
        ]);

        $departments = $this->db->select('departments', ['department_id', 'department_name'], [
            'departments.status' => 'active',
            'ORDER' => ['department_name' => 'ASC']
        ]);

        $sections = $this->db->select('sections', ['section_id', 'section_name', 'department_id'], [
            'sections.status' => 'active',
            'ORDER' => ['section_name' => 'ASC']
        ]);

        $positions = $this->db->select('positions', ['position_id', 'position_name'], [
            'positions.status' => 'active',
            'ORDER' => ['position_name' => 'ASC']
        ]);

        $companies = $this->db->select('factories', ['factory_id', 'factory_name', 'factory_code'], [
            'factories.status' => 'active',
            'ORDER' => ['factory_name' => 'ASC']
        ]);

        $categories = ['Worker', 'Staff', 'Executive', 'Daily Rated', 'Monthly Rated'];
        $locations = [
            '30 Sarabo, Kashimpur, Gazipur',
            'Head Office, Dhaka',
            'Factory Unit 1 - Kashimpur',
            'Factory Unit 2 - Sarabo',
            'Washing Plant - Gazipur'
        ];
        $divisions = ['Apparel Division', 'Textile Division', 'Operations Division', 'Admin & HR Division'];
        $subsections = ['Subsection A', 'Subsection B', 'Subsection C', 'General Line'];
        
        $leaveTypes = [
            'Casual Leave',
            'Sick Leave',
            'Earned Leave',
            'Leave Without Pay',
            'Maternity Leave',
            'Miscarriage Leave',
            'WPIL',
            'Paternity Leave',
            'Special Leave',
            'Suspended Leave',
            'Short Leave',
            'Lay Off Leave',
            'Lay Off Weekend',
            'Lay Off Holiday',
            'National Holiday',
            'GFT',
            'BFT'
        ];

        // Recent entries for grid list
        $recentLeaves = $this->db->select('leaves', [
            '[>]employees' => ['leaves.employee_id' => 'employees.employee_id'],
            '[>]departments' => ['employees.department_id' => 'departments.department_id']
        ], [
            'leaves.id',
            'leaves.employee_id',
            'leaves.leave_type',
            'leaves.start_date',
            'leaves.end_date',
            'leaves.total_days',
            'leaves.days_part',
            'leaves.reason',
            'leaves.status',
            'leaves.created_at',
            'employees.first_name',
            'employees.last_name',
            'employees.employee_code',
            'departments.department_name'
        ], [
            'ORDER' => ['leaves.id' => 'DESC'],
            'LIMIT' => 15
        ]);

        $this->view('hr/leaves/entry', [
            'employees' => $employees,
            'departments' => $departments,
            'sections' => $sections,
            'positions' => $positions,
            'companies' => $companies,
            'categories' => $categories,
            'locations' => $locations,
            'divisions' => $divisions,
            'subsections' => $subsections,
            'leaveTypes' => $leaveTypes,
            'recentLeaves' => $recentLeaves,
            'flash_success' => $flashSuccess,
            'flash_error' => $flashError
        ]);
    }

    public function getLeaveBalanceApi(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $empId = $this->request->get('employee_id');
        $empCode = trim($this->request->get('employee_code', ''));

        if (empty($empId) && !empty($empCode)) {
            $emp = $this->db->get('employees', 'employee_id', ['employee_code' => $empCode]);
            $empId = $emp ? $emp : null;
        }

        if (empty($empId)) {
            echo json_encode(['success' => false, 'message' => 'Valid employee ID or code required']);
            exit;
        }

        $empId = (int)$empId;
        $currentYear = date('Y');

        // Standard entitlements (Factory / BLA standards)
        $entitlements = [
            'CL'  => 10.0,
            'SL'  => 14.0,
            'LWP' => 0.0,
            'RL'  => 5.0,
            'SPL' => 5.0,
            'EL'  => 18.0,
            'EDL' => 10.0
        ];

        $typeMap = [
            'CL'  => ['Casual Leave'],
            'SL'  => ['Sick Leave'],
            'LWP' => ['Leave Without Pay', 'Unpaid Leave'],
            'RL'  => ['Replacement Leave', 'Short Leave'],
            'SPL' => ['Special Leave', 'WPIL', 'Paternity Leave', 'Maternity Leave'],
            'EL'  => ['Earned Leave', 'Annual Leave'],
            'EDL' => ['Extra Duty Leave', 'Lay Off Leave', 'GFT', 'BFT']
        ];

        $grid = [];

        foreach ($entitlements as $code => $ent) {
            $matchedTypes = $typeMap[$code] ?? [$code];

            // Taken (Approved leaves in current year)
            $takenQuery = $this->db->select('leaves', 'total_days', [
                'employee_id' => $empId,
                'leave_type' => $matchedTypes,
                'status' => 'Approved',
                'start_date[>=]' => "{$currentYear}-01-01",
                'end_date[<=]' => "{$currentYear}-12-31"
            ]);
            $taken = 0.0;
            foreach ($takenQuery as $d) {
                $taken += (float)($d ?? 1.0);
            }

            // Applied (Pending leaves in current year)
            $appliedQuery = $this->db->select('leaves', 'total_days', [
                'employee_id' => $empId,
                'leave_type' => $matchedTypes,
                'status' => 'Pending',
                'start_date[>=]' => "{$currentYear}-01-01",
                'end_date[<=]' => "{$currentYear}-12-31"
            ]);
            $applied = 0.0;
            foreach ($appliedQuery as $d) {
                $applied += (float)($d ?? 1.0);
            }

            $balance = max(0.0, $ent - $taken);
            $balanceAfter = max(0.0, $balance - $applied);

            $grid[$code] = [
                'entitlement' => $ent,
                'taken' => $taken,
                'balance' => $balance,
                'applied' => $applied,
                'balance_after' => $balanceAfter
            ];
        }

        echo json_encode([
            'success' => true,
            'employee_id' => $empId,
            'grid' => $grid
        ]);
        exit;
    }

    public function storeEntry(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();

        if (empty($data['employee_id']) || empty($data['leave_type']) || empty($data['start_date']) || empty($data['end_date'])) {
            $_SESSION['flash_error'] = 'Employee, Leave Type, Start Date, and End Date are mandatory fields.';
            $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/leave-entry' : '/hr/leave-entry');
            return;
        }

        try {
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];
            $daysPart = $data['days_part'] ?? 'Full Day';
            
            // Compute total days
            if ($daysPart === '1st Half' || $daysPart === '2nd Half') {
                $totalDays = 0.5;
            } else {
                $d1 = new \DateTime($startDate);
                $d2 = new \DateTime($endDate);
                $diff = $d1->diff($d2)->days + 1;
                $totalDays = max(0.5, (float)$diff);
            }

            // Handle file attachment upload if present
            $attachmentPath = null;
            if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../../public/uploads/leave_documents/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileTmp = $_FILES['attachment_file']['tmp_name'];
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['attachment_file']['name']);
                $targetFile = $uploadDir . $fileName;
                if (move_uploaded_file($fileTmp, $targetFile)) {
                    $attachmentPath = 'uploads/leave_documents/' . $fileName;
                }
            }

            $insertData = [
                'employee_id' => (int)$data['employee_id'],
                'leave_type' => $data['leave_type'],
                'days_part' => $daysPart,
                'adjust_leave_type' => !empty($data['adjust_leave_type']) ? $data['adjust_leave_type'] : null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'reason' => $data['reason'] ?? '',
                'attachment_path' => $attachmentPath,
                'during_destination' => !empty($data['during_destination']) ? $data['during_destination'] : null,
                'during_contact_person' => !empty($data['during_contact_person']) ? $data['during_contact_person'] : null,
                'during_contact_no' => !empty($data['during_contact_no']) ? $data['during_contact_no'] : null,
                'job_responsible_id' => !empty($data['job_responsible_id']) ? (int)$data['job_responsible_id'] : null,
                'special_instruction' => !empty($data['special_instruction']) ? $data['special_instruction'] : null,
                'status' => 'Pending',
                'created_by' => $this->auth->user()['user_id'] ?? $this->auth->user()['id'] ?? $_SESSION['user_id'] ?? 1
            ];

            $this->db->insert('leaves', $insertData);

            $_SESSION['flash_success'] = "Leave Entry submitted successfully ({$totalDays} days) for Employee #{$data['employee_id']}.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to record leave entry: ' . $e->getMessage();
        }

        $this->redirect(defined('BASE_URL') ? BASE_URL . '/hr/leave-entry' : '/hr/leave-entry');
    }
}

