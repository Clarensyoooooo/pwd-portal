<?php
require_once 'config.php';
requireAdminLogin($pdo);
requirePermission($pdo, 'records.view');
require_once 'spatial_functions.php'; // <-- ADD THIS LINE

$admin = getCurrentAdmin($pdo);

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    requirePermission($pdo, 'records.export');
    ob_start(); // Start output buffering

    try {
        // --- 1. COPY FILTER LOGIC (from your CSV export) ---
       // Get records with enhanced filters
$status_filter = $_GET['status'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$disability_filter = $_GET['disability_type'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$age_group_filter = $_GET['age_group'] ?? '';
$employment_filter = $_GET['employment_status'] ?? '';
$location_filter = $_GET['location'] ?? ''; // <-- 1. Get the new filter
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// --- INITIALIZE ARRAYS ONCE ---
$where_conditions = [];
$params = [];

if ($status_filter) {
    if ($status_filter === 'expired') {
        $where_conditions[] = "pr.status = 'issued' AND pr.expiry_date < CURDATE()";
    } else {
        $where_conditions[] = "pr.status = ?";
        $params[] = $status_filter;
    }
}

if ($barangay_filter) {
    $where_conditions[] = "pr.barangay = ?";
    $params[] = $barangay_filter;
}

if ($disability_filter) {
    $where_conditions[] = "pr.disability_type = ?";
    $params[] = $disability_filter;
}

if ($gender_filter) {
    $where_conditions[] = "pr.gender = ?";
    $params[] = $gender_filter;
}

if ($employment_filter) {
    $where_conditions[] = "pr.employment_status = ?";
    $params[] = $employment_filter;
}

if ($age_group_filter) {
    switch ($age_group_filter) {
        case 'children':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) < 18";
            break;
        case 'adults':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) BETWEEN 18 AND 59";
            break;
        case 'seniors':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) >= 60";
            break;
    }
}

// --- 2. ADD YOUR NEW FILTER LOGIC HERE ---
if ($location_filter === 'mapped') {
    $where_conditions[] = "pr.latitude IS NOT NULL AND pr.longitude IS NOT NULL";
} elseif ($location_filter === 'missing') {
    $where_conditions[] = "(pr.latitude IS NULL OR pr.longitude IS NULL)";
}
// --- END OF NEW FILTER LOGIC ---


if ($search) {
    // --- 3. REMOVE THE LINES THAT RESET THE ARRAYS ---
    // $where_conditions = [];  <-- DELETE THIS
    // $params = [];           <-- DELETE THIS
            
    $full_name_search = "CONCAT_WS(' ', pr.first_name, pr.middle_name, pr.last_name)";
    
    $where_conditions[] = "(
        pr.first_name LIKE ?
        OR pr.last_name LIKE ?
        OR {$full_name_search} LIKE ?
        OR pr.pwd_id_number LIKE ?
        OR pr.email_address LIKE ?
        OR pr.phone_number LIKE ?
        OR pr.barangay LIKE ?
        OR pr.disability_type LIKE ?
    )";
    
    $search_param = "%{$search}%";
    
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// This will now correctly include ALL filters
$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // --- 2. GET DATA (from your CSV export) ---
        $stmt = $pdo->prepare("
            SELECT 
                pr.pwd_id_number, pr.first_name, pr.last_name, pr.barangay,
                pr.disability_type, pr.status, pr.issue_date, pr.expiry_date,
                TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) as age
            FROM pwd_records pr
            {$where_clause}
            ORDER BY pr.barangay, pr.last_name, pr.first_name
        ");
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 3. GENERATE PDF (from map.php example) ---
        require_once '../vendor/autoload.php';
        
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('PWD Portal');
        $pdf->SetAuthor($admin['full_name']);
        $pdf->SetTitle('PWD Records Report - ' . date('Y-m-d'));
        $pdf->SetSubject('Filtered PWD Records Report');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        $pdf->AddPage();
        
        // Title
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, 'PWD Records Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Santo Tomas, Batangas', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Summary section
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Report Overview', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(40, 6, 'Total Records:', 0, 0, 'L');
        $pdf->Cell(0, 6, count($records), 0, 1, 'L');
        
        if ($status_filter) {
            $pdf->Cell(40, 6, 'Status Filter:', 0, 0, 'L');
            $pdf->Cell(0, 6, ucfirst($status_filter), 0, 1, 'L');
        }
        if ($barangay_filter) {
            $pdf->Cell(40, 6, 'Barangay Filter:', 0, 0, 'L');
            $pdf->Cell(0, 6, $barangay_filter, 0, 1, 'L');
        }
        if ($disability_filter) {
            $pdf->Cell(40, 6, 'Disability Filter:', 0, 0, 'L');
            $pdf->Cell(0, 6, $disability_filter, 0, 1, 'L');
        }
        
        $pdf->Ln(5);
        
        // Data Table
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(44, 90, 160); // Blue header from map.php
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(30, 7, 'PWD ID', 1, 0, 'L', true);
        $pdf->Cell(45, 7, 'Name', 1, 0, 'L', true);
        $pdf->Cell(35, 7, 'Barangay', 1, 0, 'L', true);
        $pdf->Cell(35, 7, 'Disability', 1, 0, 'L', true);
        $pdf->Cell(20, 7, 'Status', 1, 0, 'C', true);
        $pdf->Cell(15, 7, 'Age', 1, 1, 'C', true);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(248, 250, 252);
        $fill = false;

        if (empty($records)) {
             $pdf->Cell(180, 10, 'No records found matching the criteria.', 1, 1, 'C', $fill);
        } else {
            foreach ($records as $record) {
                // Handle status logic (same as your HTML table)
                $status = $record['status'];
                $status_text = ucfirst($status);
                if ($status === 'issued') {
                    if ($record['expiry_date'] && strtotime($record['expiry_date']) < time()) {
                        $status_text = 'Expired';
                    } else {
                        $status_text = 'Active';
                    }
                }

                $name = $record['first_name'] . ' ' . $record['last_name'];
                
                $pdf->Cell(30, 6, $record['pwd_id_number'], 1, 0, 'L', $fill);
                $pdf->Cell(45, 6, $name, 1, 0, 'L', $fill);
                $pdf->Cell(35, 6, $record['barangay'], 1, 0, 'L', $fill);
                $pdf->Cell(35, 6, $record['disability_type'], 1, 0, 'L', $fill);
                $pdf->Cell(20, 6, $status_text, 1, 0, 'C', $fill);
                $pdf->Cell(15, 6, $record['age'], 1, 1, 'C', $fill);
                $fill = !$fill;
            }
        }
        
        // --- 4. LOG AND OUTPUT ---
        
        // Log the export (from your CSV logic)
        logAdminActivity($pdo, 'export', 'records', 'pdf_report', null, [
            'record_count' => count($records),
            'filters' => array_filter([
                'status' => $status_filter,
                'barangay' => $barangay_filter,
                'disability' => $disability_filter,
                'gender' => $gender_filter,
                'age_group' => $age_group_filter,
                'employment' => $employment_filter,
                'search' => $search
            ])
        ]);
        
        ob_end_clean(); // Clean buffer
        
        // Output headers (from map.php)
        $filename = 'pwd_records_report_' . date('Y-m-d') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        $pdf->Output($filename, 'D');
        exit();
        
    } catch (Exception $e) {
        ob_end_clean(); 
        error_log("Records PDF export error: " . $e->getMessage());
        die("Export failed: " . $e->getMessage() . ". Please check server logs.");
    }
}

// Handle export (This is your existing CSV export)
if (isset($_GET['export']) && $_GET['export'] == '1') {
    requirePermission($pdo, 'records.export');
    
    // Get filter parameters
    // Get records with enhanced filters
$status_filter = $_GET['status'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$disability_filter = $_GET['disability_type'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$age_group_filter = $_GET['age_group'] ?? '';
$employment_filter = $_GET['employment_status'] ?? '';
$location_filter = $_GET['location'] ?? ''; // <-- 1. Get the new filter
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// --- INITIALIZE ARRAYS ONCE ---
$where_conditions = [];
$params = [];

if ($status_filter) {
    if ($status_filter === 'expired') {
        $where_conditions[] = "pr.status = 'issued' AND pr.expiry_date < CURDATE()";
    } else {
        $where_conditions[] = "pr.status = ?";
        $params[] = $status_filter;
    }
}

if ($barangay_filter) {
    $where_conditions[] = "pr.barangay = ?";
    $params[] = $barangay_filter;
}

if ($disability_filter) {
    $where_conditions[] = "pr.disability_type = ?";
    $params[] = $disability_filter;
}

if ($gender_filter) {
    $where_conditions[] = "pr.gender = ?";
    $params[] = $gender_filter;
}

if ($employment_filter) {
    $where_conditions[] = "pr.employment_status = ?";
    $params[] = $employment_filter;
}

if ($age_group_filter) {
    switch ($age_group_filter) {
        case 'children':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) < 18";
            break;
        case 'adults':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) BETWEEN 18 AND 59";
            break;
        case 'seniors':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) >= 60";
            break;
    }
}

// --- 2. ADD YOUR NEW FILTER LOGIC HERE ---
if ($location_filter === 'mapped') {
    $where_conditions[] = "pr.latitude IS NOT NULL AND pr.longitude IS NOT NULL";
} elseif ($location_filter === 'missing') {
    $where_conditions[] = "(pr.latitude IS NULL OR pr.longitude IS NULL)";
}
// --- END OF NEW FILTER LOGIC ---


if ($search) {
    // --- 3. REMOVE THE LINES THAT RESET THE ARRAYS ---
    // $where_conditions = [];  <-- DELETE THIS
    // $params = [];           <-- DELETE THIS
            
    $full_name_search = "CONCAT_WS(' ', pr.first_name, pr.middle_name, pr.last_name)";
    
    $where_conditions[] = "(
        pr.first_name LIKE ?
        OR pr.last_name LIKE ?
        OR {$full_name_search} LIKE ?
        OR pr.pwd_id_number LIKE ?
        OR pr.email_address LIKE ?
        OR pr.phone_number LIKE ?
        OR pr.barangay LIKE ?
        OR pr.disability_type LIKE ?
    )";
    
    $search_param = "%{$search}%";
    
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// This will now correctly include ALL filters
$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="PWD_records_report_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write report header
    fputcsv($output, ['PWD Records Report']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Generated by:', $admin['full_name']]);
    fputcsv($output, []);
    
    // Write column headers
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
    
    // Get records
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
    
    fclose($output);
    
    // Log the export activity
    logAdminActivity($pdo, 'export', 'records', 'report', null, [
        'filters' => array_filter([
            'status' => $status_filter,
            'barangay' => $barangay_filter,
            'disability' => $disability_filter,
            'gender' => $gender_filter,
            'age_group' => $age_group_filter,
            'employment' => $employment_filter,
            'search' => $search
        ])
    ]);
    
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'validate_record':
            handleValidateRecord();
            break;
        case 'issue_id':
            handleIssueID();
            break;
            case 'renew_or_activate_id': // <-- ADD THIS
            handleRenewOrActivateID();
            break;
        case 'deactivate_record': // <-- ADD THIS
            handleDeactivateRecord();
            break;
        case 'update_record':
            handleUpdateRecord();
            break;
        case 'delete_record':
            handleDeleteRecord();
            break;
        case 'get_record_details':
            handleGetRecordDetails();
            break;
        case 'create_direct_record':
            handleCreateDirectRecord();
            break;
            // --- ADD THIS NEW CASE ---
case 'update_document':
    handleUpdateDocument();
    break;
        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Get records with enhanced filters
// Get records with enhanced filters
$status_filter = $_GET['status'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$disability_filter = $_GET['disability_type'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$age_group_filter = $_GET['age_group'] ?? '';
$employment_filter = $_GET['employment_status'] ?? '';
$location_filter = $_GET['location'] ?? ''; // <-- 1. Get the new filter
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// --- INITIALIZE ARRAYS ONCE ---
$where_conditions = [];
$params = [];

if ($status_filter) {
    if ($status_filter === 'expired') {
        $where_conditions[] = "pr.status = 'issued' AND pr.expiry_date < CURDATE()";
    } else {
        $where_conditions[] = "pr.status = ?";
        $params[] = $status_filter;
    }
}

if ($barangay_filter) {
    $where_conditions[] = "pr.barangay = ?";
    $params[] = $barangay_filter;
}

if ($disability_filter) {
    $where_conditions[] = "pr.disability_type = ?";
    $params[] = $disability_filter;
}

if ($gender_filter) {
    $where_conditions[] = "pr.gender = ?";
    $params[] = $gender_filter;
}

if ($employment_filter) {
    $where_conditions[] = "pr.employment_status = ?";
    $params[] = $employment_filter;
}

if ($age_group_filter) {
    switch ($age_group_filter) {
        case 'children':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) < 18";
            break;
        case 'adults':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) BETWEEN 18 AND 59";
            break;
        case 'seniors':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) >= 60";
            break;
    }
}

// --- 2. ADD YOUR NEW FILTER LOGIC HERE ---
if ($location_filter === 'mapped') {
    $where_conditions[] = "pr.latitude IS NOT NULL AND pr.longitude IS NOT NULL";
} elseif ($location_filter === 'missing') {
    $where_conditions[] = "(pr.latitude IS NULL OR pr.longitude IS NULL)";
}
// --- END OF NEW FILTER LOGIC ---


