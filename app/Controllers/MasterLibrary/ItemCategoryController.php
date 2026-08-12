<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\ItemCategory;
use Core\Container;

class ItemCategoryController extends BaseController
{
    private ItemCategory $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->categoryModel = Container::get(ItemCategory::class);
    }

    public function index(): void
    {
        $this->view('master_library/item_category/index', [
            'categories' => $this->categoryModel->all()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/item_category/create', [
           'categories' => $this->categoryModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->categoryModel->create($data);
        $this->redirect('master_library/item_categories');
    }

    public function edit(int $id): void
    {
        $category = $this->categoryModel->find($id);
        $this->view('master_library/item_category/edit', [
            'category' => $category,
            'categories' => $this->categoryModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->categoryModel->update($id, $data);
        $this->redirect('master_library/item_categories');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->categoryModel->delete($id);
        $this->redirect('master_library/item_categories');
    }
}
