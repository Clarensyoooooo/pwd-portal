<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'reports.view');

$admin = getCurrentAdmin($pdo);

// Get filter parameters
$report_type = $_GET['type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$status_filter = $_GET['status'] ?? '';
$disability_filter = $_GET['disability'] ?? '';

// Generate reports based on type
$report_data = [];
switch ($report_type) {
    case 'overview':
        $report_data = generateOverviewReport($pdo, $date_from, $date_to);
        break;
    case 'appointments':
        $report_data = generateAppointmentReport($pdo, $date_from, $date_to, $status_filter);
        break;
    case 'records':
        $report_data = generateRecordsReport($pdo, $date_from, $date_to, $status_filter, $disability_filter);
        break;
    case 'feedback':
        $report_data = generateFeedbackReport($pdo, $date_from, $date_to);
        break;
    case 'geographic':
        $report_data = generateGeographicReport($pdo, $date_from, $date_to);
        break;
}

function generateOverviewReport($pdo, $date_from, $date_to) {
    $data = [];
    
    // Total statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_records,
            SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued_records,
            SUM(CASE WHEN status = 'pending_validation' THEN 1 ELSE 0 END) as pending_records
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['totals'] = $stmt->fetch();
    
    // Appointments statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments
        FROM appointments 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['appointments'] = $stmt->fetch();
    
    // Disability type distribution
    $stmt = $pdo->prepare("
        SELECT disability_type, COUNT(*) as count 
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY disability_type 
        ORDER BY count DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['disability_types'] = $stmt->fetchAll();
    
    // Monthly trends
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['monthly_trends'] = $stmt->fetchAll();
    
    return $data;
}

function generateAppointmentReport($pdo, $date_from, $date_to, $status_filter) {
    $data = [];
    
    $where_clause = "WHERE a.created_at BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    
    if ($status_filter) {
        $where_clause .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    // Appointment details
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name, u.phone, u.email
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        {$where_clause}
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    $data['appointments'] = $stmt->fetchAll();
    
    // Status distribution
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM appointments a
        {$where_clause}
        GROUP BY status
    ");
    $stmt->execute($params);
    $data['status_distribution'] = $stmt->fetchAll();
    
    return $data;
}

function generateRecordsReport($pdo, $date_from, $date_to, $status_filter, $disability_filter) {
    $data = [];
    
    $where_clause = "WHERE created_at BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    
    if ($status_filter) {
        $where_clause .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    if ($disability_filter) {
        $where_clause .= " AND disability_type = ?";
        $params[] = $disability_filter;
    }
    
    // Records details
    $stmt = $pdo->prepare("
        SELECT * FROM pwd_records
        {$where_clause}
        ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    $data['records'] = $stmt->fetchAll();
    
    // Age distribution
    $stmt = $pdo->prepare("
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
        {$where_clause}
        GROUP BY age_group
    ");
    $stmt->execute($params);
    $data['age_distribution'] = $stmt->fetchAll();
    
    // Gender distribution
    $stmt = $pdo->prepare("
        SELECT gender, COUNT(*) as count
        FROM pwd_records
        {$where_clause}
        GROUP BY gender
    ");
    $stmt->execute($params);
    $data['gender_distribution'] = $stmt->fetchAll();
    
    return $data;
}

function generateFeedbackReport($pdo, $date_from, $date_to) {
    $data = [];
    
    // Feedback details
    $stmt = $pdo->prepare("
        SELECT f.*, u.first_name, u.last_name
        FROM feedback f
        LEFT JOIN users u ON f.user_id = u.id
        WHERE f.created_at BETWEEN ? AND ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['feedback'] = $stmt->fetchAll();
    
    // Rating distribution
    $stmt = $pdo->prepare("
        SELECT rating, COUNT(*) as count
        FROM feedback
        WHERE created_at BETWEEN ? AND ? AND rating IS NOT NULL
        GROUP BY rating
        ORDER BY rating
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['rating_distribution'] = $stmt->fetchAll();
    
    // Average rating
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating
        FROM feedback
        WHERE created_at BETWEEN ? AND ? AND rating IS NOT NULL
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['avg_rating'] = $stmt->fetch()['avg_rating'];
    
    return $data;
}

function generateGeographicReport($pdo, $date_from, $date_to) {
    $data = [];
    
    // Records by barangay
    $stmt = $pdo->prepare("
        SELECT barangay, COUNT(*) as count
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
        GROUP BY barangay
        ORDER BY count DESC
        LIMIT 20
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['by_barangay'] = $stmt->fetchAll();
    
    // Records by city/municipality
    $stmt = $pdo->prepare("
        SELECT city_municipality, COUNT(*) as count
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
        GROUP BY city_municipality
        ORDER BY count DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['by_city'] = $stmt->fetchAll();
    
    // Records with coordinates
    $stmt = $pdo->prepare("
        SELECT pwd_id_number, first_name, last_name, latitude, longitude, disability_type
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ? 
        AND latitude IS NOT NULL 
        AND longitude IS NOT NULL
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['with_coordinates'] = $stmt->fetchAll();
    
    return $data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <div class="page-actions">
                <button onclick="exportReport()" class="btn btn-primary">
                    <i class="fas fa-download"></i> Export Report
                </button>
                <button onclick="printReport()" class="btn btn-outline">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Report Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Report Filters</h3>
            </div>
            <div class="card-content">
                <form method="GET" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type">Report Type</label>
                            <select name="type" id="type" class="form-control">
                                <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview</option>
                                <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                                <option value="records" <?php echo $report_type == 'records' ? 'selected' : ''; ?>>PWD Records</option>
                                <option value="feedback" <?php echo $report_type == 'feedback' ? 'selected' : ''; ?>>Feedback</option>
                                <option value="geographic" <?php echo $report_type == 'geographic' ? 'selected' : ''; ?>>Geographic</option>
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
                        
                        <?php if ($report_type == 'appointments' || $report_type == 'records'): ?>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">All Statuses</option>
                                <?php if ($report_type == 'appointments'): ?>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <?php else: ?>
                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="pending_validation" <?php echo $status_filter == 'pending_validation' ? 'selected' : ''; ?>>Pending Validation</option>
                                    <option value="validated" <?php echo $status_filter == 'validated' ? 'selected' : ''; ?>>Validated</option>
                                    <option value="issued" <?php echo $status_filter == 'issued' ? 'selected' : ''; ?>>Issued</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Report Content -->
        <div id="reportContent">
            <?php if ($report_type == 'overview'): ?>
                <?php include 'reports/overview.php'; ?>
            <?php elseif ($report_type == 'appointments'): ?>
                <?php include 'reports/appointments.php'; ?>
            <?php elseif ($report_type == 'records'): ?>
                <?php include 'reports/records.php'; ?>
            <?php elseif ($report_type == 'feedback'): ?>
                <?php include 'reports/feedback.php'; ?>
            <?php elseif ($report_type == 'geographic'): ?>
                <?php include 'reports/geographic.php'; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
    <script>
        function exportReport() {
            const reportType = '<?php echo $report_type; ?>';
            const dateFrom = '<?php echo $date_from; ?>';
            const dateTo = '<?php echo $date_to; ?>';
            const status = '<?php echo $status_filter; ?>';
            
            const params = new URLSearchParams({
                export: 'csv',
                type: reportType,
                date_from: dateFrom,
                date_to: dateTo,
                status: status
            });
            
            window.location.href = `api/export_report.php?${params.toString()}`;
        }
        
        function printReport() {
            window.print();
        }
        
        // Initialize charts based on report type
        document.addEventListener('DOMContentLoaded', function() {
            initializeReportCharts();
        });
        
        function initializeReportCharts() {
            // This will be called by individual report templates
        }
    </script>
</body>
</html>
