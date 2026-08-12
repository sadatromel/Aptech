<?php
/**
 * Check if user has permission for a specific action on a page
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param string $module Module name
 * @param string $page Page name/key
 * @param string $action Permission type (view, create, edit, delete, approve)
 * @return bool True if user has permission, false otherwise
 */
/**
 * Check if user has permission for a specific action on a page
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param string $module Module name
 * @param string $page Page name/key
 * @param string $action Permission type (view, create, edit, delete, approve)
 * @return bool True if user has permission, false otherwise
 */
function checkUserPermission($conn, $user_id, $module, $page, $action) {
    // Super Admin / Admin / Manager Bypass
    $bypass_roles = ['super admin', 'admin', 'manager', 'system administrator'];
    
    $role = strtolower($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '');
    
    // Fallback: check username if role not set
    if (empty($role) && isset($_SESSION['username'])) {
        $username = strtolower($_SESSION['username']);
        if ($username === 'admin' || $username === 'administrator') {
            return true;
        }
    }
    
    if (in_array($role, $bypass_roles)) {
        return true;
    }

    // Map action to database column
    $action_map = [
        'view' => 'can_view',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
        'approve' => 'can_approve'
    ];
    
    if (!isset($action_map[$action])) {
        return false;
    }
    
    $column = $action_map[$action];
    $cache_key = "perm_{$user_id}_{$module}_{$page}";

    // Check session cache if available (optional optimization)
    if (isset($_SESSION['permissions_cache'][$cache_key])) {
        // return $_SESSION['permissions_cache'][$cache_key][$action] ?? false;
        // Commented out for now to ensure real-time updates without re-login
    }
    
    // Check specific user permissions
    $stmt = $conn->prepare("
        SELECT $column 
        FROM user_permissions 
        WHERE user_id = ? AND module = ? AND page_name = ?
    ");
    $stmt->execute([$user_id, $module, $page]);
    $result = $stmt->fetch();
    
    // If explicit user override exists, return it
    if ($result) {
        return (bool)$result[$column];
    }
    
    // Use role from session
    $role = $_SESSION['user_role'] ?? '';
    
    if (empty($role)) {
        // Fetch role if not in session?
         $stmt = $conn->prepare("SELECT r.role_name, r.role_id FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
         $stmt->execute([$user_id]);
         $user_data = $stmt->fetch();
         $role_id = $user_data['role_id'] ?? 0;
    } else {
        // Get role_id from role name (Optimally this should be in session too)
        // For now, let's query.
        // Optimization: Store role_id in session during login
        $stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
        $stmt->execute([$role]);
        $role_row = $stmt->fetch();
        $role_id = $role_row['role_id'] ?? 0;
    }

    if ($role_id) {
        // Check role permissions
        $stmt = $conn->prepare("
            SELECT $column 
            FROM role_permissions 
            WHERE role_id = ? AND module = ? AND page_name = ?
        ");
        $stmt->execute([$role_id, $module, $page]);
        $role_result = $stmt->fetch();

        if ($role_result) {
             return (bool)$role_result[$column];
        }
    }
    
    // Default to FALSE for security
    return false;
}

/**
 * Check if current user has view permission
 */
function canView($module, $page, $user_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    
    // Temporary debug logging for maintenance page
    if ($page === 'vehicle_management/maintenance') {
        error_log("DEBUG canView: module=$module, page=$page, user_id=$user_id");
        error_log("DEBUG session role_name: " . ($_SESSION['role_name'] ?? 'NOT SET'));
        error_log("DEBUG session user_role: " . ($_SESSION['user_role'] ?? 'NOT SET'));
        error_log("DEBUG session username: " . ($_SESSION['username'] ?? 'NOT SET'));
    }
    
    return checkUserPermission(\Core\Container::get(\PDO::class), $user_id, $module, $page, 'view');
    
    // Log result for maintenance page
    if ($page === 'vehicle_management/maintenance') {
        error_log("DEBUG canView result: " . ($result ? 'TRUE' : 'FALSE'));
    }
    
    return $result;
}

/**
 * Check if current user has create permission
 */
function canCreate($module, $page, $user_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    return checkUserPermission(\Core\Container::get(\PDO::class), $user_id, $module, $page, 'create');
}

/**
 * Check if current user has edit permission
 */
function canEdit($module, $page, $user_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    return checkUserPermission(\Core\Container::get(\PDO::class), $user_id, $module, $page, 'edit');
}

/**
 * Check if current user has delete permission
 */
function canDelete($module, $page, $user_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    return checkUserPermission(\Core\Container::get(\PDO::class), $user_id, $module, $page, 'delete');
}

/**
 * Check if current user has approve permission
 */
function canApprove($module, $page, $user_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    return checkUserPermission(\Core\Container::get(\PDO::class), $user_id, $module, $page, 'approve');
}

/**
 * Get all permissions for a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return array Array of permissions grouped by module
 */
function getUserPermissions($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT module, page_name, can_view, can_create, can_edit, can_delete, can_approve
        FROM user_permissions
        WHERE user_id = ?
        ORDER BY module, page_name
    ");
    $stmt->execute([$user_id]);
    $permissions = $stmt->fetchAll();
    
    $grouped = [];
    foreach ($permissions as $perm) {
        $grouped[$perm['module']][$perm['page_name']] = [
            'view' => $perm['can_view'],
            'create' => $perm['can_create'],
            'edit' => $perm['can_edit'],
            'delete' => $perm['can_delete'],
            'approve' => $perm['can_approve']
        ];
    }
    
    return $grouped;
}
?>
