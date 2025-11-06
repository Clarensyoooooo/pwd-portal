<?php
require_once 'config.php';
requireAdminLogin($pdo);

// Only check view permission, not strict requirement
$canView = hasPermission($pdo, 'users.view');
$canCreate = hasPermission($pdo, 'users.create');
$canEdit = hasPermission($pdo, 'users.edit');
$canDelete = hasPermission($pdo, 'users.delete');
$canManageRoles = hasPermission($pdo, 'users.roles');

$admin = getCurrentAdmin($pdo);

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    requirePermission($pdo, 'users.view'); // Or a specific export permission

    try {
        // Get all users with roles (no pagination)
        $stmt = $pdo->query("
            SELECT au.*, ar.display_name as role_name
            FROM admin_users au
            LEFT JOIN admin_roles ar ON au.role_id = ar.id
            ORDER BY au.created_at DESC
        ");
        $users_to_export = $stmt->fetchAll();

        // Set headers for CSV download
        $filename = 'admin_users_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8 Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write CSV header
        fputcsv($output, [
            'ID', 'Username', 'Full Name', 'Email', 'Role', 
            'Status', 'Last Login', 'Created At'
        ]);

        // Write data rows
        foreach ($users_to_export as $user) {
            fputcsv($output, [
                $user['id'],
                $user['username'],
                $user['full_name'],
                $user['email'],
                $user['role_name'] ?? 'No Role',
                $user['is_active'] ? 'Active' : 'Inactive',
                $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : 'Never',
                $user['created_at']
            ]);
        }

        fclose($output);

        logAdminActivity($pdo, 'export', 'users', 'admin_users_csv', null, ['user_count' => count($users_to_export)]);
        exit;

    } catch (PDOException $e) {
        // Log error or display a user-friendly message
        error_log("CSV Export Error: " . $e->getMessage());
        die("An error occurred during CSV export. Please try again later.");
    }
}

