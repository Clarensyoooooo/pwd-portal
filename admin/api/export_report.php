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
$search = $_GET['search'] ?? '';
$rating_filter = $_GET['rating'] ?? '';

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
            exportFeedbackReport($pdo, $output, $date_from, $date_to, $status_filter, $rating_filter, $search);
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
            'employment' => $employment_filter,
            'search' => $search,
            'rating' => $rating_filter
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
    error_log('Export error: ' . $e->getMessage());
}

function exportAppointmentReport($pdo, $output, $date_from, $date_to, $status_filter) {
    // Add report header
    fputcsv($output, ['PWD Portal - Appointments Report']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
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
        'Created Date', 'Confirmed Date', 'Notes'
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
            ucfirst($row['status']),
            $row['created_at'],
            $row['confirmed_at'] ?? 'Not confirmed',
            $row['notes'] ?? 'No notes'
        ]);
    }
    
    // Add summary
    fputcsv($output, []);
    fputcsv($output, ['Summary Statistics']);
    
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments a
        {$where_clause}
    ");
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch();
    
    fputcsv($output, ['Total Appointments', $summary['total']]);
    fputcsv($output, ['Pending', $summary['pending']]);
    fputcsv($output, ['Confirmed', $summary['confirmed']]);
    fputcsv($output, ['Completed', $summary['completed']]);
    fputcsv($output, ['Cancelled', $summary['cancelled']]);
}

function exportRecordsReport($pdo, $output, $date_from, $date_to, $status_filter) {
    // Add report header
    fputcsv($output, ['PWD Portal - Records Report']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
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
        'PWD ID', 'First Name', 'Last Name', 'Date of Birth', 'Age', 'Gender',
        'Disability Type', 'Address', 'Barangay', 'City/Municipality', 'Province',
        'Phone', 'Email', 'Emergency Contact', 'Emergency Phone',
        'Employment Status', 'Status', 'Created Date', 'Validation Date',
        'Latitude', 'Longitude'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        $age = $row['date_of_birth'] ? date_diff(date_create($row['date_of_birth']), date_create('today'))->y : 'N/A';
        
        fputcsv($output, [
            $row['pwd_id_number'],
            $row['first_name'],
            $row['last_name'],
            $row['date_of_birth'],
            $age,
            $row['gender'],
            $row['disability_type'],
            $row['address_line1'],
            $row['barangay'],
            $row['city_municipality'],
            $row['province'],
            $row['phone'],
            $row['email'],
            $row['emergency_contact_name'],
            $row['emergency_contact_phone'],
            $row['employment_status'],
            ucfirst($row['status']),
            $row['created_at'],
            $row['validation_date'] ?? 'Not validated',
            $row['latitude'] ?? 'Not set',
            $row['longitude'] ?? 'Not set'
        ]);
    }
    
    // Add summary
    fputcsv($output, []);
    fputcsv($output, ['Summary Statistics']);
    
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending_validation' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
            SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age
        FROM pwd_records
        {$where_clause}
    ");
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch();
    
    fputcsv($output, ['Total Records', $summary['total']]);
    fputcsv($output, ['Pending Validation', $summary['pending']]);
    fputcsv($output, ['Validated', $summary['validated']]);
    fputcsv($output, ['ID Issued', $summary['issued']]);
    fputcsv($output, ['Average Age', round($summary['avg_age'], 1) . ' years']);
}

