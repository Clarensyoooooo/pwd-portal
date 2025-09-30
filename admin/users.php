<?php
require_once 'config.php';
requireAdminLogin();

// Only check view permission, not strict requirement
$canView = hasPermission($pdo, 'users.view');
$canCreate = hasPermission($pdo, 'users.create');
$canEdit = hasPermission($pdo, 'users.edit');
$canDelete = hasPermission($pdo, 'users.delete');
$canManageRoles = hasPermission($pdo, 'users.roles');

$admin = getCurrentAdmin($pdo);

// If user can't view, redirect to dashboard with message
if (!$canView) {
    $_SESSION['error'] = 'You do not have permission to access user management.';
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_POST) {
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
        case 'edit_role':
            if ($canManageRoles) {
                editRole($pdo);
            } else {
                $_SESSION['error'] = 'You do not have permission to manage roles.';
            }
            break;
    }
}

// Get users with roles
$stmt = $pdo->query("
    SELECT au.*, ar.display_name as role_name, ar.name as role_key
    FROM admin_users au
    LEFT JOIN admin_roles ar ON au.role_id = ar.id
    ORDER BY au.created_at DESC
");
$users = $stmt->fetchAll();

// Get roles
$stmt = $pdo->query("SELECT * FROM admin_roles ORDER BY name");
$roles = $stmt->fetchAll();

// Get permissions grouped by module
$stmt = $pdo->query("
    SELECT * FROM admin_permissions 
    ORDER BY module, name
");
$all_permissions = $stmt->fetchAll();
$permissions_by_module = [];
foreach ($all_permissions as $permission) {
    $permissions_by_module[$permission['module']][] = $permission;
}

function createUser($pdo) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $role_id = $_POST['role_id'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        $_SESSION['error'] = 'All fields are required.';
        return;
    }
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error'] = 'Username or email already exists.';
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_users (username, email, full_name, password_hash, role_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $username,
            $email,
            $full_name,
            password_hash($password, PASSWORD_DEFAULT),
            $role_id ?: null
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
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $role_id = $_POST['role_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE admin_users 
            SET username = ?, email = ?, full_name = ?, role_id = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $username,
            $email,
            $full_name,
            $role_id ?: null,
            $is_active,
            $user_id
        ]);
        
        // Update password if provided
        if (!empty($_POST['password'])) {
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
    $user_id = $_POST['user_id'];
    
    // Don't allow deleting own account
    if ($user_id == $_SESSION['admin_user_id']) {
        $_SESSION['error'] = 'You cannot delete your own account.';
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

function editRole($pdo) {
    $role_id = $_POST['role_id'];
    $display_name = trim($_POST['display_name']);
    $description = trim($_POST['description']);
    $permissions = $_POST['permissions'] ?? [];
    
    try {
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
                $stmt->execute([$role_id, $permission_id]);
            }
        }
        
        logAdminActivity($pdo, 'edit', 'roles', 'admin_role', $role_id);
        
        $pdo->commit();
        $_SESSION['success'] = 'Role updated successfully.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Failed to update role: ' . $e->getMessage();
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
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <div class="page-actions">
                <?php if ($canCreate): ?>
                <button onclick="showModal('createUserModal')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add User
                </button>
                <?php endif; ?>
                <?php if ($canManageRoles): ?>
                <button onclick="showModal('manageRolesModal')" class="btn btn-outline">
                    <i class="fas fa-user-tag"></i> Manage Roles
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
         Users Table 
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
                                        <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-outline" title="Edit User">
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
        
         Roles & Permissions 
        <?php if ($canManageRoles): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h3><i class="fas fa-user-tag"></i> Roles & Permissions</h3>
            </div>
            <div class="card-content">
                <div class="roles-grid">
                    <?php foreach ($roles as $role): ?>
                    <div class="role-card">
                        <div class="role-header">
                            <h4><?php echo htmlspecialchars($role['display_name']); ?></h4>
                            <div class="role-actions">
                                <button onclick="editRole(<?php echo $role['id']; ?>)" class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <p class="role-description"><?php echo htmlspecialchars($role['description']); ?></p>
                        
                        <?php
                        // Get role permissions
                        $stmt = $pdo->prepare("
                            SELECT ap.display_name, ap.module
                            FROM role_permissions rp
                            JOIN admin_permissions ap ON rp.permission_id = ap.id
                            WHERE rp.role_id = ?
                            ORDER BY ap.module, ap.display_name
                        ");
                        $stmt->execute([$role['id']]);
                        $role_permissions = $stmt->fetchAll();
                        ?>
                        
                        <div class="role-permissions">
                            <strong>Permissions (<?php echo count($role_permissions); ?>):</strong>
                            <div class="permission-tags">
                                <?php foreach ($role_permissions as $perm): ?>
                                    <span class="permission-tag"><?php echo htmlspecialchars($perm['display_name']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
     Create User Modal 
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
                        <input type="password" name="password" id="password" class="form-control" required placeholder="Minimum 6 characters">
                        <small class="form-text">Password must be at least 6 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="role_id">
                            <i class="fas fa-user-tag"></i> Role
                        </label>
                        <select name="role_id" id="role_id" class="form-control">
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
    
     Edit User Modal 
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
                        <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep current">
                        <small class="form-text">Only fill this if you want to change the password</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_role_id">
                            <i class="fas fa-user-tag"></i> Role
                        </label>
                        <select name="role_id" id="edit_role_id" class="form-control">
                            <option value="">Select Role</option>
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
    
     Manage Roles Modal 
    <div id="manageRolesModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <div>
                        <h3>Manage Roles</h3>
                        <p class="modal-subtitle">Configure roles and permissions</p>
                    </div>
                </div>
                <button onclick="closeModal('manageRolesModal')" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="roles-management-grid">
                    <?php foreach ($roles as $role): ?>
                        <?php
                        // Skip super_admin from editing
                        if ($role['name'] === 'super_admin') continue;
                        
                        // Get current role permissions
                        $stmt = $pdo->prepare("
                            SELECT permission_id 
                            FROM role_permissions 
                            WHERE role_id = ?
                        ");
                        $stmt->execute([$role['id']]);
                        $current_permissions = array_column($stmt->fetchAll(), 'permission_id');
                        ?>
                        
                        <div class="role-management-card">
                            <div class="role-card-header">
                                <h4><?php echo htmlspecialchars($role['display_name']); ?></h4>
                                <button onclick="openEditRoleModal(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['display_name']); ?>', '<?php echo htmlspecialchars($role['description']); ?>', <?php echo json_encode($current_permissions); ?>)" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                            <p class="role-card-description"><?php echo htmlspecialchars($role['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
     Edit Role Modal 
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
            <form method="POST" id="editRoleForm">
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="role_id" id="role_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="role_display_name">
                            <i class="fas fa-tag"></i> Role Name *
                        </label>
                        <input type="text" name="display_name" id="role_display_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role_description">
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <textarea name="description" id="role_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Permissions</label>
                        <div class="permissions-grid">
                            <?php foreach ($permissions_by_module as $module => $permissions): ?>
                                <div class="permission-module">
                                    <h5 class="module-title">
                                        <i class="fas fa-folder"></i> <?php echo ucfirst($module); ?>
                                    </h5>
                                    <div class="permission-list">
                                        <?php foreach ($permissions as $permission): ?>
                                            <label class="permission-checkbox">
                                                <input type="checkbox" name="permissions[]" value="<?php echo $permission['id']; ?>" class="permission-input">
                                                <span class="permission-label"><?php echo htmlspecialchars($permission['display_name']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editRoleModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
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
        
        function editUser(userId) {
            // Get user data via AJAX
            fetch(`api/users.php?action=get_user&id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        document.getElementById('edit_user_id').value = user.id;
                        document.getElementById('edit_username').value = user.username;
                        document.getElementById('edit_email').value = user.email;
                        document.getElementById('edit_full_name').value = user.full_name;
                        document.getElementById('edit_role_id').value = user.role_id || '';
                        document.getElementById('edit_is_active').checked = user.is_active == 1;
                        
                        showModal('editUserModal');
                    } else {
                        showNotification('Failed to load user data', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to load user data', 'error');
                });
        }
        
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
        
        function openEditRoleModal(roleId, displayName, description, permissions) {
            document.getElementById('role_id').value = roleId;
            document.getElementById('role_display_name').value = displayName;
            document.getElementById('role_description').value = description;
            
            // Clear all checkboxes first
            document.querySelectorAll('.permission-input').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Check the permissions for this role
            permissions.forEach(permId => {
                const checkbox = document.querySelector(`.permission-input[value="${permId}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            
            closeModal('manageRolesModal');
            showModal('editRoleModal');
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
        }
        
        .modal-subtitle {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
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
        }
        
        .checkbox-label:hover {
            background: #f3f4f6;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .roles-management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .role-management-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .role-management-card:hover {
            border-color: #2c5aa0;
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.1);
        }
        
        .role-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .role-card-header h4 {
            margin: 0;
            color: #1f2937;
        }
        
        .role-card-description {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 12px;
        }
        
        .permission-module {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }
        
        .module-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 12px 0;
            color: #374151;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .module-title i {
            color: #2c5aa0;
        }
        
        .permission-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .permission-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .permission-checkbox:hover {
            background: white;
        }
        
        .permission-input {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .permission-label {
            font-size: 0.85rem;
            color: #4b5563;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
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
        
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .role-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            background: #f9fafb;
        }
        
        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .role-header h4 {
            margin: 0;
            color: #1f2937;
        }
        
        .role-description {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .role-permissions {
            font-size: 0.875rem;
        }
        
        .permission-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }
        
        .permission-tag {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }
    </style>
</body>
</html>
