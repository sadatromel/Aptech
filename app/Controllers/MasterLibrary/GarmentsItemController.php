<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\GarmentsItem;
use App\Models\MasterLibrary\ProductType;
use Core\Container;

class GarmentsItemController extends BaseController
{
    private GarmentsItem $model;
    private ProductType $typeModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = Container::get(GarmentsItem::class);
        $this->typeModel = Container::get(ProductType::class);
    }

    public function index(): void
    {
        $this->requirePermission('view_garments_items');
        $items = $this->model->getWithTypes();
        $this->view('master_library/garments_item/index', ['items' => $items]);
    }

    public function create(): void
    {
        $this->requirePermission('create_garments_items');
        $types = $this->typeModel->all();
        $this->view('master_library/garments_item/create', ['types' => $types]);
    }

    public function store(): void
    {
        $this->requirePermission('create_garments_items');
        $this->verifyCsrf();
        $data = $this->request->all();

        // Create item
        $itemId = $this->model->create($data);

        // Update junction relations
        if (isset($data['type_ids']) && is_array($data['type_ids'])) {
            $this->model->updateTypes($itemId, $data['type_ids']);
        }

        $this->redirect('master_library/garments_items');
    }

    public function edit(int $id): void
    {
        $this->requirePermission('edit_garments_items');
        $item = $this->model->find((int)$id);
        if (!$item) {
            $this->redirect('master_library/garments_items');
            return;
        }

        $types = $this->typeModel->all();
        $selectedTypeIds = $this->model->getTypeIds((int)$id);

        $this->view('master_library/garments_item/edit', [
            'item' => $item,
            'types' => $types,
            'selectedTypeIds' => $selectedTypeIds
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('edit_garments_items');
        $this->verifyCsrf();
        $data = $this->request->all();

        $this->model->update((int)$id, $data);
        $this->model->updateTypes((int)$id, $data['type_ids'] ?? []);

        $this->redirect('master_library/garments_items');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('delete_garments_items');
        $this->verifyCsrf();
        $this->model->delete((int)$id);
        $this->redirect('master_library/garments_items');
    }
}