if ($search) {
    // --- 3. REMOVE THE LINES THAT RESET THE ARRAYS ---
    // $where_conditions = [];  <-- DELETE THIS
    // $params = [];           <-- DELETE THIS
            
    $full_name_search = "CONCAT_WS(' ', pr.first_name, pr.middle_name, pr.last_name)";
    
    $where_conditions[] = "(
        pr.first_name LIKE ?
        OR pr.last_name LIKE ?
        OR {$full_name_search} LIKE ?
        OR pr.pwd_id_number LIKE ?
        OR pr.email_address LIKE ?
        OR pr.phone_number LIKE ?
        OR pr.barangay LIKE ?
        OR pr.disability_type LIKE ?
    )";
    
    $search_param = "%{$search}%";
    
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// This will now correctly include ALL filters
$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pwd_records pr {$where_clause}");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get records
$stmt = $pdo->prepare("
    SELECT pr.*, au1.full_name as created_by_name, au2.full_name as validated_by_name, au3.full_name as issued_by_name,
           TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) as age
    FROM pwd_records pr
    LEFT JOIN admin_users au1 ON pr.created_by = au1.id
    LEFT JOIN admin_users au2 ON pr.validated_by = au2.id
    LEFT JOIN admin_users au3 ON pr.issued_by = au3.id
    {$where_clause}
    ORDER BY pr.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get filter options from database
$barangays_stmt = $pdo->query("SELECT DISTINCT barangay FROM pwd_records WHERE barangay IS NOT NULL ORDER BY barangay");
$barangays = $barangays_stmt->fetchAll(PDO::FETCH_COLUMN);

$disabilities_stmt = $pdo->query("SELECT DISTINCT disability_type FROM pwd_records WHERE disability_type IS NOT NULL ORDER BY disability_type");
$disabilities = $disabilities_stmt->fetchAll(PDO::FETCH_COLUMN);

