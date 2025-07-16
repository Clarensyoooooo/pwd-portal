<?php
// Admin Panel Configuration
require_once '../config.php';

// Admin-specific functions
function isAdminLoggedIn() {
    return isset($_SESSION['admin_user_id']);
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getCurrentAdmin($pdo) {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    $stmt = $pdo->prepare("
        SELECT au.*, ar.name as role_name, ar.display_name as role_display_name 
        FROM admin_users au 
        LEFT JOIN admin_roles ar ON au.role_id = ar.id 
        WHERE au.id = ?
    ");
    $stmt->execute([$_SESSION['admin_user_id']]);
    return $stmt->fetch();
}

function hasPermission($pdo, $permission) {
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    $admin = getCurrentAdmin($pdo);
    if (!$admin || !$admin['role_id']) {
        return false;
    }
    
    // Super admin has all permissions
    if ($admin['role_name'] === 'super_admin') {
        return true;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM role_permissions rp
        JOIN admin_permissions ap ON rp.permission_id = ap.id
        WHERE rp.role_id = ? AND ap.name = ?
    ");
    $stmt->execute([$admin['role_id'], $permission]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

function requirePermission($pdo, $permission) {
    if (!hasPermission($pdo, $permission)) {
        http_response_code(403);
        die('Access denied. You do not have permission to access this resource.');
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
