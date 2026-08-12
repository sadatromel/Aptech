<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use App\Models\Employee;
use Core\Container;

class HRDashboardController extends BaseController
{
    private Employee $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->employeeModel = Container::get(Employee::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        // 1. Fetch real headcounts from DB
        $totalEmployees = (int) $this->db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
        $activeEmployees = (int) $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
        $inactiveEmployees = $totalEmployees - $activeEmployees;

        // 2. Department statistics
        $deptStats = $this->db->query("
            SELECT d.department_name, COUNT(e.employee_id) as count 
            FROM departments d 
            LEFT JOIN employees e ON d.department_id = e.department_id 
            GROUP BY d.department_id 
            ORDER BY count DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Gender metrics
        $maleCount = (int) $this->db->query("SELECT COUNT(*) FROM employees WHERE gender = 'Male'")->fetchColumn();
        $femaleCount = (int) $this->db->query("SELECT COUNT(*) FROM employees WHERE gender = 'Female'")->fetchColumn();
        if ($maleCount === 0 && $femaleCount === 0 && $totalEmployees > 0) {
            // Fallback for demo data
            $maleCount = (int)($totalEmployees * 0.4);
            $femaleCount = $totalEmployees - $maleCount;
        }

        // 4. Attendance summary for today
        $today = date('Y-m-d');
        $presentToday = (int) $this->db->query("SELECT COUNT(*) FROM attendance WHERE date = '{$today}' AND status = 'Present'")->fetchColumn();
        $absentToday = (int) $this->db->query("SELECT COUNT(*) FROM attendance WHERE date = '{$today}' AND status = 'Absent'")->fetchColumn();
        $lateToday = (int) $this->db->query("SELECT COUNT(*) FROM attendance WHERE date = '{$today}' AND status = 'Late'")->fetchColumn();
        
        if ($presentToday === 0 && $totalEmployees > 0) {
            // Simulated attendance dashboard for empty/fresh DB
            $presentToday = (int)($activeEmployees * 0.9);
            $lateToday = (int)($activeEmployees * 0.05);
            $absentToday = $activeEmployees - $presentToday - $lateToday;
        }

        // 5. Group of Companies stats (multi-company)
        $companyStats = [
            ['company_name' => 'Aptech Garments Ltd.', 'employees' => (int)($activeEmployees * 0.6), 'attendance' => '94%'],
            ['company_name' => 'Aptech Woven Apparels Ltd.', 'employees' => (int)($activeEmployees * 0.3), 'attendance' => '96%'],
            ['company_name' => 'Aptech Textiles & Washing Ltd.', 'employees' => (int)($activeEmployees * 0.1), 'attendance' => '92%']
        ];

        // 6. Upcoming birthdays & new joiners
        $upcomingBirthdays = [
            ['name' => 'Sumon Rahman', 'dept' => 'Sewing Section A', 'date' => date('d M', strtotime('+1 day'))],
            ['name' => 'Fatema Khatun', 'dept' => 'Quality Control', 'date' => date('d M', strtotime('+3 days'))],
            ['name' => 'Anisul Islam', 'dept' => 'Merchandising', 'date' => date('d M', strtotime('+5 days'))]
        ];

        $newJoiners = [
            ['name' => 'Tarif Ahmed', 'designation' => 'Sewing Operator', 'join_date' => date('d-M-Y', strtotime('-2 days'))],
            ['name' => 'Jannatul Ferdous', 'designation' => 'Helper', 'join_date' => date('d-M-Y', strtotime('-4 days'))],
            ['name' => 'Abdur Rahim', 'designation' => 'Line Supervisor', 'join_date' => date('d-M-Y', strtotime('-5 days'))]
        ];

        $this->view('hr/dashboard', [
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'inactiveEmployees' => $inactiveEmployees,
            'deptStats' => $deptStats,
            'maleCount' => $maleCount,
            'femaleCount' => $femaleCount,
            'presentToday' => $presentToday,
            'absentToday' => $absentToday,
            'lateToday' => $lateToday,
            'companyStats' => $companyStats,
            'upcomingBirthdays' => $upcomingBirthdays,
            'newJoiners' => $newJoiners,
            'activeModule' => 'hr',
            'activeSubModule' => 'dashboard'
        ]);
    }

    public function showcase(string $section, string $page): void
    {
        $this->requireAuth();
        
        $sectionName = ucwords(str_replace('-', ' ', $section));
        $pageName = ucwords(str_replace('-', ' ', $page));

        // Get headers and title from mock structure
        $mockStructure = $this->getMockData($section, $page);
        
        // Fetch rows from database
        $dbRows = $this->db->select('hr_extended_records', '*', [
            'section' => $section,
            'page' => $page,
            'ORDER' => ['id' => 'ASC']
        ]);
        
        $rows = [];
        if (empty($dbRows)) {
            // Seed database table with default rows
            foreach ($mockStructure['rows'] as $row) {
                $this->db->insert('hr_extended_records', [
                    'section' => $section,
                    'page' => $page,
                    'data' => json_encode($row),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            // Re-fetch to get IDs
            $dbRows = $this->db->select('hr_extended_records', '*', [
                'section' => $section,
                'page' => $page,
                'ORDER' => ['id' => 'ASC']
            ]);
        }
        
        // Compile the rows with their database ID so they can be deleted
        $compiledRows = [];
        foreach ($dbRows as $dbRow) {
            $rowData = json_decode($dbRow['data'], true);
            $compiledRows[] = [
                'id' => $dbRow['id'],
                'cols' => $rowData
            ];
        }

        $mockData = [
            'headers' => $mockStructure['headers'],
            'rows' => $compiledRows,
            'title' => $mockStructure['title']
        ];

        $this->view('hr/showcase', [
            'section' => $section,
            'sectionName' => $sectionName,
            'pageCode' => $page,
            'pageName' => $pageName,
            'mockData' => $mockData,
            'activeModule' => 'hr',
            'activeSubModule' => $section
        ]);
    }

    public function storeRecord(string $section, string $page): void
    {
        $this->requireAuth();
        
        $data = $this->request->all();
        // Extract field_0, field_1, etc.
        $rowData = [];
        $index = 0;
        while (isset($data["field_{$index}"])) {
            $rowData[] = $data["field_{$index}"];
            $index++;
        }
        
        if (empty($rowData)) {
            $this->json(['success' => false, 'message' => 'No data received'], 400);
            return;
        }

        $insertedId = $this->db->insert('hr_extended_records', [
            'section' => $section,
            'page' => $page,
            'data' => json_encode($rowData),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'id' => $insertedId]);
        } else {
            header("Location: " . BASE_URL . "/hr/showcase/{$section}/{$page}");
        }
    }

    public function deleteRecord(int $id): void
    {
        $this->requireAuth();
        
        $this->db->delete('hr_extended_records', ['id' => $id]);

        $this->json(['success' => true]);
    }

    private function getMockData(string $section, string $page): array
    {
        // Generates realistic JSON dataset based on pages
        switch ("{$section}/{$page}") {
            case 'recruitment/cv-bank':
            case 'recruitment/candidate-database':
                return [
                    'headers' => ['Candidate Name', 'Designation Applied', 'Experience', 'Expected Salary', 'Source', 'Status'],
                    'rows' => [
                        ['Mizanur Rahman', 'Sewing Operator', '5 Years', '$180', 'Walk-in', 'Selected'],
                        ['Sultana Begum', 'Helper', '2 Years', '$130', 'Reference', 'Interviewing'],
                        ['Kamrul Hasan', 'Line Supervisor', '8 Years', '$280', 'Circular', 'Shortlisted'],
                        ['Kohinur Akter', 'Quality Inspector', '4 Years', '$170', 'Walk-in', 'Rejected'],
                        ['Sajidul Islam', 'Production Clerk', '1 Year', '$150', 'Job Portal', 'Applied']
                    ],
                    'filters' => ['Status', 'Applied Designation'],
                    'title' => 'CV Bank & Candidate Log'
                ];

            case 'recruitment/circulars':
            case 'recruitment/vacancy-management':
                return [
                    'headers' => ['Circular Title', 'Department', 'Vacancies', 'Deadline', 'Applications Recd', 'Status'],
                    'rows' => [
                        ['Senior Sewing Operator (Woven)', 'Production', '50', date('d-M-Y', strtotime('+10 days')), '145', 'Published'],
                        ['Quality Assurance Supervisor', 'Quality Control', '5', date('d-M-Y', strtotime('+5 days')), '34', 'Published'],
                        ['Merchandiser (Bulk Costing)', 'Merchandising', '2', date('d-M-Y', strtotime('-1 day')), '12', 'Closed'],
                        ['HR Compliance Officer', 'Compliance', '1', date('d-M-Y', strtotime('+15 days')), '18', 'Draft']
                    ],
                    'title' => 'Vacancy Circular Registry'
                ];

            case 'shift-management/shift-master':
                return [
                    'headers' => ['Shift Name', 'Start Time', 'End Time', 'Break Duration', 'Grace Period', 'Weekly Off', 'Status'],
                    'rows' => [
                        ['General Shift', '08:00 AM', '05:00 PM', '1 Hour', '15 Mins', 'Friday', 'Active'],
                        ['Night Shift A', '08:00 PM', '05:00 AM', '1 Hour', '10 Mins', 'Friday', 'Active'],
                        ['Rostered Evening Shift', '02:00 PM', '10:00 PM', '30 Mins', '15 Mins', 'Rotating', 'Active']
                    ],
                    'title' => 'Shift Schedule Master Setup'
                ];

            case 'overtime/ot-approval':
                return [
                    'headers' => ['Date', 'Line / Dept', 'Employee Count', 'Total OT Hours', 'Estimated Cost', 'Status'],
                    'rows' => [
                        [date('Y-m-d', strtotime('-1 day')), 'Sewing Line 03', '25 Workers', '50 Hours', '$75.00', 'Approved'],
                        [date('Y-m-d', strtotime('-1 day')), 'Finishing Section', '15 Workers', '30 Hours', '$45.00', 'Approved'],
                        [date('Y-m-d'), 'Cutting Section', '10 Workers', '20 Hours', '$30.00', 'Pending Approval'],
                        [date('Y-m-d'), 'Sewing Line 05', '28 Workers', '56 Hours', '$84.00', 'Pending Approval']
                    ],
                    'title' => 'Daily OT Production Approvals'
                ];

            case 'rmg-specific/worker-age-verification':
            case 'rmg-specific/child-labour-prevention':
                return [
                    'headers' => ['Worker Code', 'Name', 'Declared Age', 'NID Verification', 'Birth Cert Status', 'Medical Doctor Review', 'Eligible'],
                    'rows' => [
                        ['EMP-10903', 'Milon Hossain', '22 Years', 'Verified (Match)', 'Submitted', 'Fit (Above 18)', 'Yes'],
                        ['EMP-10912', 'Nazmin Akhter', '19 Years', 'Verified (Match)', 'Submitted', 'Fit (Above 18)', 'Yes'],
                        ['EMP-10915', 'Riyaz Uddin', '16 Years (Apprentice)', 'Verified (Underage)', 'Under Review', 'Restricted Hours Only', 'Special Approval'],
                        ['EMP-10920', 'Sujon Mia', '24 Years', 'Failed NID Check', 'Not Submitted', 'Fit (Above 18)', 'Hold']
                    ],
                    'title' => 'Compliance Verification - Child Labor Prevention Registry'
                ];

            case 'rmg-specific/sewing-line-operators':
            case 'rmg-specific/skill-matrix':
                return [
                    'headers' => ['Operator Code', 'Name', 'Sewing Operation Specialty', 'Grade', 'Efficiency Score', 'Machine Assignment', 'Status'],
                    'rows' => [
                        ['OP-401', 'Anwar Hossain', 'Collar Joint (Flatbed)', 'Grade A', '87%', 'Lockstitch Machine', 'Active'],
                        ['OP-405', 'Khadiza Begum', 'Sleeve Joint (Overlock)', 'Grade A+', '92%', '4-Thread Overlock', 'Active'],
                        ['OP-410', 'Babul Mia', 'Bottom Hemming', 'Grade B', '76%', 'Chainstitch Hemmer', 'Active'],
                        ['OP-415', 'Monira Akter', 'Pocket Joint', 'Grade A', '84%', 'Automatic Pocket Setter', 'Active']
                    ],
                    'title' => 'Operator Machine Mapping & Skill Matrix Register'
                ];

            default:
                // General generic log representation
                return [
                    'headers' => ['Date Logged', 'Ref ID', 'Description / Action Details', 'Department', 'Updated By', 'Status'],
                    'rows' => [
                        [date('Y-m-d'), 'REG-10023', 'Lifecycle event recorded successfully for employee.', 'HR & Admin', 'Admin User', 'Completed'],
                        [date('Y-m-d', strtotime('-1 day')), 'REG-10022', 'Scheduled cycle or record adjustment configured.', 'Operations', 'HR Executive', 'Active'],
                        [date('Y-m-d', strtotime('-3 days')), 'REG-10015', 'Verification details reviewed for buyer audit requirements.', 'Compliance', 'Audit Lead', 'Approved']
                    ],
                    'title' => 'System Registry Log'
                ];
        }
    }
}
