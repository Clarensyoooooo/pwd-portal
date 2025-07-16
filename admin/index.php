<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'dashboard.view');

$admin = getCurrentAdmin($pdo);

// Get dashboard statistics
$stats = [];

// Total appointments
$stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
$stats['total_appointments'] = $stmt->fetch()['count'];

// Pending appointments
$stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'");
$stats['pending_appointments'] = $stmt->fetch()['count'];

// Total PWD records
$stmt = $pdo->query("SELECT COUNT(*) as count FROM pwd_records");
$stats['total_records'] = $stmt->fetch()['count'];

// Validated records
$stmt = $pdo->query("SELECT COUNT(*) as count FROM pwd_records WHERE status = 'validated'");
$stats['validated_records'] = $stmt->fetch()['count'];

// Pending feedback
$stmt = $pdo->query("SELECT COUNT(*) as count FROM feedback WHERE status = 'new'");
$stats['pending_feedback'] = $stmt->fetch()['count'];

// Recent activities
$stmt = $pdo->prepare("
    SELECT aal.*, au.full_name as admin_name 
    FROM admin_activity_logs aal 
    JOIN admin_users au ON aal.admin_user_id = au.id 
    ORDER BY aal.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->fetchAll();

// Recent appointments
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_appointments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PWD Portal</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($admin['full_name']); ?>!</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_appointments']); ?></h3>
                    <p>Total Appointments</p>
                    <span class="stat-change">
                        <i class="fas fa-arrow-up"></i> +12% this month
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['pending_appointments']); ?></h3>
                    <p>Pending Appointments</p>
                    <span class="stat-change">
                        <i class="fas fa-arrow-down"></i> -5% this week
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_records']); ?></h3>
                    <p>PWD Records</p>
                    <span class="stat-change">
                        <i class="fas fa-arrow-up"></i> +8% this month
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon validated">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['validated_records']); ?></h3>
                    <p>Validated Records</p>
                    <span class="stat-change">
                        <i class="fas fa-arrow-up"></i> +15% this month
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Charts and Recent Activity -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Appointment Trends</h3>
                </div>
                <div class="card-content">
                    <canvas id="appointmentChart"></canvas>
                </div>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                </div>
                <div class="card-content">
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?php echo getActivityIcon($activity['action']); ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p><strong><?php echo htmlspecialchars($activity['admin_name']); ?></strong> 
                                       <?php echo formatActivityAction($activity['action'], $activity['module']); ?></p>
                                    <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Recent Appointments</h3>
                    <a href="appointments.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="card-content">
                    <div class="appointment-list">
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-info">
                                    <h4><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($appointment['reference_number']); ?></p>
                                    <span class="appointment-date"><?php echo date('M j, Y', strtotime($appointment['preferred_date'])); ?></span>
                                </div>
                                <div class="appointment-status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Record Status Distribution</h3>
                </div>
                <div class="card-content">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });
        
        function initializeCharts() {
            // Appointment trends chart
            const appointmentCtx = document.getElementById('appointmentChart').getContext('2d');
            new Chart(appointmentCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Appointments',
                        data: [65, 78, 90, 81, 95, 102],
                        borderColor: '#2c5aa0',
                        backgroundColor: 'rgba(44, 90, 160, 0.1)',
                        tension: 0.4
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
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Status distribution chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Validated', 'Pending', 'Draft', 'Issued'],
                    datasets: [{
                        data: [<?php echo $stats['validated_records']; ?>, 15, 8, 25],
                        backgroundColor: ['#10b981', '#f59e0b', '#6b7280', '#2c5aa0']
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
        }
    </script>
</body>
</html>

<?php
function getActivityIcon($action) {
    $icons = [
        'login' => 'sign-in-alt',
        'create' => 'plus',
        'edit' => 'edit',
        'delete' => 'trash',
        'validate' => 'check',
        'interview' => 'comments',
        'export' => 'download'
    ];
    return $icons[$action] ?? 'circle';
}

function formatActivityAction($action, $module) {
    $actions = [
        'login' => 'logged into the system',
        'create' => "created a new {$module} record",
        'edit' => "updated a {$module} record",
        'delete' => "deleted a {$module} record",
        'validate' => "validated a {$module} record",
        'interview' => "conducted an interview",
        'export' => "exported {$module} data"
    ];
    return $actions[$action] ?? "performed {$action} on {$module}";
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>
