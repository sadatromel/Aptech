<?php

declare(strict_types=1);

namespace App\Controllers\Commercial;

use App\Controllers\BaseController;
use App\Services\ExportDocumentService;
use Core\Container;
use Core\Database;

class ExportController extends BaseController
{
    private ExportDocumentService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(ExportDocumentService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        
        $search = $_GET['search'] ?? '';
        $documents = $this->service->listDocuments('50', $search);

        /** @var Database $db */
        $db = Container::get(Database::class);
        $orders = $db->query("
            SELECT po.po_id, COALESCE(NULLIF(po.po_number, ''), po.order_number, CONCAT('PO-', po.po_id)) as order_number, s.style_code
            FROM production_orders po
            LEFT JOIN styles s ON po.style_id = s.style_id
            ORDER BY po.po_id DESC
        ")->fetchAll();

        $buyers = $db->query("SELECT buyer_id, buyer_name FROM buyers ORDER BY buyer_name ASC")->fetchAll();

        $this->view('commercial/export/index', [
            'title'     => 'Export Shipping Documents',
            'documents' => $documents,
            'orders'    => $orders,
            'buyers'    => $buyers,
            'search'    => $search,
        ]);
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        $document = $this->service->getDocument($id);

        if (!$document) {
            $this->notFound();
            return;
        }

        $this->view('commercial/export/show', [
            'document' => $document,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        
        $docNumber = 'EXP-' . date('Ymd') . '-' . rand(100, 999);
        $data = [
            'document_number'   => $_POST['document_number'] ?? $docNumber,
            'order_id'          => (int)($_POST['order_id'] ?? 0),
            'buyer_id'          => (int)($_POST['buyer_id'] ?? 0),
            'document_type'     => $_POST['document_type'] ?? 'commercial_invoice',
            'export_date'       => $_POST['export_date'] ?? date('Y-m-d'),
            'consignee_name'    => $_POST['consignee_name'] ?? '',
            'consignee_address' => $_POST['consignee_address'] ?? '',
            'port_of_loading'   => $_POST['port_of_loading'] ?? 'Chittagong Port',
            'port_of_discharge' => $_POST['port_of_discharge'] ?? '',
            'vessel_name'       => $_POST['vessel_name'] ?? '',
            'voyage_number'     => $_POST['voyage_number'] ?? '',
            'invoice_number'    => $_POST['invoice_number'] ?? '',
            'invoice_value'     => (float)($_POST['invoice_value'] ?? 0),
            'currency'          => $_POST['currency'] ?? 'USD',
            'payment_terms'     => $_POST['payment_terms'] ?? 'L/C at Sight',
            'shipment_terms'    => $_POST['shipment_terms'] ?? 'FOB',
            'notes'             => $_POST['notes'] ?? '',
        ];

        try {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $docId = $this->service->createDocument($data, [], $userId);
            $_SESSION['flash_success'] = 'Export Shipping Dossier created successfully!';
            $this->redirect("/commercial/export-documents/{$docId}");
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to create export document: ' . $e->getMessage();
            $this->redirect('/commercial/export-documents');
        }
    }
}
