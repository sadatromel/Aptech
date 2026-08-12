<?php
declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\StoreLocation;
use App\Models\MasterLibrary\Store;
use App\Models\MasterLibrary\Factory;
use Core\Container;

class StoreLocationController extends BaseController
{
    private StoreLocation $locationModel;
    private Store $storeModel;
    private Factory $factoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->locationModel = Container::get(StoreLocation::class);
        $this->storeModel = Container::get(Store::class);
        $this->factoryModel = Container::get(Factory::class);
    }

    public function index(): void
    {
        $this->view('master_library/store_location/index', [
            'locations' => $this->locationModel->getAllWithStoreAndFactory()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/store_location/create', [
            'factories' => $this->factoryModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $locations = $data['locations'] ?? [];

        if ($warehouseId > 0 && !empty($locations)) {
            foreach ($locations as $loc) {
                if (!empty($loc['rack_code']) && !empty($loc['rack_name'])) {
                    $this->locationModel->create([
                        'warehouse_id' => $warehouseId,
                        'rack_code' => $loc['rack_code'],
                        'rack_name' => $loc['rack_name'],
                        'zone' => !empty($loc['zone']) ? $loc['zone'] : null,
                        'aisle' => !empty($loc['aisle']) ? $loc['aisle'] : null,
                        'level' => !empty($loc['level']) ? $loc['level'] : null,
                        'bin' => !empty($loc['bin']) ? $loc['bin'] : null,
                        'capacity_weight' => !empty($loc['capacity_weight']) ? (float)$loc['capacity_weight'] : null,
                        'capacity_volume' => !empty($loc['capacity_volume']) ? (float)$loc['capacity_volume'] : null,
                        'status' => $loc['status'] ?? 'active'
                    ]);
                }
            }
        }
        $this->redirect('master_library/store_locations');
    }

    public function edit(int $id): void
    {
        $location = $this->locationModel->find($id);
        
        // Find store to get active factory
        $store = $this->storeModel->find((int)$location['warehouse_id']);
        
        $this->view('master_library/store_location/edit', [
            'location' => $location,
            'current_store' => $store,
            'factories' => $this->factoryModel->all(),
            'stores' => $this->storeModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->locationModel->update($id, [
            'warehouse_id' => (int)$data['warehouse_id'],
            'rack_code' => $data['rack_code'],
            'rack_name' => $data['rack_name'],
            'zone' => !empty($data['zone']) ? $data['zone'] : null,
            'aisle' => !empty($data['aisle']) ? $data['aisle'] : null,
            'level' => !empty($data['level']) ? $data['level'] : null,
            'bin' => !empty($data['bin']) ? $data['bin'] : null,
            'capacity_weight' => !empty($data['capacity_weight']) ? (float)$data['capacity_weight'] : null,
            'capacity_volume' => !empty($data['capacity_volume']) ? (float)$data['capacity_volume'] : null,
            'status' => $data['status'] ?? 'active'
        ]);
        $this->redirect('master_library/store_locations');
    }

    public function destroy(?int $id = null): void
    {
        $this->verifyCsrf();
        if ($id === null) {
            $data = $this->request->all();
            $id = (int)($data['id'] ?? 0);
        }
        if ($id > 0) {
            $this->locationModel->delete($id);
        }
        $this->redirect('master_library/store_locations');
    }

    // AJAX Endpoint for fetching stores filtered by factory
    public function getStoresByFactory(): void
    {
        $factoryId = (int)$this->request->get('factory_id');
        $stores = [];
        if ($factoryId > 0) {
            $stores = $this->storeModel->where('factory_id', '=', $factoryId)->where('status', '=', 'active')->get();
        }
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        echo json_encode($stores);
        exit;
    }

    // AJAX Endpoint for fetching locations filtered by store
    public function getLocationsByStore(): void
    {
        $storeId = (int)$this->request->get('store_id');
        $locations = [];
        if ($storeId > 0) {
            $locations = $this->locationModel->where('warehouse_id', '=', $storeId)->where('status', '=', 'active')->get();
        }
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        echo json_encode($locations);
        exit;
    }
}
