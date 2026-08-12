<?php
declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\StoreRoom;
use App\Models\MasterLibrary\ProductionFloor;
use App\Models\MasterLibrary\StoreLocation;
use App\Models\MasterLibrary\Store;
use App\Models\MasterLibrary\Factory;
use Core\Container;

class StoreRoomController extends BaseController
{
    private StoreRoom $roomModel;
    private ProductionFloor $floorModel;
    private StoreLocation $locationModel;
    private Store $storeModel;
    private Factory $factoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->roomModel = Container::get(StoreRoom::class);
        $this->floorModel = Container::get(ProductionFloor::class);
        $this->locationModel = Container::get(StoreLocation::class);
        $this->storeModel = Container::get(Store::class);
        $this->factoryModel = Container::get(Factory::class);
    }

    public function index(): void
    {
        $this->view('master_library/store_room/index', [
            'rooms' => $this->roomModel->getAllWithDetails()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/store_room/create', [
            'factories' => $this->factoryModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        
        $floorId = (int)($data['floor_id'] ?? 0);
        $code = trim($data['room_code'] ?? '');
        $name = trim($data['room_name'] ?? '');

        if ($floorId > 0 && !empty($code) && !empty($name)) {
            try {
                $this->roomModel->create([
                    'floor_id' => $floorId,
                    'room_code' => $code,
                    'room_name' => $name,
                    'description' => trim($data['description'] ?? ''),
                    'status' => $data['status'] ?? 'active'
                ]);
                $_SESSION['success_message'] = 'Room added successfully!';
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') {
                    $_SESSION['error_message'] = 'A room with this code already exists on the selected floor.';
                } else {
                    $_SESSION['error_message'] = 'Failed to create room: ' . $e->getMessage();
                }
                $this->redirect('master_library/store_rooms/create');
                return;
            }
        } else {
            $_SESSION['error_message'] = 'Please fill in all required fields.';
            $this->redirect('master_library/store_rooms/create');
            return;
        }
        $this->redirect('master_library/store_rooms');
    }

    public function edit(int $id): void
    {
        $room = $this->roomModel->find($id);
        
        // Trace the hierarchy to get active pre-selections
        $currentFloor = $this->floorModel->find((int)$room['floor_id']);
        $currentLocation = null;
        $currentStore = null;
        if ($currentFloor && !empty($currentFloor['rack_id'])) {
            $currentLocation = $this->locationModel->find((int)$currentFloor['rack_id']);
            if ($currentLocation) {
                $currentStore = $this->storeModel->find((int)$currentLocation['warehouse_id']);
            }
        }

        $this->view('master_library/store_room/edit', [
            'room' => $room,
            'current_floor' => $currentFloor,
            'current_location' => $currentLocation,
            'current_store' => $currentStore,
            'factories' => $this->factoryModel->all(),
            'stores' => $this->storeModel->all(),
            'locations' => $this->locationModel->all(),
            'floors' => $this->floorModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        
        try {
            $this->roomModel->update($id, [
                'floor_id' => (int)$data['floor_id'],
                'room_code' => trim($data['room_code']),
                'room_name' => trim($data['room_name']),
                'description' => trim($data['description'] ?? ''),
                'status' => $data['status'] ?? 'active'
            ]);
            $_SESSION['success_message'] = 'Room updated successfully!';
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['error_message'] = 'A room with this code already exists on the selected floor.';
            } else {
                $_SESSION['error_message'] = 'Failed to update room: ' . $e->getMessage();
            }
            $this->redirect("master_library/store_rooms/edit/{$id}");
            return;
        }
        $this->redirect('master_library/store_rooms');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->roomModel->delete($id);
        $this->redirect('master_library/store_rooms');
    }

    // AJAX Endpoint for fetching rooms filtered by floor ID
    public function getRoomsByFloor(): void
    {
        $floorId = (int)$this->request->get('floor_id');
        $rooms = [];
        if ($floorId > 0) {
            $rooms = $this->roomModel->where('floor_id', '=', $floorId)->where('status', '=', 'active')->get();
        }
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        echo json_encode($rooms);
        exit;
    }
}