// Handle PDF Export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    requirePermission($pdo, 'users.view'); // Or a specific export permission
    ob_start();

    try {
        // Get all users with roles (no pagination)
        $stmt = $pdo->query("
            SELECT au.id, au.username, au.full_name, au.email, au.is_active, au.last_login, au.created_at, ar.display_name as role_name
            FROM admin_users au
            LEFT JOIN admin_roles ar ON au.role_id = ar.id
            ORDER BY au.created_at DESC
        ");
        $users_to_export = $stmt->fetchAll();

        // --- PDF Generation ---
        require_once '../vendor/autoload.php';

        $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator('PWD Portal');
        $pdf->SetAuthor($admin['full_name']); // Assuming $admin is available
        $pdf->SetTitle('Admin Users Report - ' . date('Y-m-d'));
        $pdf->SetSubject('List of Admin Users');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 10);

        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'Admin Users Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
        $pdf->Ln(5);

        // Summary
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Report Overview', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(35, 6, 'Total Users:', 0, 0, 'L');
        $pdf->Cell(0, 6, count($users_to_export), 0, 1, 'L');
        $pdf->Ln(5);

        // Data Table (Landscape width ~277mm)
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(44, 90, 160);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(15, 7, 'ID', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Username', 1, 0, 'L', true);
        $pdf->Cell(50, 7, 'Full Name', 1, 0, 'L', true);
        $pdf->Cell(55, 7, 'Email', 1, 0, 'L', true);
        $pdf->Cell(40, 7, 'Role', 1, 0, 'L', true);
        $pdf->Cell(20, 7, 'Status', 1, 0, 'C', true);
        $pdf->Cell(57, 7, 'Last Login', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(248, 250, 252);
        $fill = false;

        if (empty($users_to_export)) {
            $pdf->Cell(277, 10, 'No users found.', 1, 1, 'C', $fill);
        } else {
            foreach ($users_to_export as $user) {
                $status = $user['is_active'] ? 'Active' : 'Inactive';
                $last_login = $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never';
                $role = $user['role_name'] ?? 'No Role';

                $pdf->Cell(15, 6, $user['id'], 1, 0, 'C', $fill);
                $pdf->Cell(40, 6, $user['username'], 1, 0, 'L', $fill);
                $pdf->Cell(50, 6, $user['full_name'], 1, 0, 'L', $fill);
                $pdf->Cell(55, 6, $user['email'], 1, 0, 'L', $fill);
                $pdf->Cell(40, 6, $role, 1, 0, 'L', $fill);
                $pdf->Cell(20, 6, $status, 1, 0, 'C', $fill);
                $pdf->Cell(57, 6, $last_login, 1, 1, 'L', $fill);
                $fill = !$fill;
            }
        }

        // Log the export
        logAdminActivity($pdo, 'export', 'users', 'admin_users_pdf', null, ['user_count' => count($users_to_export)]);

        ob_end_clean();

        $filename = 'admin_users_report_' . date('Y-m-d_H-i-s') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        $pdf->Output($filename, 'D');
        exit;

    } catch (Exception $e) {
        ob_end_clean();
        error_log("PDF Export Error: " . $e->getMessage());
        die("An error occurred during PDF export. Please try again later.");
    }
}

// If user can't view, redirect to dashboard with message
if (!$canView) {
    $_SESSION['error'] = 'You do not have permission to access user management.';
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            if ($canCreate) {
                createUser($pdo);
            } else {
                $_SESSION['error'] = 'You do not have permission to create users.';
            }
            break;
        case 'edit_user':
            if ($canEdit) {
                editUser($pdo);
            } else {
                $_SESSION['error'] = 'You do not have permission to edit users.';
            }
            break;
        case 'delete_user':
            if ($canDelete) {
                deleteUser($pdo);
            } else {
                $_SESSION['error'] = 'You do not have permission to delete users.';
            }
            break;
        case 'create_role':
            if ($canManageRoles) {
                createRole($pdo);
            } else {
                $_SESSION['error'] = 'You do not have permission to create roles.';
            }
            break;
       
        case 'delete_role':
            if ($canManageRoles) {
                deleteRole($pdo);
            } else {
                $_SESSION['error'] = 'You do not have permission to delete roles.';
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: users.php');
    exit();
}

// Get users with roles
try {
    $stmt = $pdo->query("
        SELECT au.*, ar.display_name as role_name, ar.name as role_key
        FROM admin_users au
        LEFT JOIN admin_roles ar ON au.role_id = ar.id
        ORDER BY au.created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $_SESSION['error'] = 'Error loading users: ' . $e->getMessage();
}

// Get roles
try {
    $stmt = $pdo->query("SELECT * FROM admin_roles ORDER BY name");
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    $roles = [];
}

// Get permissions grouped by module
try {
    $stmt = $pdo->query("
        SELECT * FROM admin_permissions 
        ORDER BY module, name
    ");
    $all_permissions = $stmt->fetchAll();
    $permissions_by_module = [];
    foreach ($all_permissions as $permission) {
        $permissions_by_module[$permission['module']][] = $permission;
    }
} catch (PDOException $e) {
    $permissions_by_module = [];
}

function createUser($pdo) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $role_id = !empty($_POST['role_id']) ? intval($_POST['role_id']) : null;
    
    // Validate input
    if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        $_SESSION['error'] = 'All fields are required.';
        return;
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters long.';
        return;
    }
    
    // Check if username or email already exists
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Username or email already exists.';
            return;
        }
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO admin_users (username, email, full_name, password_hash, role_id, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $username,
            $email,
            $full_name,
            password_hash($password, PASSWORD_DEFAULT),
            $role_id
        ]);
        
        logAdminActivity($pdo, 'create', 'users', 'admin_user', $pdo->lastInsertId(), [
            'username' => $username,
            'email' => $email
        ]);
        
        $_SESSION['success'] = 'User created successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to create user: ' . $e->getMessage();
    }
}

