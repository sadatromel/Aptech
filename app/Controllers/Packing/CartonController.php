<?php

namespace App\Controllers\Packing;

use App\Controllers\BaseController;
use App\Services\CartonService;
use Core\Database;

class CartonController extends BaseController
{
    private CartonService $service;


    public function __construct(CartonService $service, Database $db)
    {
        parent::__construct();
        $this->service = $service;
        $this->db = $db;
    }

    public function index()
    {
        $search = $_GET['search'] ?? '';
        $limit = 50;

        $cartons = $this->service->listCartons((string)$limit, $search);
        $stats = $this->service->getStats();

        // Fetch dropdown data
        $plans = $this->db->query("SELECT pp.plan_id, pp.plan_number, po.order_number FROM packing_plans pp LEFT JOIN production_orders po ON pp.order_id = po.order_id WHERE pp.status IN ('planned', 'in_progress') ORDER BY pp.plan_number")->fetchAll();
        $orders = $this->db->query("SELECT po.order_id, po.order_number, s.style_code FROM production_orders po LEFT JOIN styles s ON po.style_id = s.style_id WHERE po.status IN ('in_progress', 'completed') ORDER BY po.order_number")->fetchAll();
        $styles = $this->db->query("SELECT style_id, style_code, style_name FROM styles WHERE status != 'cancelled' ORDER BY style_code")->fetchAll();
        $packers = $this->db->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) as name FROM employees WHERE department_id = (SELECT department_id FROM departments WHERE department_name = 'Packing') AND status = 'active' ORDER BY first_name")->fetchAll();

        $this->view('Packing/cartons/index', [
            'cartons' => $cartons,
            'stats' => $stats,
            'plans' => $plans,
            'orders' => $orders,
            'styles' => $styles,
            'packers' => $packers,
            'search' => $search
        ]);
    }

    public function store()
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'carton_number' => $_POST['carton_number'],
                'packing_plan_id' => $_POST['packing_plan_id'] ?: null,
                'order_id' => $_POST['order_id'],
                'style_id' => $_POST['style_id'],
                'size_name' => $_POST['size_name'],
                'quantity' => $_POST['quantity'],
                'net_weight' => $_POST['net_weight'],
                'gross_weight' => $_POST['gross_weight'],
                'length' => $_POST['length'],
                'width' => $_POST['width'],
                'height' => $_POST['height'],
                'cbm' => $_POST['cbm'],
                'packing_date' => $_POST['packing_date'],
                'packed_by' => $_POST['packed_by'] ?: null,
                'notes' => $_POST['notes']
            ];

            try {
                $this->service->createCarton($data, $_SESSION['user_id'] ?? 1);
                $this->redirect('/packing/cartons');
            } catch (\Exception $e) {
                $this->redirect('/packing/cartons?error=' . urlencode($e->getMessage()));
            }
        }
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->service->deleteCarton($id);
            $this->redirect('/packing/cartons');
        }
    }

    public function getData(int $id)
    {
        $carton = $this->service->getCarton($id);
        header('Content-Type: application/json');
        echo json_encode($carton);
    }
}
