<?php

namespace App\Controllers\Sewing;

use App\Controllers\BaseController;
use App\Services\LinePerformanceService;
use Core\Database;

class LinePerformanceController extends BaseController
{
    private LinePerformanceService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new LinePerformanceService($this->db);
    }

    public function index()
    {
        // Dropdown data
        $lines = $this->db->query("SELECT production_line_id as line_id, line_name FROM production_lines WHERE status = 'active' ORDER BY line_name")->fetchAll();
        $shifts = $this->db->query("SELECT shift_id, shift_name FROM shifts WHERE status = 'active' ORDER BY shift_name")->fetchAll();
        $supervisors = $this->db->query("SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) as name, d.department_name 
                                   FROM employees e 
                                   LEFT JOIN departments d ON e.department_id = d.department_id 
                                   WHERE d.department_name = 'Sewing' AND e.status = 'active' 
                                   ORDER BY name")->fetchAll();

        $data = [
            'records' => $this->service->listPerformance(),
            'stats' => $this->service->getStats(),
            'lines' => $lines,
            'shifts' => $shifts,
            'supervisors' => $supervisors,
            'today' => date('Y-m-d')
        ];

        $this->view('sewing/performance/index', $data);
    }

    public function store()
    {
        $this->verifyCsrf();
        try {
            $this->service->createPerformance($_POST);
            $_SESSION['success_message'] = "Line performance record added successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error adding performance: " . $e->getMessage();
        }
        
        $this->redirect('sewing/performance');
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        try {
            $this->service->deletePerformance($id);
            $_SESSION['success_message'] = "Line performance record deleted successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error deleting performance: " . $e->getMessage();
        }

        $this->redirect('sewing/performance');
    }

    // JSON API for fetching single performance data
    public function getData(int $id)
    {
        $data = $this->service->getPerformance($id);
        if ($data) {
            $this->json($data);
        } else {
            $this->json(['error' => 'Performance record not found'], 404);
        }
    }
}
