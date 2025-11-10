<?php
require_once 'config.php';
requireAdminLogin($pdo);
requirePermission($pdo, 'feedback.view');

$admin = getCurrentAdmin($pdo);

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    requirePermission($pdo, 'feedback.view'); // Or a specific export permission
    ob_start(); // Start output buffering

    try {
        // --- 1. COPY FILTER LOGIC ---
        $status_filter = $_GET['status'] ?? '';
        $rating_filter = $_GET['rating'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $where_conditions = [];
        $params = [];
        
        if ($status_filter) {
            $where_conditions[] = "f.status = ?";
            $params[] = $status_filter;
        }
        if ($rating_filter) {
            $where_conditions[] = "f.rating = ?";
            $params[] = $rating_filter;
        }
        if ($search) {
            $where_conditions[] = "(f.name LIKE ? OR f.email LIKE ? OR f.subject LIKE ? OR f.message LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // --- 2. GET DATA (All matching records, not paginated) ---
        $stmt = $pdo->prepare("
            SELECT f.*
            FROM feedback f
            {$where_clause}
            ORDER BY f.created_at DESC
        ");
        $stmt->execute($params);
        $feedback_items = $stmt->fetchAll();

        // --- 3. GENERATE PDF ---
        require_once '../vendor/autoload.php';
        
        // Use Landscape ('L') to fit more columns
        $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false); 
        
        $pdf->SetCreator('PWD Portal');
        $pdf->SetAuthor($admin['full_name']);
        $pdf->SetTitle('Feedback Report - ' . date('Y-m-d'));
        $pdf->SetSubject('Filtered Feedback Report');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 10);
        
        $pdf->AddPage();
        
        // Title
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'Feedback Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Summary
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Report Overview', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(35, 6, 'Total Messages:', 0, 0, 'L');
        $pdf->Cell(0, 6, count($feedback_items), 0, 1, 'L');
        if ($status_filter) {
            $pdf->Cell(35, 6, 'Status Filter:', 0, 0, 'L');
            $pdf->Cell(0, 6, ucfirst($status_filter), 0, 1, 'L');
        }
        if ($rating_filter) {
            $pdf->Cell(35, 6, 'Rating Filter:', 0, 0, 'L');
            $pdf->Cell(0, 6, $rating_filter . ' Stars', 0, 1, 'L');
        }
        $pdf->Ln(5);
        
        // Data Table (Landscape width ~277mm)
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(44, 90, 160);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(50, 7, 'From', 1, 0, 'L', true);
        $pdf->Cell(50, 7, 'Email', 1, 0, 'L', true);
        $pdf->Cell(77, 7, 'Subject', 1, 0, 'L', true);
        $pdf->Cell(20, 7, 'Rating', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Status', 1, 0, 'C', true);
        $pdf->Cell(50, 7, 'Date', 1, 1, 'L', true);
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(248, 250, 252);
        $fill = false;

        if (empty($feedback_items)) {
             $pdf->Cell(277, 10, 'No feedback found matching the criteria.', 1, 1, 'C', $fill);
        } else {
            foreach ($feedback_items as $item) {
                $pdf->Cell(50, 6, htmlspecialchars_decode($item['name']), 1, 0, 'L', $fill);
                $pdf->Cell(50, 6, htmlspecialchars_decode($item['email']), 1, 0, 'L', $fill);
                $pdf->Cell(77, 6, htmlspecialchars_decode($item['subject']), 1, 0, 'L', $fill);
                $pdf->Cell(20, 6, $item['rating'] ? ' ' . $item['rating'] . '/5' : 'N/A', 1, 0, 'C', $fill);
                $pdf->Cell(30, 6, ucfirst($item['status']), 1, 0, 'C', $fill);
                $pdf->Cell(50, 6, formatDateTime($item['created_at']), 1, 1, 'L', $fill);
                $fill = !$fill;
            }
        }
        
        // --- 4. LOG AND OUTPUT ---
        logAdminActivity($pdo, 'export', 'feedback', 'pdf_report', null, [
            'filter_count' => count($feedback_items),
            'filters' => ['status' => $status_filter, 'rating' => $rating_filter, 'search' => $search]
        ]);
        
        ob_end_clean(); 
        
        $filename = 'feedback_report_' . date('Y-m-d') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        $pdf->Output($filename, 'D');
        exit();
        
    } catch (Exception $e) {
        ob_end_clean(); 
        error_log("Feedback PDF export error: " . $e->getMessage());
        // Use adminJsonResponse if available, otherwise die
        if (function_exists('adminJsonResponse')) {
            adminJsonResponse(['error' => 'Export failed: ' . $e->getMessage()], 500);
        } else {
            die("Export failed: " . $e->getMessage());
        }
    }
}

// --- ADD THIS 'ELSE IF' BLOCK ---
else if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    requirePermission($pdo, 'feedback.view');
    
    try {
        // --- 1. COPY FILTER LOGIC (Same as PDF) ---
        $status_filter = $_GET['status'] ?? '';
        $rating_filter = $_GET['rating'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $where_conditions = [];
        $params = [];
        
        if ($status_filter) {
            $where_conditions[] = "f.status = ?";
            $params[] = $status_filter;
        }
        if ($rating_filter) {
            $where_conditions[] = "f.rating = ?";
            $params[] = $rating_filter;
        }
        if ($search) {
            $where_conditions[] = "(f.name LIKE ? OR f.email LIKE ? OR f.subject LIKE ? OR f.message LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // --- 2. GET DATA (All matching records, same as PDF) ---
        $stmt = $pdo->prepare("
            SELECT f.*
            FROM feedback f
            {$where_clause}
            ORDER BY f.created_at DESC
        ");
        $stmt->execute($params);
        $feedback_items = $stmt->fetchAll();

        // --- 3. GENERATE CSV ---
        $filename = 'feedback_report_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV Header
        fputcsv($output, ['Name', 'Email', 'Subject', 'Message', 'Rating', 'Status', 'Date']);
        
        // Add Data
        foreach ($feedback_items as $item) {
            fputcsv($output, [
                htmlspecialchars_decode($item['name']),
                htmlspecialchars_decode($item['email']),
                htmlspecialchars_decode($item['subject']),
                htmlspecialchars_decode($item['message']), // Add message column
                $item['rating'] ? '="' . $item['rating'] . '/5"' : 'N/A',
                ucfirst($item['status']),
                formatDateTime($item['created_at']) // Use your existing function
            ]);
        }
        
        fclose($output);
        
        // --- 4. LOG ---
        logAdminActivity($pdo, 'export', 'feedback', 'csv_report', null, [
            'filter_count' => count($feedback_items),
            'filters' => ['status' => $status_filter, 'rating' => $rating_filter, 'search' => $search]
        ]);
        
        exit();
        
    } catch (Exception $e) {
        error_log("Feedback CSV export error: " . $e->getMessage());
        die("Export failed: " . $e->getMessage());
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            handleUpdateStatus();
            break;
        case 'bulk_action':
            handleBulkAction();
            break;
        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Get feedback with filters
$status_filter = $_GET['status'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "f.status = ?";
    $params[] = $status_filter;
}

if ($rating_filter) {
    $where_conditions[] = "f.rating = ?";
    $params[] = $rating_filter;
}

if ($search) {
    $where_conditions[] = "(f.name LIKE ? OR f.email LIKE ? OR f.subject LIKE ? OR f.message LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM feedback f {$where_clause}");
$count_stmt->execute($params);
$total_feedback = $count_stmt->fetch()['total'];
$total_pages = ceil($total_feedback / $per_page);

// Get feedback
$stmt = $pdo->prepare("
    SELECT f.*, u.first_name as user_first_name, u.last_name as user_last_name
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.id
    {$where_clause}
    ORDER BY f.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$feedback_list = $stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5
    FROM feedback
";
$stats = $pdo->query($stats_query)->fetch();

function handleUpdateStatus() {
    global $pdo;
    requirePermission($pdo, 'feedback.manage');
    
    $feedback_id = $_POST['feedback_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($feedback_id) || empty($status)) {
        adminJsonResponse(['error' => 'Feedback ID and status are required'], 400);
    }
    
    $valid_statuses = ['new', 'read', 'closed'];
    if (!in_array($status, $valid_statuses)) {
        adminJsonResponse(['error' => 'Invalid status'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE feedback SET status = ? WHERE id = ?");
        $stmt->execute([$status, $feedback_id]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Feedback not found'], 404);
        }
        
        logAdminActivity($pdo, 'edit', 'feedback', 'feedback', $feedback_id, ['status' => $status]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to update status: ' . $e->getMessage()], 500);
    }
}

function handleBulkAction() {
    global $pdo;
    requirePermission($pdo, 'feedback.manage');
    
    $action = $_POST['bulk_action'] ?? '';
    $feedback_ids = $_POST['feedback_ids'] ?? [];
    
    if (empty($action) || empty($feedback_ids)) {
        adminJsonResponse(['error' => 'Action and feedback IDs are required'], 400);
    }
    
    try {
        $placeholders = str_repeat('?,', count($feedback_ids) - 1) . '?';
        
        switch ($action) {
            case 'mark_read':
                $stmt = $pdo->prepare("UPDATE feedback SET status = 'read' WHERE id IN ({$placeholders}) AND status = 'new'");
                $stmt->execute($feedback_ids);
                break;
                
            case 'mark_closed':
                $stmt = $pdo->prepare("UPDATE feedback SET status = 'closed' WHERE id IN ({$placeholders})");
                $stmt->execute($feedback_ids);
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM feedback WHERE id IN ({$placeholders})");
                $stmt->execute($feedback_ids);
                break;
                
            default:
                adminJsonResponse(['error' => 'Invalid bulk action'], 400);
        }
        
        logAdminActivity($pdo, $action, 'feedback', 'bulk', null, [
            'feedback_ids' => $feedback_ids,
            'count' => count($feedback_ids)
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => ucfirst(str_replace('_', ' ', $action)) . ' applied to ' . count($feedback_ids) . ' items'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to perform bulk action: ' . $e->getMessage()], 500);
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

function formatDateTime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    
    <main class="dashboard-container">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-comments"></i> Feedback Management</h1>
                <p>Manage user feedback and support requests</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" onclick="exportFeedbackCSV()">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn btn-outline" onclick="exportFeedbackPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button class="btn btn-primary" onclick="refreshFeedback()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        
          
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <p>Total Feedback</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['new_count']); ?></h3>
                    <p>New Messages</p>
                    <?php if ($stats['new_count'] > 0): ?>
                        <span class="stat-change">
                            <i class="fas fa-exclamation-circle"></i> Needs attention
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon validated">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['closed_count']); ?></h3>
                    <p>Resolved</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : 'N/A'; ?></h3>
                    <p>Average Rating</p>
                    <?php if ($stats['avg_rating']): ?>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= round($stats['avg_rating']) ? 'active' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
          
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Feedback Status Distribution</h3>
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Rating Distribution</h3>
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="ratingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="rating">Rating</label>
                    <select name="rating" id="rating">
                        <option value="">All Ratings</option>
                        <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Name, email, or subject..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="feedback.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
          
        <div class="bulk-actions-card">
            <div class="bulk-actions-form">
                <div class="bulk-select">
                    <label class="checkbox-label">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        <span class="checkmark"></span>
                        Select All
                    </label>
                    <span id="selectedCount" class="selected-count">0 selected</span>
                </div>
                
                <div class="bulk-actions">
                    <select id="bulkAction">
                        <option value="">Bulk Actions</option>
                        <option value="mark_read">Mark as Read</option>
                        <option value="mark_closed">Mark as Closed</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button class="btn btn-secondary" onclick="performBulkAction()">
                        <i class="fas fa-play"></i> Apply
                    </button>
                </div>
            </div>
        </div>
        
          
        <div class="data-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Feedback Messages</h3>
                <span class="record-count"><?php echo number_format($total_feedback); ?> messages</span>
            </div>
            
            <div class="feedback-list">
                <?php foreach ($feedback_list as $feedback): ?>
                    <div class="feedback-item <?php echo $feedback['status'] === 'new' ? 'unread' : ''; ?>" data-id="<?php echo $feedback['id']; ?>">
                        <div class="feedback-header">
                            <div class="feedback-select">
                                <label class="checkbox-label">
                                    <input type="checkbox" class="feedback-checkbox" value="<?php echo $feedback['id']; ?>" onchange="updateSelectedCount()">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                            
                            <div class="feedback-meta">
                                <div class="feedback-sender">
                                    <strong><?php echo htmlspecialchars($feedback['name']); ?></strong>
                                    <?php if ($feedback['user_first_name']): ?>
                                        <span class="user-badge">
                                            <i class="fas fa-user"></i> Registered User
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="feedback-details">
                                    <span class="feedback-email">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($feedback['email']); ?>
                                    </span>
                                    <span class="feedback-date">
                                        <i class="fas fa-clock"></i> <?php echo timeAgo($feedback['created_at']); ?>
                                    </span>
                                    <?php if ($feedback['rating']): ?>
                                        <div class="feedback-rating">
                                            <span class="rating-label">Rating:</span>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'active' : ''; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="rating-value">(<?php echo $feedback['rating']; ?>/5)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="feedback-status">
                                <span class="status-badge status-<?php echo $feedback['status']; ?>">
                                    <?php 
                                    $status_icons = [
                                        'new' => 'fas fa-envelope',
                                        'read' => 'fas fa-envelope-open',
                                        'closed' => 'fas fa-check-circle'
                                    ];
                                    ?>
                                    <i class="<?php echo $status_icons[$feedback['status']]; ?>"></i>
                                    <?php echo ucfirst($feedback['status']); ?>
                                </span>
                            </div>
                            
                            <div class="feedback-actions">
                                <button class="btn btn-sm btn-primary" onclick="viewFeedback(<?php echo $feedback['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline dropdown-toggle" onclick="toggleDropdown(this)" title="More Actions">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php if ($feedback['status'] === 'new'): ?>
                                            <a href="#" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'read')">
                                                <i class="fas fa-envelope-open"></i> Mark as Read
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($feedback['status'] !== 'closed'): ?>
                                            <a href="#" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'closed')">
                                                <i class="fas fa-check-circle"></i> Mark as Closed
                                            </a>
                                        <?php endif; ?>
                                        <div class="dropdown-divider"></div>
                                        <a href="#" onclick="deleteFeedback(<?php echo $feedback['id']; ?>)" class="text-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="feedback-content">
                            <h4 class="feedback-subject">
                                <i class="fas fa-comment-alt"></i>
                                <?php echo htmlspecialchars($feedback['subject']); ?>
                            </h4>
                            <div class="feedback-message">
                                <?php 
                                $message = htmlspecialchars($feedback['message']);
                                $preview = substr($message, 0, 300);
                                if (strlen($message) > 300) {
                                    echo nl2br($preview) . '...';
                                    echo '<button class="read-more-btn" onclick="toggleFullMessage(this, ' . $feedback['id'] . ')">Read More</button>';
                                    echo '<div class="full-message" style="display: none;">' . nl2br($message) . '</div>';
                                } else {
                                    echo nl2br($message);
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($feedback_list)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>No feedback found</h3>
                        <p>No feedback messages match your current filters.</p>
                        <?php if ($status_filter || $rating_filter || $search): ?>
                            <a href="feedback.php" class="btn btn-primary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
              
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_feedback); ?> 
                        of <?php echo number_format($total_feedback); ?> messages
                    </div>
                    
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?page=1&status=<?php echo urlencode($status_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <span class="current-page">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?>&status=<?php echo urlencode($status_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
     
    <div id="feedbackModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3 id="feedbackModalTitle">
                    <i class="fas fa-comment-alt"></i> Feedback Details
                </h3>
                <button class="modal-close" onclick="closeModal('feedbackModal')">&times;</button>
            </div>
            <div class="modal-body" id="feedbackModalBody">
                 Content will be loaded dynamically 
            </div>
        </div>
    </div>
    
    <script src="assets/admin.js"></script>
    <script>
        let selectedFeedback = new Set();
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });
        
        function initializeCharts() {
            // Status distribution chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusData = {
                labels: ['New', 'Read', 'Closed'],
                datasets: [{
                    data: [
                        <?php echo $stats['new_count']; ?>,
                        <?php echo $stats['read_count']; ?>,
                        <?php echo $stats['closed_count']; ?>
                    ],
                    backgroundColor: [
                        '#f59e0b',  // New - Orange
                        '#06b6d4',  // Read - Cyan
                        '#10b981'   // Closed - Green
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            };
            
            new Chart(statusCtx, {
                type: 'doughnut',
                data: statusData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Rating distribution chart
            const ratingCtx = document.getElementById('ratingChart').getContext('2d');
            const ratingData = {
                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                datasets: [{
                    label: 'Number of Ratings',
                    data: [
                        <?php echo $stats['rating_1']; ?>,
                        <?php echo $stats['rating_2']; ?>,
                        <?php echo $stats['rating_3']; ?>,
                        <?php echo $stats['rating_4']; ?>,
                        <?php echo $stats['rating_5']; ?>
                    ],
                    backgroundColor: [
                        '#ef4444',  // 1 star - Red
                        '#f97316',  // 2 stars - Orange
                        '#eab308',  // 3 stars - Yellow
                        '#22c55e',  // 4 stars - Green
                        '#10b981'   // 5 stars - Emerald
                    ],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            };
            
            new Chart(ratingCtx, {
                type: 'bar',
                data: ratingData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    return `Count: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        // View feedback details
        function viewFeedback(feedbackId) {
            const feedbackItem = document.querySelector(`[data-id="${feedbackId}"]`);
            if (!feedbackItem) return;
            
            // Extract data from the DOM
            const name = feedbackItem.querySelector('.feedback-sender strong').textContent;
            const email = feedbackItem.querySelector('.feedback-email').textContent.replace('✉ ', '');
            const date = feedbackItem.querySelector('.feedback-date').textContent.replace('🕐 ', '');
            const subject = feedbackItem.querySelector('.feedback-subject').textContent.trim();
            const messageElement = feedbackItem.querySelector('.full-message') || feedbackItem.querySelector('.feedback-message');
            const message = messageElement.textContent.trim();
            const status = feedbackItem.querySelector('.status-badge').textContent.trim();
            const ratingElement = feedbackItem.querySelector('.feedback-rating');
            let rating = null;
            
            if (ratingElement) {
                const ratingValue = ratingElement.querySelector('.rating-value');
                if (ratingValue) {
                    rating = ratingValue.textContent.match(/$$(\d+)\/5$$/)?.[1];
                }
            }
            
            displayFeedbackDetails({
                id: feedbackId,
                name: name,
                email: email,
                created_at: date,
                subject: subject,
                message: message,
                status: status.toLowerCase(),
                rating: rating
            });
            
            showModal('feedbackModal');
            
            // Mark as read if it's new
            if (status.toLowerCase() === 'new') {
                updateFeedbackStatus(feedbackId, 'read', false);
            }
        }
        
        function displayFeedbackDetails(feedback) {
            const modalTitle = document.getElementById('feedbackModalTitle');
            const modalBody = document.getElementById('feedbackModalBody');
            
            modalTitle.innerHTML = `<i class="fas fa-comment-alt"></i> ${feedback.subject}`;
            
            modalBody.innerHTML = `
                <div class="feedback-details-content">
                    <div class="feedback-header-info">
                        <div class="sender-info">
                            <h4><i class="fas fa-user"></i> ${feedback.name}</h4>
                            <p><i class="fas fa-envelope"></i> ${feedback.email}</p>
                            <p><i class="fas fa-clock"></i> ${feedback.created_at}</p>
                            ${feedback.rating ? `
                                <div class="rating-display">
                                    <span>Rating: </span>
                                    ${Array.from({length: 5}, (_, i) => 
                                        `<i class="fas fa-star ${i < feedback.rating ? 'active' : ''}"></i>`
                                    ).join('')}
                                    <span class="rating-text">(${feedback.rating}/5)</span>
                                </div>
                            ` : ''}
                        </div>
                        <div class="status-info">
                            <span class="status-badge status-${feedback.status}">
                                ${feedback.status.charAt(0).toUpperCase() + feedback.status.slice(1)}
                            </span>
                        </div>
                    </div>
                    
                    <div class="message-content">
                        <h5><i class="fas fa-comment"></i> Message</h5>
                        <div class="message-text">
                            ${feedback.message.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                    
                    <div class="feedback-actions-section">
                        ${feedback.status !== 'closed' ? `
                            <button class="btn btn-success" onclick="updateFeedbackStatus(${feedback.id}, 'closed', true); closeModal('feedbackModal');">
                                <i class="fas fa-check-circle"></i> Mark as Resolved
                            </button>
                        ` : ''}
                        <button class="btn btn-outline" onclick="closeModal('feedbackModal')">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            `;
        }
        
        // Update feedback status
        function updateFeedbackStatus(feedbackId, status, showMessage = true) {
            fetch('feedback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_status&feedback_id=${feedbackId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (showMessage) {
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        // Update the UI without reloading
                        const feedbackItem = document.querySelector(`[data-id="${feedbackId}"]`);
                        if (feedbackItem) {
                            const statusBadge = feedbackItem.querySelector('.status-badge');
                            const statusIcons = {
                                'new': 'fas fa-envelope',
                                'read': 'fas fa-envelope-open',
                                'closed': 'fas fa-check-circle'
                            };
                            statusBadge.innerHTML = `<i class="${statusIcons[status]}"></i> ${status.charAt(0).toUpperCase() + status.slice(1)}`;
                            statusBadge.className = `status-badge status-${status}`;
                            
                            if (status === 'read') {
                                feedbackItem.classList.remove('unread');
                            }
                        }
                    }
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to update status', 'error');
            });
        }
        
        // Delete feedback
        function deleteFeedback(feedbackId) {
            if (confirm('Are you sure you want to delete this feedback? This action cannot be undone.')) {
                performBulkAction('delete', [feedbackId]);
            }
        }
        
        // Toggle full message
        function toggleFullMessage(button, feedbackId) {
            const feedbackItem = document.querySelector(`[data-id="${feedbackId}"]`);
            const fullMessage = feedbackItem.querySelector('.full-message');
            const messageDiv = feedbackItem.querySelector('.feedback-message');
            
            if (fullMessage.style.display === 'none') {
                fullMessage.style.display = 'block';
                button.style.display = 'none';
                // Hide the preview
                const preview = messageDiv.childNodes[0];
                if (preview.nodeType === Node.TEXT_NODE) {
                    preview.textContent = '';
                }
            }
        }
        
        // Bulk actions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.feedback-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.feedback-checkbox:checked');
            const count = checkboxes.length;
            
            document.getElementById('selectedCount').textContent = `${count} selected`;
            
            // Update select all checkbox
            const selectAll = document.getElementById('selectAll');
            const allCheckboxes = document.querySelectorAll('.feedback-checkbox');
            selectAll.checked = count === allCheckboxes.length;
            selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
        }
        
        function performBulkAction(action = null, feedbackIds = null) {
            if (!action) {
                action = document.getElementById('bulkAction').value;
            }
            
            if (!feedbackIds) {
                const checkboxes = document.querySelectorAll('.feedback-checkbox:checked');
                feedbackIds = Array.from(checkboxes).map(cb => cb.value);
            }
            
            if (!action) {
                showNotification('Please select an action', 'warning');
                return;
            }
            
            if (feedbackIds.length === 0) {
                showNotification('Please select at least one feedback item', 'warning');
                return;
            }
            
            if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete ${feedbackIds.length} feedback item(s)? This action cannot be undone.`)) {
                    return;
                }
            }
            
            const formData = new FormData();
            formData.append('action', 'bulk_action');
            formData.append('bulk_action', action);
            feedbackIds.forEach(id => formData.append('feedback_ids[]', id));
            
            fetch('feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to perform bulk action', 'error');
            });
        }
        
        // Dropdown toggle
        function toggleDropdown(button) {
            const dropdown = button.nextElementSibling;
            const isOpen = dropdown.classList.contains('show');
            
            // Close all dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
            
            // Toggle current dropdown
            if (!isOpen) {
                dropdown.classList.add('show');
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
        
       // Export feedback as CSV (server-side, all filtered data)
        function exportFeedbackCSV() {
            const status = document.getElementById('status').value;
            const rating = document.getElementById('rating').value;
            const search = document.getElementById('search').value;
            
            const params = new URLSearchParams();
            params.append('export', 'csv'); // <-- Set to 'csv'
            
            // Add filters to params
            if (status) params.append('status', status);
            if (rating) params.append('rating', rating);
            if (search) params.append('search', search);
            
            // Trigger server-side download
            window.location.href = `feedback.php?${params.toString()}`;
        }
        
        // Export feedback as PDF (server-side, all filtered data)
        function exportFeedbackPDF() {
            const status = document.getElementById('status').value;
            const rating = document.getElementById('rating').value;
            const search = document.getElementById('search').value;
            
            const params = new URLSearchParams();
            params.append('export', 'pdf');
            
            // Add filters to params
            if (status) params.append('status', status);
            if (rating) params.append('rating', rating);
            if (search) params.append('search', search);
            
            // Trigger server-side download
            window.location.href = `feedback.php?${params.toString()}`;
        }

        // Refresh feedback
        function refreshFeedback() {
            location.reload();
        }
    </script>
    
    <style>
        /* Enhanced Feedback Styles */
        .chart-container {
            position: relative;
            height: 300px;
            padding: 20px;
        }
        
        .rating-stars {
            margin-top: 4px;
        }
        
        .rating-stars .fa-star {
            color: #e2e8f0;
            font-size: 0.8rem;
        }
        
        .rating-stars .fa-star.active {
            color: #fbbf24;
        }
        
        .bulk-actions-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        
        .bulk-actions-form {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bulk-select {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }
        
        .selected-count {
            color: #64748b;
            font-size: 0.9rem;
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .bulk-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .bulk-actions select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            min-width: 150px;
        }
        
        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .feedback-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .feedback-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        
        .feedback-item.unread {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(90deg, #fffbeb 0%, #ffffff 10%);
        }
        
        .feedback-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .feedback-select {
            flex-shrink: 0;
        }
        
        .feedback-meta {
            flex: 1;
            min-width: 0;
        }
        
        .feedback-sender {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .feedback-sender strong {
            font-size: 1.1rem;
            color: #1e293b;
        }
        
        .user-badge {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .feedback-details {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 0.9rem;
            color: #64748b;
            flex-wrap: wrap;
        }
        
        .feedback-details > span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .feedback-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            padding: 4px 8px;
            border-radius: 8px;
        }
        
        .feedback-rating .rating-label {
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .feedback-rating .fa-star {
            font-size: 0.8rem;
            color: #e2e8f0;
        }
        
        .feedback-rating .fa-star.active {
            color: #fbbf24;
        }
        
        .feedback-rating .rating-value {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .feedback-status {
            flex-shrink: 0;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-new {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-read {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-closed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .feedback-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            position: relative;
        }
        
        .feedback-content {
            padding: 0 20px 20px 20px;
        }
        
        .feedback-subject {
            color: #1e293b;
            margin-bottom: 12px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .feedback-message {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 12px;
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #e2e8f0;
        }
        
        .read-more-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-weight: 500;
            text-decoration: underline;
            margin-top: 8px;
        }
        
        .read-more-btn:hover {
            color: var(--primary-dark);
        }
        
        .full-message {
            margin-top: 8px;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            min-width: 180px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.2s;
        }
        
        .dropdown-menu a:hover {
            background: #f9fafb;
        }
        
        .dropdown-menu a.text-danger {
            color: #ef4444;
        }
        
        .dropdown-menu a.text-danger:hover {
            background: #fef2f2;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 4px 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #64748b;
        }
        
        .empty-icon {
            margin-bottom: 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #e2e8f0;
        }
        
        .empty-state h3 {
            margin-bottom: 12px;
            color: #374151;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .pagination-info {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .current-page {
            padding: 8px 12px;
            background: var(--primary-color);
            color: white;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        /* Modal Enhancements */
        .large-modal {
            max-width: 800px;
        }
        
        .feedback-details-content {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .feedback-header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .sender-info h4 {
            color: #1e293b;
            margin-bottom: 12px;
            font-size: 1.3rem;
        }
        
        .sender-info p {
            margin-bottom: 8px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .rating-display {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        .rating-display .fa-star.active {
            color: #fbbf24;
        }
        
        .rating-text {
            font-weight: 500;
            color: #374151;
        }
        
        .message-content {
            margin-bottom: 24px;
        }
        
        .message-content h5 {
            color: #374151;
            margin-bottom: 16px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .message-text {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            line-height: 1.7;
            color: #374151;
            font-size: 1rem;
        }
        
        .feedback-actions-section {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .bulk-actions-form {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .feedback-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .feedback-details {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .feedback-actions {
                justify-content: flex-end;
            }
            
            .feedback-header-info {
                flex-direction: column;
                gap: 16px;
            }
            
            .feedback-actions-section {
                flex-direction: column;
            }
            
            .pagination {
                flex-direction: column;
                gap: 12px;
            }
            
            .pagination-controls {
                justify-content: center;
            }
            
            .chart-container {
                height: 250px;
                padding: 10px;
            }
        }
        
        /* Animation for new feedback */
        @keyframes newFeedback {
            0% {
                background-color: #fef3c7;
            }
            100% {
                background-color: #fffbeb;
            }
        }
        
        .feedback-item.unread {
            animation: newFeedback 2s ease-in-out;
        }
        
        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