function editUser($pdo) {
    $user_id = intval($_POST['user_id']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $role_id = !empty($_POST['role_id']) ? intval($_POST['role_id']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {

        // --- ADD THIS PROTECTION BLOCK ---
        $stmt = $pdo->prepare("
            SELECT ar.name as role_name 
            FROM admin_users au
            LEFT JOIN admin_roles ar ON au.role_id = ar.id
            WHERE au.id = ?
        ");
        $stmt->execute([$user_id]);
        $user_being_edited = $stmt->fetch();
        
        if ($user_being_edited && $user_being_edited['role_name'] === 'super_admin') {
            // Check if the current user is trying to deactivate the Super Admin
            if ($is_active == 0) {
                $_SESSION['error'] = 'You cannot deactivate the Super Administrator account.';
                return;
            }
            
            // Check if the current user is trying to change the Super Admin's role
            // We need to get the super_admin role's ID to be sure
            $sa_role_stmt = $pdo->query("SELECT id FROM admin_roles WHERE name = 'super_admin' LIMIT 1");
            $sa_role_id = $sa_role_stmt->fetchColumn();

            if ($sa_role_id && $role_id != $sa_role_id) {
                 $_SESSION['error'] = 'You cannot change the role of the Super Administrator.';
                 return;
            }
        }
        // --- END OF PROTECTION BLOCK ---

        // Check if username or email is taken by another user
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM admin_users 
            WHERE (username = ? OR email = ?) AND id != ?
        ");
        $stmt->execute([$username, $email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Username or email already exists.';
            return;
        }
        
        // Update user
        $stmt = $pdo->prepare("
            UPDATE admin_users 
            SET username = ?, email = ?, full_name = ?, role_id = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $username,
            $email,
            $full_name,
            $role_id,
            $is_active,
            $user_id
        ]);
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                $_SESSION['error'] = 'Password must be at least 6 characters long.';
                return;
            }
            $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $user_id]);
        }
        
        logAdminActivity($pdo, 'edit', 'users', 'admin_user', $user_id, [
            'username' => $username,
            'email' => $email
        ]);
        
        $_SESSION['success'] = 'User updated successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to update user: ' . $e->getMessage();
    }
}

function deleteUser($pdo) {
    $user_id = intval($_POST['user_id']);
    
    // Don't allow deleting own account
    if ($user_id == $_SESSION['admin_user_id']) {
        $_SESSION['error'] = 'You cannot delete your own account.';
        return;
    }

    // Add this new check
if ($user_id == 1) { // <-- This protects the user with ID 1
    $_SESSION['error'] = 'This user account cannot be deleted.';
    return;
}
    
    try {
        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        logAdminActivity($pdo, 'delete', 'users', 'admin_user', $user_id);
        
        $_SESSION['success'] = 'User deleted successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete user: ' . $e->getMessage();
    }
}

function createRole($pdo) {
    $name = trim($_POST['role_name']);
    $display_name = trim($_POST['display_name']);
    $description = trim($_POST['description']);
    $permissions = $_POST['permissions'] ?? [];
    
    // Validate input
    if (empty($name) || empty($display_name)) {
        $_SESSION['error'] = 'Role name and display name are required.';
        return;
    }
    
    // Convert name to snake_case
    $name = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
    
    try {
        // Check if role name already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_roles WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'A role with this name already exists.';
            return;
        }
        
        $pdo->beginTransaction();
        
        // Insert new role
        $stmt = $pdo->prepare("
            INSERT INTO admin_roles (name, display_name, description)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$name, $display_name, $description]);
        $role_id = $pdo->lastInsertId();
        
        // Insert permissions
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissions as $permission_id) {
                $permission_id = intval($permission_id);
                if ($permission_id > 0) {
                    $stmt->execute([$role_id, $permission_id]);
                }
            }
        }
        
        logAdminActivity($pdo, 'create', 'roles', 'admin_role', $role_id, [
            'name' => $name,
            'display_name' => $display_name,
            'permissions_count' => count($permissions)
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = 'Role created successfully.';
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Failed to create role: ' . $e->getMessage();
        error_log("Role creation error: " . $e->getMessage());
    }
}

