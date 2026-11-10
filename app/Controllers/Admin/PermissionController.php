<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\UserPermission;

class PermissionController extends BaseController
{
    private array $moduleNames = [
        'user' => 'User Management',
        'role' => 'Role Management',
        'permission' => 'Permissions',
        'dashboard' => 'Dashboard',
        'settings' => 'System Settings',
        'report' => 'Reports',
        'inventory' => 'Inventory Management',
        'purchase' => 'Purchase Orders',
        'sale' => 'Sales',
        'customer' => 'Customer Management',
        'supplier' => 'Supplier Management',
        'accounting' => 'Accounting',
        'hr' => 'Human Resources',
        'payroll' => 'Payroll',
        'attendance' => 'Attendance',
        'leave' => 'Leave Management',
        'notice' => 'Notice Board',
        'document' => 'Document Management',
        'email' => 'Email System',
        'sms' => 'SMS System',
        'backup' => 'Backup & Restore',
        'update' => 'System Updates',
        'api' => 'API Management',
        'merchandising' => 'Merchandising',
        'production' => 'Production',
        'commercial' => 'Commercial',
        'finance' => 'Finance',
        'procurement' => 'Procurement',
        'store_inventory' => 'Store Inventory',
        'cutting' => 'Cutting',
        'sewing' => 'Sewing',
        'washing' => 'Washing',
        'packing' => 'Packing',
        'vehicle_management' => 'Vehicle Management',
        'visitor' => 'Visitor Management',
        'transport' => 'Transport'
    ];

    private array $moduleIcons = [
        'user' => 'users',
        'role' => 'user-shield',
        'permission' => 'key',
        'dashboard' => 'tachometer-alt',
        'settings' => 'cog',
        'report' => 'chart-bar',
        'inventory' => 'boxes',
        'purchase' => 'shopping-cart',
        'sale' => 'cash-register',
        'customer' => 'users',
        'supplier' => 'truck-loading',
        'accounting' => 'calculator',
        'hr' => 'user-tie',
        'payroll' => 'money-bill-wave',
        'attendance' => 'user-clock',
        'leave' => 'calendar-alt',
        'notice' => 'bullhorn',
        'document' => 'file-alt',
        'email' => 'envelope',
        'sms' => 'sms',
        'backup' => 'database',
        'update' => 'sync',
        'api' => 'plug',
        'merchandising' => 'tshirt',
        'production' => 'industry',
        'commercial' => 'handshake',
        'finance' => 'coins',
        'procurement' => 'clipboard-list',
        'store_inventory' => 'warehouse',
        'cutting' => 'cut',
        'sewing' => 'thread',
        'washing' => 'tint',
        'packing' => 'box',
        'vehicle_management' => 'truck',
        'visitor' => 'id-badge',
        'transport' => 'bus'
    ];

    public function index(?int $userId = null): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $targetUserId = $userId ?: (int)($this->request->get('user_id') ?: $this->request->get('id', 0));

        // Fetch users for selector list
        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        $userQuery = "SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.role_id, r.role_name
                      FROM users u
                      LEFT JOIN roles r ON u.role_id = r.role_id";
        if (!$isSuperOrSystemAdmin) {
            $userQuery .= " WHERE u.role_id NOT IN (1, 2)
                            AND LOWER(r.role_name) NOT IN ('system administrator', 'supper admin', 'super admin', 'super_admin', 'system_administrator')";
        }
        $userQuery .= " ORDER BY u.first_name ASC, u.last_name ASC";
        $usersList = $this->db->query($userQuery)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if ($targetUserId <= 0 && !empty($usersList)) {
            $targetUserId = (int)$usersList[0]['user_id'];
        }

