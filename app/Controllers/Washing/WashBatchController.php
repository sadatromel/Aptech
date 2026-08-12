<?php

namespace App\Controllers\Washing;

use App\Controllers\BaseController;
use App\Services\WashBatchService;
use Core\Request;

class WashBatchController extends BaseController
{
    protected WashBatchService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new WashBatchService($this->db);
    }

    public function index()
    {
        // Dropdown data
        $orders = $this->db->query("SELECT po.po_id as order_id, po.po_number as order_number, s.style_code, s.style_name FROM production_orders po LEFT JOIN styles s ON po.style_id = s.style_id WHERE po.status IN ('in_progress', 'completed') ORDER BY po.po_number")->fetchAll();
        $styles = $this->db->query("SELECT s.style_id, s.style_code, s.style_name FROM styles s WHERE status != 'cancelled' ORDER BY s.style_code")->fetchAll();
        $supervisors = $this->db->query("SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) as name, d.department_name 
                                   FROM employees e 
                                   LEFT JOIN departments d ON e.department_id = d.department_id 
                                   WHERE d.department_name = 'Washing' AND e.status = 'active' 
                                   ORDER BY name")->fetchAll();

        // Chemicals for dropdown if needed in JS, although we might just use input fields with logic
        // But the modal uses input fields for chemicals, so maybe just pass empty or necessary units.

        $data = [
            'batches' => $this->service->listBatches(),
            'stats' => $this->service->getStats(),
            'orders' => $orders,
            'styles' => $styles,
            'supervisors' => $supervisors
        ];

        $this->view('washing/batch/index', $data);
    }

    public function store()
    {
        $this->verifyCsrf();
        try {
            $data = $_POST;
            $this->service->createBatch($data);
            $_SESSION['success_message'] = "Wash batch created successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error creating batch: " . $e->getMessage();
        }
        
        $this->redirect('washing/wash_batch_management');
    }

    public function updateStatus(int $id)
    {
        $this->verifyCsrf();
        try {
            $status = $_POST['status'];
            $completedQty = (int)$_POST['completed_quantity'];
            $rejectionQty = (int)$_POST['rejection_quantity'];
            $date = !empty($_POST['actual_completion_date']) ? $_POST['actual_completion_date'] : null;
            
            $this->service->updateStatus($id, $status, $completedQty, $rejectionQty, $date);
            $_SESSION['success_message'] = "Batch updated successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error updating batch: " . $e->getMessage();
        }

        $this->redirect('washing/wash_batch_management');
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        try {
            $this->service->deleteBatch($id);
            $_SESSION['success_message'] = "Batch deleted successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error deleting batch: " . $e->getMessage();
        }

        $this->redirect('washing/wash_batch_management');
    }

    public function getData(int $id)
    {
        $batch = $this->service->getBatch($id);
        if ($batch) {
            $this->json($batch);
        } else {
            $this->json(['error' => 'Batch not found'], 404);
        }
    }
}
