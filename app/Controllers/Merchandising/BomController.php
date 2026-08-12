<?php

declare(strict_types=1);

namespace App\Controllers\Merchandising;

use App\Controllers\BaseController;
use App\Services\BomService;
use Core\Container;

class BomController extends BaseController
{
    private BomService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(BomService::class);
    }

    public function index(): void
    {
        $this->requireAuth();
        
        $filters = [
            'search' => $this->request->get('search', ''),
            'status' => $this->request->get('status', ''),
            'style_id' => $this->request->get('style_id', ''),
        ];

        $this->view('merchandising/bom/index', [
            'boms' => $this->service->listBoms($filters),
            'filters' => $filters,
            'stats' => $this->service->getStats(),
            'dropdowns' => $this->service->getDropdownData() // Ensure this method exists or provides what we need (styles/statuses)
        ]);
    }
    
    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('merchandising_manager');
        
        $this->view('merchandising/bom/create', [
            'styles' => $this->service->getStyles()
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');
        $data = $this->request->all();

        $errors = $this->validate([
            'style_id' => 'required',
            'version' => 'required'
        ], $data);

        if ($errors) {
            $_SESSION['error_message'] = implode(', ', $errors);
            $this->redirect('merchandising/bom/create');
            return;
        }

        try {
            $bomId = $this->service->createBom($data);
            $_SESSION['success_message'] = "BOM created successfully";
            $this->redirect("merchandising/bom/$bomId"); // Redirect to show page
        } catch (\Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $this->redirect('merchandising/bom/create');
        }
    }

    public function show(int $bomId): void
    {
        $this->requireAuth();
        
        $bom = $this->service->getBom($bomId);
        if (!$bom) {
            $_SESSION['error_message'] = "BOM not found";
            $this->redirect('merchandising/bom');
            return;
        }
        
        // Pass components for this BOM
        $components = $this->service->getComponents(['bom_id' => $bomId]);
        
        $this->view('merchandising/bom/show', [
            'bom' => $bom,
            'components' => $components,
            'stats' => [ // specific stats for this BOM
                'total_cost' => array_sum(array_column($components, 'total_cost')),
                'item_count' => count($components)
            ]
        ]);
    }

    public function edit(int $bomId): void
    {
        $this->requireAuth();
        $this->requireRole('merchandising_manager');
        
        $bom = $this->service->getBom($bomId);
        if (!$bom) {
            $_SESSION['error_message'] = "BOM not found";
            $this->redirect('merchandising/bom');
            return;
        }

        $this->view('merchandising/bom/edit', [
            'bom' => $bom
        ]);
    }

    public function update(int $bomId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');
        
        $data = $this->request->all();
        
        if ($this->service->updateBom($bomId, $data)) {
            $_SESSION['success_message'] = "BOM updated successfully";
            $this->redirect("merchandising/bom/$bomId");
        } else {
             $_SESSION['error_message'] = "Failed to update BOM";
             $this->redirect("merchandising/bom/edit/$bomId");
        }
    }

    public function destroy(int $bomId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('merchandising_manager');
        
        // Ensure standard REST check or POST override
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'POST' && $method !== 'DELETE') {
             $this->redirect('merchandising/bom');
             return;
        }

        if ($this->service->deleteBom($bomId)) {
            $_SESSION['success_message'] = "BOM deleted successfully";
        } else {
            $_SESSION['error_message'] = "Failed to delete BOM";
        }
        
        // Valid for standard form/link usage. If AJAX, we might want JSON. 
        // But implementation plan said "Update View to use secure DELETE". 
        // If view uses fetch, we should return JSON or handle redirect gracefully.
        // For compatibility with potentially mixed usage, we'll stick to redirect for BOMs (Page level)
        // unless it is an AJAX request.
        if ($this->request->isJson()) {
             $this->json(['success' => true]);
        } else {
             $this->redirect('merchandising/bom');
        }
    }

    // Component API Methods (JSON)
    public function storeComponent(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->json();
        
        try {
            $this->service->createComponent($data);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function updateComponent(int $componentId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->json();

        try {
            $this->service->updateComponent($componentId, $data);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroyComponent(int $componentId): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        try {
            $this->service->deleteComponent($componentId);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
             $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
