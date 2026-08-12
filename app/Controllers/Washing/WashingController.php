<?php

declare(strict_types=1);

namespace App\Controllers\Washing;

use App\Controllers\BaseController;

class WashingController extends BaseController
{
    public function index(): void
    {
        $this->showTab('dashboard');
    }

    public function showTab(string $tab): void
    {
        $this->requireAuth();

        $validTabs = ['dashboard', 'plan', 'recipe', 'progress', 'qc', 'reports'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'dashboard';
        }

        // ── Ensure washing tables exist ────────────────────────────────────
        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS wash_recipes (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                recipe_name      VARCHAR(100) NOT NULL,
                wash_type        VARCHAR(50),
                temp_c           INT DEFAULT 40,
                duration_mins    INT DEFAULT 30,
                liquor_ratio     VARCHAR(20) DEFAULT '1:10',
                chemical_formula TEXT,
                instructions     TEXT,
                created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS wash_batches (
                batch_id       INT AUTO_INCREMENT PRIMARY KEY,
                batch_number   VARCHAR(50) NOT NULL,
                recipe_name    VARCHAR(100),
                wash_type      VARCHAR(50),
                total_quantity INT NOT NULL DEFAULT 100,
                machine_no     VARCHAR(50),
                status         VARCHAR(30) DEFAULT 'pending',
                wash_date      DATE,
                start_time     TIME,
                order_id       INT,
                style_id       INT,
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            $this->db->query("CREATE TABLE IF NOT EXISTS wash_qc_logs (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                batch_id         INT,
                batch_number     VARCHAR(50),
                shade_status     VARCHAR(20) DEFAULT 'pass',
                ph_level         DECIMAL(4,2) DEFAULT 7.0,
                shrinkage_status VARCHAR(20) DEFAULT 'pass',
                inspector_name   VARCHAR(100),
                qc_date          DATE,
                remarks          TEXT,
                created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (\Exception $e) {
            // Table creation error or DB down
        }

        // ── Process POST submissions ───────────────────────────────────────
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->get('action', '');
            $data   = $this->request->all();

            if ($action === 'create_batch') {
                $this->handleCreateBatch($data);
                return;
            }
            if ($action === 'create_recipe') {
                $this->handleCreateRecipe($data);
                return;
            }
            if ($action === 'update_batch_status') {
                $this->handleUpdateBatchStatus($data);
                return;
            }
            if ($action === 'log_wash_qc') {
                $this->handleLogWashQC($data);
                return;
            }
        }

        // ── Fetch real database records ─────────────────────────────────────
        $batches   = [];
        $recipes   = [];
        $qcRecords = [];
        $styles    = [];
        $orders    = [];

        try {
            $batches   = $this->db->query("SELECT * FROM wash_batches ORDER BY batch_id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $recipes   = $this->db->query("SELECT * FROM wash_recipes ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $qcRecords = $this->db->query("SELECT * FROM wash_qc_logs ORDER BY id DESC LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $styles    = $this->db->query("SELECT style_id, style_code, style_name FROM styles WHERE status != 'cancelled' LIMIT 30")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $orders    = $this->db->query("SELECT po_id AS id, po_number FROM production_orders ORDER BY po_id DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            // Schema fallback
        }

        // ── Pre-compute aggregates (avoid unsupported Twig filters) ────────
        $totalBatches = count($batches);
        $totalWashed = 0;
        $activeCount = 0;
        foreach ($batches as $b) {
            if (in_array($b['status'], ['washing', 'extracting', 'drying'])) {
                $activeCount++;
            }
            if ($b['status'] === 'completed' || $b['status'] === 'qc_passed') {
                $totalWashed += (int)$b['total_quantity'];
            }
        }
        $passCount = count(array_filter($batches, fn($b) => $b['status'] === 'qc_passed'));
        $passPct = $totalBatches > 0 ? round(($passCount / $totalBatches) * 100) : 100;

        $this->view('Washing/index', [
            'activeTab'    => $tab,
            'batches'      => $batches,
            'recipes'      => $recipes,
            'qcRecords'    => $qcRecords,
            'styles'       => $styles,
            'orders'       => $orders,
            'totalBatches' => $totalBatches,
            'totalWashed'  => $totalWashed,
            'activeCount'  => $activeCount,
            'passCount'    => $passCount,
            'passPct'      => $passPct,
            'pageTitle'    => 'Washing Control Center'
        ]);
    }

    private function handleCreateBatch(array $data): void
    {
        try {
            // Get first production order to populate order_id and style_id if not selected
            $orderId = isset($data['order_id']) && $data['order_id'] !== '' ? (int)$data['order_id'] : null;
            $styleId = isset($data['style_id']) && $data['style_id'] !== '' ? (int)$data['style_id'] : null;

            if (!$orderId || !$styleId) {
                $order = $this->db->query("SELECT po_id, style_id FROM production_orders LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                $orderId = $orderId ?: ($order ? (int)$order['po_id'] : 1);
                $styleId = $styleId ?: ($order ? (int)$order['style_id'] : 1);
            }

            $batchNo = 'WASH-' . date('Ymd') . '-' . rand(100, 999);

            $this->db->insert('wash_batches', [
                'batch_number'   => $batchNo,
                'recipe_name'    => $data['recipe_name'] ?? 'Regular wash',
                'wash_type'      => $data['wash_type'] ?? 'regular',
                'total_quantity' => (int)($data['quantity'] ?? 100),
                'machine_no'     => $data['machine_no'] ?? 'M-01',
                'wash_date'      => $data['wash_date'] ?? date('Y-m-d'),
                'start_time'     => $data['start_time'] ?? date('H:i:s'),
                'order_id'       => $orderId,
                'style_id'       => $styleId,
                'status'         => 'pending',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Wash Batch {$batchNo} scheduled successfully!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to create wash batch: " . $e->getMessage();
        }
        $this->redirect('washing/plan');
    }

    private function handleCreateRecipe(array $data): void
    {
        try {
            $this->db->insert('wash_recipes', [
                'recipe_name'      => $data['recipe_name'],
                'wash_type'        => $data['wash_type'] ?? 'regular',
                'temp_c'           => (int)($data['temp_c'] ?? 40),
                'duration_mins'    => (int)($data['duration_mins'] ?? 30),
                'liquor_ratio'     => $data['liquor_ratio'] ?? '1:10',
                'chemical_formula' => $data['chemical_formula'] ?? '',
                'instructions'     => $data['instructions'] ?? '',
                'created_at'       => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Washing recipe formulation saved!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to save recipe: " . $e->getMessage();
        }
        $this->redirect('washing/recipe');
    }

    private function handleUpdateBatchStatus(array $data): void
    {
        try {
            $batchId = (int)($data['batch_id'] ?? 0);
            $newStatus = $data['status'] ?? 'pending';

            $this->db->update('wash_batches', [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                'batch_id' => $batchId
            ]);
            $_SESSION['success_message'] = "Batch status updated to " . strtoupper($newStatus);
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to update status: " . $e->getMessage();
        }
        $this->redirect('washing/progress');
    }

    private function handleLogWashQC(array $data): void
    {
        try {
            $batchId = (int)($data['batch_id'] ?? 0);
            
            // Get batch number
            $batch = $this->db->query("SELECT batch_number FROM wash_batches WHERE batch_id = ?", [$batchId])->fetch(\PDO::FETCH_ASSOC);
            $batchNo = $batch ? $batch['batch_number'] : 'WASH-UNKNOWN';

            $this->db->insert('wash_qc_logs', [
                'batch_id'         => $batchId,
                'batch_number'     => $batchNo,
                'shade_status'     => $data['shade_status'] ?? 'pass',
                'ph_level'         => (float)($data['ph_level'] ?? 7.0),
                'shrinkage_status' => $data['shrinkage_status'] ?? 'pass',
                'inspector_name'   => $data['inspector_name'] ?? 'QC Inspector',
                'qc_date'          => $data['qc_date'] ?? date('Y-m-d'),
                'remarks'          => $data['remarks'] ?? '',
                'created_at'       => date('Y-m-d H:i:s')
            ]);

            // Update batch status to qc_passed or qc_failed
            $newStatus = ($data['shade_status'] === 'pass' && $data['shrinkage_status'] === 'pass') ? 'qc_passed' : 'qc_failed';
            $this->db->update('wash_batches', ['status' => $newStatus], ['batch_id' => $batchId]);

            $_SESSION['success_message'] = "Wash QC audit logged successfully for batch {$batchNo}!";
        } catch (\Exception $e) {
            $_SESSION['error_message'] = "Failed to log QC check: " . $e->getMessage();
        }
        $this->redirect('washing/qc');
    }
}
