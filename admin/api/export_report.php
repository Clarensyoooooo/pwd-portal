<?php
require_once '../config.php';
requireAdminLogin();
requirePermission($pdo, 'reports.export');

$report_type = $_GET['type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$export_format = $_GET['export'] ?? 'csv';

try {
    if ($export_format === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        switch ($report_type) {
            case 'appointments':
                exportAppointmentReport($pdo, $output, $date_from, $date_to, $status_filter);
                break;
            case 'services':
                exportServicesReport($pdo, $output, $date_from, $date_to, $status_filter);
                break;
            case 'feedback':
                exportFeedbackReport($pdo, $output, $date_from, $date_to);
                break;
            case 'geographic':
                exportCommunityReport($pdo, $output, $date_from, $date_to);
                break;
            default:
                exportServiceOverviewReport($pdo, $output, $date_from, $date_to);
        }
        
        fclose($output);
    } else {
        // Handle other export formats (PDF, etc.)
        http_response_code(501);
        echo json_encode(['error' => 'Export format not yet implemented']);
    }
    
    logAdminActivity($pdo, 'export', 'reports', 'report', null, [
        'report_type' => $report_type,
        'export_format' => $export_format,
        'date_range' => [$date_from, $date_to]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
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
        'Reference Number', 'Community Member Name', 'Email', 'Phone',
        'Service Type', 'Preferred Date', 'Preferred Time', 'Service Status',
        'Request Date', 'Confirmation Date', 'Completion Date'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        $status_text = '';
        switch($row['status']) {
            case 'completed': $status_text = 'Service Completed'; break;
            case 'confirmed': $status_text = 'Service Confirmed'; break;
            case 'pending': $status_text = 'Service Scheduled'; break;
            case 'cancelled': $status_text = 'Service Cancelled'; break;
            default: $status_text = $row['status'];
        }
        
        fputcsv($output, [
            $row['reference_number'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['appointment_type'],
            $row['preferred_date'],
            $row['preferred_time'],
            $status_text,
            $row['created_at'],
            $row['confirmed_at'],
            $row['completed_at']
        ]);
    }
}

function exportServicesReport($pdo, $output, $date_from, $date_to, $status_filter) {
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
        'PWD ID Number', 'Community Member Name', 'Date of Birth', 'Gender',
        'Service Category', 'Address', 'Barangay', 'City/Municipality', 'Province',
        'Service Status', 'Registration Date', 'Service Completion Date'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        $status_text = '';
        switch($row['status']) {
            case 'issued': $status_text = 'PWD ID Issued'; break;
            case 'validated': $status_text = 'Service Completed'; break;
            case 'pending_validation': $status_text = 'Under Review'; break;
            case 'draft': $status_text = 'Service In Progress'; break;
            default: $status_text = $row['status'];
        }
        
        fputcsv($output, [
            $row['pwd_id_number'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['date_of_birth'],
            $row['gender'],
            $row['disability_type'],
            $row['address_line1'],
            $row['barangay'],
            $row['city_municipality'],
            $row['province'],
            $status_text,
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
        'Community Member Name', 'Email', 'Subject', 'Message', 'Satisfaction Rating',
        'Response Status', 'Feedback Date', 'Response Date'
    ]);
    
    // Write data
    while ($row = $stmt->fetch()) {
        $member_name = $row['first_name'] && $row['last_name'] ? 
            $row['first_name'] . ' ' . $row['last_name'] : 
            ($row['name'] ?: 'Anonymous');
            
        fputcsv($output, [
            $member_name,
            $row['email'],
            $row['subject'],
            $row['message'],
            $row['rating'] ? $row['rating'] . '/5 stars' : 'Not rated',
            $row['status'] == 'responded' ? 'Responded' : 'Pending Response',
            $row['created_at'],
            $row['responded_at']
        ]);
    }
}

function exportCommunityReport($pdo, $output, $date_from, $date_to) {
    // Export community reach summary
    fputcsv($output, ['Community Reach & Geographic Impact Report']);
    fputcsv($output, ['Report Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
    // Barangay distribution
    fputcsv($output, ['BARANGAY DISTRIBUTION']);
    fputcsv($output, ['Barangay', 'Community Members Served', 'Percentage']);
    
    $stmt = $pdo->prepare("
        SELECT barangay, COUNT(*) as count
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
        GROUP BY barangay
        ORDER BY count DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    
    $total_served = 0;
    $barangay_data = [];
    while ($row = $stmt->fetch()) {
        $barangay_data[] = $row;
        $total_served += $row['count'];
    }
    
    foreach ($barangay_data as $row) {
        $percentage = $total_served > 0 ? round(($row['count'] / $total_served) * 100, 1) : 0;
        fputcsv($output, [$row['barangay'], $row['count'], $percentage . '%']);
    }
    
    fputcsv($output, []);
    
    // City/Municipality distribution
    fputcsv($output, ['CITY/MUNICIPALITY DISTRIBUTION']);
    fputcsv($output, ['City/Municipality', 'Community Members Served', 'Percentage']);
    
    $stmt = $pdo->prepare("
        SELECT city_municipality, COUNT(*) as count
        FROM pwd_records
        WHERE created_at BETWEEN ? AND ?
        GROUP BY city_municipality
        ORDER BY count DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    
    while ($row = $stmt->fetch()) {
        $percentage = $total_served > 0 ? round(($row['count'] / $total_served) * 100, 1) : 0;
        fputcsv($output, [$row['city_municipality'], $row['count'], $percentage . '%']);
    }
}

function exportServiceOverviewReport($pdo, $output, $date_from, $date_to) {
    // Export service overview summary
    fputcsv($output, ['Service Impact Analytics - Overview Report']);
    fputcsv($output, ['Report Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
    // Get service statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_served,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as services_completed,
            SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as ids_issued,
            SUM(CASE WHEN status = 'pending_validation' THEN 1 ELSE 0 END) as services_pending
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $stats = $stmt->fetch();
    
    fputcsv($output, ['SERVICE IMPACT SUMMARY']);
    fputcsv($output, ['Total Community Members Served:', number_format($stats['total_served'])]);
    fputcsv($output, ['Services Successfully Completed:', number_format($stats['services_completed'])]);
    fputcsv($output, ['PWD IDs Issued:', number_format($stats['ids_issued'])]);
    fputcsv($output, ['Services Pending:', number_format($stats['services_pending'])]);
    
    $completion_rate = $stats['total_served'] > 0 ? 
        round(($stats['services_completed'] / $stats['total_served']) * 100, 1) : 0;
    fputcsv($output, ['Service Completion Rate:', $completion_rate . '%']);
    
    fputcsv($output, []);
    
    // Service categories
    fputcsv($output, ['SERVICE CATEGORIES']);
    fputcsv($output, ['Service Category', 'Community Members Served']);
    
    $stmt = $pdo->prepare("
        SELECT disability_type as service_category, COUNT(*) as count 
        FROM pwd_records 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY disability_type 
        ORDER BY count DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [$row['service_category'], $row['count']]);
    }
}
?>
