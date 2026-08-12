<?php

namespace App\Controllers\Sewing;

use App\Controllers\BaseController;
use App\Services\IncentiveService;
use Core\Database;

class IncentiveController extends BaseController
{
    private IncentiveService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new IncentiveService($this->db);
    }

    public function index()
    {
        // Dropdown data
        $employees = $this->db->query("SELECT e.employee_id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) as name, d.department_name 
                                 FROM employees e 
                                 LEFT JOIN departments d ON e.department_id = d.department_id 
                                 WHERE e.status = 'active' AND d.department_name IN ('Sewing', 'Cutting', 'Finishing')
                                 ORDER BY name")->fetchAll();

        $data = [
            'incentives' => $this->service->listIncentives(),
            'stats' => $this->service->getStats(),
            'employees' => $employees,
            'current_month' => date('Y-m')
        ];

        $this->view('sewing/incentives/index', $data);
    }

    public function store()
    {
        $this->verifyCsrf();
        try {
            $this->service->createIncentive($_POST);
            $_SESSION['success_message'] = "Incentive record added successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error adding incentive: " . $e->getMessage();
        }
        
        $this->redirect('sewing/incentives');
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        try {
            $this->service->deleteIncentive($id);
            $_SESSION['success_message'] = "Incentive record deleted successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error deleting incentive: " . $e->getMessage();
        }

        $this->redirect('sewing/incentives');
    }

    // JSON API for fetching single incentive data (replaces get_incentive.php)
    public function getData(int $id)
    {
        $data = $this->service->getIncentive($id);
        if ($data) {
            $this->json($data);
        } else {
            $this->json(['error' => 'Incentive not found'], 404);
        }
    }
}
