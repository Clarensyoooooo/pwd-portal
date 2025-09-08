<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'reports.view');

$admin = getCurrentAdmin($pdo);

// Get filter parameters
$report_type = $_GET['type'] ?? 'analytics';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$status_filter = $_GET['status'] ?? '';
$disability_filter = $_GET['disability'] ?? '';
$age_group = $_GET['age_group'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$time_period = $_GET['time_period'] ?? 'monthly';

// Generate reports based on type
$report_data = [];
switch ($report_type) {
    case 'analytics':
        $report_data = generateAnalyticsReport($pdo, $date_from, $date_to, $time_period);
        break;
    case 'demographics':
        $report_data = generateDemographicsReport($pdo, $date_from, $date_to, $age_group, $barangay_filter);
        break;
    case 'services':
        $report_data = generateServicesReport($pdo, $date_from, $date_to, $disability_filter);
        break;
    case 'performance':
        $report_data = generatePerformanceReport($pdo, $date_from, $date_to);
        break;
    case 'resources':
        $report_data = generateResourcesReport($pdo, $date_from, $date_to);
        break;
}

function generateAnalyticsReport($pdo, $date_from, $date_to, $time_period) {
    $data = [];
    
    // Summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_individuals,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_profiles,
            SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as active_ids,
            SUM(CASE WHEN status = 'pending_validation' THEN 1 ELSE 0 END) as pending_support,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as with_coordinates,
            COUNT(CASE WHEN barangay IS NOT NULL THEN 1 END) as assigned_to_barangay
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['summary'] = $stmt->fetch();
    
    // Age group distribution
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Children (0-17)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN 'Young Adults (18-30)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN 'Adults (31-50)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 65 THEN 'Mature Adults (51-65)'
                ELSE 'Senior Citizens (65+)'
            END as age_group,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records WHERE created_at BETWEEN ? AND ?), 1) as percentage
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
        GROUP BY age_group
        ORDER BY count DESC
    ");
    $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    $data['age_groups'] = $stmt->fetchAll();
    
    // Gender distribution
    $stmt = $pdo->prepare("
        SELECT 
            gender,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records WHERE created_at BETWEEN ? AND ?), 1) as percentage
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
        GROUP BY gender
    ");
    $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    $data['gender_distribution'] = $stmt->fetchAll();
    
    // Support categories distribution
    $stmt = $pdo->prepare("
        SELECT 
            disability_type as support_category,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records WHERE created_at BETWEEN ? AND ?), 1) as percentage
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
        AND disability_type IS NOT NULL
        GROUP BY disability_type 
        ORDER BY count DESC
    ");
    $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    $data['support_categories'] = $stmt->fetchAll();
    
    // Barangay distribution
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            COUNT(*) as count,
            COUNT(CASE WHEN status = 'issued' THEN 1 END) as active_ids,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
        AND barangay IS NOT NULL
        GROUP BY barangay 
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['barangay_distribution'] = $stmt->fetchAll();
    
    // Time-based trends
    $date_format = $time_period == 'yearly' ? '%Y' : ($time_period == 'quarterly' ? '%Y-Q%q' : '%Y-%m');
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '{$date_format}') as period,
            COUNT(*) as registrations,
            SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as ids_issued
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '{$date_format}')
        ORDER BY period
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['trends'] = $stmt->fetchAll();
    
    return $data;
}

