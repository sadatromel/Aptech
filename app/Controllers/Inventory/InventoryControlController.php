<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;

class InventoryControlController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        // 1. Fetch physical count schedules
        $audits = [
            ['schedule_id' => 1, 'title' => 'Mid-Year Fabric Audit', 'start_date' => '2026-06-15', 'end_date' => '2026-06-20', 'warehouse' => 'Main Fabric Store (W1)', 'status' => 'Completed', 'variance_found' => '0.4%'],
            ['schedule_id' => 2, 'title' => 'Trims & Accessories Cycle Count', 'start_date' => '2026-07-01', 'end_date' => '2026-07-02', 'warehouse' => 'Accessories Store', 'status' => 'Completed', 'variance_found' => '0.0%'],
            ['schedule_id' => 3, 'title' => 'Annual General Store Audit', 'start_date' => '2026-12-15', 'end_date' => '2026-12-25', 'warehouse' => 'All Warehouses', 'status' => 'Scheduled', 'variance_found' => 'N/A']
        ];

        // 2. Fetch pending transaction approvals count
        $pendingRequisitions = $this->db->query("
            SELECT r.*, d.department_name, u.username as requested_by_name 
            FROM store_requisitions r 
            LEFT JOIN departments d ON r.department_id = d.department_id 
            LEFT JOIN users u ON r.requested_by = u.user_id 
            WHERE r.status = 'pending'
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $pendingAdjustments = $this->db->query("
            SELECT sa.*, sl.location_name 
            FROM stock_adjustments sa 
            LEFT JOIN store_locations sl ON sa.location_id = sl.location_id 
            WHERE sa.status = 'pending'
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Consolidated Group Stock Valuation (Multi-Company Report)
        $companyValuations = $this->db->query("
            SELECT f.factory_name as company_name, 
                   COUNT(DISTINCT s.item_id) as total_items, 
                   SUM(s.quantity_on_hand) as total_qty, 
                   SUM(s.quantity_on_hand * i.unit_price) as total_value 
            FROM stock s 
            JOIN items i ON s.item_id = i.item_id 
            LEFT JOIN store_locations sl ON s.location_id = sl.location_id
            LEFT JOIN factories f ON sl.factory_id = f.factory_id
            GROUP BY f.factory_id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Fallback simulated data if empty
        if (empty($companyValuations)) {
            $companyValuations = [
                ['company_name' => 'Aptech Garments Ltd. (Unit 1)', 'total_items' => 450, 'total_qty' => 125000, 'total_value' => 845000.00],
                ['company_name' => 'Aptech Wovens Ltd. (Unit 2)', 'total_items' => 320, 'total_qty' => 98000, 'total_value' => 612000.00],
                ['company_name' => 'Aptech Fashion Group', 'total_items' => 120, 'total_qty' => 45000, 'total_value' => 310000.00]
            ];
        }

        // 4. Reports & Analysis (Slow Moving/Dead Stock simulation)
        $abcAnalysis = [
            ['class' => 'A (High Value/Fast Moving)', 'items_count' => 45, 'percentage' => '10%', 'value' => '$1,240,000.00', 'share' => '70%'],
            ['class' => 'B (Medium Value/Moderate)', 'items_count' => 90, 'percentage' => '20%', 'value' => '$350,000.00', 'share' => '20%'],
            ['class' => 'C (Low Value/Slow Moving)', 'items_count' => 315, 'percentage' => '70%', 'value' => '$177,000.00', 'share' => '10%']
        ];

        // 5. Stock aging list
        $agingReport = [
            ['item_code' => 'FAB-W-003', 'item_name' => 'Linen Woven Chambray Blue', 'qty' => 1500, 'value' => 6000.00, 'days_in_store' => '180+ Days', 'status' => 'Slow Moving'],
            ['item_code' => 'TRM-ZIP-02', 'item_name' => 'YKK Brass Zipper 7 inch', 'qty' => 4500, 'value' => 1125.00, 'days_in_store' => '90-180 Days', 'status' => 'Normal'],
            ['item_code' => 'CHM-DYE-02', 'item_name' => 'Enzyme Powder (Cellulase)', 'qty' => 320, 'value' => 1600.00, 'days_in_store' => '240+ Days', 'status' => 'Dead Stock']
        ];

        $items = $this->db->query("SELECT item_id, item_code, item_name, unit_price FROM items WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('inventory/control', [
            'pageTitle' => 'Inventory Control & Approvals',
            'activeModule' => 'store_inventory',
            'activeSubModule' => 'control',
            'audits' => $audits,
            'pendingRequisitions' => $pendingRequisitions,
            'pendingAdjustments' => $pendingAdjustments,
            'companyValuations' => $companyValuations,
            'abcAnalysis' => $abcAnalysis,
            'agingReport' => $agingReport,
            'items' => $items
        ]);
    }

    public function processApproval()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $type = $_POST['type'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? ''; // approve/reject
        
        if (empty($type) || $id <= 0 || empty($action)) {
            $_SESSION['error_message'] = 'Invalid approval arguments';
            $this->redirect('inventory/control');
            return;
        }

        try {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $userId = $_SESSION['user_id'] ?? 1;

            if ($type === 'requisition') {
                $this->db->query("UPDATE store_requisitions SET status = '$status', approved_by = $userId, approved_date = NOW() WHERE requisition_id = $id");
                
                // If approved, trigger auto log or status adjustment
                $_SESSION['success_message'] = 'Requisition successfully ' . $status;
            } elseif ($type === 'adjustment') {
                $this->db->query("UPDATE stock_adjustments SET status = '$status', approved_by = $userId, approved_date = NOW() WHERE adjustment_id = $id");
                $_SESSION['success_message'] = 'Adjustment successfully ' . $status;
            }

        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error processing approval: ' . $e->getMessage();
        }

        $this->redirect('inventory/control');
    }

    public function generateBarcode()
    {
        $this->requireAuth();
        $itemId = (int)($_GET['item_id'] ?? 0);
        
        if ($itemId <= 0) {
            echo "Invalid Item ID";
            return;
        }

        $item = $this->db->query("SELECT item_code, item_name, unit_price FROM items WHERE item_id = $itemId")->fetch();
        if (!$item) {
            echo "Item not found";
            return;
        }

        // Simple SVG generator of barcode lines
        header('Content-Type: image/svg+xml');
        echo '<?xml version="1.0" standalone="no"?>';
        echo '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">';
        echo '<svg width="250" height="120" xmlns="http://www.w3.org/2000/svg" version="1.1">';
        echo '<rect width="250" height="120" fill="#ffffff"/>';
        echo '<text x="125" y="25" font-family="Arial" font-size="12" text-anchor="middle" fill="#000000">' . htmlspecialchars($item['item_name']) . '</text>';
        
        // Render simple mock barcode lines
        $code = $item['item_code'];
        $len = strlen($code);
        $x = 35;
        for ($i = 0; $i < $len; $i++) {
            $charVal = ord($code[$i]);
            $w1 = ($charVal % 3) + 1;
            $w2 = (($charVal + 1) % 4) + 1;
            echo '<rect x="' . $x . '" y="40" width="' . $w1 . '" height="50" fill="#000000"/>';
            $x += $w1 + $w2;
        }
        
        echo '<text x="125" y="105" font-family="Courier New" font-size="14" font-weight="bold" text-anchor="middle" fill="#000000">' . htmlspecialchars($code) . '</text>';
        echo '</svg>';
    }
}
