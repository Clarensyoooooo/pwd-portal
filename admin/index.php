<?php
require_once 'config.php';
requireAdminLogin($pdo);

date_default_timezone_set('Asia/Manila');

$admin = getCurrentAdmin($pdo);
$page_title = "Dashboard Overview";

// Fetch key statistics
$stats = [];

// Total PWD Records
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pwd_records");
$stats['total_pwds'] = $stmt->fetch()['total'];

// Pending Appointments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'");
$stats['pending_appointments'] = $stmt->fetch()['total'];

// Today's Appointments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(preferred_date) = CURDATE()");
$stats['today_appointments'] = $stmt->fetch()['total'];

// Total Appointments This Month
$stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stats['month_appointments'] = $stmt->fetch()['total'];

// Active Programs (from feedback or a programs table if exists)
$stmt = $pdo->query("SELECT COUNT(DISTINCT subject) as total FROM feedback WHERE status = 'pending'");
$stats['pending_feedback'] = $stmt->fetch()['total'];

// Recent registrations this month
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stats['new_users'] = $stmt->fetch()['total'];

// Disability type distribution
$stmt = $pdo->query("
    SELECT disability_type, COUNT(*) as count 
    FROM pwd_records 
    WHERE disability_type IS NOT NULL 
    GROUP BY disability_type 
    ORDER BY count DESC 
    LIMIT 5
");
$disability_distribution = $stmt->fetchAll();

// Monthly appointments trend (last 6 months)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') as month,
        COUNT(*) as count
    FROM appointments
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at ASC
");
$appointments_trend = $stmt->fetchAll();

// Status distribution
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    GROUP BY status
");
$status_distribution = $stmt->fetchAll();

// Recent appointments
$stmt = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
");
$recent_appointments = $stmt->fetchAll();

// Recent activities from logs
$stmt = $pdo->query("
    SELECT al.*, au.username, au.full_name
    FROM admin_activity_logs al
    LEFT JOIN admin_users au ON al.admin_user_id = au.id
    ORDER BY al.created_at DESC
    LIMIT 8
");
$recent_activities = $stmt->fetchAll();

// Age group distribution
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 65 THEN '51-65'
            ELSE 'Over 65'
        END as age_group,
        COUNT(*) as count
    FROM pwd_records
    WHERE date_of_birth IS NOT NULL
    GROUP BY age_group
    ORDER BY count DESC
");
$age_distribution = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="dashboard-container">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-content">
            <h1>Welcome back, <?php echo htmlspecialchars($admin['full_name']); ?>! 👋</h1>
            <p>Here's what's happening with your PWD Portal today.</p>
        </div>
        <div class="welcome-date">
            <div class="date-info">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo date('l, F j, Y'); ?></span>
            </div>
            <div class="time-info">
                <i class="fas fa-clock"></i>
                <span id="currentTime"><?php echo date('h:i A'); ?></span>
            </div>
        </div>
    </div>

    <!-- Key Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['total_pwds']); ?></h3>
                <p>Total PWD Records</p>
            </div>
            <div class="stat-trend positive">
                <i class="fas fa-arrow-up"></i>
                <span>+<?php echo $stats['new_users']; ?> this month</span>
            </div>
        </div>

        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['pending_appointments']); ?></h3>
                <p>Pending Appointments</p>
            </div>
            <div class="stat-action">
                <a href="appointments.php?status=pending">View All <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['today_appointments']); ?></h3>
                <p>Today's Appointments</p>
            </div>
            <div class="stat-action">
                <a href="appointments.php">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['month_appointments']); ?></h3>
                <p>This Month's Appointments</p>
            </div>
            <div class="stat-trend positive">
                <i class="fas fa-arrow-up"></i>
                <span>+12% from last month</span>
            </div>
        </div>
    </div>

    <!-- Charts and Analytics Row -->
    <div class="charts-row">
        <div class="chart-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Appointments Trend</h3>
                <span class="card-subtitle">Last 6 months</span>
            </div>
            <div class="card-body">
                <canvas id="appointmentsTrendChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="card-header">
                <h3><i class="fas fa-pie-chart"></i> Disability Types</h3>
                <span class="card-subtitle">Distribution overview</span>
            </div>
            <div class="card-body">
                <canvas id="disabilityTypeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Middle Row: Status and Age Distribution -->
    <div class="charts-row">
        <div class="chart-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Appointment Status</h3>
                <span class="card-subtitle">Current status breakdown</span>
            </div>
            <div class="card-body">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="card-header">
                <h3><i class="fas fa-user-friends"></i> Age Distribution</h3>
                <span class="card-subtitle">PWD by age groups</span>
            </div>
            <div class="card-body">
                <canvas id="ageDistributionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Activity and Appointments -->
    <div class="activity-row">
        <div class="activity-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Activities</h3>
                <a href="logs.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <div class="activity-timeline">
                    <?php if (empty($recent_activities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No recent activities yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['action']; ?>">
                                <i class="fas fa-<?php 
                                    echo $activity['action'] === 'create' ? 'plus' : 
                                        ($activity['action'] === 'update' || $activity['action'] === 'edit' ? 'edit' : 
                                        ($activity['action'] === 'delete' ? 'trash' : 
                                        ($activity['action'] === 'login' ? 'sign-in-alt' : 'info'))); 
                                ?>"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-text">
                                    <strong><?php echo htmlspecialchars($activity['full_name'] ?? $activity['username']); ?></strong>
                                    <?php echo htmlspecialchars($activity['action']); ?> 
                                    <?php echo htmlspecialchars($activity['module']); ?>
                                </p>
                                <span class="activity-time">
                                    <i class="fas fa-clock"></i>
                                     <?php 
        // Create a DateTime object, telling it the time is in UTC
        $utc_time = new DateTime($activity['created_at'], new DateTimeZone('UTC'));
        // Set the object's timezone to your local one
        $utc_time->setTimezone(new DateTimeZone('Asia/Manila'));
        // Format and display the converted time
        echo $utc_time->format('M j, Y g:i A');
    ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="activity-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Recent Appointments</h3>
                <a href="appointments.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <div class="appointments-list">
                    <?php if (empty($recent_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <p>No appointments yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_appointments as $apt): ?>
                        <div class="appointment-item">
                            <div class="appointment-info">
                                <div class="appointment-header">
                                    <h4><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></h4>
                                    <span class="status-badge status-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </div>
                                <p class="appointment-details">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($apt['preferred_date'])); ?>
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('g:i A', strtotime($apt['preferred_time'])); ?>
                                </p>
                                <p class="appointment-ref">
                                    <i class="fas fa-hashtag"></i>
                                    <?php echo htmlspecialchars($apt['reference_number']); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="quick-actions-grid">
            <a href="appointments.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <h4>View Appointments</h4>
                <p>Manage PWD appointments</p>
            </a>

            <a href="records.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h4>PWD Records</h4>
                <p>Manage PWD members</p>
            </a>

            <a href="programs.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <h4>Programs & Applications</h4>
                <p>Post and Approve inquires</p>
            </a>

            <a href="reports.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h4>Generate Report</h4>
                <p>Create custom reports</p>
            </a>

            <a href="map.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h4>View Map</h4>
                <p>Geographic distribution</p>
            </a>

            <a href="feedback.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h4>Manage Feedback</h4>
                <p>Review user feedback</p>
            </a>

            <a href="users.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h4>User Management</h4>
                <p>Manage admin users</p>
            </a>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Update time every second
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    document.getElementById('currentTime').textContent = timeString;
}
setInterval(updateTime, 1000);

// Chart.js default configuration
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
Chart.defaults.color = '#64748b';

// Appointments Trend Chart
const trendCtx = document.getElementById('appointmentsTrendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($appointments_trend, 'month')); ?>,
        datasets: [{
            label: 'Appointments',
            data: <?php echo json_encode(array_column($appointments_trend, 'count')); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: '#3b82f6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                borderRadius: 8,
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
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

// Disability Type Chart
const disabilityCtx = document.getElementById('disabilityTypeChart').getContext('2d');
new Chart(disabilityCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($disability_distribution, 'disability_type')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($disability_distribution, 'count')); ?>,
            backgroundColor: [
                '#3b82f6',
                '#10b981',
                '#f59e0b',
                '#ef4444',
                '#8b5cf6'
            ],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: { size: 12 }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                borderRadius: 8,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Status Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_map('ucfirst', array_column($status_distribution, 'status'))); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($status_distribution, 'count')); ?>,
            backgroundColor: [
                '#f59e0b',
                '#10b981',
                '#ef4444',
                '#3b82f6'
            ],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: { size: 12 }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                borderRadius: 8
            }
        }
    }
});

