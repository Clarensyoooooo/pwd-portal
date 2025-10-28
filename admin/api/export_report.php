<?php
require_once '../config.php';
requireAdminLogin($pdo);
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
    header('Content-Disposition: attachment; filename="PWD_' . $report_type . '_report_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($report_type) {
        case 'analytics':
            exportAnalyticsReport($pdo, $output, $date_from, $date_to, $status_filter, $disability_filter, $barangay_filter, $gender_filter, $employment_filter);
            break;
        case 'demographics':
            exportDemographicsReport($pdo, $output, $date_from, $date_to, $age_group, $barangay_filter, $gender_filter, $disability_filter);
            break;
        case 'resources':
            exportResourcesReport($pdo, $output, $date_from, $date_to, $barangay_filter, $disability_filter);
            break;
        case 'appointments':
            exportAppointmentsReport($pdo, $output, $status_filter, $date_from, $date_to);
            break;
        case 'records':
            exportRecordsReport($pdo, $output, $status_filter, $disability_filter, $barangay_filter, $gender_filter, $employment_filter);
            break;
        default:
            exportAnalyticsReport($pdo, $output, $date_from, $date_to, $status_filter, $disability_filter, $barangay_filter, $gender_filter, $employment_filter);
    }
    
    fclose($output);
    
    // Log the export activity
    logAdminActivity($pdo, 'export', 'reports', 'report', null, [
        'report_type' => $report_type,
        'date_range' => [$date_from, $date_to],
        'filters' => array_filter([
            'status' => $status_filter,
            'disability' => $disability_filter,
            'barangay' => $barangay_filter,
            'gender' => $gender_filter,
            'employment' => $employment_filter,
            'age_group' => $age_group
        ])
    ]);
    
    exit;
    
} catch (Exception $e) {
    // If headers haven't been sent, we can send an error
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain');
    }
    echo 'Export failed: ' . $e->getMessage();
    error_log('Export error: ' . $e->getMessage());
    exit;
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
    
    // Write report header
    fputcsv($output, ['PWD Analytics Report']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
    // Write data header
    fputcsv($output, [
        'PWD ID Number',
        'First Name',
        'Last Name',
        'Date of Birth',
        'Age',
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
    
    $stmt = $pdo->prepare("
        SELECT 
            pwd_id_number,
            first_name,
            last_name,
            date_of_birth,
            TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age,
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
    
    // Write data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['pwd_id_number'] ?? 'N/A',
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['date_of_birth'] ?? '',
            $row['age'] ?? '',
            $row['gender'] ?? '',
            $row['disability_type'] ?? '',
            $row['barangay'] ?? '',
            $row['city_municipality'] ?? '',
            $row['province'] ?? '',
            $row['employment_status'] ?? 'Not Specified',
            $row['status'] ?? '',
            $row['created_at'] ?? '',
            $row['validation_date'] ?? ''
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
    
    // Write report header
    fputcsv($output, ['PWD Demographics Report']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
    // Export barangay summary
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
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
    
    fputcsv($output, []);
    fputcsv($output, ['Disability Distribution by Barangay']);
    fputcsv($output, ['Barangay', 'Disability Type', 'Count']);
    
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
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['barangay'],
            $row['disability_type'],
            $row['count']
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
    
    // Write report header
    fputcsv($output, ['PWD Resource Planning Report']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
    // Export resource needs by barangay
    fputcsv($output, ['Barangay Resource Needs']);
    fputcsv($output, ['Barangay', 'Disability Type', 'Affected Count', 'Unemployed', 'Children', 'Average Age', 'Priority Level']);
    
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
    
    // Priority mapping
    $priorities = [
        'Physical Disability' => 'High',
        'Visual Impairment' => 'High',
        'Hearing Impairment' => 'Medium',
        'Intellectual Disability' => 'High',
        'Mental/Psychosocial Disability' => 'Critical',
        'Speech Impairment' => 'Medium',
        'Multiple Disabilities' => 'Critical'
    ];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $priority = 'Medium'; // Default
        foreach ($priorities as $key => $value) {
            if (stripos($row['disability_type'], $key) !== false || stripos($key, $row['disability_type']) !== false) {
                $priority = $value;
                break;
            }
        }
        
        fputcsv($output, [
            $row['barangay'],
            $row['disability_type'],
            $row['count'],
            $row['unemployed_count'],
            $row['children_count'],
            round($row['avg_age'], 1),
            $priority
        ]);
    }
    
    // Add recommended services section
    fputcsv($output, []);
    fputcsv($output, ['Service Recommendations by Disability Type']);
    fputcsv($output, ['Disability Type', 'Priority', 'Recommended Services']);
    
    $service_recommendations = [
        ['Physical Disability', 'High', 'Mobile therapy units, Wheelchair distribution, Accessible transport, Ramp construction'],
        ['Visual Impairment', 'High', 'Braille programs, White cane training, Screen readers, Audio library'],
        ['Hearing Impairment', 'Medium', 'Sign language services, Hearing aid program, Visual alerts, Captioning'],
        ['Intellectual Disability', 'High', 'Special education, Life skills training, Supported employment, Counseling'],
        ['Mental/Psychosocial Disability', 'Critical', 'Mental health counseling, Support groups, Crisis hotline, Medication management'],
        ['Speech Impairment', 'Medium', 'Speech therapy, Communication devices, Alternative methods, Family training'],
        ['Multiple Disabilities', 'Critical', 'Assessment services, Coordinated care, Multi-disciplinary support, Specialized equipment']
    ];
    
    foreach ($service_recommendations as $recommendation) {
        fputcsv($output, $recommendation);
    }
}

function exportAppointmentsReport($pdo, $output, $status_filter, $date_from, $date_to) {
    // Build WHERE conditions
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "a.status = ?";
        $params[] = $status_filter;
    }
    
    if ($date_from && $date_to) {
        $where_conditions[] = "DATE(a.preferred_date) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Write report header
    fputcsv($output, ['PWD Appointments Report']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    if ($date_from && $date_to) {
        fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    }
    fputcsv($output, []);
    
    // Write data header
    fputcsv($output, [
        'Reference Number',
        'First Name',
        'Last Name',
        'Phone',
        'Email',
        'Appointment Type',
        'Preferred Date',
        'Preferred Time',
        'Status',
        'Created Date',
        'Interview Status',
        'PWD ID',
        'Record Status'
    ]);
    
    $stmt = $pdo->prepare("
        SELECT 
            a.reference_number,
            u.first_name,
            u.last_name,
            u.phone,
            u.email,
            a.appointment_type,
            a.preferred_date,
            a.preferred_time,
            a.status,
            a.created_at,
            ir.status as interview_status,
            pr.pwd_id_number,
            pr.status as record_status
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN interview_records ir ON a.id = ir.appointment_id
        LEFT JOIN pwd_records pr ON a.id = pr.appointment_id
        {$where_clause}
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    
    // Write data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['reference_number'] ?? '',
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['phone'] ?? '',
            $row['email'] ?? '',
            $row['appointment_type'] ?? '',
            $row['preferred_date'] ?? '',
            $row['preferred_time'] ?? '',
            $row['status'] ?? '',
            $row['created_at'] ?? '',
            $row['interview_status'] ?? 'Not Started',
            $row['pwd_id_number'] ?? 'Not Issued',
            $row['record_status'] ?? 'No Record'
        ]);
    }
}

function exportRecordsReport($pdo, $output, $status_filter, $disability_filter, $barangay_filter, $gender_filter, $employment_filter) {
    // Build WHERE conditions
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "pr.status = ?";
        $params[] = $status_filter;
    }
    
    if ($disability_filter) {
        $where_conditions[] = "pr.disability_type = ?";
        $params[] = $disability_filter;
    }
    
    if ($barangay_filter) {
        $where_conditions[] = "pr.barangay = ?";
        $params[] = $barangay_filter;
    }
    
    if ($gender_filter) {
        $where_conditions[] = "pr.gender = ?";
        $params[] = $gender_filter;
    }
    
    if ($employment_filter) {
        $where_conditions[] = "pr.employment_status = ?";
        $params[] = $employment_filter;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Write report header
    fputcsv($output, ['PWD Records Report']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Write data header
    fputcsv($output, [
        'PWD ID',
        'First Name',
        'Middle Name',
        'Last Name',
        'Suffix',
        'Date of Birth',
        'Age',
        'Gender',
        'Civil Status',
        'Phone',
        'Email',
        'Address',
        'Barangay',
        'City/Municipality',
        'Province',
        'Disability Type',
        'Disability Cause',
        'Employment Status',
        'Occupation',
        'Status',
        'Created Date',
        'Validation Date',
        'Issue Date',
        'Expiry Date'
    ]);
    
    $stmt = $pdo->prepare("
        SELECT 
            pr.pwd_id_number,
            pr.first_name,
            pr.middle_name,
            pr.last_name,
            pr.suffix,
            pr.date_of_birth,
            TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) as age,
            pr.gender,
            pr.civil_status,
            pr.phone_number,
            pr.email_address,
            pr.address_line1,
            pr.barangay,
            pr.city_municipality,
            pr.province,
            pr.disability_type,
            pr.disability_cause,
            pr.employment_status,
            pr.occupation,
            pr.status,
            pr.created_at,
            pr.validation_date,
            pr.issue_date,
            pr.expiry_date
        FROM pwd_records pr
        {$where_clause}
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute($params);
    
    // Write data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['pwd_id_number'] ?? '',
            $row['first_name'] ?? '',
            $row['middle_name'] ?? '',
            $row['last_name'] ?? '',
            $row['suffix'] ?? '',
            $row['date_of_birth'] ?? '',
            $row['age'] ?? '',
            $row['gender'] ?? '',
            $row['civil_status'] ?? '',
            $row['phone_number'] ?? '',
            $row['email_address'] ?? '',
            $row['address_line1'] ?? '',
            $row['barangay'] ?? '',
            $row['city_municipality'] ?? '',
            $row['province'] ?? '',
            $row['disability_type'] ?? '',
            $row['disability_cause'] ?? '',
            $row['employment_status'] ?? '',
            $row['occupation'] ?? '',
            $row['status'] ?? '',
            $row['created_at'] ?? '',
            $row['validation_date'] ?? '',
            $row['issue_date'] ?? '',
            $row['expiry_date'] ?? ''
        ]);
    }
}
?>