        $user = null;
        if ($targetUserId > 0) {
            $stmt = $this->db->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
            $stmt->execute([$targetUserId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        if (!$user) {
            $this->redirect('/admin/users');
            return;
        }

        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? 0)) {
            $this->redirect('/admin/users');
            return;
        }

        $permissionModel = new Permission();
        $userPermissionModel = new UserPermission();
        $rolePermissionModel = new RolePermission();

        $groupedPermissions = $permissionModel->getAllGroupedByModule();
        $userPermissions = $userPermissionModel->getByUser($targetUserId);
        $rolePermissions = !empty($user['role_id']) ? $rolePermissionModel->getByRole((int)$user['role_id']) : [];

        $hasCustomOverrides = !empty($userPermissions);
        $effectivePermissions = [];

        foreach ($groupedPermissions as $module => $perms) {
            foreach ($perms as $perm) {
                $pageName = $perm['permission_name'];
                if (isset($userPermissions[$pageName])) {
                    $effectivePermissions[$pageName] = [
                        'can_view' => (int)($userPermissions[$pageName]['can_view'] ?? 0),
                        'can_create' => (int)($userPermissions[$pageName]['can_create'] ?? 0),
                        'can_edit' => (int)($userPermissions[$pageName]['can_edit'] ?? 0),
                        'can_delete' => (int)($userPermissions[$pageName]['can_delete'] ?? 0),
                        'can_approve' => (int)($userPermissions[$pageName]['can_approve'] ?? 0),
                        'is_custom' => true,
                    ];
                } elseif (isset($rolePermissions[$pageName])) {
                    $effectivePermissions[$pageName] = [
                        'can_view' => (int)($rolePermissions[$pageName]['can_view'] ?? 0),
                        'can_create' => (int)($rolePermissions[$pageName]['can_create'] ?? 0),
                        'can_edit' => (int)($rolePermissions[$pageName]['can_edit'] ?? 0),
                        'can_delete' => (int)($rolePermissions[$pageName]['can_delete'] ?? 0),
                        'can_approve' => (int)($rolePermissions[$pageName]['can_approve'] ?? 0),
                        'is_custom' => false,
                    ];
                } else {
                    $effectivePermissions[$pageName] = [
                        'can_view' => 0,
                        'can_create' => 0,
                        'can_edit' => 0,
                        'can_delete' => 0,
                        'can_approve' => 0,
                        'is_custom' => false,
                    ];
                }
            }
        }

        // Stats calculation
        $totalPages = 0;
        foreach ($groupedPermissions as $perms) {
            $totalPages += count($perms);
        }

        $pagesWithAccess = 0;
        foreach ($effectivePermissions as $p) {
            if (!empty($p['can_view'])) {
                $pagesWithAccess++;
            }
        }

        $coverage = $totalPages > 0 ? (int)round(($pagesWithAccess / $totalPages) * 100) : 0;

        $stats = [
            'total_pages' => $totalPages,
            'pages_with_access' => $pagesWithAccess,
            'coverage' => $coverage,
            'has_custom' => $hasCustomOverrides
        ];

        $this->view('admin/permissions/index', [
            'target_user' => $user,
            'users_list' => $usersList,
            'grouped_permissions' => $groupedPermissions,
            'effective_permissions' => $effectivePermissions,
            'user_permissions' => $userPermissions,
            'role_permissions' => $rolePermissions,
            'module_names' => $this->moduleNames,
            'module_icons' => $this->moduleIcons,
            'stats' => $stats
        ]);
    }

    public function update(?int $userId = null): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        $targetUserId = $userId ?: (int)($this->request->input('user_id') ?: $this->request->get('user_id', 0));

        $user = $this->db->get('users', '*', ['user_id' => $targetUserId]);
        if (!$user) {
            $this->respond(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? 0)) {
            $this->respond(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $permissionsData = $this->request->input('permissions') ?? [];

        try {
            $userPermissionModel = new UserPermission();
            $userPermissionModel->updateForUser($targetUserId, $permissionsData);

            $this->respond([
                'success' => true,
                'message' => 'User permissions updated successfully.'
            ]);
        } catch (\Throwable $e) {
            $this->respond([
                'success' => false,
                'message' => 'Failed to update permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reset(?int $userId = null): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        $targetUserId = $userId ?: (int)($this->request->input('user_id') ?: $this->request->get('user_id', 0));

        $user = $this->db->get('users', '*', ['user_id' => $targetUserId]);
        if (!$user) {
            $this->respond(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $isSuperOrSystemAdmin = $this->auth->isSuperAdminOrSystemAdmin();
        if (!$isSuperOrSystemAdmin && $this->auth->isPrivilegedRole($user['role_id'] ?? 0)) {
            $this->respond(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        try {
            $this->db->delete('user_permissions', ['user_id' => $targetUserId]);

            $this->respond([
                'success' => true,
                'message' => 'User permissions have been reset to role defaults.'
            ]);
        } catch (\Throwable $e) {
            $this->respond([
                'success' => false,
                'message' => 'Failed to reset permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    private function respond(array $data, int $status = 200): void
    {
        $isAjax = $this->request->isAjax() || 
                  (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

        if ($isAjax) {
            $this->json($data, $status);
            return;
        }

        $userId = (int)($this->request->input('user_id') ?: $this->request->get('user_id', 0));
        $this->redirect('/admin/permissions?user_id=' . $userId);
    }
}
