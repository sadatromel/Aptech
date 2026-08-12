<?php
/**
 * User Registration Page
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../core/legacy_helpers.php';

$app_base_url = rtrim(dirname(ASSETS_URL), '/');

// Get database connection
$conn = get_db_connection();

// Check if user has permission for registration (admin or manager)
if (!isset($_SESSION['user_id'])) {
    // For non-logged in users, show simple registration form
    $simple_registration = true;
} else {
    // Check user role
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user_role = strtolower($stmt->fetchColumn() ?: '');
    
    // Only allow admin/managers to register new users
    if (!in_array($user_role, ['admin', 'manager', 'super admin', 'superadmin'])) {
        $_SESSION['error'] = 'You do not have permission to register new users';
        header('Location: ' . $app_base_url . '/index.php?page=dashboard');
        exit();
    }
    $simple_registration = false;
}

// Get all users for display
$all_users = [];
if (!$simple_registration) {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.created_at, u.status, r.role_name, r.role_id
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.role_id 
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $all_users = $stmt->fetchAll();
}

// Get all roles for dropdown
$all_roles = [];
if (!$simple_registration) {
    $stmt = $conn->prepare("SELECT role_id, role_name FROM roles ORDER BY role_name");
    $stmt->execute();
    $all_roles = $stmt->fetchAll();
}

$errors = [];
$success = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'edit_user') {
        // Edit user logic
        $user_id = intval($_POST['user_id']);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $role_id = intval($_POST['role_id'] ?? 3);
        
        // Validation
        $errors = [];
        if (empty($username)) $errors[] = "Username is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        
        // Check if username/email exists for other users
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
            $stmt->execute([$username, $email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = "Username or email already exists";
            }
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role_id = ? WHERE user_id = ?");
            if ($stmt->execute([$username, $email, $first_name, $last_name, $role_id, $user_id])) {
                $_SESSION['success'] = "User updated successfully!";
            } else {
                $errors[] = "Failed to update user";
            }
        }
    } elseif ($action === 'reset_password') {
        // Reset password logic
        $user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'] ?? '';
        
        if (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?"); 
            
            // Removed dynamic column check, using password_hash

            if ($stmt->execute([$hashed_password, $user_id])) {
                $_SESSION['success'] = "Password reset successfully!";
            } else {
                $errors[] = "Failed to reset password";
            }
        }
    } elseif ($action === 'toggle_user_status') {
        // Deactivate/activate user logic
        $user_id = intval($_POST['user_id']);
        $status = $_POST['status'] ?? 'inactive';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        if ($stmt->execute([$status, $user_id])) {
            $_SESSION['success'] = "User " . ($status == 'active' ? 'activated' : 'deactivated') . " successfully!";
        } else {
            $errors[] = "Failed to update user status";
        }
    } else {
        // Original registration logic
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        
        // Validation
        if (empty($username)) {
            $errors[] = "Username is required";
        }
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        // Check if username already exists
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = "Username or email already exists";
            }
        }
        
        // Create user if no errors
        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            

            // Set role based on who is registering
            $role_id = $simple_registration ? 2 : 3; // 2=Employee for self-registration, 3=User for admin registration
            
            // Use confirmed column name
            $password_col_name = 'password_hash';

            $stmt = $conn->prepare("INSERT INTO users (username, email, $password_col_name, first_name, last_name, role_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $role_id])) {
                $success = true;
                if ($simple_registration) {
                    $_SESSION['success_message'] = "Registration successful! Please login.";
                } else {
                    $_SESSION['success'] = "User registered successfully!";
                }
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}

// Redirects
if ($success && $simple_registration) {
    echo '<script>setTimeout(() => { window.location.href = "' . $app_base_url . '/index.php?page=login"; }, 2000);</script>';
} elseif ($success && !$simple_registration) {
    echo '<script>setTimeout(() => { window.location.href = "' . $app_base_url . '/index.php?page=register"; }, 1000);</script>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Aptech ERP</title>
    <link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/theme.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dashboard.css" rel="stylesheet">
    
    <script>
        // Init theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>

    <style>
        :root {
            --card-border-radius: 12px;
        }

        body {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }

        .main-content {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .premium-card {
            background: var(--bg-primary);
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header-styled {
            background: transparent;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header-styled h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body-styled {
            padding: 2rem;
        }

        /* Nav Tabs Styling */
        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            border-bottom: 2px solid transparent;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary);
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
            border-bottom-color: var(--primary);
        }
        
        /* Table Styling */
        .table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            border-bottom-width: 1px;
            padding: 1rem 0.75rem;
        }
        
        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
            border-bottom-color: var(--border-color);
        }
        
        /* User Avatar */
        .avatar-initials {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-control, .form-select {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-primary);
            padding: 0.6rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--bg-primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        /* Status Badge */
        .badge-status {
            padding: 0.35em 0.8em;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        /* Modals */
        .modal-content {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .modal-header, .modal-footer {
            border-color: var(--border-color);
        }
        
        .btn-close {
            filter: var(--btn-close-filter);
        }
    </style>
</head>
<body>
    <?php if (!$simple_registration): ?>
        <?php require_once __DIR__ . '/../includes/header.php'; ?>
    <?php endif; ?>

    <div class="container-fluid">
        <div class="row">
            <?php if (!$simple_registration): ?>
                <?php include_once BASEPATH . '/includes/sidebar.php'; ?>
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-0">
            <?php else: ?>
                <main class="col-12 px-0">
            <?php endif; ?>

                <div class="main-content">
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4">
                            <?php foreach ($errors as $error): ?>
                                <div><i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($simple_registration): ?>
                        <div class="text-center mb-5">
                            <h1 class="display-5 fw-bold text-primary">Join Aptech ERP</h1>
                            <p class="text-secondary">Create your account to get started</p>
                        </div>
                    <?php endif; ?>

                    <div class="premium-card">
                        <?php if (!$simple_registration): ?>
                        <div class="card-header-styled">
                            <h2>
                                <i class="fas fa-users-cog text-primary"></i>
                                User Management
                            </h2>
                            <span class="badge bg-primary rounded-pill"><?= count($all_users) ?> Users</span>
                        </div>
                        <?php endif; ?>

                        <div class="card-body-styled">
                            
                            <?php if (!$simple_registration): ?>
                            <!-- Navigation Tabs -->
                            <ul class="nav nav-tabs" id="userTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">
                                        <i class="fas fa-user-plus me-2"></i>Register New
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                                        <i class="fas fa-list-ul me-2"></i>All Users
                                    </button>
                                </li>
                            </ul>
                            <?php endif; ?>

                            <div class="tab-content" id="userTabsContent">
                                <!-- Register Form Tab -->
                                <div class="tab-pane fade show active" id="register" role="tabpanel">
                                    <form method="POST" class="row g-4">
                                        <div class="col-12">
                                            <h5 class="mb-3 text-secondary">Account Information</h5>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Username <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-transparent border-end-0 text-secondary"><i class="fas fa-user"></i></span>
                                                <input type="text" class="form-control border-start-0 ps-0" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required placeholder="jdoe">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-transparent border-end-0 text-secondary"><i class="fas fa-envelope"></i></span>
                                                <input type="email" class="form-control border-start-0 ps-0" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="john@example.com">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                                        </div>

                                        <div class="col-12 mt-4">
                                            <h5 class="mb-3 text-secondary">Security</h5>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-transparent border-end-0 text-secondary"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control border-start-0 ps-0" name="password" required placeholder="••••••••">
                                            </div>
                                            <small class="text-muted">Min. 6 characters</small>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-transparent border-end-0 text-secondary"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control border-start-0 ps-0" name="confirm_password" required placeholder="••••••••">
                                            </div>
                                        </div>

                                        <div class="col-12 mt-4 text-end">
                                            <?php if ($simple_registration): ?>
                                                <a href="<?= $app_base_url ?>/index.php?page=login" class="text-decoration-none me-3">Already have an account? Login</a>
                                            <?php endif; ?>
                                            <button type="submit" class="btn btn-primary px-4">
                                                <i class="fas fa-user-plus me-2"></i>Create Account
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <?php if (!$simple_registration): ?>
                                <!-- User List Tab -->
                                <div class="tab-pane fade" id="users" role="tabpanel">
                                    <?php if (empty($all_users)): ?>
                                        <div class="text-center py-5">
                                            <div class="mb-3 text-muted opacity-25">
                                                <i class="fas fa-users fa-4x"></i>
                                            </div>
                                            <h5>No Users Found</h5>
                                            <p class="text-secondary">Get started by registering a new user.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>User Profile</th>
                                                        <th>Role</th>
                                                        <th>Status</th>
                                                        <th>Created</th>
                                                        <th class="text-end">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($all_users as $user): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <div class="avatar-initials">
                                                                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                                                    </div>
                                                                    <div>
                                                                        <div class="fw-semibold text-primary"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                                                        <div class="small text-secondary"><?= htmlspecialchars($user['username']) ?> &bull; <?= htmlspecialchars($user['email']) ?></div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-secondary"><?= ucfirst($user['role_name'] ?? 'User') ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="badge-status bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>-subtle text-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                                                                    <?= ucfirst($user['status'] ?? 'inactive') ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-secondary small">
                                                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                                            </td>
                                                            <td class="text-end">
                                                                <div class="btn-group">
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                            onclick="editUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['first_name']) ?>', '<?= htmlspecialchars($user['last_name']) ?>', <?= $user['role_id'] ?? 3 ?>)" 
                                                                            title="Edit User">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                            onclick="resetPassword(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>')" 
                                                                            title="Reset Password">
                                                                        <i class="fas fa-key"></i>
                                                                    </button>
                                                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                                        <button type="button" class="btn btn-sm btn-outline-<?= $user['status'] === 'active' ? 'danger' : 'success' ?>" 
                                                                                onclick="toggleUserStatus(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['first_name']) ?>', '<?= $user['status'] ?? 'active' ?>')" 
                                                                                title="<?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?> User">
                                                                            <i class="fas fa-<?= $user['status'] === 'active' ? 'ban' : 'check' ?>"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role_id">
                                <?php foreach ($all_roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars(ucfirst($role['role_name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Reset Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        
                        <div class="alert alert-warning">
                            Resetting password for: <strong id="reset_user_name"></strong>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" minlength="6" required placeholder="New password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Toggle Status Modal -->
    <div class="modal fade" id="toggleStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="status_modal_title">Confirm Action</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="toggle_user_status">
                        <input type="hidden" name="user_id" id="status_user_id">
                        <input type="hidden" name="status" id="status_value">
                        
                        <p id="status_message" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="status_confirm_btn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/theme.js"></script>

    <script>
        // Tab Persistence
        document.addEventListener('DOMContentLoaded', function() {
            // Check for saved tab or URL hash
            const hash = window.location.hash || '#register';
            const tabTrigger = document.querySelector(`button[data-bs-target="${hash}"]`);
            if (tabTrigger) {
                new bootstrap.Tab(tabTrigger).show();
            }
            
            // Listen for tab changes
            const tabElsd = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabElsd.forEach(tabEl => {
                tabEl.addEventListener('shown.bs.tab', event => {
                    const target = event.target.getAttribute('data-bs-target');
                    history.replaceState(null, null, target);
                });
            });
        });

        function editUser(userId, username, email, firstName, lastName, roleId) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_role').value = roleId;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        function resetPassword(userId, userName) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_name').textContent = userName;
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }
        
        function toggleUserStatus(userId, userName, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            
            document.getElementById('status_user_id').value = userId;
            document.getElementById('status_value').value = newStatus;
            document.getElementById('status_modal_title').textContent = action.charAt(0).toUpperCase() + action.slice(1) + ' User';
            document.getElementById('status_message').textContent = `Are you sure you want to ${action} ${userName}?`;
            
            const btn = document.getElementById('status_confirm_btn');
            btn.className = `btn btn-${newStatus === 'active' ? 'success' : 'danger'}`;
            btn.textContent = action.charAt(0).toUpperCase() + action.slice(1);
            
            new bootstrap.Modal(document.getElementById('toggleStatusModal')).show();
        }
    </script>
</body>
</html>
