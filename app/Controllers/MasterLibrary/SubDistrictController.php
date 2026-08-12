<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\SubDistrict;
use App\Models\MasterLibrary\District;
use Core\Container;

class SubDistrictController extends BaseController
{
    private SubDistrict $subDistrictModel;
    private District $districtModel;

    public function __construct()
    {
        parent::__construct();
        $this->subDistrictModel = Container::get(SubDistrict::class);
        $this->districtModel = Container::get(District::class);
    }

    public function index(): void
    {
        $subDistricts = $this->subDistrictModel->getAllWithDistrictAndCountry(); 

        $flash_success = null;
        if (isset($_SESSION['flash_success'])) {
            $flash_success = $_SESSION['flash_success'];
            unset($_SESSION['flash_success']);
        }

        $this->view('master_library/sub_district/index', [
            'sub_districts' => $subDistricts,
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : '',
            'flash_success' => $flash_success
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/sub_district/create', [
            'districts' => $this->districtModel->getAllWithCountry(),
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->subDistrictModel->create($data);
        $_SESSION['flash_success'] = 'Sub-District (Upazila/Thana) created successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/sub_districts');
    }

    public function edit(int $id): void
    {
        $subDistrict = $this->subDistrictModel->find($id);
        $this->view('master_library/sub_district/edit', [
            'sub_district' => $subDistrict,
            'districts' => $this->districtModel->getAllWithCountry(),
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : ''
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->subDistrictModel->update($id, $data);
        $_SESSION['flash_success'] = 'Sub-District (Upazila/Thana) updated successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/sub_districts');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->subDistrictModel->delete($id);
        $_SESSION['flash_success'] = 'Sub-District deleted successfully.';
        $this->redirect((defined('BASE_URL') ? BASE_URL : '') . '/master_library/sub_districts');
    }

    public function getSubDistrictsByDistrict(int $districtId): void
    {
        header('Content-Type: application/json');
        $subDistricts = $this->subDistrictModel->getByDistrictId($districtId);
        echo json_encode($subDistricts);
        exit;
    }
}
