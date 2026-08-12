<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;

class FinishingController extends BaseController
{
    public function index(): void
    {
        $this->showTab('dashboard');
    }

    public function showTab(string $tab): void
    {
        $this->requireAuth();

        $validTabs = ['dashboard', 'receive', 'ironing', 'qc', 'packing', 'reports'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'dashboard';
        }

        // ── Ensure finishing tables exist ──────────────────────────────────
        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS finishing_receive (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                batch_no      VARCHAR(50),
                po_number     VARCHAR(50),
                style_code    VARCHAR(50),
                qty_received  INT NOT NULL DEFAULT 0,
                received_date DATE,
                remarks       TEXT,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS finishing_ironing (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                receive_id    INT,
                batch_no      VARCHAR(50),
                qty_ironed    INT NOT NULL DEFAULT 0,
                qty_defective INT NOT NULL DEFAULT 0,
                operator_name VARCHAR(100),
                ironing_date  DATE,
                remarks       TEXT,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS finishing_qc (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                qty_inspected   INT NOT NULL DEFAULT 0,
                qty_passed      INT NOT NULL DEFAULT 0,
                qty_rejected    INT NOT NULL DEFAULT 0,
                defect_type     VARCHAR(100),
                needle_status   VARCHAR(20) DEFAULT 'pass',
                inspector_name  VARCHAR(100),
                style_code      VARCHAR(50),
                inspection_date DATE,
                remarks         TEXT,
                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS finishing_packing (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                style_code    VARCHAR(50),
                po_number     VARCHAR(50),
                packing_type  VARCHAR(50) DEFAULT 'flat',
                qty_folded    INT NOT NULL DEFAULT 0,
                qty_packed    INT NOT NULL DEFAULT 0,
                carton_count  INT NOT NULL DEFAULT 0,
                pack_date     DATE,
                remarks       TEXT,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (\Exception $e) {
            // Schema already exists or DB unavailable
        }

        // ── Fetch data ────────────────────────────────────────────────────
        $receives  = [];
        $ironings  = [];
        $qcRecords = [];
        $packings  = [];
        $orders    = [];
        $styles    = [];

        try {
            $receives  = $this->db->query("SELECT * FROM finishing_receive  ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $ironings  = $this->db->query("SELECT * FROM finishing_ironing  ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $qcRecords = $this->db->query("SELECT * FROM finishing_qc       ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $packings  = $this->db->query("SELECT * FROM finishing_packing  ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $orders    = $this->db->query("SELECT po_id AS id, po_number FROM production_orders ORDER BY po_id DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $styles    = $this->db->query("SELECT style_id, style_code, style_name FROM styles WHERE status != 'cancelled' LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            // Tables may not exist yet
        }

        // ── Pre-compute aggregates (avoid unsupported Twig filters) ───────
        $totalReceived = array_sum(array_column($receives,  'qty_received'));
        $totalIroned   = array_sum(array_column($ironings,  'qty_ironed'));
        $totalPassed   = array_sum(array_column($qcRecords, 'qty_passed'));
        $totalRejected = array_sum(array_column($qcRecords, 'qty_rejected'));
        $totalPacked   = array_sum(array_column($packings,  'qty_packed'));
        $totalCartons  = array_sum(array_column($packings,  'carton_count'));
        $totalInspected = array_sum(array_column($qcRecords, 'qty_inspected'));
        $passRate      = $totalInspected > 0 ? round(($totalPassed / $totalInspected) * 100) : 0;
        $ironDefects   = array_sum(array_column($ironings,  'qty_defective'));
        $needleFails   = count(array_filter($qcRecords, fn($r) => ($r['needle_status'] ?? 'pass') === 'fail'));

        // ── Handle POST actions ───────────────────────────────────────────
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->get('action', '');
            $data   = $this->request->all();

            switch ($action) {
                case 'log_receive':
                    try {
                        $this->db->query(
                            "INSERT INTO finishing_receive (batch_no, po_number, style_code, qty_received, received_date, remarks, created_at)
                             VALUES (?,?,?,?,?,?, NOW())",
                            [
                                $data['batch_no']    ?? '',
                                $data['po_number']   ?? '',
                                $data['style_code']  ?? '',
                                (int)($data['qty_received'] ?? 0),
                                $data['received_date'] ?? date('Y-m-d'),
                                $data['remarks']     ?? '',
                            ]
                        );
                        $_SESSION['success_message'] = "Washing batch received and logged successfully!";
                    } catch (\Exception $e) {
                        $_SESSION['error_message'] = "Failed to log receive: " . $e->getMessage();
                    }
                    $this->redirect('finishing/receive');
                    return;

                case 'log_ironing':
                    try {
                        $this->db->query(
                            "INSERT INTO finishing_ironing (batch_no, qty_ironed, qty_defective, operator_name, ironing_date, remarks, created_at)
                             VALUES (?,?,?,?,?,?, NOW())",
                            [
                                $data['batch_no']      ?? '',
                                (int)($data['qty_ironed']    ?? 0),
                                (int)($data['qty_defective'] ?? 0),
                                $data['operator_name'] ?? '',
                                $data['ironing_date']  ?? date('Y-m-d'),
                                $data['remarks']       ?? '',
                            ]
                        );
                        $_SESSION['success_message'] = "Ironing log saved successfully!";
                    } catch (\Exception $e) {
                        $_SESSION['error_message'] = "Failed to log ironing: " . $e->getMessage();
                    }
                    $this->redirect('finishing/ironing');
                    return;

                case 'log_qc':
                    try {
                        $this->db->query(
                            "INSERT INTO finishing_qc (qty_inspected, qty_passed, qty_rejected, defect_type, needle_status, inspector_name, style_code, inspection_date, remarks, created_at)
                             VALUES (?,?,?,?,?,?,?,?,?, NOW())",
                            [
                                (int)($data['qty_inspected']  ?? 0),
                                (int)($data['qty_passed']     ?? 0),
                                (int)($data['qty_rejected']   ?? 0),
                                $data['defect_type']    ?? '',
                                $data['needle_status']  ?? 'pass',
                                $data['inspector_name'] ?? '',
                                $data['style_code']     ?? '',
                                $data['inspection_date'] ?? date('Y-m-d'),
                                $data['remarks']        ?? '',
                            ]
                        );
                        $_SESSION['success_message'] = "Final QC inspection logged successfully!";
                    } catch (\Exception $e) {
                        $_SESSION['error_message'] = "Failed to log QC: " . $e->getMessage();
                    }
                    $this->redirect('finishing/qc');
                    return;

                case 'log_packing':
                    try {
                        $this->db->query(
                            "INSERT INTO finishing_packing (style_code, po_number, packing_type, qty_folded, qty_packed, carton_count, pack_date, remarks, created_at)
                             VALUES (?,?,?,?,?,?,?,?, NOW())",
                            [
                                $data['style_code']   ?? '',
                                $data['po_number']    ?? '',
                                $data['packing_type'] ?? 'flat',
                                (int)($data['qty_folded']   ?? 0),
                                (int)($data['qty_packed']   ?? 0),
                                (int)($data['carton_count'] ?? 0),
                                $data['pack_date']    ?? date('Y-m-d'),
                                $data['remarks']      ?? '',
                            ]
                        );
                        $_SESSION['success_message'] = "Packing record saved successfully!";
                    } catch (\Exception $e) {
                        $_SESSION['error_message'] = "Failed to log packing: " . $e->getMessage();
                    }
                    $this->redirect('finishing/packing');
                    return;
            }
        }

        // ── Render view ───────────────────────────────────────────────────
        $this->view('production/finishing', [
            'activeTab'      => $tab,
            'pageTitle'      => 'Finishing Control Center',
            'receives'       => $receives,
            'ironings'       => $ironings,
            'qcRecords'      => $qcRecords,
            'packings'       => $packings,
            'orders'         => $orders,
            'styles'         => $styles,
            'totalReceived'  => $totalReceived,
            'totalIroned'    => $totalIroned,
            'totalPassed'    => $totalPassed,
            'totalRejected'  => $totalRejected,
            'totalPacked'    => $totalPacked,
            'totalCartons'   => $totalCartons,
            'totalInspected' => $totalInspected,
            'passRate'       => $passRate,
            'ironDefects'    => $ironDefects,
            'needleFails'    => $needleFails,
        ]);
    }
}
