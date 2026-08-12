<?php

declare(strict_types=1);

namespace App\Controllers\Transport;

use App\Controllers\BaseController;
use App\Services\PartsInventoryService;
use Core\Container;

class PartsInventoryController extends BaseController
{
    private PartsInventoryService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(PartsInventoryService::class);
    }

    public function index(): void
    {
        // Check if we're coming from legacy system
        if (isset($_SESSION['legacy_to_mvc'])) {
            // Legacy system already authenticated, just check role
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            // Full authentication check for direct MVC access
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        $filters = [
            'search' => $this->request->get('search', ''),
        ];

        $this->view('transport/parts-inventory/index', [
            'parts' => $this->service->getParts($filters),
            'stats' => $this->service->getStats(),
            'filters' => $filters,
            'user' => $this->auth->user() ?? [],
            'session' => $_SESSION,
            'app_base_url' => rtrim(dirname(ASSETS_URL), '/'),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        $data = $this->request->json();
        $data['created_by'] = $this->auth->user()['user_id'];

        try {
            $id = $this->service->create($data);
            $this->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        $data = $this->request->json();
        $data['updated_by'] = $this->auth->user()['user_id'];

        try {
            $this->service->update($id, $data);
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        try {
            $this->service->delete($id);
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show(int $id): void
    {
        if (isset($_SESSION['legacy_to_mvc'])) {
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        } else {
            $this->requireAuth();
            $this->requireAnyRole(['admin', 'manager', 'super admin']);
        }

        $part = $this->service->findById($id);
        if (!$part) {
            $this->notFound();
        }

        $this->json(['success' => true, 'data' => $part]);
    }
}
