<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\UserService;

class UserController extends BaseController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        parent::__construct();
        $this->userService = $userService;
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();

        $filters = [
            'search' => $this->request->get('search', ''),
            'role_id' => $this->request->get('role_id', ''),
            'status' => $this->request->get('status', ''),
        ];

        // If not super/system admin and someone filtered by privileged role_id, clear filter
        if (!$isSuperOrSystemAdmin && !empty($filters['role_id']) && $this->auth->isPrivilegedRole($filters['role_id'])) {
            $filters['role_id'] = '';
        }

        $this->view('admin/users/index', [
            'users' => $this->userService->listUsers($filters, $isSuperOrSystemAdmin),
            'access_applications' => $this->userService->getAccessApplications('pending'),
            'stats' => $this->userService->getStats($isSuperOrSystemAdmin),
            'filters' => $filters,
            'roles' => $this->userService->getRoles($isSuperOrSystemAdmin),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();

        $this->view('admin/users/create', [
            'roles' => $this->userService->getRoles($isSuperOrSystemAdmin),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        $data = $this->request->all();
        
        // Basic validation
        $errors = [];
        if (empty($data['first_name'])) $errors[] = 'First name is required';
        if (empty($data['last_name'])) $errors[] = 'Last name is required';
        if (empty($data['email'])) $errors[] = 'Email is required';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
        if (empty($data['password'])) $errors[] = 'Password is required';
        if (strlen($data['password'] ?? '') < 6) $errors[] = 'Password must be at least 6 characters';
        if (empty($data['role_id'])) $errors[] = 'Role is required';

        if (!$isSuperOrSystemAdmin && !empty($data['role_id']) && $this->auth->isPrivilegedRole($data['role_id'])) {
            $errors[] = 'You do not have permission to assign this role.';
        }

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $userId = $this->userService->createUser($data, (int) $this->auth->user()['id']);
            $this->json(['success' => true, 'user_id' => $userId, 'message' => 'User created successfully.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Failed to create user: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $user = $this->userService->getUser($id);
        if (!$user) {
            $this->json(['error' => 'User not found'], 404);
            return;
        }

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? $user['role_name'] ?? '')) {
            $this->json(['error' => 'User not found'], 404);
            return;
        }

        $this->json(['data' => $user]);
    }

    public function edit(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $user = $this->userService->getUser($id);
        if (!$user) {
            $this->redirect('/admin/users');
            return;
        }

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? $user['role_name'] ?? '')) {
            $this->redirect('/admin/users');
            return;
        }

        $this->view('admin/users/edit', [
            'user' => $user,
            'roles' => $this->userService->getRoles($isSuperOrSystemAdmin),
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        $user = $this->userService->getUser($id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? $user['role_name'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $data = $this->request->all();
        
        // Basic validation
        $errors = [];
        if (empty($data['first_name'])) $errors[] = 'First name is required';
        if (empty($data['last_name'])) $errors[] = 'Last name is required';
        if (empty($data['email'])) $errors[] = 'Email is required';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
        if (empty($data['role_id'])) $errors[] = 'Role is required';

        if (!$isSuperOrSystemAdmin && !empty($data['role_id']) && $this->auth->isPrivilegedRole($data['role_id'])) {
            $errors[] = 'You do not have permission to assign this role.';
        }

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if ($this->userService->updateUser($id, $data, (int) $this->auth->user()['id'])) {
            $this->json(['success' => true, 'message' => 'User updated successfully.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to update user.'], 500);
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        // Prevent self-deletion
        if ($id == $this->auth->user()['user_id']) {
            $this->json(['success' => false, 'message' => 'Cannot delete your own account.'], 400);
            return;
        }

        $user = $this->userService->getUser($id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? $user['role_name'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        if ($this->userService->deleteUser($id)) {
            $this->json(['success' => true, 'message' => 'User deleted successfully.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to delete user.'], 500);
    }

    public function resetPassword(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $user = $this->userService->getUser($id);
        if (!$user) {
            $this->json(['errors' => ['User not found.']], 404);
            return;
        }

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? $user['role_name'] ?? '')) {
            $this->json(['errors' => ['Access denied.']], 403);
            return;
        }

        $data = $this->request->all();
        
        // Basic validation
        $errors = [];
        if (empty($data['password'])) $errors[] = 'Password is required';
        if (strlen($data['password'] ?? '') < 6) $errors[] = 'Password must be at least 6 characters';
        if ($data['password'] !== ($data['confirm_password'] ?? '')) $errors[] = 'Passwords do not match';

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if ($this->userService->resetPassword($id, $data['password'])) {
            $this->json(['success' => true, 'message' => 'Password reset successfully.']);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to reset password.'], 500);
    }

    public function toggleStatus(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        // Prevent self-deactivation
        if ($id == $this->auth->user()['user_id']) {
            $this->json(['success' => false, 'message' => 'Cannot change your own status.'], 400);
            return;
        }

        $user = $this->userService->getUser($id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? $user['role_name'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        
        if ($this->userService->updateStatus($id, $newStatus)) {
            $this->json(['success' => true, 'message' => "User status changed to {$newStatus}."]);
            return;
        }

        $this->json(['success' => false, 'message' => 'Failed to change user status.'], 500);
    }
}
