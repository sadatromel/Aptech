<?php

declare(strict_types=1);

namespace App\Controllers\Production;

use App\Controllers\BaseController;

class QualityController extends BaseController
{
    public function index(): void
    {
        $this->showTab('dashboard');
    }

    public function showTab(string $tab): void
    {
        $this->requireAuth();

        $validTabs = ['dashboard', 'inline', 'audit', 'library', 'capa', 'reports'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'dashboard';
        }

        // ── Ensure quality tables exist ────────────────────────────────────
        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS quality_inline_qc (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                line_name       VARCHAR(50),
                style_code      VARCHAR(50),
                qty_inspected   INT NOT NULL DEFAULT 0,
                qty_defective   INT NOT NULL DEFAULT 0,
                defect_type     VARCHAR(100),
                check_point     VARCHAR(50),
                inspector_name  VARCHAR(100),
                inspection_date DATE,
                remarks         TEXT,
                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS quality_audit (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                po_number       VARCHAR(50),
                style_code      VARCHAR(50),
                qty_ordered     INT NOT NULL DEFAULT 0,
                qty_inspected   INT NOT NULL DEFAULT 0,
                qty_defective   INT NOT NULL DEFAULT 0,
                aql_level       VARCHAR(10) DEFAULT '2.5',
                audit_status    VARCHAR(20) DEFAULT 'pass',
                auditor_name    VARCHAR(100),
                audit_date      DATE,
                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS quality_defects_library (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                defect_code     VARCHAR(20) NOT NULL UNIQUE,
                defect_name     VARCHAR(100) NOT NULL,
                defect_category VARCHAR(50),
                severity        VARCHAR(20) DEFAULT 'major',
                description     TEXT
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS quality_capa (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                defect_type       VARCHAR(100),
                root_cause        TEXT,
                corrective_action TEXT,
                preventive_action TEXT,
                assigned_to       VARCHAR(100),
                target_date       DATE,
                status            VARCHAR(20) DEFAULT 'open',
                created_at        DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // Seed standard defects if empty
            $count = $this->db->query("SELECT COUNT(*) FROM quality_defects_library")->fetchColumn();
            if ((int)$count === 0) {
                $defects = [
                    ['DF-001', 'Puckering', 'Sewing', 'major', 'Seam puckering due to tension or needle heat.'],
                    ['DF-002', 'Skip Stitch', 'Sewing', 'major', 'Skipped stitches along seams.'],
                    ['DF-003', 'Broken Stitch', 'Sewing', 'major', 'Stitch lines that have broken or come loose.'],
                    ['DF-004', 'Uneven Hemming', 'Sewing', 'minor', 'Hemline width varies beyond tolerances.'],
                    ['DF-005', 'Open Seam', 'Sewing', 'critical', 'Parts of seam are not joined, resulting in a hole.'],
                    ['DF-006', 'Fabric Hole', 'Fabric', 'critical', 'Holes or slubs in the knit/woven fabric body.'],
                    ['DF-007', 'Shade Variation', 'Fabric', 'major', 'Color variation between different panels.'],
                    ['DF-008', 'Dirty Spot / Stain', 'Finishing', 'minor', 'Oil, dust, or grease stains from machinery.'],
                    ['DF-009', 'Measurement Deviation', 'Cutting', 'major', 'Garment sizes out of buyer specified tolerance.'],
                ];
                foreach ($defects as $df) {
                    $this->db->query(
                        "INSERT INTO quality_defects_library (defect_code, defect_name, defect_category, severity, description) VALUES (?,?,?,?,?)",
                        $df
                    );
                }
            }
        } catch (\Exception $e) {
            // Schema fallback
        }

        // ── Process POST submissions ───────────────────────────────────────
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->get('action', '');
            $data   = $this->request->all();

            if ($action === 'log_inline_qc') {
                $this->handleLogInlineQC($data);
                return;
            }
            if ($action === 'log_final_audit') {
                $this->handleLogFinalAudit($data);
                return;
            }
            if ($action === 'create_capa') {
                $this->handleCreateCapa($data);
                return;
            }
            if ($action === 'update_capa_status') {
                $this->handleUpdateCapaStatus($data);
                return;
            }
        }

        // ── Fetch real database records ─────────────────────────────────────
        $inlineRecords = [];
        $auditRecords  = [];
        $defectLibrary = [];
        $capaRecords   = [];
        $orders        = [];
        $styles        = [];
        $lines         = [];

        try {
            $inlineRecords = $this->db->query("SELECT * FROM quality_inline_qc ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $auditRecords  = $this->db->query("SELECT * FROM quality_audit ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $defectLibrary = $this->db->query("SELECT * FROM quality_defects_library ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $capaRecords   = $this->db->query("SELECT * FROM quality_capa ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $orders        = $this->db->query("SELECT po_id AS id, po_number FROM production_orders ORDER BY po_id DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $styles        = $this->db->query("SELECT style_id, style_code, style_name FROM styles WHERE status != 'cancelled' LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $lines         = $this->db->query("SELECT * FROM production_lines WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            // Schema fallback
        }

        // ── Pre-compute aggregates (avoid unsupported Twig filters) ────────
        $totalInspected = 0;
        $totalDefective = 0;
        
        foreach ($inlineRecords as $rec) {
            $totalInspected += (int)$rec['qty_inspected'];
            $totalDefective += (int)$rec['qty_defective'];
        }

        $auditCount  = count($auditRecords);
        $passedAudit = count(array_filter($auditRecords, fn($a) => $a['audit_status'] === 'pass'));
        $passRate    = $auditCount > 0 ? round(($passedAudit / $auditCount) * 100) : 98;
        $openCapa    = count(array_filter($capaRecords, fn($c) => $c['status'] !== 'closed'));

        $this->view('production/quality', [
            'activeTab'      => $tab,
            'inlineRecords'  => $inlineRecords,
            'auditRecords'   => $auditRecords,
            'defectLibrary'  => $defectLibrary,
            'capaRecords'    => $capaRecords,
            'orders'         => $orders,
            'styles'         => $styles,
            'lines'          => $lines,
            'totalInspected' => $totalInspected,
            'totalDefective' => $totalDefective,
            'passRate'       => $passRate,
            'openCapa'       => $openCapa,
            'pageTitle'      => 'Quality Assurance Hub'
        ]);
    }

    private function handleLogInlineQC(array $data): void
    {
        try {
            $this->db->insert('quality_inline_qc', [
                'line_name'       => $data['line_name'] ?? 'Line 1',
                'style_code'      => $data['style_code'] ?? '',
                'qty_inspected'   => (int)($data['qty_inspected'] ?? 0),
                'qty_defective'   => (int)($data['qty_defective'] ?? 0),
                'defect_type'     => $data['defect_type'] ?? '',
                'check_point'     => $data['check_point'] ?? 'endline',
                'inspector_name'  => $data['inspector_name'] ?? 'QC Inspector',
                'inspection_date' => $data['inspection_date'] ?? date('Y-m-d'),
                'remarks'         => $data['remarks'] ?? '',
                'created_at'      => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Inline QC record saved successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to log inline QC: " . $e->getMessage();
        }
        $this->redirect('quality/inline');
    }

    private function handleLogFinalAudit(array $data): void
    {
        try {
            $inspected = (int)($data['qty_inspected'] ?? 0);
            $defective = (int)($data['qty_defective'] ?? 0);
            
            // Auto calculate audit status based on simple standard threshold (e.g. max 2% defectives allowed for AQL 2.5)
            $defectPct = $inspected > 0 ? ($defective / $inspected) * 100 : 0;
            $status = $defectPct <= 2.5 ? 'pass' : 'fail';

            $this->db->insert('quality_audit', [
                'po_number'     => $data['po_number'] ?? '',
                'style_code'    => $data['style_code'] ?? '',
                'qty_ordered'   => (int)($data['qty_ordered'] ?? 1000),
                'qty_inspected' => $inspected,
                'qty_defective' => $defective,
                'aql_level'     => $data['aql_level'] ?? '2.5',
                'audit_status'  => $status,
                'auditor_name'  => $data['auditor_name'] ?? 'QA Auditor',
                'audit_date'    => $data['audit_date'] ?? date('Y-m-d'),
                'created_at'    => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Final AQL Audit logged successfully (Result: " . strtoupper($status) . ")!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to log final audit: " . $e->getMessage();
        }
        $this->redirect('quality/audit');
    }

    private function handleCreateCapa(array $data): void
    {
        try {
            $this->db->insert('quality_capa', [
                'defect_type'       => $data['defect_type'],
                'root_cause'        => $data['root_cause'] ?? '',
                'corrective_action' => $data['corrective_action'] ?? '',
                'preventive_action' => $data['preventive_action'] ?? '',
                'assigned_to'       => $data['assigned_to'] ?? '',
                'target_date'       => $data['target_date'] ?? date('Y-m-d'),
                'status'            => 'open',
                'created_at'        => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "CAPA corrective plan registered!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to create CAPA plan: " . $e->getMessage();
        }
        $this->redirect('quality/capa');
    }

    private function handleUpdateCapaStatus(array $data): void
    {
        try {
            $capaId = (int)($data['capa_id'] ?? 0);
            $newStatus = $data['status'] ?? 'open';

            $this->db->update('quality_capa', [
                'status' => $newStatus
            ], [
                'id' => $capaId
            ]);
            $_SESSION['success_message'] = "CAPA status updated to " . strtoupper($newStatus);
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to update CAPA status: " . $e->getMessage();
        }
        $this->redirect('quality/capa');
    }
}
