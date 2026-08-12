<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\ProductCategory;
use App\Models\MasterLibrary\ProductNature;
use Core\Container;

class ProductCategoryController extends BaseController
{
    private ProductCategory $model;
    private ProductNature $natureModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = Container::get(ProductCategory::class);
        $this->natureModel = Container::get(ProductNature::class);
    }

    public function index(): void
    {
        $this->requirePermission('view_product_categories');
        $categories = $this->model->getWithNatures();
        $this->view('master_library/product_category/index', ['categories' => $categories]);
    }

    public function create(): void
    {
        $this->requirePermission('create_product_categories');
        $natures = $this->natureModel->all();
        $this->view('master_library/product_category/create', ['natures' => $natures]);
    }

    public function store(): void
    {
        $this->requirePermission('create_product_categories');
        $this->verifyCsrf();
        $data = $this->request->all();
        
        $categoryId = $this->model->create($data);
        
        if (isset($data['nature_ids']) && is_array($data['nature_ids'])) {
            $this->model->updateNatures($categoryId, $data['nature_ids']);
        }
        
        $this->redirect('master_library/product_categories');
    }

    public function edit(int $id): void
    {
        $this->requirePermission('edit_product_categories');
        $category = $this->model->find((int)$id);
        if (!$category) {
            $this->redirect('master_library/product_categories');
            return;
        }
        
        $natures = $this->natureModel->all();
        $selectedNatureIds = $this->model->getNatureIds((int)$id);
        
        $this->view('master_library/product_category/edit', [
            'category' => $category,
            'natures' => $natures,
            'selectedNatureIds' => $selectedNatureIds
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('edit_product_categories');
        $this->verifyCsrf();
        $data = $this->request->all();
        
        $this->model->update((int)$id, $data);
        $this->model->updateNatures((int)$id, $data['nature_ids'] ?? []);
        
        $this->redirect('master_library/product_categories');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('delete_product_categories');
        $this->verifyCsrf();
        $this->model->delete((int)$id);
        
        $this->redirect('master_library/product_categories');
    }
}
