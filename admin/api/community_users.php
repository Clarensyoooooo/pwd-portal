<?php
require_once '../config.php'; // Correct: Loads admin/config.php
// requireAdminLogin($pdo) is called implicitly via config.php's isAdminLoggedIn check
// and the hasPermission check.

// We only handle POST requests here for actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonResponse(['error' => 'Invalid request method'], 405);
}

if (!isAdminLoggedIn()) {
    adminJsonResponse(['error' => 'Not authenticated'], 401);
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'toggle_status':
            if (!hasPermission($pdo, 'community.manage')) {
                adminJsonResponse(['error' => 'You do not have permission to manage users.'], 403);
            }
            toggleUserStatus($pdo);
            break;
            
        case 'delete_user':
            if (!hasPermission($pdo, 'community.manage')) {
                adminJsonResponse(['error' => 'You do not have permission to delete users.'], 403);
            }
            deleteUser($pdo);
            break;

        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
} catch (PDOException $e) {
    adminJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}

function toggleUserStatus($pdo) {
    $user_id = $_POST['user_id'] ?? 0;
    $is_active = $_POST['is_active'] ?? 0;

    if (empty($user_id)) {
        adminJsonResponse(['error' => 'Invalid user ID'], 400);
    }

    $stmt = $pdo->prepare("UPDATE community_users SET is_active = ? WHERE id = ?");
    $stmt->execute([$is_active, $user_id]);

    logAdminActivity($pdo, 'toggle_status', 'community', 'community_user', $user_id, ['new_status' => $is_active ? 'active' : 'inactive']);
    adminJsonResponse(['success' => true, 'message' => 'User status updated']);
}

function deleteUser($pdo) {
    $user_id = $_POST['user_id'] ?? 0;

    if (empty($user_id)) {
        adminJsonResponse(['error' => 'Invalid user ID'], 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM community_users WHERE id = ?");
    $stmt->execute([$user_id]);

    logAdminActivity($pdo, 'delete', 'community', 'community_user', $user_id);
    adminJsonResponse(['success' => true, 'message' => 'User deleted successfully']);
}

// adminJsonResponse() and logAdminActivity() are loaded from admin/config.php
?>