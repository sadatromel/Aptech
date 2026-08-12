<?php

declare(strict_types=1);

namespace App\Controllers\Packing;

use App\Controllers\BaseController;

class PackingController extends BaseController
{
    public function index(): void
    {
        $this->showTab('dashboard');
    }

    public function showTab(string $tab): void
    {
        $this->requireAuth();

        $validTabs = ['dashboard', 'list', 'cartons', 'scan', 'shipment', 'reports'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'dashboard';
        }

        // ── Ensure packing tables exist ────────────────────────────────────
        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS packing_plans (
                plan_id         INT AUTO_INCREMENT PRIMARY KEY,
                plan_number     VARCHAR(50) NOT NULL,
                order_id        INT,
                style_id        INT,
                packing_date    DATE,
                target_quantity INT NOT NULL DEFAULT 100,
                status          VARCHAR(30) DEFAULT 'planned',
                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS cartons (
                carton_id           INT AUTO_INCREMENT PRIMARY KEY,
                carton_number       VARCHAR(50) NOT NULL UNIQUE,
                style_code          VARCHAR(50),
                order_no            VARCHAR(50),
                cbm                 VARCHAR(20) DEFAULT '0.12',
                gross_weight        DECIMAL(6,2) DEFAULT 0.00,
                verification_status VARCHAR(30) DEFAULT 'passed',
                scanned_date        DATE,
                created_at          DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS packing_scans (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                carton_number VARCHAR(50) NOT NULL,
                scan_time     DATETIME,
                gross_weight  DECIMAL(6,2),
                deviation     DECIMAL(4,2),
                status        VARCHAR(30) DEFAULT 'verified'
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS shipments (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                shipment_no   VARCHAR(50) NOT NULL,
                container_no  VARCHAR(50),
                invoice_no    VARCHAR(50),
                qty_cartons   INT DEFAULT 0,
                destination   VARCHAR(100),
                ship_date     DATE,
                status        VARCHAR(30) DEFAULT 'loaded',
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (\Exception $e) {
            // Schema fallback
        }

        // ── Process POST submissions ───────────────────────────────────────
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->get('action', '');
            $data   = $this->request->all();

            if ($action === 'create_packing_plan') {
                $this->handleCreatePackingPlan($data);
                return;
            }
            if ($action === 'log_carton') {
                $this->handleLogCarton($data);
                return;
            }
            if ($action === 'log_scan') {
                $this->handleLogScan($data);
                return;
            }
            if ($action === 'log_shipment') {
                $this->handleLogShipment($data);
                return;
            }
            if ($action === 'update_plan_status') {
                $this->handleUpdatePlanStatus($data);
                return;
            }
        }

        // ── Fetch real database records ─────────────────────────────────────
        $packingPlans = [];
        $cartons      = [];
        $scans        = [];
        $shipments    = [];
        $orders       = [];
        $styles       = [];

        try {
            $packingPlans = $this->db->query("SELECT plan_id, plan_number, order_id, style_id, packing_date AS planned_date, target_quantity AS total_cartons, status, created_at FROM packing_plans ORDER BY plan_id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $cartons      = $this->db->query("SELECT * FROM cartons ORDER BY carton_id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $scans        = $this->db->query("SELECT * FROM packing_scans ORDER BY id DESC LIMIT 15")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $shipments    = $this->db->query("SELECT * FROM shipments ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $orders       = $this->db->query("SELECT po_id AS id, po_number, order_quantity FROM production_orders ORDER BY po_id DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $styles       = $this->db->query("SELECT style_id, style_code, style_name FROM styles WHERE status != 'cancelled' LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            // Schema fallback
        }

        // ── Pre-compute aggregates (avoid unsupported Twig filters) ────────
        $totalCartons = count($cartons);
        $totalPlans   = count($packingPlans);
        $totalShippedCartons = array_sum(array_column($shipments, 'qty_cartons'));
        $totalPcsReady = 0;
        foreach ($cartons as $c) {
            if ($c['verification_status'] === 'passed') {
                $totalPcsReady += 24; // Average 24 pieces per carton standard
            }
        }

        $this->view('Packing/index', [
            'activeTab'           => $tab,
            'packingPlans'        => $packingPlans,
            'cartons'             => $cartons,
            'scans'               => $scans,
            'shipments'           => $shipments,
            'orders'              => $orders,
            'styles'              => $styles,
            'totalCartons'        => $totalCartons,
            'totalPlans'          => $totalPlans,
            'totalShippedCartons' => $totalShippedCartons,
            'totalPcsReady'       => $totalPcsReady,
            'pageTitle'           => 'Packing & Shipment Control Center'
        ]);
    }

    private function handleCreatePackingPlan(array $data): void
    {
        try {
            $orderId = (int)($data['order_id'] ?? 1);
            $order = $this->db->get('production_orders', '*', ['po_id' => $orderId]);
            if (!$order) {
                $order = $this->db->query("SELECT po_id, style_id FROM production_orders LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                $orderId = $order ? (int)$order['po_id'] : 1;
            }
            $styleId = $order ? (int)$order['style_id'] : 1;

            $this->db->insert('packing_plans', [
                'plan_number'     => 'PKG-' . date('Ymd') . '-' . rand(100, 999),
                'order_id'        => $orderId,
                'style_id'        => $styleId,
                'packing_date'    => $data['planned_date'] ?? date('Y-m-d'),
                'target_quantity' => (int)($data['total_cartons'] ?? 100),
                'status'          => 'planned',
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Packing plan created successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to create packing plan: " . $e->getMessage();
        }
        $this->redirect('packing/list');
    }

    private function handleLogCarton(array $data): void
    {
        try {
            $this->db->insert('cartons', [
                'carton_number'       => $data['carton_number'],
                'style_code'          => $data['style_code'] ?? '',
                'order_no'            => $data['order_no'] ?? '',
                'cbm'                 => $data['cbm'] ?? '0.12',
                'gross_weight'        => (float)($data['gross_weight'] ?? 15.00),
                'verification_status' => $data['verification_status'] ?? 'passed',
                'scanned_date'        => $data['scanned_date'] ?? date('Y-m-d'),
                'created_at'          => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Carton logged successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to register carton: " . $e->getMessage();
        }
        $this->redirect('packing/cartons');
    }

    private function handleLogScan(array $data): void
    {
        try {
            $barcode = $data['carton_number'];
            $weight  = (float)($data['gross_weight'] ?? 15.00);
            
            // Check if carton exists to compare target weight (standard 15.0kg)
            $target = 15.00;
            $dev = $weight - $target;
            $status = (abs($dev) <= 0.5) ? 'verified' : 'diverted'; // ±0.5kg tolerance

            // Insert into scan history
            $this->db->insert('packing_scans', [
                'carton_number' => $barcode,
                'scan_time'     => date('Y-m-d H:i:s'),
                'gross_weight'  => $weight,
                'deviation'     => $dev,
                'status'        => $status
            ]);

            // Upsert / Update status in cartons register if found
            $cartonExists = $this->db->query("SELECT COUNT(*) FROM cartons WHERE carton_number = ?", [$barcode])->fetchColumn();
            if ((int)$cartonExists > 0) {
                $this->db->update('cartons', [
                    'gross_weight' => $weight,
                    'verification_status' => ($status === 'verified') ? 'passed' : 'failed',
                    'scanned_date' => date('Y-m-d')
                ], ['carton_number' => $barcode]);
            } else {
                // Auto create verified carton
                $this->db->insert('cartons', [
                    'carton_number' => $barcode,
                    'gross_weight' => $weight,
                    'verification_status' => ($status === 'verified') ? 'passed' : 'failed',
                    'scanned_date' => date('Y-m-d'),
                    'cbm' => '0.12',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            $_SESSION['success_message'] = "Scan complete. Carton " . ($status === 'verified' ? 'VERIFIED' : 'DIVERTED (Weight out of tolerance)');
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Scan failed: " . $e->getMessage();
        }
        $this->redirect('packing/scan');
    }

    private function handleLogShipment(array $data): void
    {
        try {
            $this->db->insert('shipments', [
                'shipment_no'  => 'SHIP-' . date('Ymd') . '-' . rand(100, 999),
                'container_no' => $data['container_no'] ?? '',
                'invoice_no'   => $data['invoice_no'] ?? '',
                'qty_cartons'  => (int)($data['qty_cartons'] ?? 0),
                'destination'  => $data['destination'] ?? '',
                'ship_date'    => $data['ship_date'] ?? date('Y-m-d'),
                'status'       => 'loaded',
                'created_at'   => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Shipment loading dispatch record saved!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to dispatch shipment: " . $e->getMessage();
        }
        $this->redirect('packing/shipment');
    }

    private function handleUpdatePlanStatus(array $data): void
    {
        try {
            $planId = (int)($data['plan_id'] ?? 0);
            $status = $data['status'] ?? 'planned';

            $this->db->update('packing_plans', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['plan_id' => $planId]);
            $_SESSION['success_message'] = "Plan status updated to " . strtoupper($status);
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to update status: " . $e->getMessage();
        }
        $this->redirect('packing/list');
    }
}
