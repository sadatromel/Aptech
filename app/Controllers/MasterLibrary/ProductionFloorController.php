<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\ProductionFloor;
use App\Models\MasterLibrary\Factory;
use App\Models\MasterLibrary\Store;
use App\Models\MasterLibrary\StoreLocation;
use Core\Container;

class ProductionFloorController extends BaseController
{
    private ProductionFloor $floorModel;
    private Factory $factoryModel;
    private Store $storeModel;
    private StoreLocation $locationModel;

    public function __construct()
    {
        parent::__construct();
        $this->floorModel = Container::get(ProductionFloor::class);
        $this->factoryModel = Container::get(Factory::class);
        $this->storeModel = Container::get(Store::class);
        $this->locationModel = Container::get(StoreLocation::class);
    }

    public function index(): void
    {
        $this->view('master_library/production_floor/index', [
            'floors' => $this->floorModel->getAllWithDetails()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/production_floor/create', [
            'factories' => $this->factoryModel->all(),
            'stores' => $this->storeModel->all(),
            'locations' => $this->locationModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        
        // Map nullable rack_id
        if (empty($data['rack_id'])) {
            $data['rack_id'] = null;
        }

        $this->floorModel->create($data);
        $this->redirect('master_library/production_floors');
    }

    public function edit(int $id): void
    {
        $floor = $this->floorModel->find($id);
        
        // Determine selected store and factory for pre-selection
        $currentLocation = null;
        $currentStore = null;
        if (!empty($floor['rack_id'])) {
            $currentLocation = $this->locationModel->find((int)$floor['rack_id']);
            if ($currentLocation) {
                $currentStore = $this->storeModel->find((int)$currentLocation['warehouse_id']);
            }
        }

        $this->view('master_library/production_floor/edit', [
            'floor' => $floor,
            'current_location' => $currentLocation,
            'current_store' => $currentStore,
            'factories' => $this->factoryModel->all(),
            'stores' => $this->storeModel->all(),
            'locations' => $this->locationModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();

        // Map nullable rack_id
        if (empty($data['rack_id'])) {
            $data['rack_id'] = null;
        }

        $this->floorModel->update($id, $data);
        $this->redirect('master_library/production_floors');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->floorModel->delete($id);
        $this->redirect('master_library/production_floors');
    }

    // AJAX Endpoint for fetching floors filtered by store location ID (rack_id)
    public function getFloorsByLocation(): void
    {
        $rackId = (int)$this->request->get('rack_id');
        $floors = [];
        if ($rackId > 0) {
            $floors = $this->floorModel->where('rack_id', '=', $rackId)->where('status', '=', 'active')->get();
        }
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        echo json_encode($floors);
        exit;
    }
}