function updateRole($pdo) {
    $role_id = intval($_POST['role_id']);
    $display_name = trim($_POST['display_name']);
    $description = trim($_POST['description']);
    $permissions = $_POST['permissions'] ?? [];
    
    try {
        // Check if it's the super_admin role
        $stmt = $pdo->prepare("SELECT name FROM admin_roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $role = $stmt->fetch();
        
        if (!$role) {
            $_SESSION['error'] = 'Role not found.';
            return;
        }
        
        if ($role['name'] === 'super_admin') {
            $_SESSION['error'] = 'Cannot modify super administrator role.';
            return;
        }
        
        $pdo->beginTransaction();
        
        // Update role details
        $stmt = $pdo->prepare("UPDATE admin_roles SET display_name = ?, description = ? WHERE id = ?");
        $stmt->execute([$display_name, $description, $role_id]);
        
        // Delete existing permissions
        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        
        // Insert new permissions
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissions as $permission_id) {
                $permission_id = intval($permission_id);
                if ($permission_id > 0) {
                    $stmt->execute([$role_id, $permission_id]);
                }
            }
        }
        
        logAdminActivity($pdo, 'edit', 'roles', 'admin_role', $role_id, [
            'display_name' => $display_name,
            'permissions_count' => count($permissions)
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = 'Role updated successfully. Changes may require re-login to take full effect.';
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Failed to update role: ' . $e->getMessage();
        error_log("Role update error: " . $e->getMessage());
    }
}

function deleteRole($pdo) {
    $role_id = intval($_POST['role_id']);
    
    try {
        // Check if it's a protected role
        $stmt = $pdo->prepare("SELECT name FROM admin_roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $role = $stmt->fetch();
        
        if (!$role) {
            $_SESSION['error'] = 'Role not found.';
            return;
        }
        
        if ($role['name'] === 'super_admin') {
            $_SESSION['error'] = 'Cannot delete super administrator role.';
            return;
        }
        
        // Check if any users have this role
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role_id = ?");
        $stmt->execute([$role_id]);
        $user_count = $stmt->fetchColumn();
        
        if ($user_count > 0) {
            $_SESSION['error'] = "Cannot delete role. {$user_count} user(s) are currently assigned to this role.";
            return;
        }
        
        // Delete role (permissions will be deleted automatically due to CASCADE)
        $stmt = $pdo->prepare("DELETE FROM admin_roles WHERE id = ?");
        $stmt->execute([$role_id]);
        
        logAdminActivity($pdo, 'delete', 'roles', 'admin_role', $role_id);
        
        $_SESSION['success'] = 'Role deleted successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete role: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <div class="page-actions">
                <?php if ($canCreate): ?>
                <button onclick="showModal('createUserModal')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add User
                </button>
                <?php endif; ?>
                <a href="?export=csv" class="btn btn-outline">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <a href="?export=pdf" class="btn btn-outline">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
         
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Admin Users</h3>
                <div class="card-actions">
                    <input type="text" id="userSearch" placeholder="Search users..." class="form-control" style="width: 250px;">
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['admin_user_id']): ?>
                                        <span class="badge badge-info">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['role_name']): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($user['role_name']); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">No Role</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($canEdit): ?>
                                        <button onclick='editUser(<?php echo json_encode([
                                            "id" => $user["id"],
                                            "username" => $user["username"],
                                            "email" => $user["email"],
                                            "full_name" => $user["full_name"],
                                            "role_id" => $user["role_id"],
                                            "is_active" => $user["is_active"]
                                        ]); ?>)' class="btn btn-sm btn-outline" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($canDelete && $user['id'] != $_SESSION['admin_user_id']): ?>
                                        <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="btn btn-sm btn-danger" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
          
        <?php if ($canManageRoles && !empty($roles)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <div>
                    <h3><i class="fas fa-user-tag"></i> Roles & Permissions</h3>
                    <p class="card-subtitle">Configure role permissions to control access across the admin panel</p>
                </div>
                <div class="card-actions">
                    <button onclick="showModal('createRoleModal')" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Role
                    </button>
                </div>
            </div>
            <div class="card-content">
                <div class="roles-grid">
                    <?php foreach ($roles as $role): ?>
                    <div class="role-card">
                        <div class="role-header">
                            <div class="role-title-section">
                                <h4><?php echo htmlspecialchars($role['display_name']); ?></h4>
                                <?php if ($role['name'] === 'super_admin'): ?>
                                    <span class="badge badge-warning">Protected</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($role['name'] !== 'super_admin'): ?>
                            <div class="role-actions">
                                <button onclick="loadAndEditRole(<?php echo $role['id']; ?>)" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['display_name']); ?>')" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <p class="role-description"><?php echo htmlspecialchars($role['description']); ?></p>
                        
                       <?php
