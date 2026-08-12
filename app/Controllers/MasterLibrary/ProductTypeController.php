<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\ProductType;
use App\Models\MasterLibrary\ProductCategory;
use Core\Container;

class ProductTypeController extends BaseController
{
    private ProductType $model;
    private ProductCategory $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = Container::get(ProductType::class);
        $this->categoryModel = Container::get(ProductCategory::class);
    }

    public function index(): void
    {
        $this->requirePermission('view_product_types');
        $types = $this->model->getWithCategories();
        $this->view('master_library/product_type/index', ['types' => $types]);
    }

    public function create(): void
    {
        $this->requirePermission('create_product_types');
        $categories = $this->categoryModel->all();
        $this->view('master_library/product_type/create', ['categories' => $categories]);
    }

    public function store(): void
    {
        $this->requirePermission('create_product_types');
        $this->verifyCsrf();
        $data = $this->request->all();

        // Create type
        $typeId = $this->model->create($data);

        // Update junction relations
        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            $this->model->updateCategories($typeId, $data['category_ids']);
        }

        $this->redirect('master_library/product_types');
    }

    public function edit(int $id): void
    {
        $this->requirePermission('edit_product_types');
        $type = $this->model->find((int)$id);
        if (!$type) {
            $this->redirect('master_library/product_types');
            return;
        }

        $categories = $this->categoryModel->all();
        $selectedCategoryIds = $this->model->getCategoryIds((int)$id);

        $this->view('master_library/product_type/edit', [
            'type' => $type,
            'categories' => $categories,
            'selectedCategoryIds' => $selectedCategoryIds
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('edit_product_types');
        $this->verifyCsrf();
        $data = $this->request->all();

        $this->model->update((int)$id, $data);
        $this->model->updateCategories((int)$id, $data['category_ids'] ?? []);

        $this->redirect('master_library/product_types');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('delete_product_types');
        $this->verifyCsrf();
        $this->model->delete((int)$id);
        $this->redirect('master_library/product_types');
    }
}
