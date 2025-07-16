<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'feedback.view');

$admin = getCurrentAdmin($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'respond_feedback':
            handleRespondFeedback();
            break;
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
    SELECT f.*, u.first_name as user_first_name, u.last_name as user_last_name,
           au.full_name as responded_by_name
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.id
    LEFT JOIN admin_users au ON f.responded_by = au.id
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
        SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) as responded_count,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count,
        AVG(rating) as avg_rating
    FROM feedback
";
$stats = $pdo->query($stats_query)->fetch();

function handleRespondFeedback() {
    global $pdo;
    requirePermission($pdo, 'feedback.respond');
    
    $feedback_id = $_POST['feedback_id'] ?? '';
    $response = $_POST['response'] ?? '';
    
    if (empty($feedback_id) || empty($response)) {
        adminJsonResponse(['error' => 'Feedback ID and response are required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE feedback 
            SET admin_response = ?, responded_by = ?, responded_at = NOW(), status = 'responded'
            WHERE id = ?
        ");
        
        $stmt->execute([$response, $_SESSION['admin_user_id'], $feedback_id]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Feedback not found'], 404);
        }
        
        logAdminActivity($pdo, 'respond', 'feedback', 'feedback', $feedback_id);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Response sent successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to send response: ' . $e->getMessage()], 500);
    }
}

function handleUpdateStatus() {
    global $pdo;
    requirePermission($pdo, 'feedback.manage');
    
    $feedback_id = $_POST['feedback_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($feedback_id) || empty($status)) {
        adminJsonResponse(['error' => 'Feedback ID and status are required'], 400);
    }
    
    $valid_statuses = ['new', 'read', 'responded', 'closed'];
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
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-comments"></i> Feedback Management</h1>
                <p>Manage user feedback and support requests</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" onclick="exportFeedback()">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="btn btn-primary" onclick="refreshFeedback()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Statistics Cards -->
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
                    <i class="fas fa-reply"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['responded_count']); ?></h3>
                    <p>Responded</p>
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
        
        <!-- Feedback Analytics -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Feedback Status Distribution</h3>
                </div>
                <div class="card-content">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Rating Distribution</h3>
                </div>
                <div class="card-content">
                    <canvas id="ratingChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="responded" <?php echo $status_filter === 'responded' ? 'selected' : ''; ?>>Responded</option>
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
        
        <!-- Bulk Actions -->
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
        
        <!-- Feedback List -->
        <div class="data-card">
            <div class="card-header">
                <h3>Feedback Messages</h3>
                <span class="record-count"><?php echo number_format($total_feedback); ?> messages</span>
            </div>
            
            <div class="feedback-list">
                <?php foreach ($feedback_list as $feedback): ?>
                    <div class="feedback-item <?php echo $feedback['status'] === 'new' ? 'unread' : ''; ?>">
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
                                        <span class="user-badge">Registered User</span>
                                    <?php endif; ?>
                                </div>
                                <div class="feedback-details">
                                    <span class="feedback-email"><?php echo htmlspecialchars($feedback['email']); ?></span>
                                    <span class="feedback-date"><?php echo timeAgo($feedback['created_at']); ?></span>
                                    <?php if ($feedback['rating']): ?>
                                        <div class="feedback-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'active' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="feedback-status">
                                <span class="status-badge status-<?php echo $feedback['status']; ?>">
                                    <?php echo ucfirst($feedback['status']); ?>
                                </span>
                            </div>
                            
                            <div class="feedback-actions">
                                <button class="btn btn-sm btn-primary" onclick="viewFeedback(<?php echo $feedback['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if (hasPermission($pdo, 'feedback.respond') && $feedback['status'] !== 'responded'): ?>
                                    <button class="btn btn-sm btn-success" onclick="respondToFeedback(<?php echo $feedback['id']; ?>)">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                <?php endif; ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline dropdown-toggle" onclick="toggleDropdown(this)">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a href="#" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'read')">Mark as Read</a>
                                        <a href="#" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'closed')">Mark as Closed</a>
                                        <div class="dropdown-divider"></div>
                                        <a href="#" onclick="deleteFeedback(<?php echo $feedback['id']; ?>)" class="text-danger">Delete</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="feedback-content">
                            <h4 class="feedback-subject"><?php echo htmlspecialchars($feedback['subject']); ?></h4>
                            <p class="feedback-message"><?php echo nl2br(htmlspecialchars(substr($feedback['message'], 0, 200))); ?><?php echo strlen($feedback['message']) > 200 ? '...' : ''; ?></p>
                            
                            <?php if ($feedback['admin_response']): ?>
                                <div class="admin-response">
                                    <div class="response-header">
                                        <i class="fas fa-reply"></i>
                                        <strong>Response by <?php echo htmlspecialchars($feedback['responded_by_name']); ?></strong>
                                        <span class="response-date"><?php echo timeAgo($feedback['responded_at']); ?></span>
                                    </div>
                                    <p class="response-content"><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($feedback_list)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>No feedback found</h3>
                        <p>No feedback messages match your current filters.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        (<?php echo number_format($total_feedback); ?> total messages)
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline btn-sm">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Feedback Details Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3 id="feedbackModalTitle">Feedback Details</h3>
                <button class="modal-close" onclick="closeModal('feedbackModal')">&times;</button>
            </div>
            <div class="modal-body" id="feedbackModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
    
    <!-- Response Modal -->
    <div id="responseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Respond to Feedback</h3>
                <button class="modal-close" onclick="closeModal('responseModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="responseForm">
                    <input type="hidden" id="responseFeedbackId" name="feedback_id">
                    
                    <div class="original-message" id="originalMessage">
                        <!-- Original message will be displayed here -->
                    </div>
                    
                    <div class="form-group">
                        <label for="adminResponse">Your Response</label>
                        <textarea id="adminResponse" name="response" rows="6" required 
                                  placeholder="Type your response to the user..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-reply"></i> Send Response
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('responseModal')">
                            Cancel
                        </button>
                    </div>
                </form>
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
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['New', 'Read', 'Responded', 'Closed'],
                    datasets: [{
                        data: [
                            <?php echo $stats['new_count']; ?>,
                            <?php echo $stats['read_count']; ?>,
                            <?php echo $stats['responded_count']; ?>,
                            <?php echo $stats['closed_count']; ?>
                        ],
                        backgroundColor: ['#f59e0b', '#06b6d4', '#10b981', '#6b7280']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Rating distribution chart
            const ratingCtx = document.getElementById('ratingChart').getContext('2d');
            
            // Get rating distribution data
            fetch('api/feedback.php?action=rating_distribution')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        new Chart(ratingCtx, {
                            type: 'bar',
                            data: {
                                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                                datasets: [{
                                    label: 'Count',
                                    data: data.distribution,
                                    backgroundColor: '#2c5aa0'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                }
                            }
                        });
                    }
                });
        }
        
        // View feedback details
        function viewFeedback(feedbackId) {
            fetch(`api/feedback.php?action=get_feedback&id=${feedbackId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFeedbackDetails(data.feedback);
                        showModal('feedbackModal');
                        
                        // Mark as read if it's new
                        if (data.feedback.status === 'new') {
                            updateFeedbackStatus(feedbackId, 'read', false);
                        }
                    } else {
                        showNotification(data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to load feedback details', 'error');
                });
        }
        
        function displayFeedbackDetails(feedback) {
            const modalTitle = document.getElementById('feedbackModalTitle');
            const modalBody = document.getElementById('feedbackModalBody');
            
            modalTitle.textContent = `Feedback: ${feedback.subject}`;
            
            modalBody.innerHTML = `
                <div class="feedback-details-content">
                    <div class="feedback-header-info">
                        <div class="sender-info">
                            <h4><i class="fas fa-user"></i> ${feedback.name}</h4>
                            <p><i class="fas fa-envelope"></i> ${feedback.email}</p>
                            <p><i class="fas fa-clock"></i> ${formatDateTime(feedback.created_at)}</p>
                            ${feedback.rating ? `
                                <div class="rating-display">
                                    <span>Rating: </span>
                                    ${Array.from({length: 5}, (_, i) => 
                                        `<i class="fas fa-star ${i < feedback.rating ? 'active' : ''}"></i>`
                                    ).join('')}
                                </div>
                            ` : ''}
                        </div>
                        <div class="status-info">
                            <span class="status-badge status-${feedback.status}">${feedback.status.charAt(0).toUpperCase() + feedback.status.slice(1)}</span>
                        </div>
                    </div>
                    
                    <div class="message-content">
                        <h5>Subject: ${feedback.subject}</h5>
                        <div class="message-text">
                            ${feedback.message.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                    
                    ${feedback.admin_response ? `
                        <div class="admin-response-section">
                            <h5><i class="fas fa-reply"></i> Admin Response</h5>
                            <div class="response-meta">
                                <span>Responded by: ${feedback.responded_by_name}</span>
                                <span>Date: ${formatDateTime(feedback.responded_at)}</span>
                            </div>
                            <div class="response-text">
                                ${feedback.admin_response.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="feedback-actions-section">
                        ${!feedback.admin_response && hasPermission('feedback.respond') ? `
                            <button class="btn btn-success" onclick="respondToFeedback(${feedback.id})">
                                <i class="fas fa-reply"></i> Respond to Feedback
                            </button>
                        ` : ''}
                        <button class="btn btn-outline" onclick="updateFeedbackStatus(${feedback.id}, 'closed')">
                            <i class="fas fa-check"></i> Mark as Closed
                        </button>
                    </div>
                </div>
            `;
        }
        
        // Respond to feedback
        function respondToFeedback(feedbackId) {
            fetch(`api/feedback.php?action=get_feedback&id=${feedbackId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const feedback = data.feedback;
                        document.getElementById('responseFeedbackId').value = feedbackId;
                        document.getElementById('originalMessage').innerHTML = `
                            <div class="original-feedback">
                                <h5>Original Message</h5>
                                <div class="original-content">
                                    <strong>From:</strong> ${feedback.name} (${feedback.email})<br>
                                    <strong>Subject:</strong> ${feedback.subject}<br>
                                    <strong>Message:</strong><br>
                                    <div class="original-text">${feedback.message.replace(/\n/g, '<br>')}</div>
                                </div>
                            </div>
                        `;
                        
                        closeModal('feedbackModal');
                        showModal('responseModal');
                    } else {
                        showNotification(data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to load feedback for response', 'error');
                });
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
        
        // Form submissions
        document.getElementById('responseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'respond_feedback');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            showLoading(submitBtn);
            
            fetch('feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('responseModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to send response', 'error');
            })
            .finally(() => {
                hideLoading(submitBtn);
            });
        });
        
        // Export feedback
        function exportFeedback() {
            const status = document.getElementById('status').value;
            const rating = document.getElementById('rating').value;
            const search = document.getElementById('search').value;
            
            const params = new URLSearchParams();
            if (status) params.append('status', status);
            if (rating) params.append('rating', rating);
            if (search) params.append('search', search);
            params.append('export', '1');
            
            window.open(`api/feedback.php?${params.toString()}`, '_blank');
        }
        
        // Refresh feedback
        function refreshFeedback() {
            location.reload();
        }
        
        // Helper function to check permissions
        function hasPermission(permission) {
            // This would be populated from PHP
            const permissions = <?php echo json_encode(array_keys($_SESSION['admin_permissions'] ?? [])); ?>;
            return permissions.includes(permission);
        }
    </script>
    
    <style>
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
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
        
        .selected-count {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .bulk-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        
        .feedback-item {
            background: white;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .feedback-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .feedback-item.unread {
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
        }
        
        .feedback-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
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
            gap: 8px;
            margin-bottom: 4px;
        }
        
        .user-badge {
            background: #dbeafe;
            color: #1e40af;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .feedback-details {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .feedback-rating {
            display: flex;
            gap: 2px;
        }
        
        .feedback-rating .fa-star {
            font-size: 0.8rem;
            color: #e2e8f0;
        }
        
        .feedback-rating .fa-star.active {
            color: #fbbf24;
        }
        
        .feedback-status {
            flex-shrink: 0;
        }
        
        .feedback-actions {
            display: flex;
            gap: 4px;
            align-items: center;
            position: relative;
        }
        
        .feedback-content {
            padding: 0 16px 16px 16px;
        }
        
        .feedback-subject {
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .feedback-message {
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        
        .admin-response {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 12px;
            margin-top: 12px;
        }
        
        .response-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #0369a1;
        }
        
        .response-date {
            margin-left: auto;
            color: #64748b;
        }
        
        .response-content {
            color: #1e293b;
            line-height: 1.5;
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
            border-radius: 6px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            min-width: 150px;
            z-index: 1000;
            display: none;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-menu a {
            display: block;
            padding: 8px 12px;
            color: #374151;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        
        .dropdown-menu a:hover {
            background: #f9fafb;
        }
        
        .dropdown-menu a.text-danger {
            color: #ef4444;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 4px 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: #e2e8f0;
        }
        
        .empty-state h3 {
            margin-bottom: 8px;
            color: #374151;
        }
        
        .feedback-details-content {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .feedback-header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .sender-info h4 {
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .sender-info p {
            margin-bottom: 4px;
            color: #64748b;
        }
        
        .rating-display {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .message-content {
            margin-bottom: 20px;
        }
        
        .message-content h5 {
            color: #374151;
            margin-bottom: 12px;
        }
        
        .message-text {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            line-height: 1.6;
            color: #374151;
        }
        
        .admin-response-section {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .admin-response-section h5 {
            color: #0369a1;
            margin-bottom: 8px;
        }
        
        .response-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .response-text {
            color: #374151;
            line-height: 1.6;
        }
        
        .feedback-actions-section {
            display: flex;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        
        .original-feedback {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .original-feedback h5 {
            color: #374151;
            margin-bottom: 12px;
        }
        
        .original-content {
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .original-text {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 12px;
            margin-top: 8px;
            color: #374151;
        }
        
        @media (max-width: 768px) {
            .bulk-actions-form {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }
            
            .feedback-header {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }
            
            .feedback-details {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }
            
            .feedback-actions {
                justify-content: flex-end;
            }
            
            .feedback-header-info {
                flex-direction: column;
                gap: 12px;
            }
            
            .feedback-actions-section {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
