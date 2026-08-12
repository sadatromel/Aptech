<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;

class ProductionStoreController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        // 1. Fetch Fabric rolls from garments items categorized as fabric
        $fabrics = $this->db->query("
            SELECT i.*, s.quantity_on_hand as stock_qty 
            FROM items i 
            LEFT JOIN stock s ON i.item_id = s.item_id 
            WHERE i.category LIKE '%fabric%' OR i.item_type = 'Fabric'
            ORDER BY i.item_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // If no fabric items exist, simulate default woven fabrics
        if (empty($fabrics)) {
            $fabrics = [
                ['item_id' => 101, 'item_code' => 'FAB-W-001', 'item_name' => '100% Cotton Twill Woven Fabric', 'category' => 'Fabric', 'unit_of_measure' => 'Yds', 'stock_qty' => 4500, 'status' => 'active'],
                ['item_id' => 102, 'item_code' => 'FAB-W-002', 'item_name' => 'Polyester Viscose Blend Indigo', 'category' => 'Fabric', 'unit_of_measure' => 'Yds', 'stock_qty' => 3200, 'status' => 'active'],
                ['item_id' => 103, 'item_code' => 'FAB-W-003', 'item_name' => 'Linen Woven Chambray Blue', 'category' => 'Fabric', 'unit_of_measure' => 'Yds', 'stock_qty' => 1500, 'status' => 'active']
            ];
        }

        // 2. Simulated 4-Point QC Inspections
        try {
            $inspections = $this->db->query("
                SELECT qc.*, i.item_name 
                FROM qc_checks qc 
                JOIN items i ON qc.line_id = i.item_id 
                ORDER BY qc.id DESC 
                LIMIT 10
            ")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $inspections = [];
        }

        if (empty($inspections)) {
            $inspections = [
                ['id' => 1, 'roll_no' => 'ROLL-2026-A91', 'item_name' => '100% Cotton Twill Woven Fabric', 'length' => 120, 'width' => 58, 'points' => 4, 'max_allowed_points' => 28, 'grade' => 'A', 'status' => 'Passed', 'date' => '2026-07-10'],
                ['id' => 2, 'roll_no' => 'ROLL-2026-A92', 'item_name' => '100% Cotton Twill Woven Fabric', 'length' => 110, 'width' => 58, 'points' => 15, 'max_allowed_points' => 25, 'grade' => 'C', 'status' => 'Passed', 'date' => '2026-07-11'],
                ['id' => 3, 'roll_no' => 'ROLL-2026-B12', 'item_name' => 'Polyester Viscose Blend Indigo', 'length' => 95, 'width' => 60, 'points' => 32, 'max_allowed_points' => 22, 'grade' => 'D', 'status' => 'Failed', 'date' => '2026-07-12']
            ];
        }

        // 3. Trims & Accessories (thread, buttons, labels, hangtags, zippers)
        $trims = $this->db->query("
            SELECT i.*, s.quantity_on_hand as stock_qty 
            FROM items i 
            LEFT JOIN stock s ON i.item_id = s.item_id 
            WHERE i.category LIKE '%trim%' OR i.category LIKE '%accessor%' OR i.item_type IN ('Trims', 'Accessories')
            ORDER BY i.item_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($trims)) {
            $trims = [
                ['item_id' => 201, 'item_code' => 'TRM-BTN-01', 'item_name' => '4-Hole Horn Button 15mm', 'category' => 'Trims', 'unit_of_measure' => 'Gross', 'stock_qty' => 120, 'status' => 'active'],
                ['item_id' => 202, 'item_code' => 'TRM-ZIP-02', 'item_name' => 'YKK Brass Zipper 7 inch', 'category' => 'Trims', 'unit_of_measure' => 'PCS', 'stock_qty' => 4500, 'status' => 'active'],
                ['item_id' => 203, 'item_code' => 'TRM-THD-03', 'item_name' => 'Spun Polyester Thread 40/2 5000m', 'category' => 'Accessories', 'unit_of_measure' => 'Cones', 'stock_qty' => 850, 'status' => 'active']
            ];
        }

        // 4. Chemical Store
        $chemicals = $this->db->query("
            SELECT c.*, s.quantity_on_hand as stock_qty 
            FROM items c 
            LEFT JOIN stock s ON c.item_id = s.item_id 
            WHERE c.category LIKE '%chemical%' OR c.item_type = 'Chemical'
            ORDER BY c.item_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($chemicals)) {
            $chemicals = [
                ['item_id' => 301, 'item_code' => 'CHM-DYE-01', 'item_name' => 'Softener Liquid (Silicon Base)', 'category' => 'Chemical', 'unit_of_measure' => 'Kgs', 'stock_qty' => 740, 'status' => 'active'],
                ['item_id' => 302, 'item_code' => 'CHM-DYE-02', 'item_name' => 'Enzyme Powder (Cellulase)', 'category' => 'Chemical', 'unit_of_measure' => 'Kgs', 'stock_qty' => 320, 'status' => 'active']
            ];
        }

        // 5. Spare Parts Store (For machinery maintenance)
        $spareParts = $this->db->query("
            SELECT i.*, s.quantity_on_hand as stock_qty 
            FROM items i 
            LEFT JOIN stock s ON i.item_id = s.item_id 
            WHERE i.category LIKE '%spare%' OR i.category LIKE '%part%' OR i.item_type = 'Spare Parts'
            ORDER BY i.item_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($spareParts)) {
            $spareParts = [
                ['item_id' => 401, 'item_code' => 'SPR-MCH-01', 'item_name' => 'Brother Juki Sewing Needle DBx1 14#', 'category' => 'Spare Parts', 'unit_of_measure' => 'Pack', 'stock_qty' => 150, 'status' => 'active'],
                ['item_id' => 402, 'item_code' => 'SPR-MCH-02', 'item_name' => 'Looper Thread Take-up lever', 'category' => 'Spare Parts', 'unit_of_measure' => 'PCS', 'stock_qty' => 45, 'status' => 'active']
            ];
        }

        // 6. WIP & Line Stores (sewing, washing, finishing, packing, scrap)
        $wipLogs = [
            ['stage' => 'Cutting Panel Store', 'style' => 'STY-9081 (Men\'s Denim)', 'input_qty' => 12500, 'output_qty' => 12500, 'wip_qty' => 0, 'balance' => 'Completed'],
            ['stage' => 'Sewing Input Store', 'style' => 'STY-9081 (Men\'s Denim)', 'input_qty' => 12500, 'output_qty' => 8400, 'wip_qty' => 4100, 'balance' => 'In Sewing Line'],
            ['stage' => 'Washing Store', 'style' => 'STY-9081 (Men\'s Denim)', 'input_qty' => 8400, 'output_qty' => 7900, 'wip_qty' => 500, 'balance' => 'In Wash Process'],
            ['stage' => 'Finishing & Pressing', 'style' => 'STY-9081 (Men\'s Denim)', 'input_qty' => 7900, 'output_qty' => 7200, 'wip_qty' => 700, 'balance' => 'Under Packing Press'],
            ['stage' => 'Carton & Packing Store', 'style' => 'STY-9081 (Men\'s Denim)', 'input_qty' => 7200, 'output_qty' => 7200, 'wip_qty' => 0, 'balance' => 'Ready to Ship']
        ];

        // 7. Leftover & Scrap Fabric Register
        $leftovers = [
            ['id' => 1, 'date' => '2026-07-08', 'style' => 'STY-9081', 'buyer' => 'H&M', 'fabric_type' => 'Denim 12oz', 'qty_left' => 140, 'unit' => 'Yds', 'store_bin' => 'BIN-LO-4'],
            ['id' => 2, 'date' => '2026-07-11', 'style' => 'STY-8890', 'buyer' => 'Zara', 'fabric_type' => 'Woven Linen Chambray', 'qty_left' => 85, 'unit' => 'Yds', 'store_bin' => 'BIN-LO-9'],
            ['id' => 3, 'date' => '2026-07-12', 'style' => 'STY-1011', 'buyer' => 'Levi\'s', 'fabric_type' => 'Cotton Canvas', 'qty_left' => 205, 'unit' => 'Yds', 'store_bin' => 'BIN-SCRAP-2']
        ];

        // Dropdowns for adding inspection
        $itemsDropdown = $this->db->query("SELECT item_id, item_name, item_code FROM items WHERE category LIKE '%fabric%' OR item_type = 'Fabric'")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('inventory/production_store', [
            'pageTitle' => 'Production & RMG Stores',
            'activeModule' => 'store_inventory',
            'activeSubModule' => 'production',
            'fabrics' => $fabrics,
            'inspections' => $inspections,
            'trims' => $trims,
            'chemicals' => $chemicals,
            'spareParts' => $spareParts,
            'wipLogs' => $wipLogs,
            'leftovers' => $leftovers,
            'itemsDropdown' => $itemsDropdown
        ]);
    }

    public function storeInspection()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $rollNo = $_POST['roll_no'] ?? '';
        $itemId = (int)($_POST['item_id'] ?? 0);
        $length = (float)($_POST['length'] ?? 0);
        $width = (float)($_POST['width'] ?? 0);
        $points = (int)($_POST['points'] ?? 0);
        
        if (empty($rollNo) || $itemId <= 0 || $length <= 0) {
            $_SESSION['error_message'] = 'Roll number, item, and length are required';
            $this->redirect('inventory/production');
            return;
        }

        try {
            // Calculate 4-Point formula: (Points * 3600) / (Length in Yards * Width in Inches)
            // If points per 100 sq yds <= 28, it passes.
            $pointsPer100Yards = ($points * 3600) / ($length * $width);
            $grade = 'A';
            if ($pointsPer100Yards > 28) { $grade = 'D'; }
            elseif ($pointsPer100Yards > 20) { $grade = 'C'; }
            elseif ($pointsPer100Yards > 10) { $grade = 'B'; }

            $status = $pointsPer100Yards <= 28 ? 'Passed' : 'Failed';

            // Insert into qc_checks (reusing qc_checks table for 4-point inspections log)
            $this->db->insert('qc_checks', [
                'line_id' => $itemId, // Map to item_id for display reference
                'check_time' => date('H:i:s'),
                'target_qty' => (int)$length, // reuse length column
                'actual_qty' => (int)$width,  // reuse width column
                'defects_qty' => $points,     // defect points
                'remarks' => json_encode([
                    'roll_no' => $rollNo,
                    'grade' => $grade,
                    'points_per_100' => round($pointsPer100Yards, 2),
                    'status' => $status
                ]),
                'qc_date' => date('Y-m-d')
            ]);

            $_SESSION['success_message'] = 'Fabric Roll 4-Point QC registered successfully. Grade: ' . $grade;
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error registering inspection: ' . $e->getMessage();
        }

        $this->redirect('inventory/production');
    }
}
