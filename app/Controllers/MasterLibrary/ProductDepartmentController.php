<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\ProductDepartment;
use Core\Container;

class ProductDepartmentController extends BaseController
{
    private ProductDepartment $departmentModel;

    public function __construct()
    {
        parent::__construct();
        $this->departmentModel = Container::get(ProductDepartment::class);
    }

    public function index(): void
    {
        $this->view('master_library/product_department/index', [
            'departments' => $this->departmentModel->all()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/product_department/create');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->departmentModel->create($data);
        $this->redirect('master_library/product_departments');
    }

    public function edit(int $id): void
    {
        $department = $this->departmentModel->find($id);
        $this->view('master_library/product_department/edit', [
            'department' => $department
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->departmentModel->update($id, $data);
        $this->redirect('master_library/product_departments');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->departmentModel->delete($id);
        $this->redirect('master_library/product_departments');
    }
}
