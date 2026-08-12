<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;

use App\Models\MasterLibrary\Country;
use App\Models\MasterLibrary\Currency; // Import Currency Model
use Core\Container;

class CountryController extends BaseController
{
    private Country $countryModel;
    private Currency $currencyModel; // Define property

    public function __construct()
    {
        parent::__construct();
        $this->countryModel = Container::get(Country::class);
        $this->currencyModel = Container::get(Currency::class); // Initialize Currency Model
    }

    public function index(): void
    {
        // $this->requireAuth();
        $this->view('master_library/country/index', [
            'countries' => $this->countryModel->all()
        ]);
    }

    public function create(): void
    {
        // Pass currencies to view
        $this->view('master_library/country/create', [
            'currencies' => $this->currencyModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        // Validation...
        $this->countryModel->create($data);
        $this->redirect('master_library/countries');
    }

    public function edit(int $id): void
    {
        $country = $this->countryModel->find($id);
        // Pass currencies to view
        $this->view('master_library/country/edit', [
            'country' => $country,
            'currencies' => $this->currencyModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->countryModel->update($id, $data);
        $this->redirect('master_library/countries');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->countryModel->delete($id);
        $this->redirect('master_library/countries');
    }
}
