<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\Department;
use App\Models\MasterLibrary\Section;
use Core\Container;

class EmployeeSectionController extends BaseController
{
    private Section $sectionModel;
    private Department $departmentModel;

    public function __construct()
    {
        parent::__construct();
        $this->sectionModel    = Container::get(Section::class);
        $this->departmentModel = Container::get(Department::class);
    }

    /**
     * Display the index page with list of sections and stats
     */
    public function index(): void
    {
        $this->requirePermission('view_employee_sections');

        $sections    = $this->sectionModel->getAllWithEmployeeCount();
        $stats       = $this->sectionModel->getStats();
        $departments = $this->departmentModel->all();

        $this->view('master_library/employee_section/index', [
            'sections'    => $sections,
            'stats'       => $stats,
            'departments' => $departments,
        ]);
    }

    /**
     * Show the create form
     */
    public function create(): void
    {
        $this->requirePermission('create_employee_sections');
        $departments = $this->departmentModel->all();

        $this->view('master_library/employee_section/create', [
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created section
     */
    public function store(): void
    {
        $this->verifyCsrf();
        $this->requirePermission('create_employee_sections');

        $data         = $this->request->all();
        $name         = trim($data['section_name'] ?? '');
        $departmentId = isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null;

        if (empty($name)) {
            $this->redirect('master_library/employee_sections/create');
            return;
        }

        // Check duplicate section name
        $existing = $this->sectionModel->findByName($name);
        if ($existing) {
            $this->redirect('master_library/employee_sections/create');
            return;
        }

        $params = [
            'section_name'  => $name,
            'department_id' => $departmentId,
            'status'        => ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
        ];

        $this->sectionModel->create($params);
        $this->redirect('master_library/employee_sections');
    }

    /**
     * Show the edit form pre-filled with existing section data
     */
    public function edit(int $id): void
    {
        $this->requirePermission('edit_employee_sections');

        $section = $this->sectionModel->find($id);
        if (!$section) {
            $this->redirect('master_library/employee_sections');
            return;
        }

        $departments = $this->departmentModel->all();

        $this->view('master_library/employee_section/edit', [
            'section'     => $section,
            'departments' => $departments,
        ]);
    }

    /**
     * Update an existing section
     */
    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('edit_employee_sections');

        $section = $this->sectionModel->find($id);
        if (!$section) {
            $this->redirect('master_library/employee_sections');
            return;
        }

        $data         = $this->request->all();
        $name         = trim($data['section_name'] ?? '');
        $departmentId = isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null;

        if (empty($name)) {
            $this->redirect("master_library/employee_sections/{$id}/edit");
            return;
        }

        // Check duplicate name excluding current record
        $existing = $this->sectionModel->findByName($name);
        if ($existing && (int)$existing['section_id'] !== $id) {
            $this->redirect("master_library/employee_sections/{$id}/edit");
            return;
        }

        $params = [
            'section_name'  => $name,
            'department_id' => $departmentId,
            'status'        => ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
        ];

        $this->sectionModel->update($id, $params);
        $this->redirect('master_library/employee_sections');
    }

    /**
     * Delete a section (only if no employees are linked)
     */
    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('delete_employee_sections');

        $section = $this->sectionModel->find($id);
        if (!$section) {
            $this->redirect('master_library/employee_sections');
            return;
        }

        // Check if employees are assigned
        $employeeCount = $this->sectionModel->getEmployeeCount($id);
        if ($employeeCount > 0) {
            $this->redirect('master_library/employee_sections');
            return;
        }

        $this->sectionModel->delete($id);
        $this->redirect('master_library/employee_sections');
    }
}