// Get role permissions
try {
    if ($role['name'] === 'super_admin') {
        // Super Admin has ALL permissions, so let's count them all
        $stmt = $pdo->query("SELECT display_name, module FROM admin_permissions");
        $role_permissions = $stmt->fetchAll();
    } else {
        // Other roles: just count their linked permissions
        $stmt = $pdo->prepare("
            SELECT ap.display_name, ap.module
            FROM role_permissions rp
            JOIN admin_permissions ap ON rp.permission_id = ap.id
            WHERE rp.role_id = ?
            ORDER BY ap.module, ap.display_name
        ");
        $stmt->execute([$role['id']]);
        $role_permissions = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $role_permissions = [];
}
?>
                        
                        <div class="role-permissions">
                            <div class="permissions-header">
                                <strong><i class="fas fa-key"></i> Permissions (<?php echo count($role_permissions); ?>)</strong>
                            </div>
                            <div class="permission-tags">
                                <?php if (empty($role_permissions)): ?>
                                    <span class="no-permissions">No permissions assigned</span>
                                <?php else: ?>
                                    <?php foreach (array_slice($role_permissions, 0, 8) as $perm): ?>
                                        <span class="permission-tag"><?php echo htmlspecialchars($perm['display_name']); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($role_permissions) > 8): ?>
                                        <span class="permission-tag more-tag">+<?php echo count($role_permissions) - 8; ?> more</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
     
    <div id="createUserModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <h3>Add New User</h3>
                        <p class="modal-subtitle">Create a new admin user account</p>
                    </div>
                </div>
                <button onclick="closeModal('createUserModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i> Username *
                        </label>
                        <input type="text" name="username" id="username" class="form-control" required placeholder="Enter username">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email Address *
                        </label>
                        <input type="email" name="email" id="email" class="form-control" required placeholder="user@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">
                            <i class="fas fa-id-card"></i> Full Name *
                        </label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required placeholder="John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password *
                        </label>
                        <input type="password" name="password" id="password" class="form-control" required placeholder="Minimum 6 characters" minlength="6">
                        <small class="form-text">Password must be at least 6 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_user_role_id">
                            <i class="fas fa-user-tag"></i> Role
                        </label>
                        <select name="role_id" id="create_user_role_id" class="form-control">
                            <option value="">Select Role (Optional)</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Assign a role to grant specific permissions</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('createUserModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
    
      
    <div id="editUserModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div>
                        <h3>Edit User</h3>
                        <p class="modal-subtitle">Update user information and permissions</p>
                    </div>
                </div>
                <button onclick="closeModal('editUserModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_username">
                            <i class="fas fa-user"></i> Username *
                        </label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">
                            <i class="fas fa-envelope"></i> Email Address *
                        </label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_full_name">
                            <i class="fas fa-id-card"></i> Full Name *
                        </label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep current" minlength="6">
                        <small class="form-text">Only fill this if you want to change the password</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_user_role_id">
                            <i class="fas fa-user-tag"></i> Role
                        </label>
                        <select name="role_id" id="edit_user_role_id" class="form-control">
                            <option value="">No Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                            <span>Active User</span>
                        </label>
                        <small class="form-text">Inactive users cannot log in</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editUserModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
    
      
    <div id="createRoleModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h3>Create New Role</h3>
                        <p class="modal-subtitle">Define a custom role with specific permissions</p>
                    </div>
                </div>
                <button onclick="closeModal('createRoleModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST" id="createRoleForm">
                <input type="hidden" name="action" value="create_role">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="role_name">
                            <i class="fas fa-tag"></i> Role Identifier *
                        </label>
                        <input type="text" name="role_name" id="role_name" class="form-control" required placeholder="e.g., data_entry_staff">
                        <small class="form-text">This will be converted to lowercase and underscores (e.g., "Data Entry" becomes "data_entry")</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="display_name">
                            <i class="fas fa-signature"></i> Display Name *
                        </label>
                        <input type="text" name="display_name" id="display_name" class="form-control" required placeholder="e.g., Data Entry Staff">
                        <small class="form-text">This is the name shown in the interface</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Describe what this role is for..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-key"></i> Permissions 
                            <span class="permission-counter">(<span id="createSelectedCount">0</span> selected)</span>
                        </label>
                        <div class="permissions-actions">
                            <button type="button" class="btn btn-sm btn-outline" onclick="selectAllPermissionsCreate()">
                                <i class="fas fa-check-double"></i> Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="deselectAllPermissionsCreate()">
                                <i class="fas fa-times"></i> Deselect All
                            </button>
                        </div>
                        <div class="permissions-grid" id="createPermissionsGrid">
                            <?php foreach ($permissions_by_module as $module => $permissions): ?>
                            <div class="permission-module">
                                <h5 class="module-title">
                                    <label class="module-label">
                                        <input type="checkbox" class="module-checkbox-create" data-module="<?php echo $module; ?>" onchange="toggleModulePermissionsCreate(this, '<?php echo $module; ?>')">
                                        <i class="fas fa-folder"></i> <?php echo ucfirst($module); ?>
                                    </label>
                                </h5>
                                <div class="permission-list">
                                    <?php foreach ($permissions as $perm): ?>
                                    <label class="permission-checkbox">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="<?php echo $perm['id']; ?>" 
                                               class="permission-input-create" 
                                               data-module="<?php echo $module; ?>"
                                               onchange="updatePermissionCountCreate()">
                                        <span class="permission-label"><?php echo htmlspecialchars($perm['display_name']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('createRoleModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Create Role
                    </button>
                </div>
            </form>
        </div>
    </div>
    
     
    <div id="editRoleModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h3>Edit Role</h3>
                        <p class="modal-subtitle">Configure role permissions</p>
                    </div>
                </div>
                <button onclick="closeModal('editRoleModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST" id="editRoleForm" onsubmit="return saveRole(event)">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="role_id" id="edit_role_form_id">
                <div class="modal-body" id="editRoleModalBody">
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p>Loading role data...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editRoleModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveRoleBtn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="assets/admin.js"></script>
    <script>
        // Search functionality
        document.getElementById('userSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Edit user function
        function editUser(userData) {
            document.getElementById('edit_user_id').value = userData.id;
            document.getElementById('edit_username').value = userData.username;
            document.getElementById('edit_email').value = userData.email;
            document.getElementById('edit_full_name').value = userData.full_name;
            document.getElementById('edit_user_role_id').value = userData.role_id || '';
            document.getElementById('edit_is_active').checked = userData.is_active == 1;
            document.getElementById('edit_password').value = '';
            
            showModal('editUserModal');
        }
        
        // Delete user function
        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Delete role function
        function deleteRole(roleId, roleName) {
            if (confirm(`Are you sure you want to delete the role "${roleName}"? This action cannot be undone.\n\nNote: Users with this role will have their role set to null.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_role">
                    <input type="hidden" name="role_id" value="${roleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Load and edit role function
        function loadAndEditRole(roleId) {
            showModal('editRoleModal');
            
            // Fetch role data
            fetch(`api/users.php?action=get_role&id=${roleId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        populateRoleForm(data.role);
                    } else {
                        showNotification('Failed to load role data: ' + data.message, 'error');
                        closeModal('editRoleModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to load role data. Please try again.', 'error');
                    closeModal('editRoleModal');
                });
        }
        
        function populateRoleForm(roleData) {
            const modalBody = document.getElementById('editRoleModalBody');
            
            modalBody.innerHTML = `
                <div class="form-group">
                    <label for="role_display_name">
                        <i class="fas fa-tag"></i> Role Name *
                    </label>
                    <input type="text" name="display_name" id="role_display_name" class="form-control" required value="${escapeHtml(roleData.display_name)}">
                </div>
                
                <div class="form-group">
                    <label for="role_description">
                        <i class="fas fa-align-left"></i> Description
                    </label>
                    <textarea name="description" id="role_description" class="form-control" rows="3">${escapeHtml(roleData.description || '')}</textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-key"></i> Permissions 
                        <span class="permission-counter">(<span id="selectedCount">0</span> selected)</span>
                    </label>
                    <div class="permissions-actions">
                        <button type="button" class="btn btn-sm btn-outline" onclick="selectAllPermissions()">
                            <i class="fas fa-check-double"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="deselectAllPermissions()">
                            <i class="fas fa-times"></i> Deselect All
                        </button>
                    </div>
                    <div class="permissions-grid">
                        ${generatePermissionsHTML(roleData.permissions)}
                    </div>
                </div>
            `;
            
            document.getElementById('edit_role_form_id').value = roleData.id;
            updatePermissionCount();
        }
        
        function generatePermissionsHTML(selectedPermissions) {
            const permissionsByModule = <?php echo json_encode($permissions_by_module); ?>;
            let html = '';
            
            for (const [module, permissions] of Object.entries(permissionsByModule)) {
                html += `
                    <div class="permission-module">
                        <h5 class="module-title">
                            <label class="module-label">
                                <input type="checkbox" class="module-checkbox" data-module="${module}" onchange="toggleModulePermissions(this, '${module}')">
                                <i class="fas fa-folder"></i> ${module.charAt(0).toUpperCase() + module.slice(1)}
                            </label>
                        </h5>
                        <div class="permission-list">
                `;
                
                permissions.forEach(perm => {
                    const isChecked = selectedPermissions.includes(parseInt(perm.id));
                    html += `
                        <label class="permission-checkbox">
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="${perm.id}" 
                                   class="permission-input" 
                                   data-module="${module}"
                                   onchange="updatePermissionCount()"
                                   ${isChecked ? 'checked' : ''}>
                            <span class="permission-label">${escapeHtml(perm.display_name)}</span>
                        </label>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            return html;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Save role via AJAX
        function saveRole(event) {
            event.preventDefault();
            
            const form = document.getElementById('editRoleForm');
            const formData = new FormData(form);
            const saveBtn = document.getElementById('saveRoleBtn');
            
            // Disable button and show loading
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            fetch('api/users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('editRoleModal');
                    // Reload page to show updated role
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to save role. Please try again.', 'error');
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            });
            
            return false;
        }
        
        // Update permission count (for edit modal)
        function updatePermissionCount() {
            const checked = document.querySelectorAll('.permission-input:checked').length;
            const counter = document.getElementById('selectedCount');
            if (counter) {
                counter.textContent = checked;
            }
            updateModuleCheckboxes();
        }
        
        // Update permission count (for create modal)
        function updatePermissionCountCreate() {
            const checked = document.querySelectorAll('.permission-input-create:checked').length;
            const counter = document.getElementById('createSelectedCount');
            if (counter) {
                counter.textContent = checked;
            }
            updateModuleCheckboxesCreate();
        }
        
        // Update module checkboxes based on selected permissions
        function updateModuleCheckboxes() {
            const modules = document.querySelectorAll('.module-checkbox');
            modules.forEach(moduleCheckbox => {
                const moduleName = moduleCheckbox.dataset.module;
                const modulePermissions = document.querySelectorAll(`.permission-input[data-module="${moduleName}"]`);
                const checkedPermissions = document.querySelectorAll(`.permission-input[data-module="${moduleName}"]:checked`);
                
                if (checkedPermissions.length === modulePermissions.length) {
                    moduleCheckbox.checked = true;
                    moduleCheckbox.indeterminate = false;
                } else if (checkedPermissions.length > 0) {
                    moduleCheckbox.checked = false;
                    moduleCheckbox.indeterminate = true;
                } else {
                    moduleCheckbox.checked = false;
                    moduleCheckbox.indeterminate = false;
                }
            });
        }
        
        // Update module checkboxes for create modal
        function updateModuleCheckboxesCreate() {
            const modules = document.querySelectorAll('.module-checkbox-create');
            modules.forEach(moduleCheckbox => {
                const moduleName = moduleCheckbox.dataset.module;
                const modulePermissions = document.querySelectorAll(`.permission-input-create[data-module="${moduleName}"]`);
                const checkedPermissions = document.querySelectorAll(`.permission-input-create[data-module="${moduleName}"]:checked`);
                
                if (checkedPermissions.length === modulePermissions.length) {
                    moduleCheckbox.checked = true;
                    moduleCheckbox.indeterminate = false;
                } else if (checkedPermissions.length > 0) {
                    moduleCheckbox.checked = false;
                    moduleCheckbox.indeterminate = true;
                } else {
                    moduleCheckbox.checked = false;
                    moduleCheckbox.indeterminate = false;
                }
            });
        }
        
        // Toggle all permissions in a module (edit modal)
        function toggleModulePermissions(checkbox, moduleName) {
            const modulePermissions = document.querySelectorAll(`.permission-input[data-module="${moduleName}"]`);
            modulePermissions.forEach(perm => {
                perm.checked = checkbox.checked;
            });
            updatePermissionCount();
        }
        
        // Toggle all permissions in a module (create modal)
        function toggleModulePermissionsCreate(checkbox, moduleName) {
            const modulePermissions = document.querySelectorAll(`.permission-input-create[data-module="${moduleName}"]`);
            modulePermissions.forEach(perm => {
                perm.checked = checkbox.checked;
            });
            updatePermissionCountCreate();
        }
        
        // Select all permissions (edit modal)
        function selectAllPermissions() {
            document.querySelectorAll('.permission-input').forEach(checkbox => {
                checkbox.checked = true;
            });
            updatePermissionCount();
        }
        
        // Deselect all permissions (edit modal)
        function deselectAllPermissions() {
            document.querySelectorAll('.permission-input').forEach(checkbox => {
                checkbox.checked = false;
            });
            updatePermissionCount();
        }
        
        // Select all permissions (create modal)
        function selectAllPermissionsCreate() {
            document.querySelectorAll('.permission-input-create').forEach(checkbox => {
                checkbox.checked = true;
            });
            updatePermissionCountCreate();
        }
        
        // Deselect all permissions (create modal)
        function deselectAllPermissionsCreate() {
            document.querySelectorAll('.permission-input-create').forEach(checkbox => {
                checkbox.checked = false;
            });
            updatePermissionCountCreate();
        }
    </script>
    
    <style>
        .modal-large {
            max-width: 900px;
        }
        
        .modal-header-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .modal-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        
        .modal-subtitle {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .card-subtitle {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 4px 0 0 0;
            font-weight: normal;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #2c5aa0;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
            outline: none;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group label i {
            color: #2c5aa0;
        }
        
        .form-text {
            display: block;
            margin-top: 4px;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: background 0.3s;
            font-weight: normal;
        }
        
        .checkbox-label:hover {
            background: #f3f4f6;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .role-card {
            background: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s;
        }
        
        .role-card:hover {
            border-color: #2c5aa0;
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.1);
        }
        
        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .role-title-section {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .role-title-section h4 {
            margin: 0;
            color: #1f2937;
            font-size: 1.1rem;
        }
        
        .role-actions {
            display: flex;
            gap: 6px;
        }
        
        .role-description {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0 0 16px 0;
            line-height: 1.5;
        }
        
        .permissions-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .permissions-header strong {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .permissions-header i {
            color: #2c5aa0;
        }
        
        .permission-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .permission-tag {
            background: #e0e7ff;
            color: #4338ca;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .more-tag {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .no-permissions {
            color: #9ca3af;
            font-style: italic;
            font-size: 0.85rem;
        }
        
        .permission-counter {
            font-weight: normal;
            color: #6b7280;
            margin-left: 8px;
            font-size: 0.9rem;
        }
        
        .permissions-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 12px;
            max-height: 450px;
            overflow-y: auto;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .permission-module {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }
        
        .module-title {
            margin: 0 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .module-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #374151;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin: 0;
        }
        
        .module-label i {
            color: #2c5aa0;
        }
        
        .module-checkbox,
        .module-checkbox-create {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .permission-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .permission-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            margin: 0;
        }
        
        .permission-checkbox:hover {
            background: #f9fafb;
        }
        
        .permission-input,
        .permission-input-create {
            width: 16px;
            height: 16px;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .permission-label {
            font-size: 0.85rem;
            color: #4b5563;
            font-weight: normal;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-primary {
            background: #e0e7ff;
            color: #4338ca;
        }
        
        .badge-secondary {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .action-buttons {
            display: flex;
            gap: 6px;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .mt-4 {
            margin-top: 2rem;
        }
        .modal-footer {
    display: flex;
    justify-content: flex-end; /* Aligns buttons to the right */
    gap: 8px;                  /* Adds a nice space between the buttons */
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    background-color: #f9fafb;
    /* These next two lines ensure the footer's corners are rounded like the modal */
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
}
    </style>
</body>
</html>
