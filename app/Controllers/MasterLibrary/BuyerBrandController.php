<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\BuyerBrand;
use App\Models\Buyer;
use Core\Container;

class BuyerBrandController extends BaseController
{
    private BuyerBrand $brandModel;
    private Buyer $buyerModel;

    public function __construct()
    {
        parent::__construct();
        $this->brandModel = Container::get(BuyerBrand::class);
        $this->buyerModel = Container::get(Buyer::class);
    }

    public function index(): void
    {
        $this->view('master_library/buyer_brand/index', [
            'brands' => $this->brandModel->getWithBuyer()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/buyer_brand/create', [
            'buyers' => $this->buyerModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->brandModel->create($data);
        $this->redirect('master_library/buyer_brands');
    }

    public function edit(int $id): void
    {
        $brand = $this->brandModel->find($id);
        $this->view('master_library/buyer_brand/edit', [
            'brand' => $brand,
            'buyers' => $this->buyerModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->brandModel->update($id, $data);
        $this->redirect('master_library/buyer_brands');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->brandModel->delete($id);
        $this->redirect('master_library/buyer_brands');
    }
}
