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
    // Only true Super Admins / System Admins bypass permissions
    $bypass_roles = ['super admin', 'admin', 'supper admin', 'super_admin', 'system administrator', 'system_administrator'];
    
    $role = strtolower($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '');
    
    // Fallback: check username if role not set
    if (empty($role) && isset($_SESSION['username'])) {
        $username = strtolower($_SESSION['username']);
        if ($username === 'admin' || $username === 'administrator') {
            return true;
        }
    }
    
    if (in_array($role, $bypass_roles, true)) {
        return true;
    }

    if (empty($user_id)) {
        $user_id = (int)($_SESSION['user_id'] ?? 0);
    }
    if ($user_id <= 0) {
        return false;
    }

    // Map action to database column
    $action_map = [
        'view' => 'can_view',
        'create' => 'can_create',
        'add' => 'can_create',
        'store' => 'can_create',
        'edit' => 'can_edit',
        'update' => 'can_edit',
        'delete' => 'can_delete',
        'destroy' => 'can_delete',
        'approve' => 'can_approve'
    ];
    
    if (!isset($action_map[$action])) {
        return false;
    }
    
    $column = $action_map[$action];

    // Build candidate page names (handles prefixes like 'view_' and punctuation variations)
    $cleanPage = preg_replace('/^(view|create|add|edit|update|delete|destroy|approve)_/i', '', $page);
    $pageCandidates = array_values(array_unique(array_filter([
        $page,
        $cleanPage,
        str_replace('_', '-', $cleanPage),
        str_replace('-', '_', $cleanPage),
        str_replace('_', ' ', $cleanPage),
        ucwords(str_replace('_', ' ', $cleanPage)),
    ])));

    $placeholders = implode(',', array_fill(0, count($pageCandidates), '?'));

    // Check if user has ANY custom overrides in user_permissions
    static $userOverridesCache = [];
    if (!isset($userOverridesCache[$user_id])) {
        $cntStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ?");
        $cntStmt->execute([$user_id]);
        $userOverridesCache[$user_id] = ((int)$cntStmt->fetchColumn() > 0);
    }
    $hasOverrides = $userOverridesCache[$user_id];

    if ($hasOverrides) {
        // In Custom Overrides mode: check user_permissions only
        if (!empty($module)) {
            $stmt = $conn->prepare("
                SELECT $column 
                FROM user_permissions 
                WHERE user_id = ? AND LOWER(module) = LOWER(?) AND page_name IN ($placeholders)
                LIMIT 1
            ");
            $stmt->execute(array_merge([$user_id, $module], $pageCandidates));
            $result = $stmt->fetch();
            if ($result !== false && isset($result[$column])) {
                return (bool)$result[$column];
            }
        }

        $stmt = $conn->prepare("
            SELECT $column 
            FROM user_permissions 
            WHERE user_id = ? AND page_name IN ($placeholders)
            LIMIT 1
        ");
        $stmt->execute(array_merge([$user_id], $pageCandidates));
        $result = $stmt->fetch();
        if ($result !== false && isset($result[$column])) {
            return (bool)$result[$column];
        }

        // In Custom Overrides mode, pages not in user_permissions are denied
        return false;
    }

    // Role Defaults mode: check role_permissions for user's role
    $stmt = $conn->prepare("SELECT role_id FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $role_id = (int)$stmt->fetchColumn();

    if ($role_id > 0) {
        if (!empty($module)) {
            $stmt = $conn->prepare("
                SELECT $column 
                FROM role_permissions 
                WHERE role_id = ? AND LOWER(module) = LOWER(?) AND page_name IN ($placeholders)
                LIMIT 1
            ");
            $stmt->execute(array_merge([$role_id, $module], $pageCandidates));
            $role_result = $stmt->fetch();
            if ($role_result !== false && isset($role_result[$column])) {
                return (bool)$role_result[$column];
            }
        }

        $stmt = $conn->prepare("
            SELECT $column 
            FROM role_permissions 
            WHERE role_id = ? AND page_name IN ($placeholders)
            LIMIT 1
        ");
        $stmt->execute(array_merge([$role_id], $pageCandidates));
        $role_result = $stmt->fetch();
        if ($role_result !== false && isset($role_result[$column])) {
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
