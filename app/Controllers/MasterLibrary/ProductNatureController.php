<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\ProductNature;
use App\Models\MasterLibrary\ProductCategory;
use Core\Container;

class ProductNatureController extends BaseController
{
    private ProductNature $model;
    private ProductCategory $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = Container::get(ProductNature::class);
        $this->categoryModel = Container::get(ProductCategory::class);
    }

    public function index(): void
    {
        $this->requirePermission('view_product_natures');
        $natures = $this->model->getWithCategories();
        $this->view('master_library/product_nature/index', ['natures' => $natures]);
    }

    public function create(): void
    {
        $this->requirePermission('create_product_natures');
        $categories = $this->categoryModel->all();
        $this->view('master_library/product_nature/create', ['categories' => $categories]);
    }

    public function store(): void
    {
        $this->requirePermission('create_product_natures');
        $this->verifyCsrf();
        $data = $this->request->all();

        // Create nature
        $natureId = $this->model->create($data);

        // Update junction relations
        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            $this->model->updateCategories($natureId, $data['category_ids']);
        }

        $this->redirect('master_library/product_natures');
    }

    public function edit(int $id): void
    {
        $this->requirePermission('edit_product_natures');
        $nature = $this->model->find((int)$id);
        if (!$nature) {
            $this->redirect('master_library/product_natures');
            return;
        }

        $categories = $this->categoryModel->all();
        $selectedCategoryIds = $this->model->getCategoryIds((int)$id);

        $this->view('master_library/product_nature/edit', [
            'nature' => $nature,
            'categories' => $categories,
            'selectedCategoryIds' => $selectedCategoryIds
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('edit_product_natures');
        $this->verifyCsrf();
        $data = $this->request->all();

        $this->model->update((int)$id, $data);
        $this->model->updateCategories((int)$id, $data['category_ids'] ?? []);

        $this->redirect('master_library/product_natures');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('delete_product_natures');
        $this->verifyCsrf();
        $this->model->delete((int)$id);
        $this->redirect('master_library/product_natures');
    }
}