// Age Distribution Chart
const ageCtx = document.getElementById('ageDistributionChart').getContext('2d');
new Chart(ageCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($age_distribution, 'age_group')); ?>,
        datasets: [{
            label: 'PWDs',
            data: <?php echo json_encode(array_column($age_distribution, 'count')); ?>,
            backgroundColor: [
                '#3b82f6',
                '#10b981',
                '#f59e0b',
                '#ef4444',
                '#8b5cf6'
            ],
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                borderRadius: 8
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
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

// Animate elements on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.stat-card, .chart-card, .activity-card, .action-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'all 0.6s ease';
    observer.observe(el);
});
</script>

<style>
.dashboard-container {
    padding: 30px;
    max-width: 1600px;
    margin: 0 auto;
}

/* Welcome Section */
.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px 40px;
    border-radius: 15px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.welcome-content h1 {
    font-size: 2rem;
    margin-bottom: 10px;
    font-weight: 700;
}

.welcome-content p {
    font-size: 1.1rem;
    opacity: 0.95;
}

.welcome-date {
    display: flex;
    gap: 20px;
    flex-direction: column;
    align-items: flex-end;
}

.date-info, .time-info {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 15px;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    border-left: 5px solid;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.stat-card.primary { border-left-color: #3b82f6; }
.stat-card.warning { border-left-color: #f59e0b; }
.stat-card.success { border-left-color: #10b981; }
.stat-card.info { border-left-color: #8b5cf6; }

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 15px;
}

.stat-card.primary .stat-icon {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.stat-card.warning .stat-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.stat-card.success .stat-icon {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.stat-card.info .stat-icon {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

.stat-details h3 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 5px;
    color: #1e293b;
}

.stat-details p {
    color: #64748b;
    font-size: 1rem;
    font-weight: 500;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 15px;
    font-size: 0.9rem;
    padding: 8px 12px;
    border-radius: 8px;
    background: #f1f5f9;
}

.stat-trend.positive {
    color: #10b981;
    background: #ecfdf5;
}

.stat-action {
    margin-top: 15px;
}

.stat-action a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: gap 0.3s;
}

.stat-action a:hover {
    gap: 10px;
}

/* Charts Row */
.charts-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.chart-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f5f9;
}

.card-header h3 {
    font-size: 1.3rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-subtitle {
    color: #64748b;
    font-size: 0.9rem;
}

.card-body {
    height: 250px;
    position: relative;
}

/* Activity Row */
.activity-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

/* Fixed overflow issues for activity cards */
.activity-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    max-height: 550px; /* Constrain total card height */
}

.activity-card .card-body {
    flex: 1;
    overflow: hidden; /* Prevent body from expanding beyond card */
    min-height: 0; /* Allow flex child to shrink below content size */
}

.view-all {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: gap 0.3s;
    flex-shrink: 0; /* Prevent header from shrinking */
}

.view-all:hover {
    gap: 10px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    font-size: 1.1rem;
}

/* Fixed scrolling for activity timeline */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 15px;
    height: 100%; /* Take full height of parent */
    overflow-y: auto;
    overflow-x: hidden; /* Prevent horizontal scroll */
    padding-right: 10px;
}

.activity-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 10px;
    transition: all 0.3s;
    flex-shrink: 0; /* Prevent items from shrinking */
}

.activity-item:hover {
    background: #f1f5f9;
    transform: translateX(5px);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-icon.create {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.activity-icon.update, .activity-icon.edit {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.activity-icon.delete {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.activity-icon.view, .activity-icon.login {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

.activity-content {
    flex: 1;
    min-width: 0; /* Allow text to wrap properly */
}

.activity-text {
    margin-bottom: 5px;
    color: #1e293b;
    word-wrap: break-word; /* Prevent long text from overflowing */
}

.activity-time {
    color: #64748b;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Fixed scrolling for appointments list */
.appointments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
    height: 100%; /* Take full height of parent */
    overflow-y: auto;
    overflow-x: hidden; /* Prevent horizontal scroll */
    padding-right: 10px;
}

.appointment-item {
    padding: 15px;
    background: #f8fafc;
    border-radius: 10px;
    border-left: 4px solid #3b82f6;
    transition: all 0.3s;
    flex-shrink: 0; /* Prevent items from shrinking */
}

.appointment-item:hover {
    background: #f1f5f9;
    transform: translateX(5px);
}

.appointment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    gap: 10px; /* Add gap to prevent overlap */
}

.appointment-header h4 {
    color: #1e293b;
    font-size: 1.1rem;
    word-wrap: break-word; /* Prevent long names from overflowing */
    flex: 1;
    min-width: 0;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap; /* Keep status on one line */
    flex-shrink: 0;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-confirmed {
    background: #dbeafe;
    color: #1e40af;
}

.status-completed {
    background: #d1fae5;
    color: #065f46;
}

.status-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.appointment-details, .appointment-ref {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap; /* Allow wrapping on small screens */
}

.appointment-details i, .appointment-ref i {
    width: 16px;
    flex-shrink: 0;
}

/* Quick Actions */
.quick-actions-section {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.quick-actions-section h3 {
    font-size: 1.3rem;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.action-card {
    text-align: center;
    padding: 25px 20px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border-color: #3b82f6;
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 15px;
}

.action-card h4 {
    color: #1e293b;
    font-size: 1.1rem;
    margin-bottom: 8px;
}

.action-card p {
    color: #64748b;
    font-size: 0.9rem;
}

/* Scrollbar Styles */
.activity-timeline::-webkit-scrollbar,
.appointments-list::-webkit-scrollbar {
    width: 6px;
}

.activity-timeline::-webkit-scrollbar-track,
.appointments-list::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.activity-timeline::-webkit-scrollbar-thumb,
.appointments-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.activity-timeline::-webkit-scrollbar-thumb:hover,
.appointments-list::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .charts-row, .activity-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 15px;
    }

    .welcome-section {
        flex-direction: column;
        align-items: flex-start;
        padding: 20px;
    }

    .welcome-date {
        align-items: flex-start;
        margin-top: 15px;
    }

    .welcome-content h1 {
        font-size: 1.5rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .charts-row {
        grid-template-columns: 1fr;
    }

    .activity-row {
        grid-template-columns: 1fr;
    }

    .quick-actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }

    .card-body {
        height: 200px;
    }
    
    /* Adjust activity card height for mobile */
    .activity-card {
        max-height: 450px;
    }
}

@media (max-width: 480px) {
    .welcome-content h1 {
        font-size: 1.3rem;
    }

    .stat-details h3 {
        font-size: 2rem;
    }
    
    /* Further reduce activity card height for small screens */
    .activity-card {
        max-height: 400px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
