<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class RoleController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        // Get roles with user counts
        $sql = "
            SELECT r.*, COUNT(u.user_id) as user_count
            FROM roles r
            LEFT JOIN users u ON r.role_id = u.role_id
            GROUP BY r.role_id
            ORDER BY r.role_name ASC
        ";
        $roles = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        // Get stats
        $totalUsers = $this->db->count('users');
        $activeRoles = $this->db->count('roles'); // Assuming all roles are active as there is no status column in roles table usually, or check schema. View shows "Active" for all.

        $this->view('admin/roles/index', [
            'roles' => $roles,
            'totalUsers' => $totalUsers,
            'activeRoles' => $activeRoles
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        // Get permissions
        $permissions = $this->db->select('permissions', '*');

        $this->view('admin/roles/create', ['permissions' => $permissions]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        $data = $this->request->all();
        
        $rules = [
            'name' => 'required|max:50',
            'description' => 'max:255'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Insert role
            $roleId = $this->db->insert('roles', [
                'role_name' => $data['name'],
                'description' => $data['description'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Insert role permissions
            if (isset($data['permissions'])) {
                foreach ($data['permissions'] as $permissionId) {
                    $this->db->insert('role_permissions', [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId
                    ]);
                }
            }

            $this->db->commit();

            $this->json([
                'success' => true,
                'message' => 'Role created successfully',
                'role_id' => $roleId
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->json([
                'success' => false,
                'message' => 'Failed to create role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function edit(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $role = $this->db->get('roles', '*', ['role_id' => $id]);
        if (!$role) {
            $this->redirect('/admin/roles');
            return;
        }

        $this->view('admin/roles/edit', [
            'role' => $role
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        $data = $this->request->all();
        
        $rules = [
            'name' => 'required|max:50',
            'description' => 'max:255'
        ];

        $errors = $this->validate($rules, $data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Update role
            $this->db->update('roles', [
                'role_name' => $data['name'],
                'description' => $data['description'] ?? ''
            ], ['role_id' => $id]);

            $this->db->commit();

            $this->json([
                'success' => true,
                'message' => 'Role updated successfully'
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->json([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        // Check if role is in use
        $usersWithRole = $this->db->count('users', ['role_id' => $id]);
        if ($usersWithRole > 0) {
            $this->json([
                'success' => false,
                'message' => 'Cannot delete role. It is assigned to ' . $usersWithRole . ' users.'
            ], 400);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Delete role permissions
            $this->db->delete('role_permissions', ['role_id' => $id]);
            
            // Delete role
            $this->db->delete('roles', ['role_id' => $id]);

            $this->db->commit();

            $this->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->json([
                'success' => false,
                'message' => 'Failed to delete role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function permissions(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $role = $this->db->get('roles', '*', ['role_id' => $id]);
        if (!$role) {
            $this->redirect('/admin/roles');
            return;
        }

        $permissionModel = new \App\Models\Permission();
        $rolePermissionModel = new \App\Models\RolePermission();

        // Get all permissions (flat list for processing)
        // We need all raw permissions first to group them manually if we want the "modules" structure
        // Or we can use the Grouped one and transform it.
        // Let's use the explicit manual grouping to match the View's expectation of "modules" array with metadata.
        
        $all_permissions = $permissionModel->all(); // Assuming all() exists or select('*')
        if (empty($all_permissions)) {
             // Fallback if all() not generic
             $all_permissions = $this->db->select('permissions', '*');
        }

        // Get role permissions (Matrix)
        $role_permissions = $rolePermissionModel->getByRole($id);
        
        // Flatten role permissions to just ID checks for simple "assigned" check if migrating
        // BUT wait, the new view uses Matrix checkboxes (View/Create/Edit/Delete).
        // The view I uploaded in Step 46 uses: 
        // `data.permissions` array of Permission objects with `assigned` boolean.
        // AND checks `module.permissions`.
        
        // Wait, looking at Step 46 View:
        // loops `module.permissions`
        // checks `permission.assigned`
        // This looks like the OLD Legacy "Capability" view structure?
        // Let me re-read the View code I wrote in Step 46.
        
        // ... `class="permission-card {% if permission.assigned %}selected{% endif %}"`
        // ... `input type="checkbox" ... {% if permission.assigned %}checked{% endif %}`
        
        // This View is for CAPABILITY based permissions (Single checkbox per permission).
        // It is NOT a Matrix View (View/Create/Edit).
        
        // I made a mistake. I said I was converting to Matrix, but I uploaded a View that looks like a "Select Capabilities" grid.
        // The User Permissions view (Step 45) has columns for View/Add/Edit/Delete.
        // The Role Permissions view (Step 46) has `permission-card` and `permission-checkbox`.
        
        // I should have uploaded a Matrix view for Roles too.
        // However, if I want to "Proceed in best way", maybe the User really wants the Matrix.
        
        // Let's check the User Permissions view (`views/admin/user_permissions/index.twig`) again.
        // It iterates `grouped_permissions`.
        
        // I SHOULD USE THE USER PERMISSIONS VIEW LOGIC FOR ROLES.
        // The view I uploaded in Step 46 is WRONG for the Matrix goal. It's a "List of Permissions" view.
        // But wait, my Goal in Step 44 was "columns: role_id, module, page_name, can_view...".
        // If I use the view from Step 46, it corresponds to "Role has Permission X".
        // But Permission X is "User Management".
        // Does "User Management" mean "Can View"?
        
        // The user wants "Role Permissions" to match "User Permissions".
        // User Permissions has granular flags.
        // So I must update the View to be a Matrix View.
        
        // Step 46 View IS NOT a Matrix View. It's a Card Grid.
        // I need to REPLACE the View with a Matrix View.
        
        // So, correct plan:
        // 1. Update `RoleController` to provide data compatible with a Matrix View (Grouped Permissions).
        // 2. Update `views/admin/roles/permissions.twig` to be a Matrix View (Clone of User Permissions).
        
        // Let's stick with the Controller update in this step, but make it formatted for the Matrix View.
        // The User Permissions controller uses `grouped_permissions`.
        // So my PREVIOUS controller code (Step 71) was actually closer to the Matrix goal!
        // The problem is the View was mismatched (expecting `modules` object).
        
        // So I should REVERT to the Matrix data structure in Controller, and passing the correct `stats`.
        
        // Module config
        $module_names = [
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
        
        $module_icons = [
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

        // Get all permissions grouped
        $grouped_permissions = $permissionModel->getAllGroupedByModule();
        
        // Get role permissions (Matrix)
        $role_permissions = $rolePermissionModel->getByRole($id);

        // Stats
        $total_pages = 0;
        foreach ($grouped_permissions as $perms) {
            $total_pages += count($perms);
        }

        $pages_with_access = 0;
        foreach ($role_permissions as $p) {
            if (!empty($p['can_view'])) {
                $pages_with_access++;
            }
        }
        
        $coverage = $total_pages > 0 ? round(($pages_with_access / $total_pages) * 100) : 0;

        $stats = [
            'total_pages' => $total_pages,
            'pages_with_access' => $pages_with_access,
            'coverage' => $coverage
        ];
        
        // View needs 'role' not 'user'
        $this->view('admin/roles/permissions', [
            'role' => $role,
            'grouped_permissions' => $grouped_permissions,
            'role_permissions' => $role_permissions,
            'module_names' => $module_names,
            'module_icons' => $module_icons,
            'stats' => $stats
        ]);
    }

    public function updatePermissions(int $id): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $this->requireRole('admin');

        // Check if database table exists before trying (Handling migration gap)
        try {
             $this->db->query("SELECT 1 FROM role_permissions LIMIT 1");
        } catch (\Exception $e) {
             // If table doesn't exist, we can't save.
             // Ideally we should have run the migration, but since we can't run mysql command...
             if ($this->request->isAjax()) {
                 $this->json(['success' => false, 'message' => 'Database table role_permissions missing. Please run migration.'], 500);
                 return;
             }
        }

        $permissions_data = $this->request->input('permissions') ?? [];
        
        try {
            $rolePermissionModel = new \App\Models\RolePermission();
            $rolePermissionModel->updateForRole($id, $permissions_data);

            if ($this->request->isAjax()) {
                $this->json(['success' => true, 'message' => 'Role permissions updated successfully']);
            } else {
                $this->redirect('/admin/roles/permissions?id=' . $id . '&success=1');
            }

        } catch (\Exception $e) {
             if ($this->request->isAjax()) {
                $this->json(['success' => false, 'message' => 'Failed to update permissions: ' . $e->getMessage()], 500);
            } else {
                // Handle non-ajax error
                echo "Error: " . $e->getMessage();
            }
        }
    }
}
