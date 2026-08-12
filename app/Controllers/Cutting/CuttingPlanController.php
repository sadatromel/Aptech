<?php

namespace App\Controllers\Cutting;

use App\Controllers\BaseController;
use App\Services\CuttingPlanService;
use Core\Request;
use Core\Database;

class CuttingPlanController extends BaseController
{
    protected CuttingPlanService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CuttingPlanService($this->db);
    }

    public function index()
    {
        // Get dropdown data
        $orders = $this->db->query("SELECT po.po_id as order_id, po.po_number as order_number, s.style_code, s.style_name FROM production_orders po LEFT JOIN styles s ON po.style_id = s.style_id WHERE po.status IN ('approved', 'planned', 'in_production') ORDER BY po.po_number")->fetchAll();
        $styles = $this->db->query("SELECT s.style_id, s.style_code, s.style_name FROM styles s WHERE status != 'cancelled' ORDER BY s.style_code")->fetchAll();
        $cutting_rooms = $this->db->query("SELECT cutting_room_id, cutting_room_name FROM cutting_rooms WHERE status = 'active' ORDER BY cutting_room_name")->fetchAll();

        $data = [
            'plans' => $this->service->listPlans(),
            'stats' => $this->service->getStats(),
            'orders' => $orders,
            'styles' => $styles,
            'cutting_rooms' => $cutting_rooms
        ];

        $this->view('cutting/plans/index', $data);
    }

    public function store()
    {
        $this->verifyCsrf();
        try {
            $data = $_POST; // In a real app check Request object
            $this->service->createPlan($data);
            $_SESSION['success_message'] = "Cutting plan created successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error creating plan: " . $e->getMessage();
        }
        
        $this->redirect('cutting/plans');
    }

    public function updateStatus(int $id)
    {
        $this->verifyCsrf();
        try {
            $status = $_POST['status'];
            $completedQty = (int)$_POST['completed_quantity'];
            $date = !empty($_POST['actual_completion_date']) ? $_POST['actual_completion_date'] : null;
            
            $this->service->updateStatus($id, $status, $completedQty, $date);
            $_SESSION['success_message'] = "Status updated successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error updating status: " . $e->getMessage();
        }

        $this->redirect('cutting/plans');
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        try {
            $this->service->deletePlan($id);
            $_SESSION['success_message'] = "Plan deleted successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error deleting plan: " . $e->getMessage();
        }

        $this->redirect('cutting/plans');
    }
}
