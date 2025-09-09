<?php
require_once '../config.php';

requireAdminLogin();
requirePermission($pdo, 'reports.export');

$report_type = $_GET['type'] ?? 'analytics';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$disability_filter = $_GET['disability'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$employment_filter = $_GET['employment'] ?? '';
$age_group = $_GET['age_group'] ?? '';

try {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($report_type) {
        case 'appointments':
            exportAppointmentReport($pdo, $output, $date_from, $date_to, $status_filter);
            break;
        case 'records':
            exportRecordsReport($pdo, $output, $date_from, $date_to, $status_filter);
            break;
        case 'feedback':
            exportFeedbackReport($pdo, $output, $date_from, $date_to);
            break;
        case 'analytics':
            exportAnalyticsReport($pdo, $output, $date_from, $date_to, $status_filter, $disability_filter, $barangay_filter, $gender_filter, $employment_filter);
            break;
        case 'demographics':
            exportDemographicsReport($pdo, $output, $date_from, $date_to, $age_group, $barangay_filter, $gender_filter, $disability_filter);
            break;
        case 'resources':
            exportResourcesReport($pdo, $output, $date_from, $date_to, $barangay_filter, $disability_filter);
            break;
        default:
            exportAnalyticsReport($pdo, $output, $date_from, $date_to, $status_filter, $disability_filter, $barangay_filter, $gender_filter, $employment_filter);
    }
    
    fclose($output);
    
    // Log the export activity
    logAdminActivity($pdo, 'export', 'reports', 'report', null, [
        'report_type' => $report_type,
        'date_range' => [$date_from, $date_to],
        'filters' => [
            'status' => $status_filter,
            'disability' => $disability_filter,
            'barangay' => $barangay_filter,
            'gender' => $gender_filter,
            'employment' => $employment_filter
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
    error_log('Export error: ' . $e->getMessage());
}

function exportAppointmentReport($pdo, $output, $date_from, $date_to, $status_filter) {
    $where_clause = "WHERE a.created_at BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    
    if ($status_filter) {
        $where_clause .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name, u.phone, u.email
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        {$where_clause}
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    
    // Write header
    fputcsv($output, [
        'Reference Number', 'First Name', 'Last Name', 'Email', 'Phone',
        'Appointment Type', 'Preferred Date', 'Preferred Time', 'Status',
        'Created Date', 'Confirmed Date'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['reference_number'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['appointment_type'],
            $row['preferred_date'],
            $row['preferred_time'],
            $row['status'],
            $row['created_at'],
            $row['confirmed_at']
        ]);
    }
}

function exportRecordsReport($pdo, $output, $date_from, $date_to, $status_filter) {
    $where_clause = "WHERE created_at BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    
    if ($status_filter) {
        $where_clause .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM pwd_records
        {$where_clause}
        ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    
    // Write header
    fputcsv($output, [
        'PWD ID', 'First Name', 'Last Name', 'Date of Birth', 'Gender',
        'Disability Type', 'Address', 'City/Municipality', 'Province',
        'Status', 'Created Date', 'Validation Date'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['pwd_id_number'],
            $row['first_name'],
            $row['last_name'],
            $row['date_of_birth'],
            $row['gender'],
            $row['disability_type'],
            $row['address_line1'],
            $row['city_municipality'],
            $row['province'],
            $row['status'],
            $row['created_at'],
            $row['validation_date']
        ]);
    }
}

function exportFeedbackReport($pdo, $output, $date_from, $date_to) {
    $stmt = $pdo->prepare("
        SELECT f.*, u.first_name, u.last_name
        FROM feedback f
        LEFT JOIN users u ON f.user_id = u.id
        WHERE f.created_at BETWEEN ? AND ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    
    // Write header
    fputcsv($output, [
        'Name', 'Email', 'Subject', 'Rating', 'Status',
        'Created Date', 'Responded Date'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['name'],
            $row['email'],
            $row['subject'],
            $row['rating'],
            $row['status'],
            $row['created_at'],
            $row['responded_at']
        ]);
    }
}

function exportOverviewReport($pdo, $output, $date_from, $date_to) {
    // Export summary statistics
    fputcsv($output, ['PWD Portal Overview Report']);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_records,
            SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued_records
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $stats = $stmt->fetch();
    
    fputcsv($output, ['Total PWD Records:', $stats['total_records']]);
    fputcsv($output, ['Validated Records:', $stats['validated_records']]);
    fputcsv($output, ['Issued Records:', $stats['issued_records']]);
}

function exportAnalyticsReport($pdo, $output, $date_from, $date_to, $status_filter, $disability_filter, $barangay_filter, $gender_filter, $employment_filter) {
    // Build WHERE conditions
    $where_conditions = ["created_at BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
    if ($status_filter) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    if ($disability_filter) {
        $where_conditions[] = "disability_type = ?";
        $params[] = $disability_filter;
    }
    
    if ($barangay_filter) {
        $where_conditions[] = "barangay = ?";
        $params[] = $barangay_filter;
    }
    
    if ($gender_filter) {
        $where_conditions[] = "gender = ?";
        $params[] = $gender_filter;
    }
    
    if ($employment_filter) {
        $where_conditions[] = "employment_status = ?";
        $params[] = $employment_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            pwd_id_number,
            first_name,
            last_name,
            date_of_birth,
            gender,
            disability_type,
            barangay,
            city_municipality,
            province,
            employment_status,
            status,
            created_at,
            validation_date
        FROM pwd_records
        {$where_clause}
        ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    
    // Write header
    fputcsv($output, [
        'PWD ID Number',
        'First Name',
        'Last Name',
        'Date of Birth',
        'Gender',
        'Disability Type',
        'Barangay',
        'City/Municipality',
        'Province',
        'Employment Status',
        'Status',
        'Registration Date',
        'Validation Date'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['pwd_id_number'],
            $row['first_name'],
            $row['last_name'],
            $row['date_of_birth'],
            $row['gender'],
            $row['disability_type'],
            $row['barangay'],
            $row['city_municipality'],
            $row['province'],
            $row['employment_status'],
            $row['status'],
            $row['created_at'],
            $row['validation_date']
        ]);
    }
}

function exportDemographicsReport($pdo, $output, $date_from, $date_to, $age_group, $barangay_filter, $gender_filter, $disability_filter) {
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
    
    if ($gender_filter) {
        $where_conditions[] = "gender = ?";
        $params[] = $gender_filter;
    }
    
    if ($disability_filter) {
        $where_conditions[] = "disability_type = ?";
        $params[] = $disability_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Export barangay summary
    fputcsv($output, ['PWD Demographics Report']);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    fputcsv($output, ['Barangay Summary']);
    fputcsv($output, ['Barangay', 'Total PWDs', 'Male', 'Female', 'Children', 'Average Age', 'Active IDs']);
    
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            COUNT(*) as total_individuals,
            COUNT(CASE WHEN gender = 'Male' THEN 1 END) as male_count,
            COUNT(CASE WHEN gender = 'Female' THEN 1 END) as female_count,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 1 END) as children_count,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            COUNT(CASE WHEN status = 'issued' THEN 1 END) as active_ids
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        GROUP BY barangay
        ORDER BY total_individuals DESC
    ");
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['barangay'],
            $row['total_individuals'],
            $row['male_count'],
            $row['female_count'],
            $row['children_count'],
            round($row['avg_age'], 1),
            $row['active_ids']
        ]);
    }
}

function exportResourcesReport($pdo, $output, $date_from, $date_to, $barangay_filter, $disability_filter) {
    $where_conditions = ["created_at BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
    if ($barangay_filter) {
        $where_conditions[] = "barangay = ?";
        $params[] = $barangay_filter;
    }
    
    if ($disability_filter) {
        $where_conditions[] = "disability_type = ?";
        $params[] = $disability_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Export resource recommendations
    fputcsv($output, ['PWD Resource Planning Report']);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    fputcsv($output, ['Barangay Resource Needs']);
    fputcsv($output, ['Barangay', 'Disability Type', 'Count', 'Unemployed', 'Children', 'Average Age']);
    
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            disability_type,
            COUNT(*) as count,
            COUNT(CASE WHEN employment_status = 'Unemployed' THEN 1 END) as unemployed_count,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 1 END) as children_count,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        AND disability_type IS NOT NULL
        GROUP BY barangay, disability_type
        ORDER BY barangay, count DESC
    ");
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['barangay'],
            $row['disability_type'],
            $row['count'],
            $row['unemployed_count'],
            $row['children_count'],
            round($row['avg_age'], 1)
        ]);
    }
}
?>
