<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\Store;
use Core\Container;

class StoreController extends BaseController
{
    private Store $storeModel;

    public function __construct()
    {
        parent::__construct();
        $this->storeModel = Container::get(Store::class);
    }

    public function index(): void
    {
        $this->view('master_library/store/index', [
            'stores' => $this->storeModel->getAllWithFactory()
        ]);
    }

    public function create(): void
    {
        $factoryModel = Container::get(\App\Models\MasterLibrary\Factory::class);
        $this->view('master_library/store/create', [
            'factories' => $factoryModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->storeModel->create($data);
        $this->redirect('master_library/stores');
    }

    public function edit(int $id): void
    {
        $store = $this->storeModel->find($id);
        $factoryModel = Container::get(\App\Models\MasterLibrary\Factory::class);
        $this->view('master_library/store/edit', [
            'store' => $store,
            'factories' => $factoryModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->storeModel->update($id, $data);
        $this->redirect('master_library/stores');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->storeModel->delete($id);
        $this->redirect('master_library/stores');
    }
}