function exportFeedbackReport($pdo, $output, $date_from, $date_to, $status_filter = '', $rating_filter = '', $search = '') {
    // Add report header
    fputcsv($output, ['PWD Portal - Feedback Report']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
    $where_conditions = ["f.created_at BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
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
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT f.*, u.first_name, u.last_name 
        FROM feedback f
        LEFT JOIN users u ON f.user_id = u.id
        {$where_clause}
        ORDER BY f.created_at DESC
    ");
    $stmt->execute($params);
    
    // Write header
    fputcsv($output, [
        'Name', 'Email', 'User Account', 'Subject', 'Message', 'Rating', 
        'Status', 'Created Date', 'Response Date', 'Category'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        $user_account = '';
        if ($row['first_name'] && $row['last_name']) {
            $user_account = $row['first_name'] . ' ' . $row['last_name'];
        } elseif ($row['user_id']) {
            $user_account = 'User ID: ' . $row['user_id'];
        } else {
            $user_account = 'Guest';
        }
        
        fputcsv($output, [
            $row['name'] ?? 'Anonymous',
            $row['email'] ?? 'No email',
            $user_account,
            $row['subject'] ?? 'No subject',
            $row['message'] ?? 'No message',
            $row['rating'] ? $row['rating'] . '/5 stars' : 'No rating',
            ucfirst($row['status']),
            $row['created_at'],
            $row['responded_at'] ?? 'Not responded',
            $row['category'] ?? 'General'
        ]);
    }
    
    // Add summary
    fputcsv($output, []);
    fputcsv($output, ['Summary Statistics']);
    
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_feedback,
            SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_feedback,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_feedback,
            AVG(rating) as avg_rating,
            COUNT(CASE WHEN rating IS NOT NULL THEN 1 END) as rated_feedback
        FROM feedback f
        {$where_clause}
    ");
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch();
    
    fputcsv($output, ['Total Feedback', $summary['total']]);
    fputcsv($output, ['New', $summary['new_feedback']]);
    fputcsv($output, ['Read', $summary['read_feedback']]);
    fputcsv($output, ['Closed', $summary['closed_feedback']]);
    fputcsv($output, ['Average Rating', $summary['avg_rating'] ? round($summary['avg_rating'], 2) . '/5' : 'N/A']);
    fputcsv($output, ['Feedback with Ratings', $summary['rated_feedback']]);
}

function exportAnalyticsReport($pdo, $output, $date_from, $date_to, $status_filter, $disability_filter, $barangay_filter, $gender_filter, $employment_filter) {
    // Add report header
    fputcsv($output, ['PWD Portal - Analytics Report']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
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
    
    // Export detailed records
    fputcsv($output, ['Detailed PWD Records']);
    fputcsv($output, [
        'PWD ID Number', 'First Name', 'Last Name', 'Date of Birth', 'Age', 'Gender',
        'Disability Type', 'Barangay', 'City/Municipality', 'Province',
        'Employment Status', 'Status', 'Registration Date', 'Validation Date'
    ]);
    
    $stmt = $pdo->prepare("
        SELECT 
            pwd_id_number, first_name, last_name, date_of_birth, gender,
            disability_type, barangay, city_municipality, province,
            employment_status, status, created_at, validation_date
        FROM pwd_records
        {$where_clause}
        ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        $age = $row['date_of_birth'] ? date_diff(date_create($row['date_of_birth']), date_create('today'))->y : 'N/A';
        
        fputcsv($output, [
            $row['pwd_id_number'],
            $row['first_name'],
            $row['last_name'],
            $row['date_of_birth'],
            $age,
            $row['gender'],
            $row['disability_type'],
            $row['barangay'],
            $row['city_municipality'],
            $row['province'],
            $row['employment_status'],
            ucfirst($row['status']),
            $row['created_at'],
            $row['validation_date'] ?? 'Not validated'
        ]);
    }
    
    // Add analytics summary
    fputcsv($output, []);
    fputcsv($output, ['Analytics Summary']);
    
    // Age group distribution
    fputcsv($output, []);
    fputcsv($output, ['Age Group Distribution']);
    fputcsv($output, ['Age Group', 'Count', 'Percentage']);
    
    $age_stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Children (0-17)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN 'Young Adults (18-30)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN 'Adults (31-50)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 65 THEN 'Mature Adults (51-65)'
                ELSE 'Senior Citizens (65+)'
            END as age_group,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records {$where_clause}), 1) as percentage
        FROM pwd_records
        {$where_clause}
        GROUP BY age_group
        ORDER BY count DESC
    ");
    $age_stmt->execute(array_merge($params, $params));
    
    while ($row = $age_stmt->fetch()) {
        fputcsv($output, [$row['age_group'], $row['count'], $row['percentage'] . '%']);
    }
    
    // Disability distribution
    fputcsv($output, []);
    fputcsv($output, ['Disability Type Distribution']);
    fputcsv($output, ['Disability Type', 'Count', 'Percentage']);
    
    $disability_stmt = $pdo->prepare("
        SELECT 
            disability_type,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records {$where_clause}), 1) as percentage
        FROM pwd_records 
        {$where_clause}
        AND disability_type IS NOT NULL
        GROUP BY disability_type 
        ORDER BY count DESC
    ");
    $disability_stmt->execute(array_merge($params, $params));
    
    while ($row = $disability_stmt->fetch()) {
        fputcsv($output, [$row['disability_type'], $row['count'], $row['percentage'] . '%']);
    }
}

function exportDemographicsReport($pdo, $output, $date_from, $date_to, $age_group, $barangay_filter, $gender_filter, $disability_filter) {
    // Add report header
    fputcsv($output, ['PWD Portal - Demographics Report']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
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
    fputcsv($output, ['Barangay Demographics Summary']);
    fputcsv($output, ['Barangay', 'Total PWDs', 'Male', 'Female', 'Children', 'Average Age', 'Active IDs', 'Coverage Rate']);
    
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
        $coverage_rate = $row['total_individuals'] > 0 ? round(($row['active_ids'] / $row['total_individuals']) * 100, 1) : 0;
        
        fputcsv($output, [
            $row['barangay'],
            $row['total_individuals'],
            $row['male_count'],
            $row['female_count'],
            $row['children_count'],
            round($row['avg_age'], 1) . ' years',
            $row['active_ids'],
            $coverage_rate . '%'
        ]);
    }
    
    // Export disability by barangay
    fputcsv($output, []);
    fputcsv($output, ['Disability Distribution by Barangay']);
    fputcsv($output, ['Barangay', 'Disability Type', 'Count', 'Percentage in Barangay']);
    
    $disability_stmt = $pdo->prepare("
        SELECT 
            barangay,
            disability_type,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (
                SELECT COUNT(*) 
                FROM pwd_records r2 
                WHERE r2.barangay = pwd_records.barangay 
                AND r2.created_at BETWEEN ? AND ?
            ), 1) as percentage
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        AND disability_type IS NOT NULL
        GROUP BY barangay, disability_type
        ORDER BY barangay, count DESC
    ");
    $disability_stmt->execute(array_merge([$date_from, $date_to], $params));
    
    while ($row = $disability_stmt->fetch()) {
        fputcsv($output, [
            $row['barangay'],
            $row['disability_type'],
            $row['count'],
            $row['percentage'] . '%'
        ]);
    }
}

function exportResourcesReport($pdo, $output, $date_from, $date_to, $barangay_filter, $disability_filter) {
    // Add report header
    fputcsv($output, ['PWD Portal - Resource Planning Report']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
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
    
    // Export resource needs by barangay
    fputcsv($output, ['Resource Needs Assessment by Barangay']);
    fputcsv($output, ['Barangay', 'Disability Type', 'Count', 'Unemployed', 'Children', 'Average Age', 'Priority Level', 'Recommended Services']);
    
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
    
    // Service recommendations mapping
    $service_recommendations = [
        'Physical Disability' => [
            'priority' => 'High',
            'services' => 'Mobile physical therapy units; Wheelchair and mobility aid distribution; Accessible public transportation; Ramp construction program; Assistive device maintenance'
        ],
        'Visual Impairment' => [
            'priority' => 'High',
            'services' => 'Braille literacy programs; White cane training sessions; Screen reader software training; Audio book library; Guide dog training programs'
        ],
        'Hearing Impairment' => [
            'priority' => 'Medium',
            'services' => 'Sign language interpretation services; Hearing aid maintenance program; Deaf community social groups; Visual alert system installation; Communication device training'
        ],
        'Intellectual Disability' => [
            'priority' => 'High',
            'services' => 'Special education programs; Life skills training workshops; Supported employment initiatives; Family counseling services; Cognitive development programs'
        ],
        'Mental/Psychosocial Disability' => [
            'priority' => 'Critical',
            'services' => 'Mental health counseling services; Peer support group meetings; Crisis intervention hotline; Medication management programs; Community integration support'
        ],
        'Speech and Language Impairment' => [
            'priority' => 'Medium',
            'services' => 'Speech therapy sessions; Communication device training; Alternative communication methods; Social skills development; Family communication training'
        ],
        'Learning Disability' => [
            'priority' => 'High',
            'services' => 'Specialized tutoring programs; Educational assessment services; Learning support tools; Teacher training programs; Parent education workshops'
        ],
        'Autism Spectrum Disorder' => [
            'priority' => 'High',
            'services' => 'Behavioral intervention programs; Social skills training; Sensory integration therapy; Family support services; Educational accommodations'
        ],
        'Multiple Disabilities' => [
            'priority' => 'Critical',
            'services' => 'Comprehensive care coordination; Multi-disciplinary therapy; Adaptive equipment provision; Respite care services; Intensive family support'
        ],
        'Chronic Illness' => [
            'priority' => 'Medium',
            'services' => 'Medical management support; Health monitoring programs; Medication assistance; Nutrition counseling; Exercise therapy programs'
        ]
    ];
    
    while ($row = $stmt->fetch()) {
        $disability_type = $row['disability_type'];
        $priority = $service_recommendations[$disability_type]['priority'] ?? 'Medium';
        $services = $service_recommendations[$disability_type]['services'] ?? 'General support services; Community integration programs; Skills development training';
        
        fputcsv($output, [
            $row['barangay'],
            $disability_type,
            $row['count'],
            $row['unemployed_count'],
            $row['children_count'],
            round($row['avg_age'], 1) . ' years',
            $priority,
            $services
        ]);
    }
    
    // Add resource allocation summary
    fputcsv($output, []);
    fputcsv($output, ['Resource Allocation Summary']);
    fputcsv($output, ['Priority Level', 'Total Individuals', 'Percentage', 'Recommended Action']);
    
    $priority_stmt = $pdo->prepare("
        SELECT 
            disability_type,
            COUNT(*) as count
        FROM pwd_records
        {$where_clause}
        AND disability_type IS NOT NULL
        GROUP BY disability_type
        ORDER BY count DESC
    ");
    $priority_stmt->execute($params);
    
    $priority_summary = [];
    $total_count = 0;
    
    while ($row = $priority_stmt->fetch()) {
        $disability_type = $row['disability_type'];
        $priority = $service_recommendations[$disability_type]['priority'] ?? 'Medium';
        
        if (!isset($priority_summary[$priority])) {
            $priority_summary[$priority] = 0;
        }
        $priority_summary[$priority] += $row['count'];
        $total_count += $row['count'];
    }
    
    foreach (['Critical', 'High', 'Medium'] as $priority) {
        if (isset($priority_summary[$priority])) {
            $count = $priority_summary[$priority];
            $percentage = $total_count > 0 ? round(($count / $total_count) * 100, 1) : 0;
            
            $action = '';
            switch ($priority) {
                case 'Critical':
                    $action = 'Immediate intervention required; Allocate emergency resources';
                    break;
                case 'High':
                    $action = 'Priority resource allocation; Develop specialized programs';
                    break;
                case 'Medium':
                    $action = 'Standard service provision; Monitor and support';
                    break;
            }
            
            fputcsv($output, [$priority, $count, $percentage . '%', $action]);
        }
    }
}
?>
