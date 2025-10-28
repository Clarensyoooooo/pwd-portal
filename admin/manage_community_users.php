<?php
require_once 'config.php'; // Loads admin/config.php
requireAdminLogin($pdo);   // Checks for admin login
requirePermission($pdo, 'community.view'); // Checks for permission

$admin = getCurrentAdmin($pdo);

// --- START: FILTER LOGIC ---
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if ($status_filter !== '') {
    $where_conditions[] = "cu.is_active = ?";
    $params[] = $status_filter;
}
if ($search) {
    $where_conditions[] = "(cu.first_name LIKE ? OR cu.last_name LIKE ? OR cu.email LIKE ? OR pr.pwd_id_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
// --- END: FILTER LOGIC ---


// --- START: EXPORT LOGIC (CSV / PDF) ---
if (isset($_GET['export'])) {
    requirePermission($pdo, 'community.manage'); // Use manage perm for export
    
    // Get all filtered records (no pagination)
    $stmt = $pdo->prepare("
        SELECT 
            cu.id, cu.first_name, pr.middle_name, cu.last_name, cu.email, 
            cu.is_active, cu.created_at,
            pr.pwd_id_number, pr.status AS pwd_status, pr.expiry_date
        FROM community_users cu
        LEFT JOIN pwd_records pr ON cu.pwd_record_id = pr.id
        {$where_clause}
        ORDER BY cu.last_name, cu.first_name
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $log_details = [
        'record_count' => count($records),
        'filters' => array_filter(['status' => $status_filter, 'search' => $search])
    ];

    // Handle PDF Export
    if ($_GET['export'] == 'pdf') {
        require_once '../vendor/autoload.php';
        ob_start();
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('PWD Portal');
        $pdf->SetAuthor($admin['full_name']);
        $pdf->SetTitle('Community Users Report - ' . date('Y-m-d'));
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        $pdf->AddPage();
        
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'Community Users Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(44, 90, 160);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(45, 7, 'Name', 1, 0, 'L', true);
        $pdf->Cell(50, 7, 'Email', 1, 0, 'L', true);
        $pdf->Cell(30, 7, 'PWD ID', 1, 0, 'L', true);
        $pdf->Cell(25, 7, 'PWD Status', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Account Status', 1, 1, 'C', true);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;

        foreach ($records as $user) {
            $pdf->SetFillColor($fill ? 245 : 255);
            $pdf->Cell(45, 6, $user['first_name'] . ' ' . $user['last_name'], 1, 0, 'L', true);
            $pdf->Cell(50, 6, $user['email'], 1, 0, 'L', true);
            $pdf->Cell(30, 6, $user['pwd_id_number'] ?? 'N/A', 1, 0, 'L', true);
            $pdf->Cell(25, 6, getPwdStatusText($user['pwd_status'], $user['expiry_date']), 1, 0, 'C', true);
            $pdf->Cell(30, 6, $user['is_active'] ? 'Active' : 'Inactive', 1, 1, 'C', true);
            $fill = !$fill;
        }
        
        logAdminActivity($pdo, 'export', 'community', 'pdf_report', null, $log_details);
        ob_end_clean(); 
        $pdf->Output('community_users_report_' . date('Y-m-d') . '.pdf', 'D');
        exit;
    }
    
    // Handle CSV Export
    if ($_GET['export'] == '1') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Community_Users_Report_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        fputcsv($output, ['Community Users Report']);
        fputcsv($output, ['Generated:', date('Y-m-d H:i:s'), 'By:', $admin['full_name']]);
        fputcsv($output, []);
        
        fputcsv($output, [
            'ID', 'First Name', 'Last Name', 'Email', 'Account Status', 'Date Joined', 
            'PWD ID Number', 'PWD Status', 'PWD Expiry'
        ]);

        foreach ($records as $user) {
            fputcsv($output, [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['is_active'] ? 'Active' : 'Inactive',
                $user['created_at'],
                $user['pwd_id_number'] ?? 'N/A',
                getPwdStatusText($user['pwd_status'], $user['expiry_date']),
                $user['expiry_date'] ?? 'N/A'
            ]);
        }
        
        fclose($output);
        logAdminActivity($pdo, 'export', 'community', 'csv_report', null, $log_details);
        exit;
    }
}
// --- END: EXPORT LOGIC ---


// --- START: PAGE DATA FETCHING ---

// Get Stat Cards
$stats_query = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM community_users
");
$stats = $stats_query->fetch();

// Get Total Count for Pagination
$count_stmt = $pdo->prepare("
    SELECT COUNT(cu.id) as total 
    FROM community_users cu
    LEFT JOIN pwd_records pr ON cu.pwd_record_id = pr.id
    {$where_clause}
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get Records for current page
$stmt = $pdo->prepare("
    SELECT 
        cu.id, cu.first_name, cu.last_name, cu.email, 
        cu.is_active, cu.created_at,
        pr.pwd_id_number, 
        pr.status AS pwd_status, 
        pr.expiry_date AS pwd_expiry_date
    FROM community_users cu
    LEFT JOIN pwd_records pr ON cu.pwd_record_id = pr.id
    {$where_clause}
    ORDER BY cu.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// --- END: PAGE DATA FETCHING ---

// Helper functions for displaying status badges (used in PHP loop)
function getAccountStatusBadge($is_active) {
    if ($is_active == 1) {
        return '<span class="status-badge status-active">Active</span>';
    } else {
        return '<span class="status-badge status-inactive">Inactive</span>';
    }
}

function getPwdStatusText($pwdStatus, $expiryDate) {
    if (!$pwdStatus) return 'N/A';
    $s = strtolower($pwdStatus);
    if ($s === 'issued') {
        if ($expiryDate && strtotime($expiryDate) < time()) {
            return 'Expired';
        }
        return 'Issued';
    }
    return ucfirst($s);
}

function getPwdStatusBadge($pwdStatus, $expiryDate) {
    $statusText = getPwdStatusText($pwdStatus, $expiryDate);
    $statusClass = 'status-pwd-other';
    
    if ($statusText === 'Issued') $statusClass = 'status-pwd-issued';
    if ($statusText === 'Expired') $statusClass = 'status-pwd-expired';

    return '<span class="status-badge ' . $statusClass . '">' . htmlspecialchars($statusText) . '</span>';
}

$page_title = "Manage Community Users";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - PWD Admin</title>
    <link rel="stylesheet" href="assets/admin.css"> <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Styles copied from records.php for consistency */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }
        .data-table th { background-color: #f8f9fa; }
        .data-table tr:nth-child(even) { background-color: #fdfdfd; }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .status-pwd-issued { background: #d1e7fd; color: #0a58ca; }
        .status-pwd-other { background: #e2e3e5; color: #41464b; }
        .status-pwd-expired { background: #fef3c7; color: #92400e; }

        .action-btn {
            padding: 8px 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            margin: 2px;
            transition: opacity 0.3s ease;
        }
        .action-btn:hover { opacity: 0.8; }
        .btn-deactivate { background-color: #ffc107; color: #333; }
        .btn-activate { background-color: #198754; color: white; }
        .btn-delete { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    
    <?php include 'includes/header.php'; ?>
            
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-users-cog"></i> <?php echo $page_title; ?></h1>
                <p>View, activate, or deactivate community member accounts.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" onclick="exportRecords('1')">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="btn btn-outline" onclick="exportRecords('pdf')">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                    <p>Total Community Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon validated">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['active'] ?? 0); ?></h3>
                    <p>Active Accounts</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-user-lock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['inactive'] ?? 0); ?></h3>
                    <p>Inactive Accounts</p>
                </div>
            </div>
        </div>
        
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="status">Account Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="filter-group" style="grid-column: 2 / 4;">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Name, Email, or PWD ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="manage_community_users.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
          
        <div class="data-card">
            <div class="card-header">
                <h3>Community Member Accounts</h3>
                <span class="record-count"><?php echo number_format($total_records); ?> records found</span>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>PWD ID Number</th>
                            <th>PWD Status</th>
                            <th>Account Status</th>
                            <th>Date Joined</th>
                            <?php if (hasPermission($pdo, 'community.manage')): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="user-table-body">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding: 30px;">
                                    <i class="fas fa-users"></i>
                                    <p style="margin-top: 10px;">No community users found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr data-user-id="<?php echo $user['id']; ?>">
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['pwd_id_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo getPwdStatusBadge($user['pwd_status'], $user['pwd_expiry_date']); ?></td>
                                    <td><?php echo getAccountStatusBadge($user['is_active']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    
                                    <?php if (hasPermission($pdo, 'community.manage')): ?>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($user['is_active'] == 1): ?>
                                                    <button class="action-btn btn-deactivate" onclick="toggleStatus(<?php echo $user['id']; ?>, 0)" title="Deactivate Account">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="action-btn btn-activate" onclick="toggleStatus(<?php echo $user['id']; ?>, 1)" title="Activate Account">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="action-btn btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')" title="Delete Account">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php 
                    // Build query string for pagination links
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $query_string = http_build_query($query_params);
                    ?>
                
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo $query_string; ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline btn-sm" style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo $query_string; ?>" class="btn btn-outline btn-sm">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline btn-sm" style="opacity: 0.5; cursor: not-allowed;">Next <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="assets/admin.js"></script> <script>
    
    // --- Export Functions ---
    function exportRecords(type) {
        const params = new URLSearchParams(window.location.search); // Get current filters
        if (type === 'pdf') {
            params.set('export', 'pdf');
        } else {
            params.set('export', '1'); // '1' for CSV
        }
        window.location.href = `manage_community_users.php?${params.toString()}`;
    }

    // --- Action Functions ---
    function toggleStatus(userId, newStatus) {
        const actionText = newStatus == 1 ? 'activate' : 'deactivate';
        if (!confirm(`Are you sure you want to ${actionText} this user account?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('user_id', userId);
        formData.append('is_active', newStatus);

        fetch('api/community_users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                // Find the row and update it dynamically
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                if (row) {
                    // Reload the whole list to reflect changes
                    window.location.reload(); 
                }
            } else {
                showNotification('Error: ' + data.error, 'error');
            }
        });
    }

    function deleteUser(userId, email) {
        if (!confirm(`Are you sure you want to DELETE the community account for ${escapeHTML(email)}? This action cannot be undone.`)) {
            return;
        }
        
        if (!confirm(`FINAL WARNING: This will permanently delete the user's login. This does NOT delete their PWD Record. Are you absolutely sure?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);

        fetch('api/community_users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                // Find the row and remove it
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                if (row) {
                    row.remove();
                }
            } else {
                showNotification('Error: ' + data.error, 'error');
            }
        });
    }
    
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return str.toString().replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }
    </script>
</body>
</html>