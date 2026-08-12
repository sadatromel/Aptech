<?php

namespace App\Controllers\Packing;

use App\Controllers\BaseController;
use App\Services\PackingPlanService;
use Core\Database;

class PackingPlanController extends BaseController
{
    private PackingPlanService $service;


    public function __construct(PackingPlanService $service, Database $db)
    {
        parent::__construct();
        $this->service = $service;
        $this->db = $db;
    }

    public function index()
    {
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $plans = $this->service->listPlans((string)$offset, (string)$limit, $search);
        $stats = $this->service->getStats();

        // Fetch dropdown data
        $orders = $this->db->query("SELECT po.po_id as order_id, po.po_number as order_number, s.style_code FROM production_orders po LEFT JOIN styles s ON po.style_id = s.style_id WHERE po.status IN ('in_progress', 'completed') ORDER BY po.po_number")->fetchAll();
        $styles = $this->db->query("SELECT style_id, style_code, style_name FROM styles WHERE status != 'cancelled' ORDER BY style_code")->fetchAll();
        $supervisors = $this->db->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) as name FROM employees WHERE department_id = (SELECT department_id FROM departments WHERE department_name = 'Packing') AND status = 'active' ORDER BY first_name")->fetchAll();

        $this->view('Packing/plans/index', [
            'plans' => $plans,
            'stats' => $stats,
            'orders' => $orders,
            'styles' => $styles,
            'supervisors' => $supervisors,
            'search' => $search,
            'page' => $page
        ]);
    }

    public function store()
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'plan_number' => $_POST['plan_number'],
                'order_id' => $_POST['order_id'],
                'style_id' => $_POST['style_id'],
                'packing_date' => $_POST['packing_date'],
                'target_quantity' => $_POST['target_quantity'],
                'packing_line' => $_POST['packing_line'],
                'supervisor_id' => $_POST['supervisor_id'] ?: null,
                'notes' => $_POST['notes']
            ];

            $sizes = $_POST['sizes'] ?? [];

            try {
                $this->service->createPlan($data, $sizes, $_SESSION['user_id'] ?? 1);
                $this->redirect('/packing/plans');
            } catch (\Exception $e) {
                // In a real app, flash error message
                $this->redirect('/packing/plans?error=' . urlencode($e->getMessage()));
            }
        }
    }

    public function updateStatus(int $id)
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $status = $_POST['status'];
            $actualQuantity = (int)$_POST['actual_quantity'];
            $completionDate = $_POST['completion_date'] ?: null;

            $this->service->updateStatus($id, $status, $actualQuantity, $completionDate);
            $this->redirect('/packing/plans');
        }
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->service->deletePlan($id);
            $this->redirect('/packing/plans');
        }
    }

    public function getData(int $id)
    {
        $plan = $this->service->getPlan($id);
        header('Content-Type: application/json');
        echo json_encode($plan);
    }
}
