<?php

namespace App\Controllers\Sewing;

use App\Controllers\BaseController;
use App\Services\QCService;
use Core\Database;

class QCController extends BaseController
{
    private QCService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new QCService($this->db);
    }

    public function index()
    {
        // Dropdown data
        $orders = $this->db->query("SELECT po.order_id, po.order_number, s.style_code, s.style_name FROM production_orders po LEFT JOIN styles s ON po.style_id = s.style_id WHERE po.status IN ('in_progress', 'completed') ORDER BY po.order_number")->fetchAll();
        $styles = $this->db->query("SELECT s.style_id, s.style_code, s.style_name FROM styles s WHERE status != 'cancelled' ORDER BY s.style_code")->fetchAll();
        $lines = $this->db->query("SELECT line_id, line_name, line_code FROM production_lines WHERE status = 'active' ORDER BY line_name")->fetchAll();
        $inspectors = $this->db->query("SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) as name, d.department_name 
                                  FROM employees e 
                                  LEFT JOIN departments d ON e.department_id = d.department_id 
                                  WHERE d.department_name = 'Quality Control' AND e.status = 'active' 
                                  ORDER BY name")->fetchAll();

        $data = [
            'inspections' => $this->service->listInspections(),
            'stats' => $this->service->getStats(),
            'orders' => $orders,
            'styles' => $styles,
            'lines' => $lines,
            'inspectors' => $inspectors,
            'today' => date('Y-m-d')
        ];

        $this->view('sewing/qc/index', $data);
    }

    public function store()
    {
        $this->verifyCsrf();
        try {
            $this->service->createInspection($_POST);
            $_SESSION['success_message'] = "QC inspection added successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error adding inspection: " . $e->getMessage();
        }
        
        $this->redirect('sewing/qc');
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        try {
            $this->service->deleteInspection($id);
            $_SESSION['success_message'] = "QC inspection deleted successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error deleting inspection: " . $e->getMessage();
        }

        $this->redirect('sewing/qc');
    }

    public function getData(int $id)
    {
        $data = $this->service->getInspection($id);
        if ($data) {
            $this->json($data);
        } else {
            $this->json(['error' => 'Inspection not found'], 404);
        }
    }
}
