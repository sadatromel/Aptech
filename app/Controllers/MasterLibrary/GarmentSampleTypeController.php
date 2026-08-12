<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\GarmentSampleType;
use Core\Container;

class GarmentSampleTypeController extends BaseController
{
    private GarmentSampleType $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = Container::get(GarmentSampleType::class);
    }

    public function index(): void
    {
        $sampleTypes = $this->model->all();
        $this->view('master_library/garment_sample_type/index', ['sampleTypes' => $sampleTypes]);
    }

    public function create(): void
    {
        $this->view('master_library/garment_sample_type/create');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->model->create($data);
        $this->redirect('master_library/garment_sample_types');
    }

    public function edit($id): void
    {
        $sampleType = $this->model->find((int)$id);
        $this->view('master_library/garment_sample_type/edit', ['sampleType' => $sampleType]);
    }

    public function update($id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        $this->model->update((int)$id, $data);
        $this->redirect('master_library/garment_sample_types');
    }

    public function destroy($id): void
    {
        $this->verifyCsrf();
        $this->model->delete((int)$id);
        $this->redirect('master_library/garment_sample_types');
    }
}
