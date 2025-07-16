<?php
require_once '../config.php';
requireAdminLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle export request
if (isset($_GET['export'])) {
    handleExport();
    exit();
}

header('Content-Type: application/json');

switch ($action) {
    case 'get_record':
        getRecord();
        break;
    default:
        adminJsonResponse(['error' => 'Invalid action'], 400);
}

function getRecord() {
    global $pdo;
    requirePermission($pdo, 'records.view');
    
    $record_id = $_GET['id'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, au1.full_name as created_by_name, au2.full_name as validated_by_name, au3.full_name as issued_by_name
            FROM pwd_records pr
            LEFT JOIN admin_users au1 ON pr.created_by = au1.id
            LEFT JOIN admin_users au2 ON pr.validated_by = au2.id
            LEFT JOIN admin_users au3 ON pr.issued_by = au3.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$record_id]);
        $record = $stmt->fetch();
        
        if (!$record) {
            adminJsonResponse(['error' => 'Record not found'], 404);
        }
        
        adminJsonResponse([
            'success' => true,
            'record' => $record
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to get record: ' . $e->getMessage()], 500);
    }
}

function handleExport() {
    global $pdo;
    requirePermission($pdo, 'reports.export');
    
    // Get filters
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    if ($search) {
        $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR pwd_id_number LIKE ? OR email_address LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    try {
        $stmt = $pdo->prepare("
            SELECT pwd_id_number, first_name, middle_name, last_name, suffix,
                   date_of_birth, gender, civil_status, disability_type, disability_cause,
                   phone_number, email_address, address_line1, city_municipality, province,
                   employment_status, occupation, status, created_at, validation_date, issue_date
            FROM pwd_records
            {$where_clause}
            ORDER BY created_at DESC
        ");
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pwd_records_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        fputcsv($output, [
            'PWD ID', 'First Name', 'Middle Name', 'Last Name', 'Suffix',
            'Date of Birth', 'Gender', 'Civil Status', 'Disability Type', 'Disability Cause',
            'Phone', 'Email', 'Address', 'City/Municipality', 'Province',
            'Employment Status', 'Occupation', 'Status', 'Created Date', 'Validation Date', 'Issue Date'
        ]);
        
        // Write data rows
        foreach ($records as $record) {
            fputcsv($output, [
                $record['pwd_id_number'],
                $record['first_name'],
                $record['middle_name'],
                $record['last_name'],
                $record['suffix'],
                $record['date_of_birth'],
                $record['gender'],
                $record['civil_status'],
                $record['disability_type'],
                $record['disability_cause'],
                $record['phone_number'],
                $record['email_address'],
                $record['address_line1'],
                $record['city_municipality'],
                $record['province'],
                $record['employment_status'],
                $record['occupation'],
                $record['status'],
                $record['created_at'],
                $record['validation_date'],
                $record['issue_date']
            ]);
        }
        
        fclose($output);
        
        logAdminActivity($pdo, 'export', 'records', 'csv', null, [
            'record_count' => count($records),
            'filters' => compact('status_filter', 'search')
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'Export failed: ' . $e->getMessage();
    }
}
?>
