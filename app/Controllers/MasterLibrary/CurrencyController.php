<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\Currency;
use Core\Container;

class CurrencyController extends BaseController
{
    private Currency $currencyModel;

    public function __construct()
    {
        parent::__construct();
        $this->currencyModel = Container::get(Currency::class);
    }

    public function index(): void
    {
        $this->view('master_library/currency/index', [
            'currencies' => $this->currencyModel->all()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/currency/create');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->currencyModel->create($data);
        $this->redirect('master_library/currencies');
    }

    public function edit(int $id): void
    {
        $currency = $this->currencyModel->find($id);
        $this->view('master_library/currency/edit', ['currency' => $currency]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->currencyModel->update($id, $data);
        $this->redirect('master_library/currencies');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->currencyModel->delete($id);
        $this->redirect('master_library/currencies');
    }
}
