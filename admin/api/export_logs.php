<?php
require_once '../config.php';
requireAdminLogin();
requirePermission($pdo, 'system.logs');

// Get filter parameters (same as logs.php)
$search = $_GET['search'] ?? '';
$admin_filter = $_GET['admin_user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$module_filter = $_GET['module'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query conditions
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(aal.action LIKE ? OR aal.module LIKE ? OR au.full_name LIKE ? OR aal.details LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($admin_filter) {
    $where_conditions[] = "aal.admin_user_id = ?";
    $params[] = $admin_filter;
}

if ($action_filter) {
    $where_conditions[] = "aal.action = ?";
    $params[] = $action_filter;
}

if ($module_filter) {
    $where_conditions[] = "aal.module = ?";
    $params[] = $module_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(aal.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(aal.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $stmt = $pdo->prepare("
        SELECT aal.*, au.full_name as admin_name, au.username
        FROM admin_activity_logs aal
        JOIN admin_users au ON aal.admin_user_id = au.id
        {$where_clause}
        ORDER BY aal.created_at DESC
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, [
        'Date/Time', 'Admin User', 'Username', 'Action', 'Module', 
        'Target Type', 'Target ID', 'IP Address', 'User Agent', 'Details'
    ]);
    
    // Write data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['admin_name'],
            $log['username'],
            $log['action'],
            $log['module'],
            $log['target_type'],
            $log['target_id'],
            $log['ip_address'],
            $log['user_agent'],
            $log['details']
        ]);
    }
    
    fclose($output);
    
    logAdminActivity($pdo, 'export', 'system', 'activity_logs', null, [
        'log_count' => count($logs),
        'filters' => compact('search', 'admin_filter', 'action_filter', 'module_filter', 'date_from', 'date_to')
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
}
?>
