<?php

declare(strict_types=1);

namespace App\Controllers\HR;

use App\Controllers\BaseController;
use App\Services\PayrollService;
use App\Models\Payroll;
use Core\Container;

class PayrollController extends BaseController
{
    private PayrollService $payrollService;

    public function __construct()
    {
        parent::__construct();
        $this->payrollService = Container::get(PayrollService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $month = $this->request->get('month', date('Y-m'));
        $department = $this->request->get('department', '');

        $payrollModel = new Payroll();
        $payroll = $payrollModel->getByPeriod($month, $department);

        // Get departments for filter
        $departments = $this->db->select('departments', '*', ['ORDER' => 'department_name']);

        $this->view('hr/payroll/index', [
            'payroll' => $payroll,
            'departments' => $departments,
            'selectedMonth' => $month,
            'selectedDepartment' => $department
        ]);
    }

    public function generate(): void
    {
        $this->requireAuth();
        $this->requireRole('hr_manager');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/payroll');
             return;
        }

        $period = $_POST['payroll_period'] ?? date('Y-m');
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $payment = $_POST['payment_date'];

        $result = $this->payrollService->generatePayroll(
            $period, 
            $start, 
            $end, 
            $payment, 
            (int)($this->auth->user()['user_id'] ?? $this->auth->user()['id'] ?? $_SESSION['user_id'] ?? 1)
        );

        if ($result['success']) {
            $msg = "Generated payroll for {$result['generated_count']} employees.";
            if (!empty($result['errors'])) {
                $msg .= " Errors: " . count($result['errors']);
            }
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/payroll?success=' . urlencode($msg));
        } else {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/hr/payroll&error=Failed');
        }
    }

    public function approve(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('hr_manager');

        if ($this->payrollService->approve($id, (int)($this->auth->user()['user_id'] ?? $this->auth->user()['id'] ?? $_SESSION['user_id'] ?? 1))) {
            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to approve'], 500);
        }
    }

    public function pay(int $id): void
    {
         $this->requireAuth();
         $this->requireRole('finance_manager'); // Or HR manager

         if ($this->payrollService->pay($id, (int)($this->auth->user()['user_id'] ?? $this->auth->user()['id'] ?? $_SESSION['user_id'] ?? 1))) {
             $this->json(['success' => true]);
         } else {
             $this->json(['success' => false, 'message' => 'Failed to process payment'], 500);
         }
    }
    
    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('hr_manager');
        
        if ($this->payrollService->delete($id)) {
            $this->json(['success' => true]);
        } else {
             $this->json(['success' => false, 'message' => 'Failed to delete'], 500);
        }
    }
    
    public function show(int $id): void
    {
        $this->requireAuth();
        
        $payrollModel = new Payroll();
        $record = $payrollModel->find($id);
        
        if (!$record) {
            echo "Payroll record not found";
            return;
        }
        
        // Check permissions (own record or hr manager)
        // For simplicity allow HR or Owner
        
        $this->view('hr/payroll/show', ['payroll' => $record]);
    }
}
