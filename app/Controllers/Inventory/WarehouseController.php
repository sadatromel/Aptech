<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;

class WarehouseController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        // 1. Fetch Warehouses
        $warehouses = $this->db->query("
            SELECT w.*, 'Consolidated' as factory_name 
            FROM warehouses w 
            ORDER BY w.warehouse_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Fetch Store Locations (Sub-warehouses/physical stores)
        $storeLocations = $this->db->query("
            SELECT sl.*, f.factory_name 
            FROM store_locations sl 
            LEFT JOIN factories f ON sl.factory_id = f.factory_id
            ORDER BY sl.location_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Fetch Rooms
        $rooms = $this->db->query("
            SELECT sr.*, pf.floor_name, pf.floor_code 
            FROM store_rooms sr 
            LEFT JOIN production_floors pf ON sr.floor_id = pf.floor_id
            ORDER BY sr.room_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 4. Fetch Racks
        $racks = $this->db->query("
            SELECT r.*, rm.room_name, rm.room_code 
            FROM store_racks r 
            LEFT JOIN store_rooms rm ON r.room_id = rm.room_id
            ORDER BY r.rack_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch Factories (Companies) for select options
        $factories = $this->db->query("SELECT factory_id, factory_name FROM factories WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Fetch Floors for room mapping
        $floors = $this->db->query("SELECT floor_id, floor_name, floor_code FROM production_floors WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch store locations / sub-warehouses
        $subWarehouses = $this->db->query("SELECT location_id, location_name FROM store_locations WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch users for warehouse users assignment
        $users = $this->db->query("
            SELECT user_id as id, CONCAT(first_name, ' ', last_name) as name, email, role_id as role 
            FROM users 
            ORDER BY name ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Simulated Warehouse Capacity data (in percentage of total volume capacity)
        $warehouseCapacities = [];
        foreach ($warehouses as $wh) {
            $usedVolume = $this->db->query("
                SELECT SUM(s.quantity_on_hand) 
                FROM stock s 
                WHERE s.warehouse_id = {$wh['warehouse_id']}
            ")->fetchColumn() ?? 0;
            
            $maxCap = 100000; // Simulated capacity ceiling of 100k items
            $percentage = min(100, round(($usedVolume / $maxCap) * 100, 2));
            
            $warehouseCapacities[] = [
                'warehouse_id' => $wh['warehouse_id'],
                'warehouse_name' => $wh['warehouse_name'],
                'warehouse_code' => $wh['warehouse_code'],
                'used_volume' => $usedVolume,
                'max_capacity' => $maxCap,
                'percentage' => $percentage
            ];
        }

        $this->view('inventory/warehouse', [
            'pageTitle' => 'Warehouse Master & Mapping',
            'activeModule' => 'store_inventory',
            'activeSubModule' => 'warehouse',
            'warehouses' => $warehouses,
            'storeLocations' => $storeLocations,
            'rooms' => $rooms,
            'racks' => $racks,
            'factories' => $factories,
            'floors' => $floors,
            'subWarehouses' => $subWarehouses,
            'users' => $users,
            'warehouseCapacities' => $warehouseCapacities
        ]);
    }

    public function storeWarehouse()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $name = $_POST['warehouse_name'] ?? '';
        $code = $_POST['warehouse_code'] ?? '';
        $location = $_POST['location'] ?? '';

        if (empty($name) || empty($code)) {
            $_SESSION['error_message'] = 'Name and code are required';
            $this->redirect('inventory/warehouses');
            return;
        }

        try {
            $this->db->insert('warehouses', [
                'warehouse_name' => $name,
                'warehouse_code' => $code,
                'location' => $location,
                'status' => 'active'
            ]);
            $_SESSION['success_message'] = 'Warehouse created successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error creating warehouse: ' . $e->getMessage();
        }

        $this->redirect('inventory/warehouses');
    }

    public function storeRoom()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $name = $_POST['room_name'] ?? '';
        $code = $_POST['room_code'] ?? '';
        $floorId = (int)($_POST['floor_id'] ?? 0);
        $desc = $_POST['description'] ?? '';

        if (empty($name) || empty($code) || $floorId <= 0) {
            $_SESSION['error_message'] = 'Room name, code, and floor mapping are required';
            $this->redirect('inventory/warehouses');
            return;
        }

        try {
            $this->db->insert('store_rooms', [
                'room_name' => $name,
                'room_code' => $code,
                'floor_id' => $floorId,
                'description' => $desc,
                'status' => 'active'
            ]);
            $_SESSION['success_message'] = 'Store Room created successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error creating store room: ' . $e->getMessage();
        }

        $this->redirect('inventory/warehouses');
    }

    public function storeRack()
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $name = $_POST['rack_name'] ?? '';
        $code = $_POST['rack_code'] ?? '';
        $roomId = (int)($_POST['room_id'] ?? 0);
        $desc = $_POST['description'] ?? '';

        if (empty($name) || empty($code) || $roomId <= 0) {
            $_SESSION['error_message'] = 'Rack name, code, and room mapping are required';
            $this->redirect('inventory/warehouses');
            return;
        }

        try {
            $this->db->insert('store_racks', [
                'rack_name' => $name,
                'rack_code' => $code,
                'room_id' => $roomId,
                'description' => $desc,
                'status' => 'active'
            ]);
            $_SESSION['success_message'] = 'Store Rack created successfully';
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error creating store rack: ' . $e->getMessage();
        }

        $this->redirect('inventory/warehouses');
    }
}