function generateDemographicsReport($pdo, $date_from, $date_to, $age_group, $barangay_filter) {
    $data = [];
    
    $where_conditions = ["created_at BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
    if ($age_group) {
        switch ($age_group) {
            case 'children':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18";
                break;
            case 'adults':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 64";
                break;
            case 'seniors':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 65";
                break;
        }
    }
    
    if ($barangay_filter) {
        $where_conditions[] = "barangay = ?";
        $params[] = $barangay_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Barangay profiles
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            COUNT(*) as total_individuals,
            COUNT(CASE WHEN gender = 'Male' THEN 1 END) as male_count,
            COUNT(CASE WHEN gender = 'Female' THEN 1 END) as female_count,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 1 END) as children_count,
            COUNT(CASE WHEN status = 'issued' THEN 1 END) as active_ids
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        GROUP BY barangay
        ORDER BY total_individuals DESC
    ");
    $stmt->execute($params);
    $data['barangay_profiles'] = $stmt->fetchAll();
    
    // Disability distribution by barangay
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            disability_type,
            COUNT(*) as count
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        AND disability_type IS NOT NULL
        GROUP BY barangay, disability_type
        ORDER BY barangay, count DESC
    ");
    $stmt->execute($params);
    $data['disability_by_barangay'] = $stmt->fetchAll();
    
    return $data;
}

function generateServicesReport($pdo, $date_from, $date_to, $disability_filter) {
    $data = [];
    
    $where_conditions = ["created_at BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
    if ($disability_filter) {
        $where_conditions[] = "disability_type = ?";
        $params[] = $disability_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Service utilization by disability type
    $stmt = $pdo->prepare("
        SELECT 
            disability_type,
            COUNT(*) as total_individuals,
            COUNT(CASE WHEN status = 'issued' THEN 1 END) as active_beneficiaries,
            COUNT(CASE WHEN employment_status = 'Employed' THEN 1 END) as employed_count,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 1 END) as children_count
        FROM pwd_records
        {$where_clause}
        AND disability_type IS NOT NULL
        GROUP BY disability_type
        ORDER BY total_individuals DESC
    ");
    $stmt->execute($params);
    $data['service_utilization'] = $stmt->fetchAll();
    
    // Barangay service coverage
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            disability_type,
            COUNT(*) as individuals_served,
            COUNT(CASE WHEN status = 'issued' THEN 1 END) as active_beneficiaries,
            COUNT(CASE WHEN employment_status = 'Employed' THEN 1 END) as employed_count
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        AND disability_type IS NOT NULL
        GROUP BY barangay, disability_type
        ORDER BY barangay, individuals_served DESC
    ");
    $stmt->execute($params);
    $data['barangay_services'] = $stmt->fetchAll();
    
    return $data;
}

function generatePerformanceReport($pdo, $date_from, $date_to) {
    $data = [];
    
    // Data completeness metrics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            COUNT(CASE WHEN first_name IS NOT NULL AND first_name != '' THEN 1 END) as has_first_name,
            COUNT(CASE WHEN date_of_birth IS NOT NULL THEN 1 END) as has_birth_date,
            COUNT(CASE WHEN disability_type IS NOT NULL AND disability_type != '' THEN 1 END) as has_disability_type,
            COUNT(CASE WHEN address_line1 IS NOT NULL AND address_line1 != '' THEN 1 END) as has_address,
            COUNT(CASE WHEN phone_number IS NOT NULL AND phone_number != '' THEN 1 END) as has_phone,
            COUNT(CASE WHEN barangay IS NOT NULL AND barangay != '' THEN 1 END) as has_barangay,
            AVG(DATEDIFF(COALESCE(validation_date, CURDATE()), created_at)) as avg_processing_days
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['completeness'] = $stmt->fetch();
    
    // Monthly performance trends
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as new_registrations,
            COUNT(CASE WHEN validation_date IS NOT NULL THEN 1 END) as validated_this_month,
            AVG(DATEDIFF(COALESCE(validation_date, CURDATE()), created_at)) as avg_processing_days
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['monthly_performance'] = $stmt->fetchAll();
    
    return $data;
}

function generateResourcesReport($pdo, $date_from, $date_to) {
    $data = [];
    
    // Barangay-specific resource recommendations
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            disability_type,
            COUNT(*) as count,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            COUNT(CASE WHEN employment_status = 'Unemployed' THEN 1 END) as unemployed_count,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 1 END) as children_count
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
        AND barangay IS NOT NULL
        AND disability_type IS NOT NULL
        GROUP BY barangay, disability_type
        ORDER BY barangay, count DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $barangay_disabilities = $stmt->fetchAll();
    
    // Group by barangay and get recommendations
    $barangay_recommendations = [];
    foreach ($barangay_disabilities as $row) {
        $barangay = $row['barangay'];
        if (!isset($barangay_recommendations[$barangay])) {
            $barangay_recommendations[$barangay] = [
                'total_pwd' => 0,
                'disabilities' => [],
                'unemployed_count' => 0,
                'children_count' => 0
            ];
        }
        
        $barangay_recommendations[$barangay]['disabilities'][] = [
            'type' => $row['disability_type'],
            'count' => $row['count'],
            'avg_age' => $row['avg_age']
        ];
        
        $barangay_recommendations[$barangay]['total_pwd'] += $row['count'];
        $barangay_recommendations[$barangay]['unemployed_count'] += $row['unemployed_count'];
        $barangay_recommendations[$barangay]['children_count'] += $row['children_count'];
    }
    
    // Add service recommendations
    $service_recommendations = [
        'Physical Disability' => [
            'priority' => 'High',
            'services' => [
                'Mobile physical therapy units',
                'Wheelchair and mobility aid distribution',
                'Accessible public transportation',
                'Ramp construction program'
            ]
        ],
        'Visual Impairment' => [
            'priority' => 'High',
            'services' => [
                'Braille literacy programs',
                'White cane training sessions',
                'Screen reader software training',
                'Audio book library'
            ]
        ],
        'Hearing Impairment' => [
            'priority' => 'Medium',
            'services' => [
                'Sign language interpretation services',
                'Hearing aid maintenance program',
                'Deaf community social groups',
                'Visual alert system installation'
            ]
        ],
        'Intellectual Disability' => [
            'priority' => 'High',
            'services' => [
                'Special education programs',
                'Life skills training workshops',
                'Supported employment initiatives',
                'Family counseling services'
            ]
        ],
        'Mental/Psychosocial Disability' => [
            'priority' => 'Critical',
            'services' => [
                'Mental health counseling services',
                'Peer support group meetings',
                'Crisis intervention hotline',
                'Medication management programs'
            ]
        ]
    ];
    
    foreach ($barangay_recommendations as $barangay => &$data_item) {
        $data_item['recommended_services'] = [];
        
        // Sort disabilities by count
        usort($data_item['disabilities'], function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Get top 3 disabilities
        $top_disabilities = array_slice($data_item['disabilities'], 0, 3);
        
        foreach ($top_disabilities as $disability) {
            if (isset($service_recommendations[$disability['type']])) {
                $data_item['recommended_services'][] = [
                    'disability_type' => $disability['type'],
                    'affected_count' => $disability['count'],
                    'priority' => $service_recommendations[$disability['type']]['priority'],
                    'services' => $service_recommendations[$disability['type']]['services']
                ];
            }
        }
    }
    
    $data['barangay_recommendations'] = $barangay_recommendations;
    
    return $data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Insights - PWD Support Portal</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-header {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e40af 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .analytics-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .analytics-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .report-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
            overflow-x: auto;
        }
        
        .report-tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            white-space: nowrap;
            text-decoration: none;
        }
        
        .report-tab.active {
            color: #2c5aa0;
            border-bottom-color: #2c5aa0;
        }
        
        .report-tab:hover {
            color: #2c5aa0;
            background: #f8fafc;
        }
        
        .filters-panel {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .analytics-card h3 {
            margin: 0 0 1rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid #cbd5e1;
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c5aa0;
            margin: 0;
        }
        
        .metric-label {
            color: #64748b;
            font-weight: 500;
            margin: 0.5rem 0 0 0;
        }
        
        .metric-change {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            color: #10b981;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2c5aa0, #3b82f6);
            transition: width 0.3s ease;
        }
        
        .resource-card {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin: 0.5rem 0;
        }
        
        .resource-card h4 {
            margin: 0 0 0.5rem 0;
            color: #0c4a6e;
        }
        
        .resource-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .resource-list li {
            padding: 0.25rem 0;
            color: #0369a1;
        }
        
        .resource-list li:before {
            content: "→";
            margin-right: 0.5rem;
            color: #2c5aa0;
        }
        
        .priority-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-critical {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .priority-high {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fed7aa;
        }
        
        .priority-medium {
            background: #dbeafe;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        
        @media (max-width: 768px) {
            .analytics-header h1 {
                font-size: 2rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="analytics-header">
            <h1><i class="fas fa-chart-line"></i> Community Analytics & Insights</h1>
            <p>Data-driven insights to support our community and improve services</p>
        </div>
        
        <!-- Report Type Tabs -->
        <div class="report-tabs">
            <a href="?type=analytics" class="report-tab <?php echo $report_type == 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Overview Analytics
            </a>
            <a href="?type=demographics" class="report-tab <?php echo $report_type == 'demographics' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Demographics
            </a>
            <a href="?type=services" class="report-tab <?php echo $report_type == 'services' ? 'active' : ''; ?>">
                <i class="fas fa-hands-helping"></i> Services by Area
            </a>
            <a href="?type=performance" class="report-tab <?php echo $report_type == 'performance' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Data Quality
            </a>
            <a href="?type=resources" class="report-tab <?php echo $report_type == 'resources' ? 'active' : ''; ?>">
                <i class="fas fa-lightbulb"></i> Resource Planning
            </a>
        </div>
        
        <!-- Filters Panel -->
        <div class="filters-panel">
            <form method="GET" id="filtersForm">
                <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                <div class="filters-grid">
                    <div class="form-group">
                        <label for="date_from">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <?php if ($report_type == 'analytics'): ?>
                    <div class="form-group">
                        <label for="time_period">Time Period</label>
                        <select name="time_period" id="time_period" class="form-control">
                            <option value="monthly" <?php echo $time_period == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarterly" <?php echo $time_period == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="yearly" <?php echo $time_period == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Update Analytics
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" onclick="exportReport()" class="btn btn-outline">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Report Content -->
        <div id="reportContent">
            <?php if ($report_type == 'analytics'): ?>
                <?php include 'reports/analytics.php'; ?>
            <?php elseif ($report_type == 'demographics'): ?>
                <?php include 'reports/demographics.php'; ?>
            <?php elseif ($report_type == 'services'): ?>
                <?php include 'reports/services.php'; ?>
            <?php elseif ($report_type == 'performance'): ?>
                <?php include 'reports/performance.php'; ?>
            <?php elseif ($report_type == 'resources'): ?>
                <?php include 'reports/resources.php'; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
    <script>
        function exportReport() {
            const form = document.getElementById('filtersForm');
            const formData = new FormData(form);
            formData.append('export', 'csv');
            
            const params = new URLSearchParams(formData);
            window.location.href = `api/export_report.php?${params.toString()}`;
        }
        
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeAnalyticsCharts();
        });
        
        function initializeAnalyticsCharts() {
            // This will be called by individual report templates
        }
    </script>
</body>
</html>
