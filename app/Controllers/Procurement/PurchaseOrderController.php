<?php

declare(strict_types=1);

namespace App\Controllers\Procurement;

use App\Controllers\BaseController;
use App\Services\Procurement\PurchaseOrderService;
use Core\Container;

class PurchaseOrderController extends BaseController
{
    private PurchaseOrderService $service;
    private array $statusOptions = [
        'draft',
        'submitted',
        'confirmed',
        'partial_received',
        'received',
        'cancelled',
        'closed'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(PurchaseOrderService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $tab = $this->request->get('tab', 'dashboard');
        $validTabs = ['dashboard', 'po_list', 'rfq', 'returns', 'reports'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'dashboard';
        }

        // ── Ensure new procurement tables exist ────────────────────────────
        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS procurement_rfqs (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                rfq_no        VARCHAR(50) NOT NULL UNIQUE,
                pr_id         INT NULL,
                item_name     VARCHAR(100) NOT NULL,
                quantity      INT NOT NULL DEFAULT 1,
                rfq_date      DATE,
                deadline_date DATE,
                status        VARCHAR(30) DEFAULT 'open',
                remarks       TEXT,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS procurement_rfq_vendors (
                id                 INT AUTO_INCREMENT PRIMARY KEY,
                rfq_id             INT NOT NULL,
                vendor_id          INT NOT NULL,
                quoted_price       DECIMAL(10,2) NULL,
                delivery_lead_time VARCHAR(50) NULL,
                payment_terms      VARCHAR(100) NULL,
                status             VARCHAR(20) DEFAULT 'pending',
                created_at         DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS procurement_returns (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                return_no    VARCHAR(50) NOT NULL UNIQUE,
                po_id        INT NOT NULL,
                return_date  DATE,
                qty_returned INT NOT NULL DEFAULT 0,
                debit_amount DECIMAL(10,2) DEFAULT 0.00,
                reason       TEXT,
                status       VARCHAR(20) DEFAULT 'draft',
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (\Exception $e) {
            // Schema fallback
        }

        // ── Process POST submissions ───────────────────────────────────────
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->input('action', '');
            $data   = $this->request->all();

            if ($action === 'create_rfq') {
                $this->handleCreateRfq($data);
                return;
            }
            if ($action === 'submit_quote') {
                $this->handleSubmitQuote($data);
                return;
            }
            if ($action === 'select_vendor') {
                $this->handleSelectVendor($data);
                return;
            }
            if ($action === 'create_return') {
                $this->handleCreateReturn($data);
                return;
            }
            if ($action === 'update_return_status') {
                $this->handleUpdateReturnStatus($data);
                return;
            }
        }

        // ── Fetch existing PO data using the standard filters ───────────────
        $filters = [
            'status' => $this->request->get('status', ''),
            'vendor_id' => $this->request->get('vendor_id', ''),
            'search' => $this->request->get('search', ''),
        ];

        $purchaseOrders = [];
        $vendors        = [];
        $rfqs           = [];
        $rfqVendors     = [];
        $returns        = [];
        $purchaseRequests = [];

        try {
            $purchaseOrders = $this->service->listPurchaseOrders($filters) ?: [];
            $vendors        = $this->db->query("SELECT vendor_id, vendor_name, email FROM vendors WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $rfqs           = $this->db->query("SELECT * FROM procurement_rfqs ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $rfqVendors     = $this->db->query("
                SELECT rv.*, v.vendor_name, r.rfq_no, r.item_name 
                FROM procurement_rfq_vendors rv
                JOIN vendors v ON rv.vendor_id = v.vendor_id
                JOIN procurement_rfqs r ON rv.rfq_id = r.id
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $returns        = $this->db->query("
                SELECT pr.*, po.po_number 
                FROM procurement_returns pr
                JOIN purchase_orders po ON pr.po_id = po.po_id
                ORDER BY pr.id DESC
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $purchaseRequests = $this->db->query("SELECT pr_id, item_name, quantity, status FROM purchase_requests WHERE status = 'approved'")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            // Fallback
        }

        // ── Pre-compute aggregates ─────────────────────────────────────────
        $stats = $this->service->getStats() ?: [
            'total' => 0, 'draft' => 0, 'submitted' => 0, 'confirmed' => 0, 'total_value' => 0.00
        ];
        
        $activeRfqs = count(array_filter($rfqs, fn($r) => $r['status'] === 'open'));
        $totalReturns = array_sum(array_column($returns, 'debit_amount'));
        $openPRs = count($purchaseRequests);

        $this->view('procurement/purchase-orders/index', [
            'activeTab'        => $tab,
            'purchase_orders'  => $purchaseOrders,
            'vendors'          => $vendors,
            'rfqs'             => $rfqs,
            'rfqVendors'       => $rfqVendors,
            'returns'          => $returns,
            'purchaseRequests' => $purchaseRequests,
            'stats'            => $stats,
            'activeRfqs'       => $activeRfqs,
            'totalReturns'     => $totalReturns,
            'openPRs'          => $openPRs,
            'filters'          => $filters,
            'dropdowns'        => $this->service->getDropdownData(),
            'page'             => 'purchase_orders'
        ]);
    }

    private function handleCreateRfq(array $data): void
    {
        try {
            $rfqNo = 'RFQ-' . date('Ymd') . '-' . rand(100, 999);
            $prId = isset($data['pr_id']) && $data['pr_id'] !== '' ? (int)$data['pr_id'] : null;

            $rfqId = $this->db->insert('procurement_rfqs', [
                'rfq_no'        => $rfqNo,
                'pr_id'         => $prId,
                'item_name'     => $data['item_name'],
                'quantity'      => (int)($data['quantity'] ?? 1),
                'rfq_date'      => $data['rfq_date'] ?? date('Y-m-d'),
                'deadline_date' => $data['deadline_date'] ?? date('Y-m-d', strtotime('+7 days')),
                'remarks'       => $data['remarks'] ?? '',
                'status'        => 'open',
                'created_at'    => date('Y-m-d H:i:s')
            ]);

            // Invite selected vendors
            if (isset($data['vendor_ids']) && is_array($data['vendor_ids'])) {
                foreach ($data['vendor_ids'] as $vId) {
                    $this->db->insert('procurement_rfq_vendors', [
                        'rfq_id'    => $rfqId,
                        'vendor_id' => (int)$vId,
                        'status'    => 'pending'
                    ]);
                }
            }

            $_SESSION['success_message'] = "RFQ {$rfqNo} floated to selected vendors successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to float RFQ: " . $e->getMessage();
        }
        $this->redirect('purchase_orders?tab=rfq');
    }

    private function handleSubmitQuote(array $data): void
    {
        try {
            $rfqId = (int)$data['rfq_id'];
            $vendorId = (int)$data['vendor_id'];

            $this->db->update('procurement_rfq_vendors', [
                'quoted_price'       => (float)$data['quoted_price'],
                'delivery_lead_time' => $data['delivery_lead_time'] ?? '',
                'payment_terms'      => $data['payment_terms'] ?? '',
                'status'             => 'quoted'
            ], [
                'rfq_id'    => $rfqId,
                'vendor_id' => $vendorId
            ]);

            $_SESSION['success_message'] = "Vendor quote successfully updated!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to save quote: " . $e->getMessage();
        }
        $this->redirect('purchase_orders?tab=rfq');
    }

    private function handleSelectVendor(array $data): void
    {
        try {
            $rfqId = (int)$data['rfq_id'];
            $selectedVendorId = (int)$data['vendor_id'];

            // Reject all other vendors for this RFQ
            $this->db->update('procurement_rfq_vendors', ['status' => 'rejected'], [
                'rfq_id' => $rfqId,
                'vendor_id[!]' => $selectedVendorId
            ]);

            // Select this vendor
            $this->db->update('procurement_rfq_vendors', ['status' => 'selected'], [
                'rfq_id' => $rfqId,
                'vendor_id' => $selectedVendorId
            ]);

            // Close the RFQ
            $this->db->update('procurement_rfqs', ['status' => 'comparison_done'], [
                'id' => $rfqId
            ]);

            $_SESSION['success_message'] = "Vendor selected. RFQ comparison finalized!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to finalize selection: " . $e->getMessage();
        }
        $this->redirect('purchase_orders?tab=rfq');
    }

    private function handleCreateReturn(array $data): void
    {
        try {
            $returnNo = 'RET-' . date('Ymd') . '-' . rand(100, 999);

            $this->db->insert('procurement_returns', [
                'return_no'    => $returnNo,
                'po_id'        => (int)$data['po_id'],
                'return_date'  => $data['return_date'] ?? date('Y-m-d'),
                'qty_returned' => (int)($data['qty_returned'] ?? 0),
                'debit_amount' => (float)($data['debit_amount'] ?? 0.00),
                'reason'       => $data['reason'] ?? '',
                'status'       => 'draft',
                'created_at'   => date('Y-m-d H:i:s')
            ]);

            $_SESSION['success_message'] = "Purchase Return {$returnNo} created successfully in draft!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to create return: " . $e->getMessage();
        }
        $this->redirect('purchase_orders?tab=returns');
    }

    private function handleUpdateReturnStatus(array $data): void
    {
        try {
            $returnId = (int)$data['return_id'];
            $newStatus = $data['status'] ?? 'draft';

            $this->db->update('procurement_returns', ['status' => $newStatus], [
                'id' => $returnId
            ]);

            $_SESSION['success_message'] = "Debit note status updated to " . strtoupper($newStatus);
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to update return: " . $e->getMessage();
        }
        $this->redirect('purchase_orders?tab=returns');
    }

    // ── Keep existing REST API methods intact ──────────────────────────────
    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        $data = $this->request->json() ?: $this->request->all();

        $errors = $this->validate([
            'vendor_id' => 'required|integer',
            'po_date' => 'required|date',
            'required_date' => 'required|date',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $poId = $this->service->createPurchaseOrder($data, (int) $this->auth->user()['id']);
            $this->json(['success' => true, 'po_id' => $poId, 'message' => 'Purchase order created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(int $poId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        $data = $this->request->json() ?: $this->request->all();

        $errors = $this->validate([
            'vendor_id' => 'required|integer',
            'po_date' => 'required|date',
            'required_date' => 'required|date',
        ], $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->service->updatePurchaseOrder($poId, $data);
            $this->json(['success' => true, 'message' => 'Purchase order updated successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(int $poId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        $data = $this->request->json() ?: $this->request->all();

        $errors = $this->validate(['status' => 'required'], $data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if ($this->service->updateStatus($poId, $data['status'])) {
            $this->json(['success' => true, 'message' => 'Status updated successfully.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to update status.'], 500);
    }

    public function show(int $poId): void
    {
        $this->requireAuth();
        $po = $this->service->getPurchaseOrder($poId);
        if (!$po) {
            $this->json(['error' => 'Purchase order not found'], 404);
            return;
        }
        $this->json(['data' => $po]);
    }

    public function destroy(int $poId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('procurement_manager');

        try {
            $this->service->deletePurchaseOrder($poId);
            $this->json(['success' => true, 'message' => 'Purchase order deleted.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
