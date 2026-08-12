<?php

namespace App\Controllers\Cutting;

use App\Controllers\BaseController;
use App\Services\MarkerService;
use Core\Database;

class MarkerController extends BaseController
{
    private MarkerService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new MarkerService($this->db);
    }

    public function index()
    {
        // Dropdown data
        $orders = $this->db->query("SELECT po.po_id as order_id, po.po_number as order_number, s.style_code, s.style_name FROM production_orders po LEFT JOIN styles s ON po.style_id = s.style_id WHERE po.status IN ('approved', 'planned', 'in_production') ORDER BY po.po_number")->fetchAll();
        $styles = $this->db->query("SELECT s.style_id, s.style_code, s.style_name FROM styles s WHERE status != 'cancelled' ORDER BY s.style_code")->fetchAll();
        
        $data = [
            'markers' => $this->service->listMarkers(),
            'stats' => $this->service->getStats(),
            'orders' => $orders,
            'styles' => $styles
        ];

        $this->view('cutting/markers/index', $data);
    }

    public function store()
    {
        $this->verifyCsrf();
        try {
            $this->service->createMarker($_POST);
            $_SESSION['success_message'] = "Marker created successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error creating marker: " . $e->getMessage();
        }
        
        $this->redirect('cutting/markers');
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        try {
            $this->service->deleteMarker($id);
            $_SESSION['success_message'] = "Marker deleted successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error deleting marker: " . $e->getMessage();
        }

        $this->redirect('cutting/markers');
    }
}
