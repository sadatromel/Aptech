<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\ProductionLine;
use App\Models\MasterLibrary\ProductionFloor;
use Core\Container;

class ProductionLineController extends BaseController
{
    private ProductionLine $lineModel;
    private ProductionFloor $floorModel;

    public function __construct()
    {
        parent::__construct();
        $this->lineModel = Container::get(ProductionLine::class);
        $this->floorModel = Container::get(ProductionFloor::class);
    }

    public function index(): void
    {
        $this->view('master_library/production_line/index', [
            'lines' => $this->lineModel->getAllWithDetails()
        ]);
    }

    public function create(): void
    {
        $this->view('master_library/production_line/create', [
            'floors' => $this->floorModel->all()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        
        $payload = [
            'line_code' => trim($data['line_number'] ?? ''),
            'line_name' => trim($data['line_name'] ?? ''),
            'floor_id' => !empty($data['floor_id']) ? (int)$data['floor_id'] : null,
            'capacity_per_day' => !empty($data['daily_capacity']) ? (int)$data['daily_capacity'] : null,
            'status' => $data['status'] ?? 'active'
        ];

        $this->lineModel->create($payload);
        $this->redirect('master_library/production_lines');
    }

    public function edit(int $id): void
    {
        $line = $this->lineModel->find($id);
        $this->view('master_library/production_line/edit', [
            'line' => $line,
            'floors' => $this->floorModel->all()
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $data = $this->request->all();
        
        $payload = [
            'line_code' => trim($data['line_number'] ?? ''),
            'line_name' => trim($data['line_name'] ?? ''),
            'floor_id' => !empty($data['floor_id']) ? (int)$data['floor_id'] : null,
            'capacity_per_day' => !empty($data['daily_capacity']) ? (int)$data['daily_capacity'] : null,
            'status' => $data['status'] ?? 'active'
        ];

        $this->lineModel->update($id, $payload);
        $this->redirect('master_library/production_lines');
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->lineModel->delete($id);
        $this->redirect('master_library/production_lines');
    }
}
