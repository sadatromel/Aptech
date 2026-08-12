<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\Department;
use App\Models\MasterLibrary\Factory;
use Core\Container;

class EmployeeDepartmentController extends BaseController
{
    private Department $departmentModel;
    private Factory $factoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->departmentModel = Container::get(Department::class);
        $this->factoryModel    = Container::get(Factory::class);
    }

    /**
     * Display the index page with list of departments and stats
     */
    public function index(): void
    {
        $this->requirePermission('view_employee_departments');

        $departments = $this->departmentModel->getAllWithEmployeeCount();
        $stats       = $this->departmentModel->getStats();
        $factories   = $this->factoryModel->all();

        $this->view('master_library/employee_department/index', [
            'departments' => $departments,
            'stats'       => $stats,
            'factories'   => $factories,
        ]);
    }

    /**
     * Show the create form
     */
    public function create(): void
    {
        $this->requirePermission('create_employee_departments');
        $factories = $this->factoryModel->all();

        $this->view('master_library/employee_department/create', [
            'factories' => $factories,
        ]);
    }

    /**
     * Store a newly created department
     */
    public function store(): void
    {
        $this->verifyCsrf();
        $this->requirePermission('create_employee_departments');

        $data = $this->request->all();
        $code = trim($data['department_code'] ?? '');
        $name = trim($data['department_name'] ?? '');

        if (empty($code) || empty($name)) {
            $this->redirect('master_library/employee_departments/create');
            return;
        }

        // Check duplicate department code
        $existing = $this->departmentModel->findByCode($code);
        if ($existing) {
            $this->redirect('master_library/employee_departments/create');
            return;
        }

        $params = [
            'department_code' => $code,
            'department_name' => $name,
            'description'     => trim($data['description'] ?? ''),
            'status'          => ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
        ];

        $newId = $this->departmentModel->create($params);

        // Save linked factories
        $factoryIds = $data['factory_ids'] ?? [];
        if (!is_array($factoryIds)) {
            $factoryIds = [];
        }
        $this->departmentModel->saveFactories($newId, $factoryIds);

        $this->redirect('master_library/employee_departments');
    }

    /**
     * Show the edit form pre-filled with existing department data
     */
    public function edit(int $id): void
    {
        $this->requirePermission('edit_employee_departments');

        $department = $this->departmentModel->find($id);
        if (!$department) {
            $this->redirect('master_library/employee_departments');
            return;
        }

        $factories      = $this->factoryModel->all();
        $linkedFactories = $this->departmentModel->getFactories($id);
        $selectedFactoryIds = array_column($linkedFactories, 'factory_id');

        $this->view('master_library/employee_department/edit', [
            'department'         => $department,
            'factories'          => $factories,
            'selectedFactoryIds' => $selectedFactoryIds,
        ]);
    }

    /**
     * Update an existing department
     */
    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('edit_employee_departments');

        $department = $this->departmentModel->find($id);
        if (!$department) {
            $this->redirect('master_library/employee_departments');
            return;
        }

        $data = $this->request->all();
        $code = trim($data['department_code'] ?? '');
        $name = trim($data['department_name'] ?? '');

        if (empty($code) || empty($name)) {
            $this->redirect("master_library/employee_departments/{$id}/edit");
            return;
        }

        // Check duplicate code excluding current record
        $existing = $this->departmentModel->findByCode($code);
        if ($existing && (int)$existing['department_id'] !== $id) {
            $this->redirect("master_library/employee_departments/{$id}/edit");
            return;
        }

        $params = [
            'department_code' => $code,
            'department_name' => $name,
            'description'     => trim($data['description'] ?? ''),
            'status'          => ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
        ];

        $this->departmentModel->update($id, $params);

        // Sync linked factories
        $factoryIds = $data['factory_ids'] ?? [];
        if (!is_array($factoryIds)) {
            $factoryIds = [];
        }
        $this->departmentModel->saveFactories($id, $factoryIds);

        $this->redirect('master_library/employee_departments');
    }

    /**
     * Delete a department (only if no employees or sections are linked)
     */
    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('delete_employee_departments');

        $department = $this->departmentModel->find($id);
        if (!$department) {
            $this->redirect('master_library/employee_departments');
            return;
        }

        // Check if sections are assigned
        $sectionModel = Container::get(\App\Models\MasterLibrary\Section::class);
        $sectionCount = $sectionModel->getSectionsCountForDepartment($id);
        if ($sectionCount > 0) {
            $this->redirect('master_library/employee_departments');
            return;
        }

        // Check if employees are assigned
        $employeeCount = $this->departmentModel->getEmployeeCount($id);
        if ($employeeCount > 0) {
            $this->redirect('master_library/employee_departments');
            return;
        }

        $this->departmentModel->delete($id);
        $this->redirect('master_library/employee_departments');
    }
}
