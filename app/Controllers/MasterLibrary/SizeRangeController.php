<?php

declare(strict_types=1);


namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Services\SizeRangeService;
use Core\Container;

class SizeRangeController extends BaseController
{
    private SizeRangeService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(SizeRangeService::class);
    }

    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'search' => $this->request->get('search', ''),
            'sort' => $this->request->get('sort', ''),
            'direction' => $this->request->get('direction', ''),
        ];

        $this->view('master_library/size-ranges/index', [
            'ranges' => $this->service->listRanges($filters),
            'filters' => $filters,
            'dropdowns' => $this->service->getDropdownData(),
        ]);
    }
    
    public function create(): void
    {
        $this->requireAuth();
        $this->view('master_library/size-ranges/create');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();

        $errors = $this->validate([
            'range_name' => 'required',
            'sizes' => 'required',
        ], $data);

        if ($errors) {
            $_SESSION['error_message'] = implode(', ', $errors);
            $this->redirect('master_library/size-ranges/create');
            return;
        }

        $sizes = $data['sizes'];
        if (is_string($sizes)) {
            $sizes = array_map('trim', explode(',', $sizes));
        }

        try {
            $this->service->createRange([
                'range_name' => $data['range_name'],
                'description' => $data['description'] ?? '',
                'sizes' => $sizes,
            ]);
            $_SESSION['success_message'] = "Size range created successfully";
            $this->redirect('master_library/size-ranges');
        } catch (\Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $this->redirect('master_library/size-ranges/create');
        }
    }
    
    public function edit(int $rangeId): void
    {
        $this->requireAuth();
        
        $range = $this->service->getRange($rangeId);
        if (!$range) {
             $_SESSION['error_message'] = "Size range not found";
            $this->redirect('master_library/size-ranges');
            return;
        }
        
        $this->view('master_library/size-ranges/edit', [
            'range' => $range
        ]);
    }

    public function update(int $rangeId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        $data = $this->request->all();

        $errors = $this->validate([
            'range_name' => 'required',
            'sizes' => 'required',
        ], $data);

        if ($errors) {
            $_SESSION['error_message'] = implode(', ', $errors);
            $this->redirect("master_library/size-ranges/edit/$rangeId");
            return;
        }

        $sizes = $data['sizes'];
        if (is_string($sizes)) {
            $sizes = array_map('trim', explode(',', $sizes));
        }

        if ($this->service->updateRange($rangeId, [
            'range_name' => $data['range_name'],
            'description' => $data['description'] ?? '',
            'sizes' => $sizes,
        ])) {
            $_SESSION['success_message'] = "Size range updated successfully";
            $this->redirect('master_library/size-ranges');
            return;
        }

        $_SESSION['error_message'] = "Failed to update size range";
        $this->redirect("master_library/size-ranges/edit/$rangeId");
    }

    public function destroy(int $rangeId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();

        if ($this->service->deleteRange($rangeId)) {
            $_SESSION['success_message'] = "Size range deleted successfully";
            $this->redirect('master_library/size-ranges');
            return;
        }

        $_SESSION['error_message'] = "Failed to delete size range";
        $this->redirect('master_library/size-ranges');
    }
}