$employment_stmt = $pdo->query("SELECT DISTINCT employment_status FROM pwd_records WHERE employment_status IS NOT NULL ORDER BY employment_status");
$employment_statuses = $employment_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get barangay list for dropdown
$barangay_boundaries_stmt = $pdo->prepare("
    SELECT id, barangay_name, city_municipality, province 
    FROM barangay_boundaries 
    ORDER BY barangay_name ASC
");
$barangay_boundaries_stmt->execute();
$barangay_boundaries = $barangay_boundaries_stmt->fetchAll();

function handleValidateRecord() {
    global $pdo;
    requirePermission($pdo, 'records.validate');
    
    $record_id = $_POST['record_id'] ?? '';
    $validation_notes = $_POST['validation_notes'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE pwd_records 
            SET status = 'validated', validation_date = NOW(), validated_by = ?
            WHERE id = ? AND status = 'draft'
        ");
        
        $stmt->execute([$_SESSION['admin_user_id'], $record_id]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Record not found or already validated'], 400);
        }
        
        logAdminActivity($pdo, 'validate', 'records', 'pwd_record', $record_id, [
            'validation_notes' => $validation_notes
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD record validated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to validate record: ' . $e->getMessage()], 500);
    }
}

function handleIssueID() {
    global $pdo;
    requirePermission($pdo, 'records.issue');
    
    $record_id = $_POST['record_id'] ?? '';
    $expiry_years = intval($_POST['expiry_years'] ?? 5);
    $official_pwd_id = trim($_POST['official_pwd_id_number'] ?? '');

    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    // --- START OF NEW VALIDATION BLOCK ---
    $pattern = '/^[0-9]{2}-[0-9]{4}-[0-9]{3}-[0-9]{3}$/';
    
    if (empty($official_pwd_id)) {
        adminJsonResponse(['error' => 'Official PWD ID Number is required'], 400);
    }
    
    if (!preg_match($pattern, $official_pwd_id)) {
        adminJsonResponse(['error' => 'Invalid Official PWD ID format. Expected 12 digits.'], 400);
    }
    // --- END OF NEW VALIDATION BLOCK ---
    
    try {
        $expiry_date = date('Y-m-d', strtotime("+{$expiry_years} years"));
        
        $stmt = $pdo->prepare("
            UPDATE pwd_records 
            SET status = 'issued', 
                issue_date = NOW(), 
                expiry_date = ?, 
                issued_by = ?,
                official_pwd_id_number = ?
            WHERE id = ? AND status = 'validated'
        ");
        
        $stmt->execute([
            $expiry_date, 
            $_SESSION['admin_user_id'], 
            $official_pwd_id,
            $record_id
        ]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Record not found or not validated'], 400);
        }
        
        logAdminActivity($pdo, 'issue', 'records', 'pwd_record', $record_id, [
            'expiry_date' => $expiry_date,
            'official_pwd_id' => $official_pwd_id
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD ID issued successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to issue ID: ' + $e->getMessage()], 500);
    }
}

function handleUpdateRecord() {
    global $pdo;
    requirePermission($pdo, 'records.edit');
    
    $record_id = $_POST['record_id'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        // Build update query dynamically based on provided fields
        $update_fields = [];
        $params = [];
        
        $allowed_fields = [
            'first_name', 'middle_name', 'last_name', 'suffix',
            'phone_number', 'email_address', 'address_line1', 'address_line2',
            'barangay', 'city_municipality', 'province', 'postal_code',
            'disability_type', 'disability_cause', 'disability_description',
            'medical_condition', 'medication', 'attending_physician',
            'employment_status', 'occupation', 'employer_name', 'monthly_income',
            'latitude', 'longitude'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                if ($field === 'latitude' || $field === 'longitude') {
                    $value = !empty($_POST[$field]) ? floatval($_POST[$field]) : null;
                } elseif ($field === 'monthly_income') {
                    $value = !empty($_POST[$field]) ? floatval($_POST[$field]) : null;
                } else {
                    $value = $_POST[$field];
                }
                $update_fields[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        if (empty($update_fields)) {
            adminJsonResponse(['error' => 'No fields to update'], 400);
        }
        
        $update_fields[] = "updated_at = NOW()";
        $params[] = $record_id;
        
        $sql = "UPDATE pwd_records SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        logAdminActivity($pdo, 'edit', 'records', 'pwd_record', $record_id);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD record updated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to update record: ' . $e->getMessage()], 500);
    }
}

function handleDeleteRecord() {
    global $pdo;
    requirePermission($pdo, 'records.delete');
    
    $record_id = $_POST['record_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        // Get record details before deletion
        $stmt = $pdo->prepare("SELECT pwd_id_number, first_name, last_name FROM pwd_records WHERE id = ?");
        $stmt->execute([$record_id]);
        $record = $stmt->fetch();
        
        if (!$record) {
            adminJsonResponse(['error' => 'Record not found'], 404);
        }
        
        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM pwd_records WHERE id = ?");
        $stmt->execute([$record_id]);
        
        logAdminActivity($pdo, 'delete', 'records', 'pwd_record', $record_id, [
            'pwd_id' => $record['pwd_id_number'],
            'name' => $record['first_name'] . ' ' . $record['last_name'],
            'reason' => $reason
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD record deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to delete record: ' . $e->getMessage()], 500);
    }
}

function handleGetRecordDetails() {
    global $pdo;
    
    $record_id = $_POST['record_id'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, au1.full_name as created_by_name, au2.full_name as validated_by_name, au3.full_name as issued_by_name,
                   TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) as age,
                   ir.documents_verified -- <-- ADD THIS LINE
            FROM pwd_records pr
            LEFT JOIN admin_users au1 ON pr.created_by = au1.id
            LEFT JOIN admin_users au2 ON pr.validated_by = au2.id
            LEFT JOIN admin_users au3 ON pr.issued_by = au3.id
            -- ADD THIS JOIN (It links the PWD record back to its original interview)
            LEFT JOIN interview_records ir ON pr.appointment_id = ir.appointment_id 
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
        adminJsonResponse(['error' => 'Failed to get record details: ' . $e->getMessage()], 500);
    }
}

function handleCreateDirectRecord() {
    global $pdo;
    requirePermission($pdo, 'records.create');
    
    try {
        // --- 1. VALIDATION BLOCK (Copied from interview.php) ---
        $errors = [];

        // Field: first_name (Required, Text)
        $first_name = trim($_POST['first_name'] ?? '');
        if (empty($first_name)) {
            $errors[] = 'First Name is required.';
        } elseif (strlen($first_name) > 100) {
            $errors[] = 'First Name is too long (max 100 chars).';
        }

        // Field: last_name (Required, Text)
        $last_name = trim($_POST['last_name'] ?? '');
        if (empty($last_name)) {
            $errors[] = 'Last Name is required.';
        } elseif (strlen($last_name) > 100) {
            $errors[] = 'Last Name is too long (max 100 chars).';
        }

        // Field: middle_name (Optional, Text)
        $middle_name = trim($_POST['middle_name'] ?? '');
        if (empty($middle_name)) {
            $middle_name = null; // Set to NULL if empty
        } elseif (strlen($middle_name) > 100) {
            $errors[] = 'Middle Name is too long (max 100 chars).';
        }

        // Field: suffix (Optional, Whitelist)
        $allowed_suffixes = ['', 'Jr.', 'Sr.', 'II', 'III', 'IV']; // '' is for "None"
        $suffix = trim($_POST['suffix'] ?? '');
        if (!in_array($suffix, $allowed_suffixes)) {
            $errors[] = 'Invalid Suffix selected.';
        }
        if (empty($suffix)) {
            $suffix = null; // Set to NULL if "None"
        }

        // Field: date_of_birth (Required, Date, Past)
        $dob_string = trim($_POST['date_of_birth'] ?? '');
        if (empty($dob_string)) {
            $errors[] = 'Date of Birth is required.';
        } else {
            $date_format = 'Y-m-d';
            $d = DateTime::createFromFormat($date_format, $dob_string);
            // Check if format is correct AND it's a real date (e.g., no 2025-02-31)
            if (!$d || $d->format($date_format) !== $dob_string) {
                $errors[] = 'Invalid Date of Birth format. Please use YYYY-MM-DD.';
            } elseif ($d > new DateTime()) {
                // Check if the date is in the future
                $errors[] = 'Date of Birth cannot be in the future.';
            }
        }

        // Field: place_of_birth (Optional, Text)
        $place_of_birth = trim($_POST['place_of_birth'] ?? '');
        if (empty($place_of_birth)) {
            $place_of_birth = null;
        } elseif (strlen($place_of_birth) > 255) {
            $errors[] = 'Place of Birth is too long (max 255 chars).';
        }

        // Field: gender (Required, Whitelist)
        $allowed_genders = ['Male', 'Female', 'Other'];
        $gender = trim($_POST['gender'] ?? '');
        if (empty($gender)) {
            $errors[] = 'Gender is required.';
        } elseif (!in_array($gender, $allowed_genders)) {
            $errors[] = 'Invalid Gender selected.';
        }
        
        // Field: civil_status (Required, Whitelist)
        $allowed_civil_statuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
        $civil_status = trim($_POST['civil_status'] ?? '');
        if (empty($civil_status)) {
            $errors[] = 'Civil Status is required.';
        } elseif (!in_array($civil_status, $allowed_civil_statuses)) {
            $errors[] = 'Invalid Civil Status selected.';
        }

        // --- 1b. VALIDATION BLOCK (Address Information) ---

        // Field: address_line1 (Required, Text)
        $address_line1 = trim($_POST['address_line1'] ?? '');
        if (empty($address_line1)) {
            $errors[] = 'Address Line 1 is required.';
        } elseif (strlen($address_line1) > 255) {
            $errors[] = 'Address Line 1 is too long (max 255 chars).';
        }

        // Field: address_line2 (Optional, Text)
        $address_line2 = trim($_POST['address_line2'] ?? '');
        if (empty($address_line2)) {
            $address_line2 = null;
        } elseif (strlen($address_line2) > 255) {
            $errors[] = 'Address Line 2 is too long (max 255 chars).';
        }

        // Fields: barangay_id OR barangay_manual (One is required)
        $selected_barangay_id = $_POST['barangay_id'] ?? '';
        $barangay_manual = trim($_POST['barangay_manual'] ?? '');
        $barangay_name = ''; // This will hold our final, clean value
        $barangay_info = null;

        if (!empty($selected_barangay_id)) {
            // User selected from dropdown, this is preferred.
            $barangay_stmt = $pdo->prepare("SELECT * FROM barangay_boundaries WHERE id = ?");
            $barangay_stmt->execute([$selected_barangay_id]);
            $barangay_info = $barangay_stmt->fetch();
            
            if (!$barangay_info) {
                $errors[] = 'Invalid Barangay selected.';
            } else {
                $barangay_name = $barangay_info['barangay_name'];
            }
        } elseif (!empty($barangay_manual)) {
            // User entered manually
            $barangay_name = $barangay_manual;
            if (strlen($barangay_name) > 100) {
                 $errors[] = 'Barangay (Manual Entry) is too long (max 100 chars).';
            }
        } else {
            // Neither was provided
            $barangay_name = trim($_POST['barangay'] ?? ''); // Fallback for manual entry in create modal
            if(empty($barangay_name)) {
                $errors[] = 'Barangay is required. Please select from the list or enter manually.';
            }
        }

        // Fields: city_municipality & province (Required, Text)
        $city_municipality = $barangay_info['city_municipality'] ?? $_POST['city_municipality'] ?? 'Santo Tomas City';
        $province = $barangay_info['province'] ?? $_POST['province'] ?? 'Batangas';
        
        if (empty($city_municipality)) {
            $errors[] = 'City/Municipality is required.';
        }
        if (empty($province)) {
            $errors[] = 'Province is required.';
        }

        // Field: postal_code (Optional, 4-digit number)
        $postal_code = trim($_POST['postal_code'] ?? '');
        if (empty($postal_code)) {
            $postal_code = null;
        } elseif (!ctype_digit($postal_code) || strlen($postal_code) != 4) {
            $errors[] = 'Postal Code must be a 4-digit number.';
        }

       // Fields: latitude & longitude (Optional for direct create)
        $latitude_str = trim($_POST['latitude'] ?? '');
        $longitude_str = trim($_POST['longitude'] ?? '');
        $latitude = null;
        $longitude = null;

        if (!empty($latitude_str) && !empty($longitude_str)) {
            if (!is_numeric($latitude_str) || !is_numeric($longitude_str)) {
                $errors[] = 'Latitude and Longitude must be valid numbers (from map).';
            } else {
                $latitude = floatval($latitude_str);
                $longitude = floatval($longitude_str);
            }
        }

        // --- 1c. VALIDATION BLOCK (Contact Information) ---

        // Field: phone_number (Required, Text)
        $phone_number = trim($_POST['phone_number'] ?? '');
        if (empty($phone_number)) {
            $errors[] = 'Phone Number is required.';
        } elseif (strlen($phone_number) > 20) {
            $errors[] = 'Phone Number is too long (max 20 chars).';
        }
        
        // Field: email_address (Optional, Email Format)
        $email_address = trim($_POST['email_address'] ?? '');
        if (empty($email_address)) {
            $email_address = null;
        } elseif (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email Address is not in a valid format.';
        } elseif (strlen($email_address) > 100) {
             $errors[] = 'Email Address is too long (max 100 chars).';
        }

       // --- ADD THIS NEW BLOCK (replaces the old one) ---
        // Field: email_address (Check for Duplicates, logic from appointments.php)
        if (!empty($email_address)) {
            
            // 1. Check if email is in the master PWD records
            $stmt = $pdo->prepare("SELECT id FROM pwd_records WHERE email_address = ?");
            $stmt->execute([$email_address]);
            if ($stmt->fetch()) {
                $errors[] = 'This Email Address is already registered in the PWD master records.';
            } else {
                // 2. If not in master, check if it's in 'users' with a pending/confirmed appointment
                $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $userStmt->execute([$email_address]);
                $user = $userStmt->fetch();

                if ($user) {
                    $apptStmt = $pdo->prepare("
                        SELECT id FROM appointments 
                        WHERE user_id = ? AND status IN ('pending', 'confirmed')
                    ");
                    $apptStmt->execute([$user['id']]);
                    if ($apptStmt->fetch()) {
                        $errors[] = 'This Email Address is tied to a pending/confirmed appointment. Please resolve that appointment first.';
                    }
                }
            }
        }
        // --- END OF NEW BLOCK ---

        // --- 1d. VALIDATION BLOCK (Disability Information) ---

        // Field: disability_type (Required, Whitelist)
        $allowed_disability_types = [
            'Physical Disability', 'Visual Impairment', 'Hearing Impairment', 
            'Intellectual Disability', 'Psychosocial Disability', 
            'Multiple Disabilities', 'Other'
        ];
        $disability_type = trim($_POST['disability_type'] ?? '');
        if (empty($disability_type)) {
            $errors[] = 'Type of Disability is required.';
        } elseif (!in_array($disability_type, $allowed_disability_types)) {
            $errors[] = 'Invalid Type of Disability selected.';
        }

        // Field: disability_cause (Optional, Whitelist)
        $allowed_disability_causes = ['', 'Congenital', 'Accident', 'Illness', 'Injury', 'Other']; // '' for "Select Cause"
        $disability_cause = trim($_POST['disability_cause'] ?? '');
        if (!in_array($disability_cause, $allowed_disability_causes)) {
            $errors[] = 'Invalid Cause of Disability selected.';
        }
        if (empty($disability_cause)) {
            $disability_cause = null;
        }

        // Field: disability_description (Optional, Textarea)
        $disability_description = trim($_POST['disability_description'] ?? '');
        if (empty($disability_description)) {
            $disability_description = null;
        } elseif (strlen($disability_description) > 1000) { // 1000 chars for a textarea
            $errors[] = 'Disability Description is too long (max 1000 chars).';
        }

        // Field: assistive_device (Optional, Text)
        $assistive_device = trim($_POST['assistive_device'] ?? '');
        if (empty($assistive_device)) {
            $assistive_device = null;
        } elseif (strlen($assistive_device) > 255) {
            $errors[] = 'Assistive Devices field is too long (max 255 chars).';
        }

        // --- 1e. VALIDATION BLOCK (Medical Information) ---

        // Field: medical_condition (Optional, Textarea)
        $medical_condition = trim($_POST['medical_condition'] ?? '');
        if (empty($medical_condition)) {
            $medical_condition = null;
        } elseif (strlen($medical_condition) > 1000) {
            $errors[] = 'Medical Condition description is too long (max 1000 chars).';
        }

        // Field: medication (Optional, Textarea)
        $medication = trim($_POST['medication'] ?? '');
        if (empty($medication)) {
            $medication = null;
        } elseif (strlen($medication) > 1000) {
            $errors[] = 'Current Medications list is too long (max 1000 chars).';
        }

        // Field: attending_physician (Optional, Text)
        $attending_physician = trim($_POST['attending_physician'] ?? '');
        if (empty($attending_physician)) {
            $attending_physician = null;
        } elseif (strlen($attending_physician) > 100) {
            $errors[] = 'Attending Physician name is too long (max 100 chars).';
        }

        // --- 1f. VALIDATION BLOCK (Emergency Contact) ---

        // Field: emergency_contact_name (Optional, Text)
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        if (empty($emergency_contact_name)) {
            $emergency_contact_name = null;
        } elseif (strlen($emergency_contact_name) > 100) {
            $errors[] = 'Emergency Contact Name is too long (max 100 chars).';
        }

        // Field: emergency_contact_relationship (Optional, Whitelist)
        $allowed_relationships = [
            '', 'Spouse', 'Parent', 'Child', 'Sibling', 'Relative', 'Friend', 'Guardian', 'Other'
        ];
        $emergency_contact_relationship = trim($_POST['emergency_contact_relationship'] ?? '');
        if (!in_array($emergency_contact_relationship, $allowed_relationships)) {
            $errors[] = 'Invalid Emergency Contact Relationship selected.';
        }
        if (empty($emergency_contact_relationship)) {
            $emergency_contact_relationship = null;
        }
        
        // Field: emergency_contact_phone (Optional, Text)
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
        if (empty($emergency_contact_phone)) {
            $emergency_contact_phone = null;
        } elseif (strlen($emergency_contact_phone) > 20) {
            $errors[] = 'Emergency Contact Phone is too long (max 20 chars).';
        }

        // Field: emergency_contact_address (Optional, Textarea)
        $emergency_contact_address = trim($_POST['emergency_contact_address'] ?? '');
        if (empty($emergency_contact_address)) {
            $emergency_contact_address = null;
        } elseif (strlen($emergency_contact_address) > 500) {
            $errors[] = 'Emergency Contact Address is too long (max 500 chars).';
        }

        // --- 1g. VALIDATION BLOCK (Employment Information) ---

        // Field: employment_status (Optional, Whitelist, has default)
        $allowed_employment_statuses = ['Unemployed', 'Employed', 'Self-employed', 'Student', 'Retired'];
        $employment_status = trim($_POST['employment_status'] ?? 'Unemployed'); 
        if (!in_array($employment_status, $allowed_employment_statuses)) {
            $errors[] = 'Invalid Employment Status selected.';
        }

        // Field: occupation (Optional, Text)
        $occupation = trim($_POST['occupation'] ?? '');
        if (empty($occupation)) {
            $occupation = null;
        } elseif (strlen($occupation) > 100) {
            $errors[] = 'Occupation field is too long (max 100 chars).';
        }
        
        // Field: employer_name (Optional, Text)
        $employer_name = trim($_POST['employer_name'] ?? '');
        if (empty($employer_name)) {
            $employer_name = null;
        } elseif (strlen($employer_name) > 100) {
            $errors[] = 'Employer Name field is too long (max 100 chars).';
        }

        // Field: monthly_income (Optional, Numeric)
        $monthly_income_str = trim($_POST['monthly_income'] ?? '');
        $monthly_income = null;
        if (!empty($monthly_income_str)) {
            if (!is_numeric($monthly_income_str)) {
                $errors[] = 'Monthly Income must be a valid number.';
            } elseif (floatval($monthly_income_str) < 0) {
                 $errors[] = 'Monthly Income cannot be negative.';
            } else {
                $monthly_income = floatval($monthly_income_str);
            }
        }

        // --- 1h. VALIDATION BLOCK (Government IDs) ---

        // Field: sss_number (Optional, Text)
        $sss_number = trim($_POST['sss_number'] ?? '');
        if (empty($sss_number)) {
            $sss_number = null;
        } elseif (strlen($sss_number) > 20) {
            $errors[] = 'SSS Number is too long (max 20 chars).';
        }
        
        // Field: philhealth_number (Optional, Text)
        $philhealth_number = trim($_POST['philhealth_number'] ?? '');
        if (empty($philhealth_number)) {
            $philhealth_number = null;
        } elseif (strlen($philhealth_number) > 20) {
            $errors[] = 'PhilHealth Number is too long (max 20 chars).';
        }

        // Field: tin_number (Optional, Text)
        $tin_number = trim($_POST['tin_number'] ?? '');
        if (empty($tin_number)) {
            $tin_number = null;
        } elseif (strlen($tin_number) > 20) {
            $errors[] = 'TIN Number is too long (max 20 chars).';
        }

        

        // --- 2. CHECK FOR ERRORS ---
        if (!empty($errors)) {
            // If there are any errors, combine them and stop the function
            throw new Exception(implode('<br>', $errors));
        }

        // --- 3. PROCEED WITH DATABASE LOGIC ---
        
        $pdo->beginTransaction();
        
        // Generate PWD ID
        $year = date('Y');
        $unique_part = substr(strtoupper(bin2hex(random_bytes(4))), 0, 6); 
        $pwd_id = "PWD-{$year}-" . $unique_part;
        
        // Create PWD record
        // This statement is adapted from interview.php
        // 1. Removed `appointment_id`
        // 2. Added new interview/document fields
        $stmt = $pdo->prepare("
            INSERT INTO pwd_records (
                pwd_id_number, first_name, middle_name, last_name, suffix,
                date_of_birth, place_of_birth, gender, civil_status, barangay_id,
                address_line1, address_line2, barangay, city_municipality, province, postal_code,
                latitude, longitude,
                phone_number, email_address,
                disability_type, disability_cause, disability_description, assistive_device,
                medical_condition, medication, attending_physician,
                emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, emergency_contact_address,
                employment_status, occupation, employer_name, monthly_income,
                sss_number, philhealth_number, tin_number,
                status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Prepare parameters array with CLEANED variables
        // This now has 43 parameters
        $params = [
            // --- Personal Info (10) ---
            $pwd_id,                                               // 1
            $first_name,                                           // 2 (Cleaned)
            $middle_name,                                          // 3 (Cleaned)
            $last_name,                                            // 4 (Cleaned)
            $suffix,                                               // 5 (Cleaned)
            $dob_string,                                           // 6 (Validated)
            $place_of_birth,                                       // 7 (Cleaned)
            $gender,                                               // 8 (Validated)
            $civil_status,                                         // 9 (Validated)
            $selected_barangay_id,                                 // 10
            // --- Address Info (8) ---
            $address_line1,                                        // 11
            $address_line2,                                        // 12
            $barangay_name,                                        // 13
            $city_municipality,                                    // 14
            $province,                                             // 15
            $postal_code,                                          // 16
            $latitude,                                             // 17
            $longitude,                                            // 18
            // --- Contact Info (2) ---
            $phone_number,                                         // 19
            $email_address,                                        // 20
            // --- Disability Info (4) ---
            $disability_type,                                      // 21
            $disability_cause,                                     // 22
            $disability_description,                               // 23
            $assistive_device,                                     // 24
            // --- Medical Info (3) ---
            $medical_condition,                                    // 25
            $medication,                                           // 26
            $attending_physician,                                  // 27
            // --- Emergency Contact (4) ---
            $emergency_contact_name,                               // 28
            $emergency_contact_relationship,                       // 29
            $emergency_contact_phone,                              // 30
            $emergency_contact_address,                            // 31
            // --- Employment Info (4) ---
            $employment_status,                                    // 32
            $occupation,                                           // 33
            $employer_name,                                        // 34
            $monthly_income,                                       // 35
            // --- Government IDs (3) ---
            $sss_number,                                           // 36
            $philhealth_number,                                    // 37
            $tin_number,                                           // 38
            // --- Record Status (2) ---
            $_POST['record_status'] ?? 'draft',                    // 39 - Allow setting initial status
            $_SESSION['admin_user_id'],                            // 40
            
        ];
        
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to insert PWD record');
        }
        
        $record_id = $pdo->lastInsertId();
        assignSinglePwdToBarangay($pdo, $record_id, $latitude, $longitude);
        
        logAdminActivity($pdo, 'create', 'records', 'pwd_record', $record_id, [
            'pwd_id' => $pwd_id,
            'type' => 'direct_creation'
        ]);
        
        $pdo->commit();
        
        adminJsonResponse([
            'success' => true,
            'message' => "PWD record created successfully! PWD ID: {$pwd_id}",
            'record_id' => $record_id,
            'pwd_id' => $pwd_id
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Send the validation errors back as a JSON response
        adminJsonResponse(['error' => 'Failed to create PWD record:<br>' . $e->getMessage()], 500);
    }
}

function handleRenewOrActivateID() {
    global $pdo;
    // We re-use the 'issue' permission for this
    requirePermission($pdo, 'records.issue');
    
    $record_id = $_POST['record_id'] ?? '';
    $expiry_years = intval($_POST['expiry_years'] ?? 5);
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $expiry_date = date('Y-m-d', strtotime("+{$expiry_years} years"));
        
        $stmt = $pdo->prepare("
            UPDATE pwd_records 
            SET status = 'issued', expiry_date = ?, issued_by = ?, updated_at = NOW()
            WHERE id = ? AND (status = 'issued' OR status = 'expired' OR status = 'inactive')
        ");
        
        $stmt->execute([$expiry_date, $_SESSION['admin_user_id'], $record_id]);
        
        if ($stmt->rowCount() === 0) {
             adminJsonResponse(['error' => 'Record not found or not in a renewable/activatable state'], 400);
        }
        
        logAdminActivity($pdo, 'renew/activate', 'records', 'pwd_record', $record_id, [
            'new_expiry_date' => $expiry_date
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD ID renewed/reactivated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to renew ID: ' . $e->getMessage()], 500);
    }
}

function handleDeactivateRecord() {
    global $pdo;
    // Use our newly renamed permission
    requirePermission($pdo, 'records.deactivate'); 
    
    $record_id = $_POST['record_id'] ?? '';
    $reason = $_POST['reason'] ?? 'No reason provided';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE pwd_records 
            SET status = 'inactive', updated_at = NOW()
            WHERE id = ? AND status = 'issued'
        ");
        $stmt->execute([$record_id]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Record not found or not currently active'], 400);
        }
        
        logAdminActivity($pdo, 'deactivate', 'records', 'pwd_record', $record_id, [
            'reason' => $reason
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD ID deactivated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to deactivate record: ' . $e->getMessage()], 500);
    }
}

/**
 * Finds and assigns a single PWD record to its correct barangay
 * and updates the barangay's PWD count.
 *
 * @param PDO $pdo The database connection.
 * @param int $pwd_id The ID of the PWD record just created.
 * @param float $latitude The latitude of the new PWD.
 * @param float $longitude The longitude of the new PWD.
 * @return bool True on success, false on failure or no match.
 */
function assignSinglePwdToBarangay($pdo, $pwd_id, $latitude, $longitude) {
    // If there are no coordinates, we can't do anything.
    if (empty($latitude) || empty($longitude)) {
        return false;
    }

    try {
        // Get all barangay boundaries
        $stmt = $pdo->query("SELECT id, geojson_data FROM barangay_boundaries WHERE geojson_data IS NOT NULL");
        $barangays = $stmt->fetchAll();
        
        $assigned_barangay_id = null;
        
        // Loop through each barangay to find a match
        foreach ($barangays as $barangay) {
            // This is your magic function from spatial_functions.php
            if (isPointInBarangay($latitude, $longitude, $barangay['geojson_data'])) {
                $assigned_barangay_id = $barangay['id'];
                break; // Found a match, stop looping
            }
        }
        
        // If we found a matching barangay, update the records
        if ($assigned_barangay_id) {
            // 1. Assign the barangay to the PWD record
            $update_pwd = $pdo->prepare("UPDATE pwd_records SET barangay_id = ? WHERE id = ?");
            $update_pwd->execute([$assigned_barangay_id, $pwd_id]);
            
            // 2. Increment the count for that barangay
            $update_brgy = $pdo->prepare("UPDATE barangay_boundaries SET pwd_count = pwd_count + 1 WHERE id = ?");
            $update_brgy->execute([$assigned_barangay_id]);
            
            return true;
        }
        
        return false; // No matching barangay found
        
    } catch (Exception $e) {
        // Log the error but don't stop the whole process
        error_log("Error in assignSinglePwdToBarangay: " . $e->getMessage());
        return false;
    }
}

function handleUpdateDocument() {
    global $pdo;
    requirePermission($pdo, 'records.edit');

    $record_id = $_POST['record_id'] ?? '';
    $doc_type = $_POST['doc_type'] ?? '';

    $allowed_columns = [
        'doc_id_picture', 'doc_birth_certificate', 
        'doc_medical_certificate', 'doc_voters_certificate', 
        'doc_registration_form'
    ];

    if (empty($record_id) || !in_array($doc_type, $allowed_columns)) {
        $_SESSION['error_message'] = "Invalid request details.";
        header("Location: records.php?highlight=" . $record_id);
        exit;
    }

    if (!isset($_FILES['new_file']) || $_FILES['new_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = "File upload failed.";
        header("Location: records.php?highlight=" . $record_id);
        exit;
    }

    $file_tmp = $_FILES['new_file']['tmp_name'];
    $file_name = $_FILES['new_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
        $_SESSION['error_message'] = "Invalid file type. Only JPG, PNG, PDF allowed.";
        header("Location: records.php?highlight=" . $record_id);
        exit;
    }

    // Get current filename to delete it later
    $stmt = $pdo->prepare("SELECT $doc_type FROM pwd_records WHERE id = ?");
    $stmt->execute([$record_id]);
    $old_file = $stmt->fetchColumn();

    // Upload new file
    $upload_dir = '../uploads/applicant_docs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $new_filename = uniqid('rec_', true) . '.' . $file_ext;

    if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
        // Update DB
        $update = $pdo->prepare("UPDATE pwd_records SET $doc_type = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$new_filename, $record_id]);

        // Delete old file if it exists
        if ($old_file && file_exists($upload_dir . $old_file)) {
            unlink($upload_dir . $old_file);
        }

        logAdminActivity($pdo, 'update_doc', 'records', 'pwd_record', $record_id, ['field' => $doc_type]);
        $_SESSION['success_message'] = "Document updated successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to move uploaded file.";
    }

    // Redirect back to show the modal again
    header("Location: records.php?highlight=" . $record_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWD Records - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    
    <main class="dashboard-container">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-id-card"></i> PWD Records</h1>
                <p>Manage PWD records and ID issuance</p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission($pdo, 'records.create')): ?>
                    <button class="btn btn-success" onclick="showCreateRecordModal()">
                        <i class="fas fa-plus"></i> Create New Record
                    </button>
                    <a href="interview.php" class="btn btn-primary">
                        <i class="fas fa-comments"></i> Via Interview
                    </a>
                <?php endif; ?>
                <button class="btn btn-outline" onclick="exportRecords()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="btn btn-outline" onclick="exportRecordsPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        
          
        <div class="stats-grid">
           <?php
            // OLD STATS QUERY (lines 811-820) IS REPLACED BY THIS
            $stats_query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
                    
                    -- 'Active' is issued AND not expired
                    SUM(CASE 
                        WHEN status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 
                        ELSE 0 
                    END) as active,
                    
                    -- 'Expired' is issued AND past expiry date
                    SUM(CASE 
                        WHEN status = 'issued' AND expiry_date < CURDATE() THEN 1 
                        ELSE 0 
                    END) as expired,
                    
                    -- 'Inactive' is status = inactive
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                    
                FROM pwd_records
            ";
            $stats_result = $pdo->query($stats_query)->fetch();
            ?>
            
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['total'] ?? 0); ?></h3>
                    <p>Total Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['draft'] ?? 0); ?></h3>
                    <p>Draft Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon validated">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['validated'] ?? 0); ?></h3>
                    <p>Validated Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-id-badge"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['active'] ?? 0); ?></h3>
                    <p>Active IDs</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon expired">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['expired'] ?? 0); ?></h3>
                    <p>Expired IDs</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon inactive">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['inactive'] ?? 0); ?></h3>
                    <p>Inactive Records</p>
                </div>
            </div>
        </div>
        
          
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="validated" <?php echo $status_filter === 'validated' ? 'selected' : ''; ?>>Validated</option>
                        <option value="issued" <?php echo $status_filter === 'issued' ? 'selected' : ''; ?>>Active</option>
                        <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="location_filter">Location Status</label>
                    <select name="location" id="location_filter">
                        <option value="">All Locations</option>
                        <option value="mapped" <?php echo ($_GET['location'] ?? '') === 'mapped' ? 'selected' : ''; ?>>Has Location</option>
                        <option value="missing" <?php echo ($_GET['location'] ?? '') === 'missing' ? 'selected' : ''; ?>>Missing Location</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="barangay">Barangay</label>
                    <select name="barangay" id="barangay">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays as $barangay): ?>
                            <option value="<?php echo htmlspecialchars($barangay); ?>" 
                                    <?php echo $barangay_filter === $barangay ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($barangay); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="disability_type">Disability Type</label>
                    <select name="disability_type" id="disability_type">
                        <option value="">All Disabilities</option>
                        <?php foreach ($disabilities as $disability): ?>
                            <option value="<?php echo htmlspecialchars($disability); ?>" 
                                    <?php echo $disability_filter === $disability ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($disability); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="gender">Gender</label>
                    <select name="gender" id="gender">
                        <option value="">All Genders</option>
                        <option value="Male" <?php echo $gender_filter === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $gender_filter === 'Female' ? 'selected' : ''; ?>>Female</option>
                        
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="age_group">Age Group</label>
                    <select name="age_group" id="age_group">
                        <option value="">All Ages</option>
                        <option value="children" <?php echo $age_group_filter === 'children' ? 'selected' : ''; ?>>Children (0-17)</option>
                        <option value="adults" <?php echo $age_group_filter === 'adults' ? 'selected' : ''; ?>>Adults (18-59)</option>
                        <option value="seniors" <?php echo $age_group_filter === 'seniors' ? 'selected' : ''; ?>>Seniors (60+)</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="employment_status">Employment</label>
                    <select name="employment_status" id="employment_status">
                        <option value="">All Employment</option>
                        <?php foreach ($employment_statuses as $employment): ?>
                            <option value="<?php echo htmlspecialchars($employment); ?>" 
                                    <?php echo $employment_filter === $employment ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employment); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Name, PWD ID, email, or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="records.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
          
        <div class="data-card">
            <div class="card-header">
                <h3>PWD Records</h3>
                <span class="record-count"><?php echo number_format($total_records); ?> records</span>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PWD ID</th>
                            <th>Name & Age</th>
                            <th>Contact</th>
                            <th>Disability</th>
                            <th>Location</th>
                            <th>Employment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td>
    <strong><?php echo htmlspecialchars($record['pwd_id_number']); ?></strong>
    
    <?php if ($record['official_pwd_id_number']): ?>
        <br><small class="text-primary" style="font-weight: 500;">
        Official PWD ID (NCDA / LGU): <?php echo htmlspecialchars($record['official_pwd_id_number']); ?>
        </small>
    <?php endif; ?>
    <?php if ($record['issue_date']): ?>
        <br><small class="text-muted">
            Issued: <?php echo date('M j, Y', strtotime($record['issue_date'])); ?>
        </small>
    <?php endif; ?>
    <?php if ($record['expiry_date'] && $record['status'] === 'issued'): ?>
        <br><small class="text-muted">
            Expires: <?php echo date('M j, Y', strtotime($record['expiry_date'])); ?>
        </small>
    <?php endif; ?>
</td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                    <?php if ($record['middle_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['middle_name']); ?></small>
                                    <?php endif; ?>
                                    <br><small class="text-muted">
                                        <?php echo $record['gender']; ?>, Age <?php echo $record['age']; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($record['phone_number']); ?>
                                    <?php if ($record['email_address']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['email_address']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge"><?php echo htmlspecialchars($record['disability_type']); ?></span>
                                    <?php if ($record['disability_cause']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['disability_cause']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['barangay']); ?></strong>
                                    <br><small class="text-muted">
                                        <?php 
                                        $city = $record['city_municipality'];
                                        if (empty($city) || $city === 'Unknown City') {
                                            echo 'Santo Tomas City';
                                        } else {
                                            echo htmlspecialchars($city);
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="employment-badge employment-<?php echo strtolower(str_replace(' ', '-', $record['employment_status'])); ?>">
                                        <?php echo htmlspecialchars($record['employment_status']); ?>
                                    </span>
                                    <?php if ($record['occupation']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['occupation']); ?></small>
                                    <?php endif; ?>
                                </td>
                               <td>
                                    <?php 
                                    $status = $record['status'];
                                    $status_text = ucfirst($status);
                                    
                                    if ($status === 'issued') {
                                        if ($record['expiry_date'] && strtotime($record['expiry_date']) < time()) {
                                            $status = 'expired';
                                            $status_text = 'Expired';
                                        } else {
                                            $status_text = 'Active';
                                        }
                                    } elseif ($status === 'inactive') {
                                        $status_text = 'Inactive';
                                    }
                                    ?>
                                    <span class="status-badge status-<?php echo $status; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                    <br><small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($record['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="viewRecord(<?php echo $record['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if (hasPermission($pdo, 'records.edit')): ?>
                                            <button class="btn btn-sm btn-warning" onclick="editRecord(<?php echo $record['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php // Validate button (only for draft)
                                        if (hasPermission($pdo, 'records.validate') && $record['status'] === 'draft'): ?>
                                            <button class="btn btn-sm btn-success" onclick="validateRecord(<?php echo $record['id']; ?>)" title="Validate">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php // Issue button (only for validated)
                                        if (hasPermission($pdo, 'records.issue') && $record['status'] === 'validated'): ?>
                                            <button class="btn btn-sm btn-info" onclick="issueID(<?php echo $record['id']; ?>)" title="Issue ID">
                                                <i class="fas fa-id-badge"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php 
                                        $is_expired = ($record['status'] === 'issued' && $record['expiry_date'] && strtotime($record['expiry_date']) < time());
                                        ?>

                                        <?php // Renew button (for ACTIVE or EXPIRED)
                                        if (hasPermission($pdo, 'records.issue') && ($record['status'] === 'issued' || $is_expired)): ?>
                                            <button class="btn btn-sm <?php echo $is_expired ? 'btn-warning' : 'btn-secondary'; ?>" onclick="renewOrActivateID(<?php echo $record['id']; ?>)" title="<?php echo $is_expired ? 'Renew Expired ID' : 'Renew Active ID'; ?>">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php // Deactivate button (only for ACTIVE 'issued' records)
                                        if (hasPermission($pdo, 'records.deactivate') && $record['status'] === 'issued' && !$is_expired): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deactivateRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['pwd_id_number']); ?>')" title="Set Inactive">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php // Activate button (only for INACTIVE)
                                        if (hasPermission($pdo, 'records.issue') && $record['status'] === 'inactive'): ?>
                                            <button class="btn btn-sm btn-success" onclick="renewOrActivateID(<?php echo $record['id']; ?>)" title="Activate ID">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!--
<?php if (hasPermission($pdo, 'records.delete')): ?>
<button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['pwd_id_number']); ?>')" title="Delete">
    <i class="fas fa-trash"></i>
</button>
<?php endif; ?>
-->

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="fas fa-id-card"></i>
                                    <p>No PWD records found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
             
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        (<?php echo number_format($total_records); ?> total records)
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" class="btn btn-outline btn-sm">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <div id="recordModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3 id="recordModalTitle">PWD Record Details</h3>
                <button class="modal-close" onclick="closeModal('recordModal')">&times;</button>
            </div>
            <div class="modal-body" id="recordModalBody">
                 Content will be loaded dynamically 
            </div>
        </div>
    </div>
    
    
    <div id="editRecordModal" class="modal">
        <div class="modal-content extra-large-modal">
            <div class="modal-header">
                <h3>Edit PWD Record</h3>
                <button class="modal-close" onclick="closeModal('editRecordModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editRecordForm">
                    <input type="hidden" id="editRecordId" name="record_id">
                    
                    <div class="form-section">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editFirstName">First Name</label>
                                <input type="text" id="editFirstName" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="editMiddleName">Middle Name</label>
                                <input type="text" id="editMiddleName" name="middle_name">
                            </div>
                            <div class="form-group">
                                <label for="editLastName">Last Name</label>
                                <input type="text" id="editLastName" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label for="editSuffix">Suffix</label>
                                <input type="text" id="editSuffix" name="suffix">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-phone"></i> Contact Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editPhone">Phone Number</label>
                                <input type="tel" id="editPhone" name="phone_number" required>
                            </div>
                            <div class="form-group">
                                <label for="editEmail">Email Address</label>
                                <input type="email" id="editEmail" name="email_address">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="editAddress1">Address Line 1</label>
                                <input type="text" id="editAddress1" name="address_line1" required>
                            </div>
                            <div class="form-group full-width">
                                <label for="editAddress2">Address Line 2</label>
                                <input type="text" id="editAddress2" name="address_line2">
                            </div>
                            <div class="form-group">
                                <label for="editBarangay">Barangay</label>
                                <input type="text" id="editBarangay" name="barangay" required>
                            </div>
                            <div class="form-group">
                                <label for="editCity">City/Municipality</label>
                                <input type="text" id="editCity" name="city_municipality" required>
                            </div>
                            <div class="form-group">
                                <label for="editProvince">Province</label>
                                <input type="text" id="editProvince" name="province" required>
                            </div>
                            <div class="form-group">
                                <label for="editPostal">Postal Code</label>
                                <input type="text" id="editPostal" name="postal_code">
                            </div>
                        </div>
                    </div>
                    
                      
                    <div class="form-section">
                        <div class="section-header">
                            <h4><i class="fas fa-map"></i> Geographic Location*</h4>
                            <p class="section-description">Click on the map to update the exact location</p>
                        </div>
                        
                        <div class="location-container">
                            <div class="location-inputs">
                                <div class="form-group">
                                    <label for="editLatitude">Latitude</label>
                                    <input type="number" id="editLatitude" name="latitude" class="form-input" 
                                           step="0.000001" placeholder="14.0000" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="editLongitude">Longitude</label>
                                    <input type="number" id="editLongitude" name="longitude" class="form-input" 
                                           step="0.000001" placeholder="121.0000" readonly>
                                </div>
                                <div class="location-actions">
                                    <button type="button" class="btn btn-outline btn-sm expand-map-btn" 
            onclick="toggleMapExpand('edit')">
        <i class="fas fa-expand-arrows-alt"></i> Expand Map
    </button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="getCurrentLocationEdit()">
                                        <i class="fas fa-crosshairs"></i> Use Current Location
                                    </button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="clearLocationEdit()">
                                        <i class="fas fa-times"></i> Clear Location
                                    </button>
                                </div>
                            </div>
                            
                            <div class="map-container">
                                <div id="editLocationMap" class="location-map"></div>
                                <div class="map-instructions">
                                    <i class="fas fa-mouse-pointer"></i>
                                    <span>Click anywhere on the map to update the location</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-wheelchair"></i> Disability Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editDisabilityType">Disability Type</label>
                                <select id="editDisabilityType" name="disability_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Physical Disability">Physical Disability</option>
                                    <option value="Visual Impairment">Visual Impairment</option>
                                    <option value="Hearing Impairment">Hearing Impairment</option>
                                    <option value="Intellectual Disability">Intellectual Disability</option>
                                    <option value="Psychosocial Disability">Psychosocial Disability</option>
                                    <option value="Multiple Disabilities">Multiple Disabilities</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="editDisabilityCause">Disability Cause</label>
                                <select id="editDisabilityCause" name="disability_cause">
                                    <option value="">Select Cause</option>
                                    <option value="Congenital">Congenital</option>
                                    <option value="Accident">Accident</option>
                                    <option value="Illness">Illness</option>
                                    <option value="Injury">Injury</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label for="editDisabilityDescription">Disability Description</label>
                                <textarea id="editDisabilityDescription" name="disability_description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-briefcase"></i> Employment Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editEmploymentStatus">Employment Status</label>
                                <select id="editEmploymentStatus" name="employment_status">
                                    <option value="Unemployed">Unemployed</option>
                                    <option value="Employed">Employed</option>
                                    <option value="Self-employed">Self-employed</option>
                                    <option value="Student">Student</option>
                                    <option value="Retired">Retired</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="editOccupation">Occupation</label>
                                <input type="text" id="editOccupation" name="occupation">
                            </div>
                            <div class="form-group">
                                <label for="editEmployer">Employer Name</label>
                                <input type="text" id="editEmployer" name="employer_name">
                            </div>
                            <div class="form-group">
                                <label for="editIncome">Monthly Income</label>
                                <input type="number" id="editIncome" name="monthly_income" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Record
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('editRecordModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
     
    <div id="validationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Validate PWD Record</h3>
                <button class="modal-close" onclick="closeModal('validationModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="validationForm">
                    <input type="hidden" id="validateRecordId" name="record_id">
                    <div class="form-group">
                        <label for="validationNotes">Validation Notes (Optional)</label>
                        <textarea id="validationNotes" name="validation_notes" rows="4" 
                                  placeholder="Add any notes about the validation process..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Validate Record
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('validationModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
     
    <div id="issueModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Issue PWD ID</h3>
                <button class="modal-close" onclick="closeModal('issueModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="issueForm">
                    <input type="hidden" id="issueRecordId" name="record_id">
                    <input type="hidden" id="issueAction" name="action"> <div class="form-group">
                        <label for="expiryYears">ID Validity Period</label>
                        <select id="expiryYears" name="expiry_years" required>
                            <option value="5">5 Years</option>
                        </select>
                        <div class="form-group" style="margin-top: 16px;">
    <label for="official_pwd_id_number">Official PWD ID Number *</label>
    <input type="text" id="official_pwd_id_input" name="official_pwd_id_number" 
       class="form-input" 
       placeholder="Type 12 digits (e.g., 041001001001)" 
       required 
       maxlength="15">
</div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        This will mark the record as "Issued" and set the expiry date. Please ensure the PWD ID number is correct.
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-id-badge"></i> Issue PWD ID
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('issueModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="createRecordModal" class="modal">
        <div class="modal-content extra-large-modal">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Create New PWD Record</h3>
                <button class="modal-close" onclick="closeModal('createRecordModal')">&times;</button>
            </div>
            <div class="modal-body">
                
                <div class="interview-tabs">
                    <button class="tab-btn active" data-tab="pwd-record-tab">
                        <i class="fas fa-id-card"></i> PWD Record
                    </button>
                    <button class="tab-btn" data-tab="interview-notes-tab">
                        <i class="fas fa-clipboard-list"></i> Interview Notes
                    </button>
                </div>

                <form id="createRecordForm">
                    <input type="hidden" name="action" value="create_direct_record">

                    <div id="pwd-record-tab" class="tab-content active">
                        <div class="create-record-info">
                            <div class="info-banner">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>Direct Record Creation</strong>
                                    <p>Create PWD records directly. Fill in all required fields marked with *</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-user"></i> Personal Information</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="createFirstName">First Name *</label>
                                    <input type="text" id="createFirstName" name="first_name" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label for="createMiddleName">Middle Name</label>
                                    <input type="text" id="createMiddleName" name="middle_name" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label for="createLastName">Last Name *</label>
                                    <input type="text" id="createLastName" name="last_name" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label for="createSuffix">Suffix</label>
                                    <select id="createSuffix" name="suffix" class="form-select">
                                        <option value="">None</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="createDateOfBirth">Date of Birth *</label>
                                    <input type="date" id="createDateOfBirth" name="date_of_birth" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label for="createPlaceOfBirth">Place of Birth</label>
                                    <input type="text" id="createPlaceOfBirth" name="place_of_birth" class="form-input" placeholder="City, Province">
                                </div>
                                <div class="form-group">
                                    <label for="createGender">Gender *</label>
                                    <select id="createGender" name="gender" class="form-select" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <!-- <option value="Other">Other</option> -->
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="createCivilStatus">Civil Status *</label>
                                    <select id="createCivilStatus" name="civil_status" class="form-select" required>
                                        <option value="">Select Status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                        <option value="Divorced">Divorced</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="createAddress1">Address Line 1 *</label>
                                    <input type="text" id="createAddress1" name="address_line1" class="form-input" required placeholder="House/Unit Number, Street Name">
                                </div>
                                <div class="form-group full-width">
                                    <label for="createAddress2">Address Line 2</label>
                                    <input type="text" id="createAddress2" name="address_line2" class="form-input" placeholder="Building, Subdivision, etc.">
                                </div>
                                
                                <div class="form-group">
                                    <label for="createBarangayId">Barangay *</label>
                                    <select id="createBarangayId" name="barangay_id" class="form-select" onchange="updateCreateCityProvince()">
                                        <option value="">Select Barangay</option>
                                        <?php 
                                        foreach ($barangay_boundaries as $barangay): 
                                            $city = $barangay['city_municipality'];
                                            $province = $barangay['province'];
                                            
                                            if (empty($city) || $city === 'Unknown City') {
                                                $city = 'Santo Tomas City';
                                            }
                                            if (empty($province) || $province === 'Unknown Province') {
                                                $province = 'Batangas';
                                            }
                                        ?>
                                            <option value="<?php echo $barangay['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($barangay['barangay_name']); ?>"
                                                    data-city="<?php echo htmlspecialchars($city); ?>"
                                                    data-province="<?php echo htmlspecialchars($province); ?>">
                                                <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" id="createManualBarangay" style="display: none;">
                                    <label for="createBarangayManual">Barangay (Manual Entry)</label>
                                    <input type="text" id="createBarangayManual" name="barangay_manual" class="form-input" placeholder="Enter barangay name manually">
                                </div>
                                
                                <input type="hidden" id="createBarangayName" name="barangay">
                                
                                <div class="form-group">
                                    <label for="createCity">City/Municipality</label>
                                    <input type="text" id="createCity" name="city_municipality" class="form-input" value="Santo Tomas City" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="createProvince">Province</label>
                                    <input type="text" id="createProvince" name="province" class="form-input" value="Batangas" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="createPostalCode">Postal Code</label>
                                    <input type="text" id="createPostalCode" name="postal_code" class="form-input" pattern="[0-9]{4}" placeholder="4234" value="4234">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-map"></i> Geographic Location*</h4>
                                <p class="section-description">Click on the map to set the exact location for GIS mapping feature</p>
                            </div>
                            
                            <div class="location-container">
                                <div class="location-inputs">
                                    <div class="form-group">
                                        <label for="createLatitude">Latitude</label>
                                        <input type="number" id="createLatitude" name="latitude" class="form-input" 
                                               step="0.000001" placeholder="14.0000" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="createLongitude">Longitude</label>
                                        <input type="number" id="createLongitude" name="longitude" class="form-input" 
                                               step="0.000001" placeholder="121.0000" readonly>
                                    </div>
                                    <div class="location-actions">
                                        <button type="button" class="btn btn-outline btn-sm expand-map-btn" 
                onclick="toggleMapExpand('create')">
            <i class="fas fa-expand-arrows-alt"></i> Expand Map
        </button>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="getCurrentLocationCreate()">
                                            <i class="fas fa-crosshairs"></i> Use Current Location
                                        </button>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="clearLocationCreate()">
                                            <i class="fas fa-times"></i> Clear Location
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="map-container">
                                    <div id="createLocationMap" class="location-map"></div>
                                    <div class="map-instructions">
                                        <i class="fas fa-mouse-pointer"></i>
                                        <span>Click anywhere on the map to set the exact location</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-phone"></i> Contact Information</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="createPhone">Phone Number *</label>
                                    <input type="tel" id="createPhone" name="phone_number" class="form-input" required placeholder="+63 912 345 6789">
                                </div>
                                <div class="form-group">
                                    <label for="createEmail">Email Address</label>
                                    <input type="email" id="createEmail" name="email_address" class="form-input" placeholder="email@example.com">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-wheelchair"></i> Disability Information</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="createDisabilityType">Type of Disability *</label>
                                    <select id="createDisabilityType" name="disability_type" class="form-select" required>
                                        <option value="">Select Disability Type</option>
                                        <option value="Physical Disability">Physical Disability</option>
                                        <option value="Visual Impairment">Visual Impairment</option>
                                        <option value="Hearing Impairment">Hearing Impairment</option>
                                        <option value="Intellectual Disability">Intellectual Disability</option>
                                        <option value="Psychosocial Disability">Psychosocial Disability</option>
                                        <option value="Multiple Disabilities">Multiple Disabilities</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="createDisabilityCause">Cause of Disability</label>
                                    <select id="createDisabilityCause" name="disability_cause" class="form-select">
                                        <option value="">Select Cause</option>
                                        <option value="Congenital">Congenital</option>
                                        <option value="Accident">Accident</option>
                                        <option value="Illness">Illness</option>
                                        <option value="Injury">Injury</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group full-width">
                                    <label for="createDisabilityDescription">Disability Description</label>
                                    <textarea id="createDisabilityDescription" name="disability_description" rows="3" class="form-textarea" placeholder="Detailed description of the disability..."></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="createAssistiveDevice">Assistive Devices Used</label>
                                    <input type="text" id="createAssistiveDevice" name="assistive_device" class="form-input" placeholder="Wheelchair, hearing aid, white cane, etc.">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa- stethoscope"></i> Medical Information</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="createMedicalCondition">Medical Condition</label>
                                    <textarea id="createMedicalCondition" name="medical_condition" rows="3" class="form-textarea" placeholder="Current medical conditions and diagnoses..."></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="createMedication">Current Medications</label>
                                    <textarea id="createMedication" name="medication" rows="2" class="form-textarea" placeholder="List current medications and dosages..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="createAttendingPhysician">Attending Physician</label>
                                    <input type="text" id="createAttendingPhysician" name="attending_physician" class="form-input" placeholder="Dr. Juan Dela Cruz">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="createEmergencyContactName">Contact Name</label>
                                    <input type="text" id="createEmergencyContactName" name="emergency_contact_name" class="form-input" placeholder="Full name of emergency contact">
                                </div>
                                <div class="form-group">
                                    <label for="createEmergencyContactRelationship">Relationship</label>
                                    <select id="createEmergencyContactRelationship" name="emergency_contact_relationship" class="form-select">
                                        <option value="">Select Relationship</option>
                                        <option value="Spouse">Spouse</option>
                                        <option value="Parent">Parent</option>
                                        <option value="Child">Child</option>
                                        <option value="Sibling">Sibling</option>
                                        <option value="Relative">Relative</option>
                                        <option value="Friend">Friend</option>
                                        <option value="Guardian">Guardian</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="createEmergencyContactPhone">Contact Phone</label>
                                    <input type="tel" id="createEmergencyContactPhone" name="emergency_contact_phone" class="form-input" placeholder="+63 912 345 6789">
                                </div>
                                <div class="form-group full-width">
                                    <label for="createEmergencyContactAddress">Contact Address</label>
                                    <textarea id="createEmergencyContactAddress" name="emergency_contact_address" rows="2" class="form-textarea" placeholder="Complete address of emergency contact..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-briefcase"></i> Employment Information</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="createEmploymentStatus">Employment Status</label>
                                    <select id="createEmploymentStatus" name="employment_status" class="form-select">
                                        <option value="Unemployed">Unemployed</option>
                                        <option value="Employed">Employed</option>
                                        <option value="Self-employed">Self-employed</option>
                                        <option value="Student">Student</option>
                                        <option value="Retired">Retired</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="createOccupation">Occupation</label>
                                    <input type="text" id="createOccupation" name="occupation" class="form-input" placeholder="Job title or profession">
                                </div>
                                <div class="form-group">
                                    <label for="createEmployer">Employer Name</label>
                                    <input type="text" id="createEmployer" name="employer_name" class="form-input" placeholder="Company or organization name">
                                </div>
                                <div class="form-group">
                                    <label for="createIncome">Monthly Income (PHP)</label>
                                    <input type="number" id="createIncome" name="monthly_income" class="form-input" min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-id-card-alt"></i> Government IDs</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="createSssNumber">SSS Number</label>
                                    <input type="text" id="createSssNumber" name="sss_number" class="form-input" placeholder="XX-XXXXXXX-X">
                                </div>
                                <div class="form-group">
                                    <label for="createPhilhealthNumber">PhilHealth Number</label>
                                    <input type="text" id="createPhilhealthNumber" name="philhealth_number" class="form-input" placeholder="XX-XXXXXXXXX-X">
                                </div>
                                <div class="form-group">
                                    <label for="createTinNumber">TIN Number</label>
                                    <input type="text" id="createTinNumber" name="tin_number" class="form-input" placeholder="XXX-XXX-XXX-XXX">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h4><i class="fas fa-flag"></i> Record Status</h4>
                                <p class="section-description">Set the initial status for this record</p>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="createRecordStatus">Initial Status</label>
                                    <select id="createRecordStatus" name="record_status" class="form-select">
                                        <option value="draft">Draft - Needs validation</option>
                                        <option value="validated">Validated - Ready for ID issuance</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div> <div id="interview-notes-tab" class="tab-content">
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-clipboard-check"></i> Document Verification</h3>
                                <p class="section-description">Check off all documents that have been verified (Optional)</p>
                            </div>
                            
                            <div class="document-checklist">
                                <?php 
                                $required_docs = [
                                    'medical_certificate' => ['Medical Certificate', 'Medical assessment from licensed physician'],
                                    'barangay_certificate' => ['Barangay Certificate', 'Certificate of residency from barangay'],
                                    'id_pictures' => ['2x2 ID Pictures', 'Recent passport-size photographs'],
                                    'valid_id' => ['Valid Government ID', 'Any government-issued identification'],
                                    'birth_certificate' => ['Birth Certificate', 'PSA-issued birth certificate'],
                                    'disability_assessment' => ['Disability Assessment Report', 'Professional disability evaluation'],
                                    'income_certificate' => ['Certificate of Indigency', 'If applicable for financial assistance']
                                ];
                                ?>
                                
                                <?php foreach ($required_docs as $key => $doc_info): ?>
                                    <div class="document-item">
                                        <label class="document-label">
                                            <input type="checkbox" name="documents_verified[]" value="<?php echo $key; ?>">
                                            <div class="document-content">
                                                <div class="document-title"><?php echo $doc_info[0]; ?></div>
                                                <div class="document-description"><?php echo $doc_info[1]; ?></div>
                                            </div>
                                            <div class="document-status">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-notes-medical"></i> Interview Notes</h3>
                                <p class="section-description">Record observations, questions asked, and applicant responses (Optional)</p>
                            </div>
                            <div class="form-group">
                                <textarea name="interview_notes" rows="6" class="form-textarea" 
                                          placeholder="Document any conversation, observations, or relevant details..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-user-check"></i> Eligibility Assessment</h3>
                                <p class="section-description">Evaluate the applicant's eligibility (Optional)</p>
                            </div>
                            <div class="form-group">
                                <textarea name="eligibility_assessment" rows="4" class="form-textarea" 
                                          placeholder="Assess eligibility based on disability type, documentation, etc..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-lightbulb"></i> Recommendations</h3>
                                <p class="section-description">Provide recommendations for services or next steps (Optional)</p>
                            </div>
                            <div class="form-group">
                                <textarea name="recommendations" rows="4" class="form-textarea" 
                                          placeholder="Recommend appropriate services, accommodations, or referrals..."></textarea>
                            </div>
                        </div>

                    </div> <div class="form-actions">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-plus-circle"></i> Create PWD Record
                        </button>
                        <button type="button" class="btn btn-outline" onclick="resetCreateForm()">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('createRecordModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    
    
    </div>
    
    <script src="assets/admin.js"></script>
    <script src="assets/admin.js"></script>
<script>
    // --- Global Variables (Declared ONCE at the top) ---
    let createLocationMap = null;
    let createLocationMarker = null;
    let editLocationMap = null;
    let editLocationMarker = null;

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Auto-open modal if 'highlight' param exists (after document upload)
        const urlParams = new URLSearchParams(window.location.search);
        const highlightId = urlParams.get('highlight');

        if (highlightId) {
            viewRecord(highlightId);
            // Optional: Clean URL to remove ?highlight=123
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({
                path: newUrl
            }, '', newUrl);
        }

        // 2. Initialize PWD ID Input Formatting
        const pwdIdInput = document.getElementById('official_pwd_id_input');
        if (pwdIdInput) {
            pwdIdInput.addEventListener('input', function(e) {
                let rawValue = e.target.value.replace(/[^0-9]/g, '');
                rawValue = rawValue.substring(0, 12);

                let formattedValue = '';
                if (rawValue.length > 0) formattedValue = rawValue.substring(0, 2);
                if (rawValue.length > 2) formattedValue += '-' + rawValue.substring(2, 6);
                if (rawValue.length > 6) formattedValue += '-' + rawValue.substring(6, 9);
                if (rawValue.length > 9) formattedValue += '-' + rawValue.substring(9, 12);

                e.target.value = formattedValue;
            });
        }

        // 3. Initialize Tabs for Create Record Modal
        setupModalTabs('createRecordModal');
    });

    // --- Functions ---

    function setupModalTabs(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        const tabBtns = modal.querySelectorAll('.tab-btn');
        const tabContents = modal.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const tabId = btn.getAttribute('data-tab');

                // Remove active class from all
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                // Add active class to clicked
                btn.classList.add('active');
                modal.querySelector('#' + tabId).classList.add('active');

                // Refresh maps if needed
                if (tabId === 'pwd-record-tab') {
                    if (createLocationMap) {
                        setTimeout(() => createLocationMap.invalidateSize(), 100);
                    }
                }
            });
        });
    }

    // Initialize create location map
    function initializeCreateLocationMap() {
        const mapElement = document.getElementById('createLocationMap');
        if (!mapElement || createLocationMap) return;

        const defaultLat = 14.1078;
        const defaultLng = 121.1414;

        createLocationMap = L.map('createLocationMap').setView([defaultLat, defaultLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(createLocationMap);

        createLocationMap.on('click', function(e) {
            setCreateLocation(e.latlng.lat, e.latlng.lng);
        });
    }

    // Initialize edit location map
    function initializeEditLocationMap() {
        const mapElement = document.getElementById('editLocationMap');
        if (!mapElement || editLocationMap) return;

        const defaultLat = 14.1078;
        const defaultLng = 121.1414;

        editLocationMap = L.map('editLocationMap').setView([defaultLat, defaultLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(editLocationMap);

        editLocationMap.on('click', function(e) {
            setEditLocation(e.latlng.lat, e.latlng.lng);
        });
    }

    // Set location on create map
    function setCreateLocation(lat, lng) {
        if (createLocationMarker) {
            createLocationMap.removeLayer(createLocationMarker);
        }
        createLocationMarker = L.marker([lat, lng]).addTo(createLocationMap);
        document.getElementById('createLatitude').value = lat.toFixed(6);
        document.getElementById('createLongitude').value = lng.toFixed(6);
        showNotification('Location set successfully!', 'success');
    }

    // Set location on edit map
    function setEditLocation(lat, lng) {
        if (editLocationMarker) {
            editLocationMap.removeLayer(editLocationMarker);
        }
        editLocationMarker = L.marker([lat, lng]).addTo(editLocationMap);
        document.getElementById('editLatitude').value = lat.toFixed(6);
        document.getElementById('editLongitude').value = lng.toFixed(6);
        showNotification('Location updated successfully!', 'success');
    }

    // Get current location for create
    function getCurrentLocationCreate() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                setCreateLocation(lat, lng);
                createLocationMap.setView([lat, lng], 16);
                showNotification('Current location detected!', 'success');
            }, function(error) {
                showNotification('Unable to get current location: ' + error.message, 'error');
            });
        } else {
            showNotification('Geolocation is not supported by this browser', 'error');
        }
    }

    // Get current location for edit
    function getCurrentLocationEdit() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                setEditLocation(lat, lng);
                editLocationMap.setView([lat, lng], 16);
                showNotification('Current location detected!', 'success');
            }, function(error) {
                showNotification('Unable to get current location: ' + error.message, 'error');
            });
        } else {
            showNotification('Geolocation is not supported by this browser', 'error');
        }
    }

    // Clear location for create
    function clearLocationCreate() {
        if (createLocationMarker) {
            createLocationMap.removeLayer(createLocationMarker);
            createLocationMarker = null;
        }
        document.getElementById('createLatitude').value = '';
        document.getElementById('createLongitude').value = '';
        showNotification('Location cleared', 'info');
    }

    // Clear location for edit
    function clearLocationEdit() {
        if (editLocationMarker) {
            editLocationMap.removeLayer(editLocationMarker);
            editLocationMarker = null;
        }
        document.getElementById('editLatitude').value = '';
        document.getElementById('editLongitude').value = '';
        showNotification('Location cleared', 'info');
    }

    function toggleMapExpand(type) {
        let container, button, mapInstance, actionsContainer;

        if (type === 'create') {
            container = document.getElementById('createLocationMap').parentElement;
            actionsContainer = document.getElementById('createRecordForm').querySelector('.location-actions');
            mapInstance = createLocationMap;
            button = container.querySelector('.expand-map-btn') || actionsContainer.querySelector('.expand-map-btn');

        } else if (type === 'edit') {
            container = document.getElementById('editLocationMap').parentElement;
            actionsContainer = document.getElementById('editRecordForm').querySelector('.location-actions');
            mapInstance = editLocationMap;
            button = container.querySelector('.expand-map-btn') || actionsContainer.querySelector('.expand-map-btn');

        } else {
            return;
        }

        if (!button) return;

        const isExpanded = container.classList.toggle('map-expanded');

        if (isExpanded) {
            container.appendChild(button);
            button.innerHTML = '<i class="fas fa-compress-arrows-alt"></i> Compress Map';
        } else {
            actionsContainer.prepend(button);
            button.innerHTML = '<i class="fas fa-expand-arrows-alt"></i> Expand Map';
        }

        setTimeout(() => {
            if (mapInstance) {
                mapInstance.invalidateSize();
            }
        }, 100);
    }

    // Update city and province based on barangay selection for create
    function updateCreateCityProvince() {
        const barangaySelect = document.getElementById('createBarangayId');
        const selectedOption = barangaySelect.options[barangaySelect.selectedIndex];

        if (selectedOption.value) {
            const barangayName = selectedOption.getAttribute('data-name');
            const city = selectedOption.getAttribute('data-city');
            const province = selectedOption.getAttribute('data-province');

            document.getElementById('createBarangayName').value = barangayName;
            document.getElementById('createCity').value = city;
            document.getElementById('createProvince').value = province;

            document.getElementById('createManualBarangay').style.display = 'none';
            document.getElementById('createBarangayManual').required = false;
            document.getElementById('createBarangayId').required = true;
        }
    }

    // Toggle manual barangay entry for create
    function toggleCreateManualBarangay() {
        const manualDiv = document.getElementById('createManualBarangay');
        const barangaySelect = document.getElementById('createBarangayId');
        const manualInput = document.getElementById('createBarangayManual');

        if (manualDiv.style.display === 'none') {
            manualDiv.style.display = 'block';
            manualInput.required = true;
            barangaySelect.required = false;
            barangaySelect.value = '';

            document.getElementById('createCity').readOnly = false;
            document.getElementById('createProvince').readOnly = false;

            showNotification('Manual barangay entry enabled', 'info');
        } else {
            manualDiv.style.display = 'none';
            manualInput.required = false;
            barangaySelect.required = true;
            manualInput.value = '';

            document.getElementById('createCity').readOnly = true;
            document.getElementById('createProvince').readOnly = true;
            document.getElementById('createCity').value = 'Santo Tomas City';
            document.getElementById('createProvince').value = 'Batangas';

            showNotification('Switched back to barangay dropdown', 'info');
        }
    }

    // View record details
    function viewRecord(recordId) {
        fetch('records.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_record_details&record_id=${recordId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayRecordDetails(data.record);
                    showModal('recordModal');
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to load record details', 'error');
            });
    }

    // Helper to trigger the hidden upload form
    function triggerRecordDocUpload(recordId, docType) {
        document.getElementById('upload_record_id').value = recordId;
        document.getElementById('upload_doc_type').value = docType;
        document.getElementById('upload_file_input').click();
    }

    // Helper to generate the HTML for a single document row
    function generateDocRow(record, dbField, label, iconClass) {
        const fileName = record[dbField];
        const hasFile = fileName != null && fileName !== '';

        let html = `
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 8px;">
        <div style="display: flex; align-items: center; gap: 10px; overflow: hidden;">
            <div style="width: 30px; text-align: center; color: #4b5563;"><i class="fas ${iconClass}"></i></div>
            <div style="display: flex; flex-direction: column;">
                <span style="font-weight: 500; font-size: 0.9rem; color: #1f2937;">${label}</span>
                ${hasFile 
                    ? `<a href="../uploads/applicant_docs/${fileName}" target="_blank" style="font-size: 0.8rem; color: #2563eb; text-decoration: none;">View Current File</a>` 
                    : `<span style="font-size: 0.8rem; color: #ef4444;">Not provided</span>`
                }
            </div>
        </div>
        
        <button type="button" 
                onclick="triggerRecordDocUpload(${record.id}, '${dbField}')"
                style="padding: 6px 12px; font-size: 0.8rem; border-radius: 4px; border: 1px solid #d1d5db; background: white; cursor: pointer; color: #374151; transition: all 0.2s;">
            <i class="fas fa-upload"></i> ${hasFile ? 'Replace' : 'Upload'}
        </button>
    </div>`;

        return html;
    }

    function displayRecordDetails(record) {
        const modalBody = document.getElementById('recordModalBody');
        const modalTitle = document.getElementById('recordModalTitle');

        modalTitle.textContent = `PWD Record: ${record.pwd_id_number}`;

        let documentsVerified = [];
        try {
            documentsVerified = record.documents_verified ? JSON.parse(record.documents_verified) : [];
        } catch (e) {
            documentsVerified = [];
        }

        const documentLabels = {
            'medical_certificate': 'Medical Certificate',
            'barangay_certificate': 'Barangay Certificate',
            'id_pictures': '2x2 ID Pictures',
            'valid_id': 'Valid Government ID',
            'birth_certificate': 'Birth Certificate',
            'disability_assessment': 'Disability Assessment Report',
            'income_certificate': 'Certificate of Indigency'
        };

        let city = record.city_municipality;
        let province = record.province;

        if (!city || city === 'Unknown City') {
            city = 'Santo Tomas City';
        }
        if (!province || province === 'Unknown Province') {
            province = 'Batangas';
        }

        modalBody.innerHTML = `
                <div class="record-details">
                    <div class="details-grid">
                        <div class="detail-section">
                            <h4><i class="fas fa-user"></i> Personal Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Full Name:</span>
                                    <span class="value">${record.first_name} ${record.middle_name || ''} ${record.last_name} ${record.suffix || ''}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Date of Birth:</span>
                                    <span class="value">${formatDate(record.date_of_birth)}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Age:</span>
                                    <span class="value">${record.age} years old</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Gender:</span>
                                    <span class="value">${record.gender}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Civil Status:</span>
                                    <span class="value">${record.civil_status}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Address:</span>
                                    <span class="value">${record.address_line1}${record.address_line2 ? ', ' + record.address_line2 : ''}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Barangay:</span>
                                    <span class="value">${record.barangay}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">City/Municipality:</span>
                                    <span class="value">${city}</span> </div>
                                <div class="detail-row">
                                    <span class="label">Province:</span>
                                    <span class="value">${province}</span> </div>
                                <div class="detail-row">
                                    <span class="label">Postal Code:</span>
                                    <span class="value">${record.postal_code || 'Not specified'}</span>
                                </div>
                                ${record.latitude && record.longitude ? `
                                <div class="detail-row">
                                    <span class="label">Coordinates:</span>
                                    <span class="value">${parseFloat(record.latitude).toFixed(6)}, ${parseFloat(record.longitude).toFixed(6)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-wheelchair"></i> Disability Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Type:</span>
                                    <span class="value">${record.disability_type}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Cause:</span>
                                    <span class="value">${record.disability_cause || 'Not specified'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Description:</span>
                                    <span class="value">${record.disability_description || 'Not provided'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Assistive Device:</span>
                                    <span class="value">${record.assistive_device || 'None specified'}</span>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section full-width">
                            <h4><i class="fas fa-folder-open"></i> Uploaded Documents</h4>
                            <div class="documents-list-admin">
                                ${generateDocRow(record, 'doc_id_picture', '1x1 ID Pictures', 'fa-id-card')}
                                ${generateDocRow(record, 'doc_birth_certificate', 'Birth Certificate', 'fa-file-alt')}
                                ${generateDocRow(record, 'doc_medical_certificate', 'Certificate of Disability', 'fa-file-medical')}
                                ${generateDocRow(record, 'doc_voters_certificate', 'Voter\'s Certification', 'fa-vote-yea')}
                                ${generateDocRow(record, 'doc_registration_form', 'PWD Registration Form', 'fa-file-signature')}
                            </div>
                        </div>

                        ${documentsVerified.length > 0 ? `
                        <div class="detail-section">
                            <h4><i class="fas fa-clipboard-check"></i> Documents Verified</h4>
                            <div class="documents-list">
                                ${documentsVerified.map(doc => `
                                    <div class="document-verified">
                                        <i class="fas fa-check-circle"></i>
                                        <span>${documentLabels[doc] || doc}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-phone"></i> Contact Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Phone:</span>
                                    <span class="value">${record.phone_number}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Email:</span>
                                    <span class="value">${record.email_address || 'Not provided'}</span>
                                </div>
                                ${record.emergency_contact_name ? `
                                <div class="detail-row">
                                    <span class="label">Emergency Contact:</span>
                                    <span class="value">${record.emergency_contact_name} (${record.emergency_contact_relationship || 'Relationship not specified'})</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Emergency Phone:</span>
                                    <span class="value">${record.emergency_contact_phone || 'Not provided'}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-briefcase"></i> Employment Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Status:</span>
                                    <span class="value">${record.employment_status}</span>
                                </div>
                                ${record.occupation ? `
                                <div class="detail-row">
                                    <span class="label">Occupation:</span>
                                    <span class="value">${record.occupation}</span>
                                </div>
                                ` : ''}
                                ${record.employer_name ? `
                                <div class="detail-row">
                                    <span class="label">Employer:</span>
                                    <span class="value">${record.employer_name}</span>
                                </div>
                                ` : ''}
                                ${record.monthly_income ? `
                                <div class="detail-row">
                                    <span class="label">Monthly Income:</span>
                                    <span class="value">₱${parseFloat(record.monthly_income).toLocaleString()}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-flag"></i> Record Status</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Status:</span>
                                    <span class="value"><span class="status-badge status-${record.status}">${record.status.charAt(0).toUpperCase() + record.status.slice(1)}</span></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Created:</span>
                                    <span class="value">${formatDateTime(record.created_at)} by ${record.created_by_name}</span>
                                </div>
                                ${record.validation_date ? `
                                <div class="detail-row">
                                    <span class="label">Validated:</span>
                                    <span class="value">${formatDateTime(record.validation_date)} by ${record.validated_by_name}</span>
                                </div>
                                ` : ''}
                                ${record.issue_date ? `
                                <div class="detail-row">
                                    <span class="label">Issued:</span>
                                    <span class="value">${formatDateTime(record.issue_date)} by ${record.issued_by_name}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Expires:</span>
                                    <span class="value">${formatDate(record.expiry_date)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
    }

    // Edit record
    function editRecord(recordId) {
        fetch('records.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_record_details&record_id=${recordId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateEditForm(data.record);
                    showModal('editRecordModal');
                    // Initialize map after modal is shown
                    setTimeout(() => {
                        initializeEditLocationMap();
                        // Set existing location if available
                        if (data.record.latitude && data.record.longitude) {
                            const lat = parseFloat(data.record.latitude);
                            const lng = parseFloat(data.record.longitude);
                            setEditLocation(lat, lng);
                            editLocationMap.setView([lat, lng], 16);
                        }
                    }, 300);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to load record details', 'error');
            });
    }

    function populateEditForm(record) {
        let city = record.city_municipality;
        let province = record.province;

        if (!city || city === 'Unknown City') {
            city = 'Santo Tomas City';
        }
        if (!province || province === 'Unknown Province') {
            province = 'Batangas';
        }

        document.getElementById('editRecordId').value = record.id;
        document.getElementById('editFirstName').value = record.first_name || '';
        document.getElementById('editMiddleName').value = record.middle_name || '';
        document.getElementById('editLastName').value = record.last_name || '';
        document.getElementById('editSuffix').value = record.suffix || '';
        document.getElementById('editPhone').value = record.phone_number || '';
        document.getElementById('editEmail').value = record.email_address || '';
        document.getElementById('editAddress1').value = record.address_line1 || '';
        document.getElementById('editAddress2').value = record.address_line2 || '';
        document.getElementById('editBarangay').value = record.barangay || '';
        document.getElementById('editCity').value = city;
        document.getElementById('editProvince').value = province;
        document.getElementById('editPostal').value = record.postal_code || '';
        document.getElementById('editLatitude').value = record.latitude || '';
        document.getElementById('editLongitude').value = record.longitude || '';
        document.getElementById('editDisabilityType').value = record.disability_type || '';
        document.getElementById('editDisabilityCause').value = record.disability_cause || '';
        document.getElementById('editDisabilityDescription').value = record.disability_description || '';
        document.getElementById('editEmploymentStatus').value = record.employment_status || '';
        document.getElementById('editOccupation').value = record.occupation || '';
        document.getElementById('editEmployer').value = record.employer_name || '';
        document.getElementById('editIncome').value = record.monthly_income || '';
    }

    // Validate record
    function validateRecord(recordId) {
        document.getElementById('validateRecordId').value = recordId;
        showModal('validationModal');
    }

    // Issue ID
    function issueID(recordId) {
        document.getElementById('issueRecordId').value = recordId;
        document.getElementById('issueAction').value = 'issue_id';
        document.querySelector('#issueModal .modal-header h3').textContent = 'Issue PWD ID';
        showModal('issueModal');
    }

    // Delete record
    function deleteRecord(recordId, pwdId) {
        const reason = prompt(`Please provide a reason for deleting PWD record ${pwdId}:`);
        if (reason) {
            if (confirm(`Are you sure you want to delete PWD record ${pwdId}? This action cannot be undone.`)) {
                fetch('records.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_record&record_id=${recordId}&reason=${encodeURIComponent(reason)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification(data.error, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Failed to delete record', 'error');
                    });
            }
        }
    }

    // Activate / Renew ID
    function renewOrActivateID(recordId) {
        document.getElementById('issueRecordId').value = recordId;
        document.getElementById('issueAction').value = 'renew_or_activate_id';
        document.querySelector('#issueModal .modal-header h3').textContent = 'Renew / Activate PWD ID';
        showModal('issueModal');
    }

    // Deactivate record
    function deactivateRecord(recordId, pwdId) {
        const reason = prompt(`Please provide a reason for deactivating PWD ID ${pwdId}:`);
        if (reason) {
            if (confirm(`Are you sure you want to DEACTIVATE PWD ID ${pwdId}? This will mark it as inactive.`)) {
                fetch('records.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=deactivate_record&record_id=${recordId}&reason=${encodeURIComponent(reason)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification(data.error, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Failed to deactivate record', 'error');
                    });
            }
        }
    }

    // Export records
    function exportRecords() {
        const params = new URLSearchParams();
        params.append('export', '1');

        const status = document.getElementById('status').value;
        const barangay = document.getElementById('barangay').value;
        const disabilityType = document.getElementById('disability_type').value;
        const gender = document.getElementById('gender').value;
        const ageGroup = document.getElementById('age_group').value;
        const employment = document.getElementById('employment_status').value;
        const search = document.getElementById('search').value;
        const location_filter = document.getElementById('location_filter').value;

        if (status) params.append('status', status);
        if (barangay) params.append('barangay', barangay);
        if (disabilityType) params.append('disability_type', disabilityType);
        if (gender) params.append('gender', gender);
        if (ageGroup) params.append('age_group', ageGroup);
        if (employment) params.append('employment_status', employment);
        if (search) params.append('search', search);
        if (location_filter) params.append('location', location_filter);

        window.location.href = `records.php?${params.toString()}`;
    }

    // Form submissions
    document.getElementById('editRecordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'update_record');

        fetch('records.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('editRecordModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to update record', 'error');
            });
    });

    document.getElementById('validationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'validate_record');

        fetch('records.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('validationModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to validate record', 'error');
            });
    });

    document.getElementById('issueForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('records.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('issueModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to issue ID', 'error');
            });
    });

    function exportRecordsPDF() {
        const params = new URLSearchParams();
        params.append('export', 'pdf');

        const status = document.getElementById('status').value;
        const barangay = document.getElementById('barangay').value;
        const disabilityType = document.getElementById('disability_type').value;
        const gender = document.getElementById('gender').value;
        const ageGroup = document.getElementById('age_group').value;
        const employment = document.getElementById('employment_status').value;
        const search = document.getElementById('search').value;
        const location_filter = document.getElementById('location_filter').value;

        if (status) params.append('status', status);
        if (barangay) params.append('barangay', barangay);
        if (disabilityType) params.append('disability_type', disabilityType);
        if (gender) params.append('gender', gender);
        if (ageGroup) params.append('age_group', ageGroup);
        if (employment) params.append('employment_status', employment);
        if (search) params.append('search', search);
        if (location_filter) params.append('location', location_filter);

        window.location.href = `records.php?${params.toString()}`;
    }

    function showCreateRecordModal() {
        document.getElementById('createRecordForm').reset();
        document.getElementById('createCity').value = 'Santo Tomas City';
        document.getElementById('createProvince').value = 'Batangas';
        document.getElementById('createPostalCode').value = '4234';

        document.getElementById('createManualBarangay').style.display = 'none';
        document.getElementById('createBarangayManual').required = false;
        document.getElementById('createBarangayId').required = true;

        showModal('createRecordModal');
        setTimeout(() => {
            initializeCreateLocationMap();
        }, 300);
    }

    function resetCreateForm() {
        if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
            const form = document.getElementById('createRecordForm');
            if (form) {
                form.reset();
                document.getElementById('createCity').value = 'Santo Tomas City';
                document.getElementById('createProvince').value = 'Batangas';
                document.getElementById('createPostalCode').value = '4234';
                document.getElementById('createManualBarangay').style.display = 'none';
                document.getElementById('createBarangayManual').required = false;
                document.getElementById('createBarangayId').required = true;
                clearLocationCreate();
                showNotification('Form has been reset', 'info');
            }
        }
    }

    document.getElementById('createRecordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const requiredFields = this.querySelectorAll('[required]');
        let isValid = true;
        let missingFields = [];

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                missingFields.push(field.name || field.id);
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });

        const barangaySelect = document.getElementById('createBarangayId');
        const manualBarangay = document.getElementById('createBarangayManual');
        const barangayName = document.getElementById('createBarangayName');

        if (!barangaySelect.value && !manualBarangay.value) {
            showNotification('Please select a barangay or enter it manually.', 'error');
            isValid = false;
            missingFields.push('barangay');
        } else if (manualBarangay.value) {
            barangayName.value = manualBarangay.value;
        }

        if (!isValid) {
            showNotification('Please fill in all required fields: ' + missingFields.join(', '), 'error');
            return false;
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Record...';
        }

        const formData = new FormData(this);

        fetch('records.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('createRecordModal');
                    setTimeout(() => {
                        if (data.record_id) {
                            window.location.href = `records.php?highlight=${data.record_id}`;
                        } else {
                            location.reload();
                        }
                    }, 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to create PWD record', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        return false;
    });
</script>
    
    <style>

        .documents-list-admin {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
    padding-top: 10px;
}
.document-link-admin {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; background: #eef2ff;
    color: #312e81; text-decoration: none;
    font-weight: 500; border-radius: 6px;
    border: 1px solid #c7d2fe;
    transition: background-color 0.2s ease;
}
.document-link-admin i { color: #4f46e5; }
.document-link-admin:hover { background: #e0e7ff; }
.document-missing-admin {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; background: #fdf2f2;
    color: #7f1d1d; font-weight: 500;
    border-radius: 6px; border: 1px solid #fecaca;
    opacity: 0.8;
}
.document-missing-admin i { color: #b91c1c; }
.document-physical-admin {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; background: #f0f9ff;
    color: #0369a1; font-weight: 500;
    border-radius: 6px; border: 1px solid #bae6fd;
    opacity: 0.8;
}
.document-physical-admin i { color: #0ea5e9; }

.interview-tabs {
            display: flex;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            margin: -24px -24px 24px -24px; /* Adjust to fit modal padding */
        }
        
        .tab-btn {
            flex: 1;
            padding: 20px 24px;
            background: none;
            border: none;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
        }
        
        .tab-btn:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        .tab-btn.active {
            background: white;
            color: #2563eb;
            border-bottom: 3px solid #2563eb;
        }

        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }

        .document-checklist {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }
        
        .document-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .document-item.verified {
            border-color: #10b981;
            background: #ecfdf5;
        }
        
        .document-label {
            display: flex;
            align-items: center;
            padding: 16px;
            cursor: pointer;
            gap: 16px;
        }
        
        .document-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #10b981;
            flex-shrink: 0;
        }

        .document-label input[type="checkbox"]:checked + .document-content + .document-status {
             opacity: 1;
        }

        .document-label input[type="checkbox"]:checked ~ .document-item {
            border-color: #10b981;
            background: #ecfdf5;
        }
        
        .document-content {
            flex: 1;
        }
        
        .document-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .document-description {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .document-status {
            color: #10b981;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        /* --- END OF NEW STYLES --- */

        .employment-badge {
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .employment-badge.employment-employed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .employment-badge.employment-unemployed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .employment-badge.employment-self-employed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .employment-badge.employment-student {
            background: #fef3c7;
            color: #92400e;
        }
        
        .employment-badge.employment-retired {
            background: #f3e8ff;
            color: #7c3aed;
        }
        
        .large-modal .modal-content {
            max-width: 900px;
        }
        
        .record-details {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .detail-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .detail-section h4 {
            color: #2c5aa0;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }
        
        .detail-rows {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-row .label {
            font-weight: 500;
            color: #64748b;
            min-width: 120px;
            flex-shrink: 0;
        }
        
        .detail-row .value {
            color: #1e293b;
            text-align: right;
            flex: 1;
            word-break: break-word;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h4 {
            color: #2c5aa0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .pagination-info {
            color: #64748b;
            font-size: 0.9rem;
        }

        .status-badge.status-expired {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.status-inactive {
            background: #4b5563; /* Dark gray */
            color: #f9fafb;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .extra-large-modal .modal-content {
            max-width: 1100px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .create-record-info {
            margin-bottom: 24px;
        }

        .info-banner {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 1px solid #93c5fd;
            border-radius: 8px;
            color: #1e40af;
        }

        .info-banner i {
            font-size: 1.2rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .info-banner strong {
            display: block;
            margin-bottom: 4px;
            font-size: 1rem;
        }

        .info-banner p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-header h4 {
            color: #1f2937;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }

        .section-description {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
            background: white;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-input.error,
        .form-select.error,
        .form-textarea.error {
            border-color: #ef4444;
        }

        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .location-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            align-items: start;
        }

        .location-inputs {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .location-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .map-container {
            position: relative;
        }

        .location-map {
            height: 300px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }

        .map-instructions {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 1000;
        }

        .address-helper {
            margin-top: 20px;
            padding: 16px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .helper-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0369a1;
            font-size: 0.9rem;
        }

        .helper-info i {
            color: #0ea5e9;
        }
        
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            
            .detail-row .value {
                text-align: left;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .pagination {
                flex-direction: column;
                gap: 12px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .extra-large-modal .modal-content {
                max-width: 95vw;
                margin: 20px;
            }

            .location-container {
                grid-template-columns: 1fr;
            }

            .location-actions {
                flex-direction: row;
            }
        }

        .status-badge.status-expired {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.status-inactive {
            background: #4b5563; /* Dark gray */
            color: #f9fafb;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        /* --- ADD THIS NEW CSS --- */
        .map-container.map-expanded {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            z-index: 10001; /* Higher than modal z-index */
            padding: 20px;
            width: 100vw; 
            height: 100vh;
        }
        
        .map-container.map-expanded .location-map {
            height: 100%;
            border: none;
        }

        /* Button when expanded */
        .map-container.map-expanded .expand-map-btn {
            position: absolute;
            top: 30px;
            right: 30px;
            z-index: 10002;
            background: white;
        }
        /* --- END OF NEW CSS --- */

        /* ADD THIS to your <style> block at the bottom */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .stat-icon.expired {
            background: #fef3c7;
            color: #92400e;
        }
        
        .stat-icon.inactive {
            background: #e5e7eb;
            color: #4b5563;
        }

        .document-verified {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #d1fae5;
    border-radius: 6px;
    color: #065f46;
    font-size: 0.9rem;
}

.document-verified i {
    color: #10b981;
}

/* Hide the confusing tabs in the 'Create Record' modal */
#createRecordModal .interview-tabs {
    display: none;
}

#createRecordModal #interview-notes-tab {
    display: none;
}

/* Ensure the main form is always visible */
#createRecordModal #pwd-record-tab {
    display: block !important;
}
    </style>

    <form id="globalRecordUploadForm" method="POST" enctype="multipart/form-data" style="display: none;">
    <input type="hidden" name="action" value="update_document">
    <input type="hidden" name="record_id" id="upload_record_id">
    <input type="hidden" name="doc_type" id="upload_doc_type">
    <input type="file" name="new_file" id="upload_file_input" 
           accept=".jpg,.jpeg,.png,.pdf"
           onchange="if(confirm('Are you sure you want to replace this document?')) document.getElementById('globalRecordUploadForm').submit();">
</form>

</body>
</html>
