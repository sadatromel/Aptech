<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\City;
use App\Models\MasterLibrary\Country;
use Core\Container;

class CityController extends BaseController
{
    private City $cityModel;
    private Country $countryModel;

    public function __construct()
    {
        parent::__construct();
        $this->cityModel = Container::get(City::class);
        $this->countryModel = Container::get(Country::class);
    }

    public function index(): void
    {
        // Join with countries to get country names
        $cities = $this->cityModel->getAllWithCountry(); 

        // Helper for Flash Message
        $flash_success = null;
        if (isset($_SESSION['flash_success'])) {
            $flash_success = $_SESSION['flash_success'];
            unset($_SESSION['flash_success']);
        }

        $this->view('master_library/city/index', [
            'cities' => $cities,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : '',
            'flash_success' => $flash_success
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/city/create', [
            'countries' => $this->countryModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->cityModel->create($data);
        $_SESSION['flash_success'] = 'City created successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/cities');
    }

    public function edit(int $id): void
    {
        $city = $this->cityModel->find($id);
        $this->view('master_library/city/edit', [
            'city' => $city,
            'countries' => $this->countryModel->all(),
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->cityModel->update($id, $data);
        $_SESSION['flash_success'] = 'City updated successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/cities');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->cityModel->delete($id);
        $_SESSION['flash_success'] = 'City deleted successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/cities');
    }
}
