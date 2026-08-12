<?php

namespace App\Controllers\Cutting;

use App\Controllers\BaseController;
use App\Services\BundleService;
use Core\Database;

class BundleController extends BaseController
{
    private BundleService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new BundleService($this->db);
    }

    public function index()
    {
        // Dropdown data
        $markers = $this->db->query("SELECT marker_id, marker_number FROM markers WHERE status = 'approved' ORDER BY marker_number DESC")->fetchAll();
        $styles = $this->db->query("SELECT s.style_id, s.style_code, s.style_name FROM styles s WHERE status != 'cancelled' ORDER BY s.style_code")->fetchAll();
        $cutting_rooms = $this->db->query("SELECT cutting_room_id, cutting_room_name FROM cutting_rooms WHERE status = 'active' ORDER BY cutting_room_name")->fetchAll();

        $data = [
            'bundles' => $this->service->listBundles(),
            'stats' => $this->service->getStats(),
            'markers' => $markers,
            'styles' => $styles,
            'cutting_rooms' => $cutting_rooms
        ];

        $this->view('cutting/bundles/index', $data);
    }

    public function store()
    {
        $this->verifyCsrf();
        try {
            $this->service->createBundle($_POST);
            $_SESSION['success_message'] = "Bundle generated successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error generating bundle: " . $e->getMessage();
        }
        
        $this->redirect('cutting/bundles');
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        try {
            $this->service->deleteBundle($id);
            $_SESSION['success_message'] = "Bundle deleted successfully.";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Error deleting bundle: " . $e->getMessage();
        }

        $this->redirect('cutting/bundles');
    }
}
