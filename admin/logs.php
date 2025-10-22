<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'system.logs');

$admin = getCurrentAdmin($pdo);

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Get filter parameters
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
        
        exit;
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'Export failed: ' . $e->getMessage();
        exit;
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$admin_filter = $_GET['admin_user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$module_filter = $_GET['module'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

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

// Get total count for pagination
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM admin_activity_logs aal
    JOIN admin_users au ON aal.admin_user_id = au.id
    {$where_clause}
");
$count_stmt->execute($params);
$total_logs = $count_stmt->fetch()['total'];
$total_pages = ceil($total_logs / $per_page);

// Get logs
$stmt = $pdo->prepare("
    SELECT aal.*, au.full_name as admin_name, au.username
    FROM admin_activity_logs aal
    JOIN admin_users au ON aal.admin_user_id = au.id
    {$where_clause}
    ORDER BY aal.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get filter options
$admin_users_stmt = $pdo->query("SELECT id, full_name, username FROM admin_users WHERE is_active = 1 ORDER BY full_name");
$admin_users = $admin_users_stmt->fetchAll();

$actions_stmt = $pdo->query("SELECT DISTINCT action FROM admin_activity_logs ORDER BY action");
$actions = $actions_stmt->fetchAll();

$modules_stmt = $pdo->query("SELECT DISTINCT module FROM admin_activity_logs ORDER BY module");
$modules = $modules_stmt->fetchAll();

function getActionIcon($action) {
    $icons = [
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'create' => 'plus',
        'edit' => 'edit',
        'delete' => 'trash',
        'validate' => 'check',
        'interview' => 'comments',
        'export' => 'download',
        'import' => 'upload',
        'view' => 'eye'
    ];
    return $icons[$action] ?? 'circle';
}

function getActionColor($action) {
    $colors = [
        'login' => 'success',
        'logout' => 'secondary',
        'create' => 'primary',
        'edit' => 'warning',
        'delete' => 'danger',
        'validate' => 'success',
        'interview' => 'info',
        'export' => 'primary',
        'import' => 'primary',
        'view' => 'secondary'
    ];
    return $colors[$action] ?? 'secondary';
}

function getBrowserName($userAgent) {
    if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
    if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
    if (strpos($userAgent, 'Safari') !== false) return 'Safari';
    if (strpos($userAgent, 'Edge') !== false) return 'Edge';
    if (strpos($userAgent, 'Opera') !== false) return 'Opera';
    return 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
   
    
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-list-alt"></i> Activity Logs</h1>
            <div class="page-actions">
                <a href="?export=csv&<?php echo http_build_query(array_filter([
                    'search' => $search,
                    'admin_user' => $admin_filter,
                    'action' => $action_filter,
                    'module' => $module_filter,
                    'date_from' => $date_from,
                    'date_to' => $date_to
                ])); ?>" class="btn btn-primary">
                    <i class="fas fa-download"></i> Export Logs
                </a>
            </div>
        </div>
        
         Filters 
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Logs</h3>
                <a href="logs.php" class="btn btn-sm btn-outline">Reset Filters</a>
            </div>
            <div class="card-content">
                <form method="GET" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="Search actions, modules, users..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_user">Admin User</label>
                            <select name="admin_user" id="admin_user" class="form-control">
                                <option value="">All Users</option>
                                <?php foreach ($admin_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $admin_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="action">Action</label>
                            <select name="action" id="action" class="form-control">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo $action['action']; ?>" <?php echo $action_filter == $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($action['action']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="module">Module</label>
                            <select name="module" id="module" class="form-control">
                                <option value="">All Modules</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['module']; ?>" <?php echo $module_filter == $module['module'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($module['module']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_from">Date From</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">Date To</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
         Logs Table 
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Activity Logs</h3>
                <div class="card-meta">
                    Showing <?php echo number_format(count($logs)); ?> of <?php echo number_format($total_logs); ?> logs
                </div>
            </div>
            <div class="card-content">
                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-alt"></i>
                        <h3>No Activity Logs Found</h3>
                        <p>No logs match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="logs-timeline">
                        <?php foreach ($logs as $log): ?>
                            <div class="log-entry">
                                <div class="log-icon">
                                    <i class="fas fa-<?php echo getActionIcon($log['action']); ?> text-<?php echo getActionColor($log['action']); ?>"></i>
                                </div>
                                <div class="log-content">
                                    <div class="log-header">
                                        <div class="log-user">
                                            <strong><?php echo htmlspecialchars($log['admin_name']); ?></strong>
                                            <span class="log-username">@<?php echo htmlspecialchars($log['username']); ?></span>
                                        </div>
                                       <div class="log-time">
    <?php 
        // Create a DateTime object, telling it the time is in UTC
        $utc_time = new DateTime($log['created_at'], new DateTimeZone('UTC'));
        // Set the object's timezone to your local one
        $utc_time->setTimezone(new DateTimeZone('Asia/Manila'));
        // Format and display the converted time
        echo $utc_time->format('M j, Y g:i A');
    ?>
</div>
                                    </div>
                                    
                                    <div class="log-action">
                                        <span class="action-badge action-<?php echo $log['action']; ?>">
                                            <?php echo ucfirst($log['action']); ?>
                                        </span>
                                        <span class="module-badge">
                                            <?php echo ucfirst($log['module']); ?>
                                        </span>
                                        
                                        <?php if ($log['target_type'] && $log['target_id']): ?>
                                            <span class="target-info">
                                                <?php echo ucfirst($log['target_type']); ?> #<?php echo $log['target_id']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($log['details']): ?>
                                        <div class="log-details">
                                            <button onclick="toggleDetails(<?php echo $log['id']; ?>)" class="btn btn-sm btn-link">
                                                <i class="fas fa-chevron-down"></i> View Details
                                            </button>
                                            <div id="details-<?php echo $log['id']; ?>" class="log-details-content" style="display: none;">
                                                <?php 
                                                $details = json_decode($log['details'], true);
                                                if (is_array($details)) {
                                                    echo '<div class="details-list">';
                                                    foreach ($details as $key => $value) {
                                                        $displayKey = ucwords(str_replace('_', ' ', $key));
                                                        if (is_array($value)) {
                                                            echo '<div class="detail-item">';
                                                            echo '<span class="detail-label">' . htmlspecialchars($displayKey) . ':</span>';
                                                            echo '<div class="detail-value nested">';
                                                            foreach ($value as $subKey => $subValue) {
                                                                $subDisplayKey = ucwords(str_replace('_', ' ', $subKey));
                                                                echo '<div class="detail-subitem">';
                                                                echo '<span class="detail-label">' . htmlspecialchars($subDisplayKey) . ':</span> ';
                                                                echo '<span class="detail-value">' . htmlspecialchars(is_bool($subValue) ? ($subValue ? 'Yes' : 'No') : $subValue) . '</span>';
                                                                echo '</div>';
                                                            }
                                                            echo '</div>';
                                                            echo '</div>';
                                                        } else {
                                                            echo '<div class="detail-item">';
                                                            echo '<span class="detail-label">' . htmlspecialchars($displayKey) . ':</span> ';
                                                            echo '<span class="detail-value">' . htmlspecialchars(is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . '</span>';
                                                            echo '</div>';
                                                        }
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo '<p>' . htmlspecialchars($log['details']) . '</p>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="log-meta">
                                        <span class="log-ip">
                                            <i class="fas fa-globe"></i> <?php echo $log['ip_address'] ?: 'Unknown'; ?>
                                        </span>
                                        <?php if ($log['user_agent']): ?>
                                            <span class="log-agent" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                                <i class="fas fa-desktop"></i> <?php echo getBrowserName($log['user_agent']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                     Pagination 
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <span class="pagination-info">
                                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                </span>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
    <script>
        function toggleDetails(logId) {
            const details = document.getElementById(`details-${logId}`);
            const button = details.previousElementSibling;
            const icon = button.querySelector('i');
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
                button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
            } else {
                details.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
                button.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
            }
        }
    </script>
</body>
</html>

<style>
.logs-timeline {
    position: relative;
}

.log-entry {
    display: flex;
    margin-bottom: 1.5rem;
    position: relative;
}

.log-entry:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 15px;
    top: 40px;
    bottom: -24px;
    width: 2px;
    background: #e5e7eb;
}

.log-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.log-content {
    flex: 1;
    background: #f9fafb;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #e5e7eb;
}

.log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.log-user {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.log-username {
    color: #6b7280;
    font-size: 0.875rem;
}

.log-time {
    color: #6b7280;
    font-size: 0.875rem;
}

.log-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}

.action-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.action-login { background: #dcfce7; color: #166534; }
.action-logout { background: #f3f4f6; color: #374151; }
.action-create { background: #dbeafe; color: #1e40af; }
.action-edit { background: #fef3c7; color: #92400e; }
.action-delete { background: #fee2e2; color: #dc2626; }
.action-validate { background: #dcfce7; color: #166534; }
.action-interview { background: #e0f2fe; color: #0369a1; }
.action-export { background: #dbeafe; color: #1e40af; }
.action-import { background: #dbeafe; color: #1e40af; }
.action-view { background: #f3f4f6; color: #374151; }

.module-badge {
    background: #f3f4f6;
    color: #374151;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.target-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.log-details {
    margin-top: 0.5rem;
}

.log-details-content {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 0.75rem;
    margin-top: 0.5rem;
}

.details-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item {
    display: flex;
    align-items: flex-start;
    padding: 0.375rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #374151;
    min-width: 120px;
    margin-right: 0.75rem;
}

.detail-value {
    color: #6b7280;
    flex: 1;
}

.detail-value.nested {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 0.25rem;
}

.detail-subitem {
    display: flex;
    padding-left: 1rem;
    font-size: 0.875rem;
}

.detail-subitem .detail-label {
    min-width: 100px;
    font-weight: 500;
}

.log-meta {
    display: flex;
    gap: 1rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e5e7eb;
    font-size: 0.75rem;
    color: #6b7280;
}

.log-ip, .log-agent {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pagination-wrapper {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pagination-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    color: #374151;
    text-decoration: none;
    transition: all 0.2s;
}

.pagination-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.filter-form .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: #d1d5db;
}

.empty-state h3 {
    margin-bottom: 0.5rem;
    color: #374151;
}

@media (max-width: 768px) {
    .log-entry {
        flex-direction: column;
    }
    
    .log-icon {
        align-self: flex-start;
        margin-bottom: 0.5rem;
        margin-right: 0;
    }
    
    .log-entry:not(:last-child)::after {
        display: none;
    }
    
    .log-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .log-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>
