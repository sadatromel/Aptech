<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use Core\Controller;
use Core\Container;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = Container::get(AuthService::class);
    }

    /**
     * Show login page (legacy compatibility)
     */
    /**
     * Show login page
     */
    public function showLogin(): void
    {
        if ($this->authService->isLoggedIn()) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/dashboard');
            exit;
        }

        $this->view('auth/login');
    }

    /**
     * Handle login submission
     */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showLogin();
            return;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->view('auth/login', [
                'error' => 'Email and password are required',
                'email' => $email
            ]);
            return;
        }

        $user = $this->authService->login($email, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['full_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/dashboard');
            exit;
        } else {
            $this->view('auth/login', [
                'error' => 'Invalid email/username or password',
                'email' => $email
            ]);
        }
    }

    /**
     * Show registration page
     */
    public function showRegister(): void
    {
        if ($this->authService->isLoggedIn()) {
            $user = $this->authService->getCurrentUser();
            $role = strtolower($user['role'] ?? '');
            $isAdmin = in_array($role, ['admin', 'super admin', 'super_admin', 'system administrator', 'supper admin']);

            if (!$isAdmin) {
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/dashboard');
                exit;
            }
        }

        $this->view('auth/register');
    }

    /**
     * Handle registration submission
     */
    public function register(): void
    {
        if ($this->authService->isLoggedIn()) {
            $user = $this->authService->getCurrentUser();
            $role = strtolower($user['role'] ?? '');
            $isAdmin = in_array($role, ['admin', 'super admin', 'super_admin', 'system administrator', 'supper admin']);

            if (!$isAdmin) {
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/dashboard');
                exit;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showRegister();
            return;
        }

        $fullName = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $employeeId = $_POST['employee_id'] ?? '';
        $unit = $_POST['unit'] ?? '';
        $designation = $_POST['designation'] ?? '';
        $department = $_POST['department'] ?? '';
        $reason = $_POST['reason'] ?? '';

        $data = [
            'full_name' => $fullName,
            'email' => $email,
            'employee_id' => $employeeId,
            'unit' => $unit,
            'designation' => $designation,
            'department' => $department,
            'reason' => $reason
        ];

        // Validation
        if (empty($fullName) || empty($email) || empty($employeeId) || empty($unit) || empty($designation) || empty($department) || empty($reason)) {
            $this->view('auth/register', array_merge($data, ['error' => 'All fields are required']));
            return;
        }
        
        // Handle File Upload
        $profilePhoto = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadFile)) {
                $profilePhoto = $uploadFile;
            } else {
                $this->view('auth/register', array_merge($data, ['error' => 'Failed to upload photo']));
                return;
            }
        }

        // Submit Application
        $result = $this->authService->submitApplication(array_merge($data, ['profile_photo' => $profilePhoto]));

        if ($result['success']) {
            $_SESSION['success_message'] = 'Application submitted successfully! Please wait for admin approval.';
            // Redirect to same page or login to show success message
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/register');
            exit;
        } else {
            $this->view('auth/register', array_merge($data, ['error' => $result['message']]));
        }
    }

    /**
     * Handle logout (legacy compatibility)
     */
    public function logout(): void
    {
        if ($this->authService->isLoggedIn()) {
            if (isset($_SESSION['user_id'])) {
                 $this->authService->logout($_SESSION['user_id']);
            }
        }
        session_destroy();
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login');
        exit;
    }

    public function isLoggedIn(): bool
    {
        return $this->authService->isLoggedIn();
    }

    public function getCurrentUser(): ?array
    {
        return $this->authService->getCurrentUser();
    }

    public function checkPermission(string $module, string $action, string $permissionType = 'view'): bool
    {
        $user = $this->authService->getCurrentUser();
        if (!$user) {
            return false;
        }
        return $this->authService->checkPermission($user['user_id'], $module, $action, $permissionType);
    }
}
