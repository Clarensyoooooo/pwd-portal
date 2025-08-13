<?php
require_once '../config.php';
requireAdminLogin();
requirePermission($pdo, 'reports.export');

$report_type = $_GET['type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';

try {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
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
        default:
            exportOverviewReport($pdo, $output, $date_from, $date_to);
    }
    
    fclose($output);
    
    logAdminActivity($pdo, 'export', 'reports', 'report', null, [
        'report_type' => $report_type,
        'date_range' => [$date_from, $date_to]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
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
?>
