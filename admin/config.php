<?php
// Admin Panel Configuration
require_once __DIR__ . '/../config.php';

// Admin-specific functions
function isAdminLoggedIn() {
    return isset($_SESSION['admin_user_id']);
}

/**
 * Requires admin login and checks for a single active session.
 * This function will now log out any "old" sessions if a new one is started.
 *
 * @param PDO $pdo The database connection object.
 */
function requireAdminLogin($pdo) {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    // --- START: Single-Session Check ---
    
    // Check if the active session ID is even set in our session
    if (!isset($_SESSION['active_session_id'])) {
        session_destroy();
        header('Location: login.php?error=session_expired');
        exit();
    }

    try {
        // Fetch the *only* valid session ID from the database
        $stmt = $pdo->prepare("SELECT active_session_id FROM admin_users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_user_id']]);
        $db_session_id = $stmt->fetchColumn();

        // Compare the session ID in our cookie with the one in the DB
        if (!$db_session_id || $db_session_id !== $_SESSION['active_session_id']) {
            // If they don't match, this is an old/invalid session.
            // A new login has occurred elsewhere.
            session_destroy();
            // Redirect with a message explaining why they were logged out
            header('Location: login.php?error=multi_session');
            exit();
        }
    } catch (PDOException $e) {
        // If the DB check fails, log out for safety.
        error_log("Session check database error: " . $e->getMessage());
        session_destroy();
        header('Location: login.php?error=db_error');
        exit();
    }
    // --- END: Single-Session Check ---
}

function getCurrentAdmin($pdo) {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT au.*, ar.name as role_name, ar.display_name as role_display_name 
            FROM admin_users au 
            LEFT JOIN admin_roles ar ON au.role_id = ar.id 
            WHERE au.id = ? AND au.is_active = 1
        ");
        $stmt->execute([$_SESSION['admin_user_id']]);
        $admin = $stmt->fetch();
        
        // If user is not active or doesn't exist, clear session
        if (!$admin) {
            session_destroy();
            return null;
        }
        
        return $admin;
    } catch (PDOException $e) {
        error_log("Error getting current admin: " . $e->getMessage());
        return null;
    }
}

function hasPermission($pdo, $permission) {
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    try {
        $admin = getCurrentAdmin($pdo);
        if (!$admin) {
            return false;
        }
        
        // Super admin has all permissions - use role_name check
        if (isset($admin['role_name']) && $admin['role_name'] === 'super_admin') {
            return true;
        }
        
        // If no role assigned, deny access (except for basic permissions)
        if (!isset($admin['role_id']) || !$admin['role_id']) {
            // Allow basic navigation permissions
            $basicPermissions = ['dashboard.view'];
            return in_array($permission, $basicPermissions);
        }
        
        // Check if role has the specific permission
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM role_permissions rp
            JOIN admin_permissions ap ON rp.permission_id = ap.id
            WHERE rp.role_id = ? AND ap.name = ?
        ");
        $stmt->execute([$admin['role_id'], $permission]);
        $result = $stmt->fetch();
        
        return $result && $result['count'] > 0;
    } catch (PDOException $e) {
        // Log error but don't crash - fail safely
        error_log("Permission check error: " . $e->getMessage());
        
        // If there's a database error, allow super_admin through
        $admin = getCurrentAdmin($pdo);
        return isset($admin['role_name']) && $admin['role_name'] === 'super_admin';
    }
}

function requirePermission($pdo, $permission) {
    if (!hasPermission($pdo, $permission)) {
        // Don't use die() - use proper error handling
        $_SESSION['error'] = 'Access denied. You do not have permission to access this resource.';
        header('Location: index.php');
        exit();
    }
}

function logAdminActivity($pdo, $action, $module, $target_type = null, $target_id = null, $details = null) {
    if (!isAdminLoggedIn()) {
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_user_id, action, module, target_type, target_id, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['admin_user_id'],
        $action,
        $module,
        $target_type,
        $target_id,
        $details ? json_encode($details) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

function generatePWDId($pdo) {
    $year = date('Y');
    $attempts = 0;
    $maxAttempts = 100;
    
    do {
        $counter = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $pwd_id = "PWD-{$year}-{$counter}";
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pwd_records WHERE pwd_id_number = ?");
        $stmt->execute([$pwd_id]);
        $exists = $stmt->fetch()['count'] > 0;
        
        $attempts++;
    } while ($exists && $attempts < $maxAttempts);
    
    if ($attempts >= $maxAttempts) {
        throw new Exception('Unable to generate unique PWD ID');
    }
    
    return $pwd_id;
}

// Admin response helper
function adminJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>
