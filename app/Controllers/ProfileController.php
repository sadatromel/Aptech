<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use Core\Container;
use App\Http\Response;

class ProfileController extends BaseController
{
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = Container::get(UserService::class);
    }

    /**
     * Show user profile
     */
    public function index(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $user = $this->userService->getUser($userId);

        if (!$user) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/dashboard');
            exit;
        }

        $this->view('users/profile', [
            'user' => $user,
            'success_message' => $_SESSION['success_message'] ?? null,
            'error_message' => $_SESSION['error_message'] ?? null
        ]);

        // Clear flash messages
        unset($_SESSION['success_message'], $_SESSION['error_message']);
    }

    /**
     * Update user profile
     */
    public function update(): void
    {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        
        // Split full name into first and last name
        $fullName = trim($_POST['full_name'] ?? '');
        $parts = explode(' ', $fullName, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        // We preserve role_id
        $currentUser = $this->userService->getUser($userId);
        $roleId = $currentUser['role_id'] ?? 0;

        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $_POST['email'] ?? '',
            'role_id' => $roleId
        ];
        
        // Handle password change if provided
        if (!empty($_POST['new_password'])) {
            $data['password'] = $_POST['new_password'];
            if ($_POST['new_password'] !== ($_POST['confirm_password'] ?? '')) {
                $_SESSION['error_message'] = 'Passwords do not match';
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/profile');
                exit;
            }
            $this->userService->resetPassword($userId, $data['password']);
        }

        try {
            // Update profile info
            // Note: UpdateUser in UserService might require role_id, hence we fetched it.
            $result = $this->userService->updateUser($userId, $data, $userId);
            
            if ($result) {
                $_SESSION['success_message'] = 'Profile updated successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to update profile';
            }
        } catch (\Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/profile');
        exit;
    }
}
