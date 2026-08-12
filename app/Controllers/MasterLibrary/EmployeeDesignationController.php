<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\Position;
use Core\Container;

class EmployeeDesignationController extends BaseController
{
    private Position $positionModel;

    public function __construct()
    {
        parent::__construct();
        $this->positionModel = Container::get(Position::class);
    }

    /**
     * Display the index page with list of designations and stats
     */
    public function index(): void
    {
        $this->requirePermission('view_employee_designations');

        $designations = $this->positionModel->getAllWithEmployeeCount();
        $stats        = $this->positionModel->getStats();

        $this->view('master_library/employee_designation/index', [
            'designations' => $designations,
            'stats'        => $stats,
        ]);
    }

    /**
     * Show the create form
     */
    public function create(): void
    {
        $this->requirePermission('create_employee_designations');
        $this->view('master_library/employee_designation/create');
    }

    /**
     * Store a newly created designation
     */
    public function store(): void
    {
        $this->verifyCsrf();
        $this->requirePermission('create_employee_designations');

        $data = $this->request->all();
        $name = trim($data['position_name'] ?? '');

        if (empty($name)) {
            $this->redirect('master_library/employee_designations/create');
            return;
        }

        // Check duplicate designation name
        $existing = $this->positionModel->findByName($name);
        if ($existing) {
            $this->redirect('master_library/employee_designations/create');
            return;
        }

        $params = [
            'position_name' => $name,
            'status'        => ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
        ];

        $this->positionModel->create($params);
        $this->redirect('master_library/employee_designations');
    }

    /**
     * Show the edit form pre-filled with existing designation data
     */
    public function edit(int $id): void
    {
        $this->requirePermission('edit_employee_designations');

        $designation = $this->positionModel->find($id);
        if (!$designation) {
            $this->redirect('master_library/employee_designations');
            return;
        }

        $this->view('master_library/employee_designation/edit', [
            'designation' => $designation,
        ]);
    }

    /**
     * Update an existing designation
     */
    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('edit_employee_designations');

        $designation = $this->positionModel->find($id);
        if (!$designation) {
            $this->redirect('master_library/employee_designations');
            return;
        }

        $data = $this->request->all();
        $name = trim($data['position_name'] ?? '');

        if (empty($name)) {
            $this->redirect("master_library/employee_designations/{$id}/edit");
            return;
        }

        // Check duplicate name excluding current record
        $existing = $this->positionModel->findByName($name);
        if ($existing && (int)$existing['position_id'] !== $id) {
            $this->redirect("master_library/employee_designations/{$id}/edit");
            return;
        }

        $params = [
            'position_name' => $name,
            'status'        => ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
        ];

        $this->positionModel->update($id, $params);
        $this->redirect('master_library/employee_designations');
    }

    /**
     * Delete a designation (only if no employees are linked)
     */
    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('delete_employee_designations');

        $designation = $this->positionModel->find($id);
        if (!$designation) {
            $this->redirect('master_library/employee_designations');
            return;
        }

        // Check if employees are assigned
        $employeeCount = $this->positionModel->getEmployeeCount($id);
        if ($employeeCount > 0) {
            $this->redirect('master_library/employee_designations');
            return;
        }

        $this->positionModel->delete($id);
        $this->redirect('master_library/employee_designations');
    }
}
