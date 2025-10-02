<?php
require_once '../config.php';
requireAdminLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_user':
            getUserData($pdo);
            break;
            
        case 'get_role':
            getRoleData($pdo);
            break;
            
        case 'update_role':
            updateRole($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getUserData($pdo) {
    $user_id = intval($_GET['id'] ?? 0);
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, username, email, full_name, role_id, is_active
        FROM admin_users
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
}

function getRoleData($pdo) {
    $role_id = intval($_GET['id'] ?? 0);
    
    if (!$role_id) {
        echo json_encode(['success' => false, 'message' => 'Role ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, name, display_name, description
        FROM admin_roles
        WHERE id = ?
    ");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found']);
        return;
    }
    
    // Get permissions for this role
    $stmt = $pdo->prepare("
        SELECT permission_id
        FROM role_permissions
        WHERE role_id = ?
    ");
    $stmt->execute([$role_id]);
    $permissions = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
    
    $role['permissions'] = array_map('intval', $permissions);
    
    echo json_encode(['success' => true, 'role' => $role]);
}

function updateRole($pdo) {
    // Get POST data
    $role_id = intval($_POST['role_id'] ?? 0);
    $display_name = trim($_POST['display_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $permissions = $_POST['permissions'] ?? [];
    
    if (!$role_id) {
        echo json_encode(['success' => false, 'message' => 'Role ID is required']);
        return;
    }
    
    if (empty($display_name)) {
        echo json_encode(['success' => false, 'message' => 'Role name is required']);
        return;
    }
    
    try {
        // Check if role exists and is not super_admin
        $stmt = $pdo->prepare("SELECT name FROM admin_roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            echo json_encode(['success' => false, 'message' => 'Role not found']);
            return;
        }
        
        if ($role['name'] === 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Cannot modify super administrator role']);
            return;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update role details
        $stmt = $pdo->prepare("UPDATE admin_roles SET display_name = ?, description = ? WHERE id = ?");
        $stmt->execute([$display_name, $description, $role_id]);
        
        // Delete existing permissions
        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        
        // Insert new permissions
        if (!empty($permissions) && is_array($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissions as $permission_id) {
                $permission_id = intval($permission_id);
                if ($permission_id > 0) {
                    $stmt->execute([$role_id, $permission_id]);
                }
            }
        }
        
        // Log activity
        logAdminActivity($pdo, 'edit', 'roles', 'admin_role', $role_id, [
            'display_name' => $display_name,
            'permissions_count' => count($permissions)
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Role updated successfully. Changes will take effect on next login.'
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Role update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
