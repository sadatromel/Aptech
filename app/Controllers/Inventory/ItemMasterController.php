<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\Item;

class ItemMasterController extends BaseController
{
    public function index()
    {
        $this->requireAuth();
        
        // Fetch all items with category and vendor details
        $items = $this->db->query("
            SELECT i.*, v.vendor_name 
            FROM items i 
            LEFT JOIN vendors v ON i.supplier_id = v.vendor_id 
            ORDER BY i.item_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Fetch categories
        $categories = $this->db->query("SELECT * FROM item_categories ORDER BY category_name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Fetch UOMs (Unit of Measure) from dynamic uoms table
        $uoms = $this->db->query("SELECT uom_code FROM uoms WHERE status = 'active' ORDER BY uom_code ASC")->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($uoms)) {
            $uoms = ['PCS', 'Yds', 'Kgs', 'Mtr', 'Ltr', 'Box', 'Dzn', 'Roll', 'Cone'];
        }
        
        // Fetch Brands (from buyer_brands or vendors)
        $brands = $this->db->query("SELECT * FROM buyer_brands ORDER BY brand_name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Fetch Suppliers
        $suppliers = $this->db->query("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('inventory/item_master', [
            'pageTitle' => 'Item Master Management',
            'activeModule' => 'store_inventory',
            'activeSubModule' => 'item_master',
            'items' => $items,
            'categories' => $categories,
            'uoms' => $uoms,
            'brands' => $brands,
            'suppliers' => $suppliers
        ]);
    }

    public function store()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();
        
        try {
            // Generate auto code if empty
            if (empty($data['item_code'])) {
                $count = $this->db->query("SELECT COUNT(*) FROM items")->fetchColumn();
                $data['item_code'] = 'ITEM-' . str_pad((string)($count + 1), 5, '0', STR_PAD_LEFT);
            }

            $this->db->insert('items', [
                'item_code' => $data['item_code'],
                'item_name' => $data['item_name'],
                'item_type' => $data['item_type'] ?? 'Raw Material',
                'category' => $data['category'] ?? '',
                'unit_of_measure' => $data['unit_of_measure'] ?? 'PCS',
                'min_stock_level' => (int)($data['min_stock_level'] ?? 0),
                'max_stock_level' => (int)($data['max_stock_level'] ?? 0),
                'unit_price' => (float)($data['unit_price'] ?? 0),
                'supplier_id' => !empty($data['supplier_id']) ? (int)$data['supplier_id'] : null,
                'status' => $data['status'] ?? 'active',
                'current_stock' => (int)($data['opening_balance'] ?? 0)
            ]);

            // If opening balance > 0, insert an initial stock entry and log it in stock_movements
            $openingBalance = (int)($data['opening_balance'] ?? 0);
            if ($openingBalance > 0) {
                $itemId = (int)$this->db->lastInsertId();
                
                // Get default warehouse/location
                $locationId = $this->db->query("SELECT location_id FROM store_locations LIMIT 1")->fetchColumn();
                $warehouseId = $this->db->query("SELECT warehouse_id FROM warehouses LIMIT 1")->fetchColumn();
                
                if ($locationId && $warehouseId) {
                    $this->db->insert('stock', [
                        'item_id' => $itemId,
                        'location_id' => $locationId,
                        'warehouse_id' => $warehouseId,
                        'quantity_on_hand' => $openingBalance,
                        'quantity_available' => $openingBalance,
                        'reorder_level' => (int)($data['min_stock_level'] ?? 0),
                        'max_level' => (int)($data['max_stock_level'] ?? 0)
                    ]);
                    
                    $this->db->insert('stock_movements', [
                        'location_id' => $locationId,
                        'item_id' => $itemId,
                        'movement_type' => 'in',
                        'quantity' => $openingBalance,
                        'reference_type' => 'other',
                        'reference_no' => 'OP-BAL',
                        'unit_cost' => (float)($data['unit_price'] ?? 0),
                        'total_cost' => $openingBalance * (float)($data['unit_price'] ?? 0),
                        'balance_before' => 0,
                        'balance_after' => $openingBalance,
                        'remarks' => 'Opening Balance Entry'
                    ]);
                }
            }

            $_SESSION['success_message'] = 'Item added successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error adding item: ' . $e->getMessage();
        }

        $this->redirect('inventory/items');
    }

    public function storeCategory()
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        $name = $_POST['category_name'] ?? '';
        $code = $_POST['category_code'] ?? '';
        
        if (empty($name)) {
            $_SESSION['error_message'] = 'Category Name is required';
            $this->redirect('inventory/items');
            return;
        }
        
        try {
            if (empty($code)) {
                $code = strtoupper(substr($name, 0, 3)) . rand(100, 999);
            }
            
            $this->db->insert('item_categories', [
                'category_name' => $name,
                'category_code' => $code,
                'status' => 'active'
            ]);
            
            $_SESSION['success_message'] = 'Category added successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error adding category: ' . $e->getMessage();
        }
        
        $this->redirect('inventory/items');
    }

    public function updateOpeningBalance()
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
        
        if ($itemId <= 0 || $qty < 0 || $warehouseId <= 0) {
            $_SESSION['error_message'] = 'Invalid item, warehouse, or quantity';
            $this->redirect('inventory/items');
            return;
        }
        
        try {
            // Get first available location
            $locationId = $this->db->query("SELECT location_id FROM store_locations LIMIT 1")->fetchColumn();
            
            // Check if stock entry exists
            $existing = $this->db->query("SELECT * FROM stock WHERE item_id = $itemId AND warehouse_id = $warehouseId")->fetch();
            
            if ($existing) {
                $oldQty = (int)$existing['quantity_on_hand'];
                $this->db->query("UPDATE stock SET quantity_on_hand = $qty, quantity_available = $qty WHERE stock_id = {$existing['stock_id']}");
                
                $diff = $qty - $oldQty;
                if ($diff !== 0) {
                    $this->db->insert('stock_movements', [
                        'location_id' => $locationId,
                        'item_id' => $itemId,
                        'movement_type' => $diff > 0 ? 'in' : 'out',
                        'quantity' => abs($diff),
                        'reference_type' => 'adjustment',
                        'reference_no' => 'OP-BAL-UPD',
                        'remarks' => 'Opening Balance Adjusted from ' . $oldQty . ' to ' . $qty,
                        'balance_before' => $oldQty,
                        'balance_after' => $qty
                    ]);
                }
            } else {
                $this->db->insert('stock', [
                    'item_id' => $itemId,
                    'location_id' => $locationId,
                    'warehouse_id' => $warehouseId,
                    'quantity_on_hand' => $qty,
                    'quantity_available' => $qty
                ]);
                
                $this->db->insert('stock_movements', [
                    'location_id' => $locationId,
                    'item_id' => $itemId,
                    'movement_type' => 'in',
                    'quantity' => $qty,
                    'reference_type' => 'other',
                    'reference_no' => 'OP-BAL-NEW',
                    'remarks' => 'Opening Balance Created',
                    'balance_before' => 0,
                    'balance_after' => $qty
                ]);
            }
            
            // Update items table current stock sum
            $totalStock = $this->db->query("SELECT SUM(quantity_on_hand) FROM stock WHERE item_id = $itemId")->fetchColumn() ?? 0;
            $this->db->query("UPDATE items SET current_stock = $totalStock WHERE item_id = $itemId");
            
            $_SESSION['success_message'] = 'Opening balance updated successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error updating opening balance: ' . $e->getMessage();
        }
        
        $this->redirect('inventory/items');
    }
}
