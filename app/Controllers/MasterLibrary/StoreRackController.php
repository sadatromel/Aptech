<?php
declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\StoreRack;
use App\Models\MasterLibrary\StoreRoom;
use App\Models\MasterLibrary\ProductionFloor;
use App\Models\MasterLibrary\StoreLocation;
use App\Models\MasterLibrary\Store;
use App\Models\MasterLibrary\Factory;
use Core\Container;

class StoreRackController extends BaseController
{
    private StoreRack $rackModel;
    private StoreRoom $roomModel;
    private ProductionFloor $floorModel;
    private StoreLocation $locationModel;
    private Store $storeModel;
    private Factory $factoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->rackModel = Container::get(StoreRack::class);
        $this->roomModel = Container::get(StoreRoom::class);
        $this->floorModel = Container::get(ProductionFloor::class);
        $this->locationModel = Container::get(StoreLocation::class);
        $this->storeModel = Container::get(Store::class);
        $this->factoryModel = Container::get(Factory::class);
    }

    public function index(): void
    {
        $this->view('master_library/store_rack/index', [
            'racks' => $this->rackModel->getAllWithDetails()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/store_rack/create', [
            'factories' => $this->factoryModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        
        $roomId = (int)($data['room_id'] ?? 0);
        $code = trim($data['rack_code'] ?? '');
        $name = trim($data['rack_name'] ?? '');

        if ($roomId > 0 && !empty($code) && !empty($name)) {
            try {
                $this->rackModel->create([
                    'room_id' => $roomId,
                    'rack_code' => $code,
                    'rack_name' => $name,
                    'description' => trim($data['description'] ?? ''),
                    'status' => $data['status'] ?? 'active'
                ]);
                $_SESSION['success_message'] = 'Rack added successfully!';
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') {
                    $_SESSION['error_message'] = 'A rack with this code already exists in the selected room.';
                } else {
                    $_SESSION['error_message'] = 'Failed to create rack: ' . $e->getMessage();
                }
                $this->redirect('master_library/store_racks/create');
                return;
            }
        } else {
            $_SESSION['error_message'] = 'Please fill in all required fields.';
            $this->redirect('master_library/store_racks/create');
            return;
        }
        $this->redirect('master_library/store_racks');
    }

    public function edit(int $id): void
    {
        $rack = $this->rackModel->find($id);
        
        // Trace the hierarchy to get active pre-selections
        $currentRoom = $this->roomModel->find((int)$rack['room_id']);
        $currentFloor = null;
        $currentLocation = null;
        $currentStore = null;
        if ($currentRoom) {
            $currentFloor = $this->floorModel->find((int)$currentRoom['floor_id']);
            if ($currentFloor && !empty($currentFloor['rack_id'])) {
                $currentLocation = $this->locationModel->find((int)$currentFloor['rack_id']);
                if ($currentLocation) {
                    $currentStore = $this->storeModel->find((int)$currentLocation['warehouse_id']);
                }
            }
        }

        $this->view('master_library/store_rack/edit', [
            'rack' => $rack,
            'current_room' => $currentRoom,
            'current_floor' => $currentFloor,
            'current_location' => $currentLocation,
            'current_store' => $currentStore,
            'factories' => $this->factoryModel->all(),
            'stores' => $this->storeModel->all(),
            'locations' => $this->locationModel->all(),
            'floors' => $this->floorModel->all(),
            'rooms' => $this->roomModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        
        try {
            $this->rackModel->update($id, [
                'room_id' => (int)$data['room_id'],
                'rack_code' => trim($data['rack_code']),
                'rack_name' => trim($data['rack_name']),
                'description' => trim($data['description'] ?? ''),
                'status' => $data['status'] ?? 'active'
            ]);
            $_SESSION['success_message'] = 'Rack updated successfully!';
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['error_message'] = 'A rack with this code already exists in the selected room.';
            } else {
                $_SESSION['error_message'] = 'Failed to update rack: ' . $e->getMessage();
            }
            $this->redirect("master_library/store_racks/edit/{$id}");
            return;
        }
        $this->redirect('master_library/store_racks');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->rackModel->delete($id);
        $this->redirect('master_library/store_racks');
    }
}
