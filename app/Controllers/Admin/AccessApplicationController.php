<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\UserService;
use Core\Database;
use Core\Container;

class AccessApplicationController extends BaseController
{
    private UserService $userService;
    // BaseController likely has protected $db, or we can just use $this->db if inherited.
    // If we need type hinting, we can use an accessor or just protected.
    // Let's assume we can just use the parent's db or define as protected if we re-declare.
    protected Database $db;

    public function __construct(UserService $userService)
    {
        parent::__construct();
        $this->userService = $userService;
        $this->db = Container::get(Database::class);
    }

    public function approve(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $data = $this->request->all();
        
        // Validation
        if (empty($data['username']) || empty($data['password']) || empty($data['application_id'])) {
            $this->json(['success' => false, 'message' => 'Missing required fields'], 400);
            return;
        }

        $app = $this->userService->getAccessApplication((int) $data['application_id']);
        if (!$app) {
            $this->json(['success' => false, 'message' => 'Application not found'], 404);
            return;
        }

        try {
            // Create user
            $names = explode(' ', $app['full_name'], 2);
            $userId = $this->userService->createUser([
                'first_name' => $names[0] ?? '',
                'last_name' => $names[1] ?? '',
                'email' => $data['email'] ?? $app['email'],
                'password' => $data['password'],
                'role_id' => (int) $data['role_id'],
                'username' => $data['username'], // Ensure createUser supports username if schema has it, otherwise email logic
            ], (int) $this->auth->user()['user_id']);

            // Update application status
            $this->userService->updateApplicationStatus((int) $data['application_id'], 'approved');

            // Optionally copy profile photo if exists and user table supports it
            // For now, simpler is better.

            $this->json(['success' => true, 'message' => 'User created and application approved']);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Failed to create user: ' . $e->getMessage()], 500);
        }
    }

    public function reject(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $id = (int) $this->request->get('id');
        if ($this->userService->updateApplicationStatus($id, 'rejected')) {
            $this->json(['success' => true, 'message' => 'Application rejected']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to reject application']);
        }
    }
}
