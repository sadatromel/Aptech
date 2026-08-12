<?php

namespace App\Controllers\Packing;

use App\Controllers\BaseController;
use App\Services\ExportDocumentService;
use Core\Database;

class ExportDocumentController extends BaseController
{
    private ExportDocumentService $service;


    public function __construct(ExportDocumentService $service, Database $db)
    {
        parent::__construct();
        $this->service = $service;
        $this->db = $db;
    }

    public function index()
    {
        $search = $_GET['search'] ?? '';
        $limit = 20;

        $documents = $this->service->listDocuments((string)$limit, $search);
        $stats = $this->service->getStats();

        // Fetch dropdown data
        $orders = $this->db->query("SELECT po.po_id as order_id, po.po_number as order_number, s.style_code FROM production_orders po LEFT JOIN styles s ON po.style_id = s.style_id WHERE po.status = 'completed' ORDER BY po.po_number")->fetchAll();
        $buyers = $this->db->query("SELECT buyer_id, buyer_name FROM buyer_profiles WHERE buyer_status = 'active' ORDER BY buyer_name")->fetchAll();

        $this->view('Packing/documents/index', [
            'documents' => $documents,
            'stats' => $stats,
            'orders' => $orders,
            'buyers' => $buyers,
            'search' => $search
        ]);
    }

    public function store()
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'document_number' => $_POST['document_number'],
                'order_id' => $_POST['order_id'],
                'document_type' => $_POST['document_type'],
                'export_date' => $_POST['export_date'],
                'buyer_id' => $_POST['buyer_id'],
                'consignee_name' => $_POST['consignee_name'],
                'consignee_address' => $_POST['consignee_address'],
                'port_of_loading' => $_POST['port_of_loading'],
                'port_of_discharge' => $_POST['port_of_discharge'],
                'vessel_name' => $_POST['vessel_name'],
                'voyage_number' => $_POST['voyage_number'],
                'invoice_number' => $_POST['invoice_number'],
                'invoice_value' => $_POST['invoice_value'],
                'currency' => $_POST['currency'],
                'payment_terms' => $_POST['payment_terms'],
                'shipment_terms' => $_POST['shipment_terms'],
                'notes' => $_POST['notes']
            ];

            $cartons = $_POST['cartons'] ?? [];

            try {
                $this->service->createDocument($data, $cartons, $_SESSION['user_id'] ?? 1);
                $this->redirect('/packing/documents');
            } catch (\Exception $e) {
                $this->redirect('/packing/documents?error=' . urlencode($e->getMessage()));
            }
        }
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->service->deleteDocument($id);
            $this->redirect('/packing/documents');
        }
    }

    public function getData(int $id)
    {
        $document = $this->service->getDocument($id);
        header('Content-Type: application/json');
        echo json_encode($document);
    }
}
