<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;

class StockTransactionController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        // 1. Fetch GRNs
        $grns = $this->db->query("
            SELECT g.*, v.vendor_name, w.warehouse_name 
            FROM grn g 
            LEFT JOIN vendors v ON g.vendor_id = v.vendor_id 
            LEFT JOIN warehouses w ON g.warehouse_id = w.warehouse_id 
            ORDER BY g.grn_id DESC 
            LIMIT 15
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Fetch Store Requisitions
        $requisitions = $this->db->query("
            SELECT r.*, d.department_name, u.username as requested_by_name 
            FROM store_requisitions r 
            LEFT JOIN departments d ON r.department_id = d.department_id 
            LEFT JOIN users u ON r.requested_by = u.user_id
            ORDER BY r.requisition_id DESC 
            LIMIT 15
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Fetch Stock Transfers
        $transfers = $this->db->query("
            SELECT t.*, fl.location_name as from_loc_name, tl.location_name as to_loc_name 
            FROM stock_transfer_requests t 
            LEFT JOIN store_locations fl ON t.from_location_id = fl.location_id 
            LEFT JOIN store_locations tl ON t.to_location_id = tl.location_id 
            ORDER BY t.transfer_id DESC 
            LIMIT 15
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 4. Fetch Gate Passes
        $gatePasses = $this->db->query("
            SELECT gp.*, d.department_name, u.username as prepared_by_name 
            FROM gate_passes gp 
            LEFT JOIN departments d ON gp.department_id = d.department_id 
            LEFT JOIN users u ON gp.prepared_by = u.user_id
            ORDER BY gp.id DESC 
            LIMIT 15
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 5. Fetch Stock Adjustments
        $adjustments = $this->db->query("
            SELECT sa.*, sl.location_name 
            FROM stock_adjustments sa 
            LEFT JOIN store_locations sl ON sa.location_id = sl.location_id 
            ORDER BY sa.adjustment_id DESC 
            LIMIT 15
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 6. Fetch Material Issues (from stock_movements where type is 'out' and reference_type is 'requisition' or 'other')
        $materialIssues = $this->db->query("
            SELECT sm.*, i.item_name, i.item_code, sl.location_name 
            FROM stock_movements sm 
            JOIN items i ON sm.item_id = i.item_id 
            LEFT JOIN store_locations sl ON sm.location_id = sl.location_id
            WHERE sm.movement_type = 'out' AND sm.reference_type IN ('requisition', 'other', 'production')
            ORDER BY sm.movement_id DESC 
            LIMIT 15
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Dropdowns data
        $items = $this->db->query("SELECT item_id, item_code, item_name, unit_price FROM items WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);
        $warehouses = $this->db->query("SELECT warehouse_id, warehouse_name FROM warehouses WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);
        $locations = $this->db->query("SELECT location_id, location_name FROM store_locations WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);
        $departments = $this->db->query("SELECT department_id, department_name FROM departments")->fetchAll(\PDO::FETCH_ASSOC);
        $vendors = $this->db->query("SELECT vendor_id, vendor_name FROM vendors")->fetchAll(\PDO::FETCH_ASSOC);
        $purchaseOrders = $this->db->query("SELECT po_id as id, po_number FROM purchase_orders")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('inventory/transactions', [
            'pageTitle' => 'Receiving & Goods Transactions',
            'activeModule' => 'store_inventory',
            'activeSubModule' => 'transactions',
            'grns' => $grns,
            'requisitions' => $requisitions,
            'transfers' => $transfers,
            'gatePasses' => $gatePasses,
            'adjustments' => $adjustments,
            'materialIssues' => $materialIssues,
            'items' => $items,
            'warehouses' => $warehouses,
            'locations' => $locations,
            'departments' => $departments,
            'vendors' => $vendors,
            'purchaseOrders' => $purchaseOrders
        ]);
    }

    public function storeGRN()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();
        
        try {
            $this->db->beginTransaction();
            
            // Auto number
            $count = $this->db->query("SELECT COUNT(*) FROM grn")->fetchColumn();
            $grnNo = 'GRN-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

            $grnId = $this->db->insert('grn', [
                'grn_number' => $grnNo,
                'po_id' => !empty($data['po_id']) ? (int)$data['po_id'] : null,
                'vendor_id' => !empty($data['vendor_id']) ? (int)$data['vendor_id'] : null,
                'warehouse_id' => (int)$data['warehouse_id'],
                'received_date' => $data['received_date'] ?? date('Y-m-d'),
                'received_by' => $_SESSION['user_id'] ?? 1,
                'total_amount' => (float)($data['total_amount'] ?? 0),
                'status' => $data['qc_required'] === 'yes' ? 'pending_qc' : 'approved',
                'remarks' => $data['remarks'] ?? ''
            ]);

            // Add lines if items are provided
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $itemId = (int)$item['item_id'];
                    $qty = (int)$item['quantity'];
                    $price = (float)$item['unit_price'];

                    $this->db->insert('grn_lines', [
                        'grn_id' => $grnId,
                        'item_id' => $itemId,
                        'quantity_received' => $qty,
                        'quantity_accepted' => $data['qc_required'] === 'yes' ? 0 : $qty,
                        'quantity_rejected' => 0,
                        'unit_price' => $price,
                        'total_amount' => $qty * $price
                    ]);

                    // Update stock and movement if approved directly
                    if ($data['qc_required'] !== 'yes') {
                        $this->updateStockAndLogMovement($itemId, (int)$data['warehouse_id'], $qty, 'in', 'purchase', $grnId, $grnNo, $price, 'GRN Receipt');
                    }
                }
            }

            $this->db->commit();
            $_SESSION['success_message'] = 'GRN ' . $grnNo . ' created successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error_message'] = 'Error creating GRN: ' . $e->getMessage();
        }

        $this->redirect('inventory/transactions');
    }

    public function storeRequisition()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();
        
        try {
            $this->db->beginTransaction();

            $count = $this->db->query("SELECT COUNT(*) FROM store_requisitions")->fetchColumn();
            $reqNo = 'SRQ-' . date('Y') . '-' . str_pad((string)($count + 1), 5, '0', STR_PAD_LEFT);

            $reqId = $this->db->insert('store_requisitions', [
                'requisition_no' => $reqNo,
                'location_id' => (int)$data['location_id'],
                'department_id' => (int)$data['department_id'],
                'requisition_date' => $data['requisition_date'] ?? date('Y-m-d'),
                'required_date' => $data['required_date'] ?? date('Y-m-d'),
                'purpose' => $data['purpose'] ?? '',
                'status' => 'pending',
                'requested_by' => $_SESSION['user_id'] ?? 1
            ]);

            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->db->insert('store_requisition_lines', [
                        'requisition_id' => $reqId,
                        'item_id' => (int)$item['item_id'],
                        'quantity_requested' => (int)$item['quantity']
                    ]);
                }
            }

            $this->db->commit();
            $_SESSION['success_message'] = 'Requisition ' . $reqNo . ' created successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('inventory/transactions');
    }

    public function storeIssue()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();
        $itemId = (int)$data['item_id'];
        $qty = (int)$data['quantity'];
        $warehouseId = (int)$data['warehouse_id'];
        $deptId = (int)$data['department_id'];

        try {
            $this->db->beginTransaction();

            // Check stock level first
            $stock = $this->db->query("SELECT * FROM stock WHERE item_id = $itemId AND warehouse_id = $warehouseId")->fetch();
            if (!$stock || (float)$stock['quantity_on_hand'] < $qty) {
                throw new \Exception('Insufficient stock on hand in the selected warehouse.');
            }

            $price = (float)$this->db->query("SELECT unit_price FROM items WHERE item_id = $itemId")->fetchColumn();
            
            // Subtract stock and log
            $refNo = 'ISS-' . rand(10000, 99999);
            $this->updateStockAndLogMovement($itemId, $warehouseId, $qty, 'out', 'requisition', $deptId, $refNo, $price, 'Material Issue to Dept ' . $deptId);

            $this->db->commit();
            $_SESSION['success_message'] = 'Material issued successfully under Reference: ' . $refNo;
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error_message'] = 'Error issuing material: ' . $e->getMessage();
        }

        $this->redirect('inventory/transactions');
    }

    public function storeTransfer()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();
        
        try {
            $this->db->beginTransaction();

            $count = $this->db->query("SELECT COUNT(*) FROM stock_transfer_requests")->fetchColumn();
            $transNo = 'TRF-' . date('Y') . '-' . str_pad((string)($count + 1), 5, '0', STR_PAD_LEFT);

            $transId = $this->db->insert('stock_transfer_requests', [
                'transfer_no' => $transNo,
                'from_location_id' => (int)$data['from_location_id'],
                'to_location_id' => (int)$data['to_location_id'],
                'request_date' => date('Y-m-d'),
                'status' => 'completed', // Direct completed transfer
                'remarks' => $data['remarks'] ?? '',
                'requested_by' => $_SESSION['user_id'] ?? 1
            ]);

            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $itemId = (int)$item['item_id'];
                    $qty = (int)$item['quantity'];

                    $this->db->insert('stock_transfer_lines', [
                        'transfer_id' => $transId,
                        'item_id' => $itemId,
                        'quantity' => $qty
                    ]);

                    // Subtract from source
                    $price = (float)$this->db->query("SELECT unit_price FROM items WHERE item_id = $itemId")->fetchColumn();
                    
                    // Subtract stock from from_location_id (we need to resolve warehouse_id)
                    $fromWh = $this->db->query("SELECT warehouse_id FROM warehouses LIMIT 1")->fetchColumn(); // Simplified mapping
                    $toWh = $this->db->query("SELECT warehouse_id FROM warehouses LIMIT 1")->fetchColumn();
                    if (!$toWh) { $toWh = $fromWh; }

                    // Out from source
                    $this->updateStockAndLogMovement($itemId, (int)$fromWh, $qty, 'transfer_out', 'transfer', $transId, $transNo, $price, 'Transfer Out to Loc ' . $data['to_location_id']);
                    // In to destination
                    $this->updateStockAndLogMovement($itemId, (int)$toWh, $qty, 'transfer_in', 'transfer', $transId, $transNo, $price, 'Transfer In from Loc ' . $data['from_location_id']);
                }
            }

            $this->db->commit();
            $_SESSION['success_message'] = 'Stock Transfer ' . $transNo . ' completed successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect('inventory/transactions');
    }

    public function storeAdjustment()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();
        
        try {
            $this->db->beginTransaction();

            $count = $this->db->query("SELECT COUNT(*) FROM stock_adjustments")->fetchColumn();
            $adjNo = 'ADJ-' . date('Y') . '-' . str_pad((string)($count + 1), 5, '0', STR_PAD_LEFT);
            $type = $data['adjustment_type'] ?? 'positive'; // positive/negative

            $adjId = $this->db->insert('stock_adjustments', [
                'adjustment_no' => $adjNo,
                'location_id' => (int)$data['location_id'],
                'adjustment_date' => date('Y-m-d'),
                'adjustment_type' => $type,
                'reason' => $data['reason'] ?? 'count_difference',
                'status' => 'approved',
                'total_adjustment_value' => 0.00,
                'remarks' => $data['remarks'] ?? '',
                'created_by' => $_SESSION['user_id'] ?? 1
            ]);

            if (!empty($data['items']) && is_array($data['items'])) {
                $totalVal = 0.00;
                foreach ($data['items'] as $item) {
                    $itemId = (int)$item['item_id'];
                    $qty = (int)$item['quantity'];
                    $price = (float)$this->db->query("SELECT unit_price FROM items WHERE item_id = $itemId")->fetchColumn();
                    $totalItemVal = $qty * $price;
                    $totalVal += $totalItemVal;

                    $this->db->insert('stock_adjustment_lines', [
                        'adjustment_id' => $adjId,
                        'item_id' => $itemId,
                        'quantity' => $qty,
                        'unit_cost' => $price,
                        'total_cost' => $totalItemVal
                    ]);

                    // Update stock
                    $whId = $this->db->query("SELECT warehouse_id FROM warehouses LIMIT 1")->fetchColumn();
                    $moveType = $type === 'positive' ? 'in' : 'out';
                    $this->updateStockAndLogMovement($itemId, (int)$whId, $qty, $moveType, 'adjustment', $adjId, $adjNo, $price, 'Stock Adjustment: ' . ($data['reason'] ?? 'count_difference'));
                }

                $this->db->query("UPDATE stock_adjustments SET total_adjustment_value = $totalVal WHERE adjustment_id = $adjId");
            }

            $this->db->commit();
            $_SESSION['success_message'] = 'Adjustment ' . $adjNo . ' processed successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error_message'] = 'Error adjusting stock: ' . $e->getMessage();
        }

        $this->redirect('inventory/transactions');
    }

    private function updateStockAndLogMovement(int $itemId, int $warehouseId, int $qty, string $movementType, string $refType, int $refId, string $refNo, float $price, string $remarks = '')
    {
        // Get or Create stock entry
        $stock = $this->db->query("SELECT * FROM stock WHERE item_id = $itemId AND warehouse_id = $warehouseId")->fetch();
        $locationId = $this->db->query("SELECT location_id FROM store_locations LIMIT 1")->fetchColumn();

        $qtyDiff = in_array($movementType, ['in', 'transfer_in', 'return']) ? $qty : -$qty;

        if ($stock) {
            $oldQty = (float)$stock['quantity_on_hand'];
            $newQty = $oldQty + $qtyDiff;
            $this->db->query("UPDATE stock SET quantity_on_hand = $newQty, quantity_available = $newQty WHERE stock_id = {$stock['stock_id']}");
        } else {
            $oldQty = 0;
            $newQty = $qtyDiff;
            $this->db->insert('stock', [
                'item_id' => $itemId,
                'location_id' => $locationId,
                'warehouse_id' => $warehouseId,
                'quantity_on_hand' => $newQty,
                'quantity_available' => $newQty
            ]);
        }

        // Insert stock movement record
        $this->db->insert('stock_movements', [
            'location_id' => $locationId,
            'item_id' => $itemId,
            'movement_type' => in_array($movementType, ['in', 'transfer_in', 'return']) ? 'in' : 'out',
            'quantity' => $qty,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'reference_no' => $refNo,
            'unit_cost' => $price,
            'total_cost' => $qty * $price,
            'balance_before' => $oldQty,
            'balance_after' => $newQty,
            'remarks' => $remarks
        ]);

        // Update main item stock level
        $totalStock = $this->db->query("SELECT SUM(quantity_on_hand) FROM stock WHERE item_id = $itemId")->fetchColumn() ?? 0;
        $this->db->query("UPDATE items SET current_stock = $totalStock WHERE item_id = $itemId");
    }
}
