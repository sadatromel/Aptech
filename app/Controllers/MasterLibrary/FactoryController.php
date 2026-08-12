<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\Factory;
use Core\Container;

class FactoryController extends BaseController
{
    private Factory $factoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->factoryModel = Container::get(Factory::class);
    }

    public function index(): void
    {
        $this->view('master_library/factory/index', [
            'factories' => $this->factoryModel->all()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/factory/create');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->factoryModel->create($data);
        $this->redirect('master_library/factories');
    }

    public function edit(int $id): void
    {
        $factory = $this->factoryModel->find($id);
        $this->view('master_library/factory/edit', ['factory' => $factory]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->factoryModel->update($id, $data);
        $this->redirect('master_library/factories');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->factoryModel->delete($id);
        $this->redirect('master_library/factories');
    }
}
