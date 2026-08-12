<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Models\MasterLibrary\Uom;
use Core\Container;

class UomController extends BaseController
{
    private Uom $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = Container::get(Uom::class);
    }

    public function index(): void
    {
        $this->requirePermission('view_uoms');
        $uoms = $this->model->all();
        $this->view('master_library/uom/index', [
            'uoms' => $uoms
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('create_uoms');
        $this->view('master_library/uom/create');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requirePermission('create_uoms');
        
        $data = $this->request->all();
        
        // Basic validation
        $errors = $this->validate([
            'uom_code' => 'required|max:50',
            'uom_name' => 'required|max:100'
        ], $data);

        if (!empty($errors)) {
            $_SESSION['error_message'] = implode(' ', array_map(fn($e) => $e[0], $errors));
            $this->redirect('master_library/uoms/create');
            return;
        }

        $this->model->create([
            'uom_code' => $data['uom_code'],
            'uom_name' => $data['uom_name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active'
        ]);

        $_SESSION['success_message'] = 'Unit of Measure created successfully.';
        $this->redirect('master_library/uoms');
    }

    public function edit($id): void
    {
        $this->requirePermission('edit_uoms');
        $uom = $this->model->find((int)$id);
        
        if (!$uom) {
            $this->notFound();
            return;
        }

        $this->view('master_library/uom/edit', [
            'uom' => $uom
        ]);
    }

    public function update($id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('edit_uoms');
        
        $uom = $this->model->find((int)$id);
        if (!$uom) {
            $this->notFound();
            return;
        }

        $data = $this->request->all();
        
        // Basic validation
        $errors = $this->validate([
            'uom_code' => 'required|max:50',
            'uom_name' => 'required|max:100'
        ], $data);

        if (!empty($errors)) {
            $_SESSION['error_message'] = implode(' ', array_map(fn($e) => $e[0], $errors));
            $this->redirect("master_library/uoms/edit/{$id}");
            return;
        }

        $this->model->update((int)$id, [
            'uom_code' => $data['uom_code'],
            'uom_name' => $data['uom_name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active'
        ]);

        $_SESSION['success_message'] = 'Unit of Measure updated successfully.';
        $this->redirect('master_library/uoms');
    }

    public function destroy($id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('delete_uoms');
        
        // For deleting, we can either receive the ID as an argument or from $_POST['id']
        $targetId = (int)($id ?? $_POST['id'] ?? 0);
        
        if ($targetId > 0) {
            $this->model->delete($targetId);
            $_SESSION['success_message'] = 'Unit of Measure deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'Invalid UOM ID for deletion.';
        }
        
        $this->redirect('master_library/uoms');
    }
}
