<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\District;
use App\Models\MasterLibrary\Country;
use Core\Container;

class DistrictController extends BaseController
{
    private District $districtModel;
    private Country $countryModel;

    public function __construct()
    {
        parent::__construct();
        $this->districtModel = Container::get(District::class);
        $this->countryModel = Container::get(Country::class);
    }

    public function index(): void
    {
        $districts = $this->districtModel->getAllWithCountry(); 

        $flash_success = null;
        if (isset($_SESSION['flash_success'])) {
            $flash_success = $_SESSION['flash_success'];
            unset($_SESSION['flash_success']);
        }

        $this->view('master_library/district/index', [
            'districts' => $districts,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : '',
            'flash_success' => $flash_success
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/district/create', [
            'countries' => $this->countryModel->all(),
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->districtModel->create($data);
        $_SESSION['flash_success'] = 'District created successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/districts');
    }

    public function edit(int $id): void
    {
        $district = $this->districtModel->find($id);
        $this->view('master_library/district/edit', [
            'district' => $district,
            'countries' => $this->countryModel->all(),
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->districtModel->update($id, $data);
        $_SESSION['flash_success'] = 'District updated successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/districts');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->districtModel->delete($id);
        $_SESSION['flash_success'] = 'District deleted successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/districts');
    }
}
