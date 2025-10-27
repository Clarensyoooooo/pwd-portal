<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'records.view');

$admin = getCurrentAdmin($pdo);

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    requirePermission($pdo, 'records.export');
    ob_start(); // Start output buffering

    try {
        // --- 1. COPY FILTER LOGIC (from your CSV export) ---
        $status_filter = $_GET['status'] ?? '';
        $barangay_filter = $_GET['barangay'] ?? '';
        $disability_filter = $_GET['disability_type'] ?? '';
        $gender_filter = $_GET['gender'] ?? '';
        $age_group_filter = $_GET['age_group'] ?? '';
        $employment_filter = $_GET['employment_status'] ?? '';
        $search = $_GET['search'] ?? '';
        
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
        if ($search) {
            $where_conditions[] = "(pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.pwd_id_number LIKE ? OR pr.email_address LIKE ? OR pr.phone_number LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
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
    $status_filter = $_GET['status'] ?? '';
    $barangay_filter = $_GET['barangay'] ?? '';
    $disability_filter = $_GET['disability_type'] ?? '';
    $gender_filter = $_GET['gender'] ?? '';
    $age_group_filter = $_GET['age_group'] ?? '';
    $employment_filter = $_GET['employment_status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build WHERE conditions
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
    
    if ($search) {
        $where_conditions[] = "(pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.pwd_id_number LIKE ? OR pr.email_address LIKE ? OR pr.phone_number LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
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
        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Get records with enhanced filters
$status_filter = $_GET['status'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$disability_filter = $_GET['disability_type'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$age_group_filter = $_GET['age_group'] ?? '';
$employment_filter = $_GET['employment_status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

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

if ($search) {
    $where_conditions[] = "(pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.pwd_id_number LIKE ? OR pr.email_address LIKE ? OR pr.phone_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

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
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $expiry_date = date('Y-m-d', strtotime("+{$expiry_years} years"));
        
        $stmt = $pdo->prepare("
            UPDATE pwd_records 
            SET status = 'issued', issue_date = NOW(), expiry_date = ?, issued_by = ?
            WHERE id = ? AND status = 'validated'
        ");
        
        $stmt->execute([$expiry_date, $_SESSION['admin_user_id'], $record_id]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Record not found or not validated'], 400);
        }
        
        logAdminActivity($pdo, 'issue', 'records', 'pwd_record', $record_id, [
            'expiry_date' => $expiry_date
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD ID issued successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to issue ID: ' . $e->getMessage()], 500);
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
                   TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) as age
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
        adminJsonResponse(['error' => 'Failed to get record details: ' . $e->getMessage()], 500);
    }
}

function handleCreateDirectRecord() {
    global $pdo;
    requirePermission($pdo, 'records.create');
    
    try {
        $pdo->beginTransaction();
        
         // Generate a more robust unique PWD ID
        $year = date('Y');
        // This creates a short, random, and highly unique identifier
        $unique_part = substr(strtoupper(bin2hex(random_bytes(4))), 0, 6); 
        $pwd_id = "PWD-{$year}-" . $unique_part;
        // --- END OF NEW CODE ---
        
        // Get coordinates if provided
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        
        // Validate required fields
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['barangay'])) {
            throw new Exception('Required fields are missing');
        }
        
        // Create PWD record directly (no appointment_id) - FIXED parameter count
        $stmt = $pdo->prepare("
            INSERT INTO pwd_records (
                pwd_id_number, first_name, middle_name, last_name, suffix,
                date_of_birth, place_of_birth, gender, civil_status,
                address_line1, address_line2, barangay, city_municipality, province, postal_code,
                latitude, longitude,
                phone_number, email_address,
                disability_type, disability_cause, disability_description, assistive_device,
                medical_condition, medication, attending_physician,
                emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, emergency_contact_address,
                employment_status, occupation, employer_name, monthly_income,
                sss_number, philhealth_number, tin_number,
                status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Prepare parameters array with exact count (39 parameters)
        $params = [
            $pwd_id,                                               // 1
            $_POST['first_name'],                                  // 2
            $_POST['middle_name'] ?? null,                         // 3
            $_POST['last_name'],                                   // 4
            $_POST['suffix'] ?? null,                              // 5
            $_POST['date_of_birth'],                               // 6
            $_POST['place_of_birth'] ?? null,                      // 7
            $_POST['gender'],                                      // 8
            $_POST['civil_status'],                                // 9
            $_POST['address_line1'],                               // 10
            $_POST['address_line2'] ?? null,                       // 11
            $_POST['barangay'],                                    // 12
            $_POST['city_municipality'] ?? 'Santo Tomas City',     // 13
            $_POST['province'] ?? 'Batangas',                      // 14
            $_POST['postal_code'] ?? null,                         // 15
            $latitude,                                             // 16
            $longitude,                                            // 17
            $_POST['phone_number'],                                // 18
            $_POST['email_address'] ?? null,                       // 19
            $_POST['disability_type'],                             // 20
            $_POST['disability_cause'] ?? null,                    // 21
            $_POST['disability_description'] ?? null,              // 22
            $_POST['assistive_device'] ?? null,                    // 23
            $_POST['medical_condition'] ?? null,                   // 24
            $_POST['medication'] ?? null,                          // 25
            $_POST['attending_physician'] ?? null,                 // 26
            $_POST['emergency_contact_name'] ?? null,              // 27
            $_POST['emergency_contact_relationship'] ?? null,      // 28
            $_POST['emergency_contact_phone'] ?? null,             // 29
            $_POST['emergency_contact_address'] ?? null,           // 30
            $_POST['employment_status'] ?? 'Unemployed',           // 31
            $_POST['occupation'] ?? null,                          // 32
            $_POST['employer_name'] ?? null,                       // 33
            !empty($_POST['monthly_income']) ? floatval($_POST['monthly_income']) : null, // 34
            $_POST['sss_number'] ?? null,                          // 35
            $_POST['philhealth_number'] ?? null,                   // 36
            $_POST['tin_number'] ?? null,                          // 37
            $_POST['record_status'] ?? 'draft',                    // 38 - Allow setting initial status
            $_SESSION['admin_user_id']                             // 39
        ];
        
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to insert PWD record');
        }
        
        $record_id = $pdo->lastInsertId();
        
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
        $pdo->rollBack();
        adminJsonResponse(['error' => 'Failed to create PWD record: ' . $e->getMessage()], 500);
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
    
    
    <main class="main-content">
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
            $stats_query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
                    SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued,
                    SUM(CASE WHEN expiry_date < CURDATE() AND status = 'issued' THEN 1 ELSE 0 END) as expired
                FROM pwd_records
            ";
            $stats_result = $pdo->query($stats_query)->fetch();
            ?>
            
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['total']); ?></h3>
                    <p>Total Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['draft']); ?></h3>
                    <p>Draft Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon validated">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['validated']); ?></h3>
                    <p>Validated Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-id-badge"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['issued']); ?></h3>
                    <p>IDs Issued</p>
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
                        <option value="Other" <?php echo $gender_filter === 'Other' ? 'selected' : ''; ?>>Other</option>
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
                                        
                                        <?php if (hasPermission($pdo, 'records.delete')): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['pwd_id_number']); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
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
                    
                     Geographic Location for Edit 
                    <div class="form-section">
                        <div class="section-header">
                            <h4><i class="fas fa-map"></i> Geographic Location (Optional)</h4>
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
                            <option value="3">3 Years</option>
                            <option value="1">1 Year</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        This will mark the record as "Issued" and set the expiry date. The PWD ID can then be printed and given to the applicant.
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
                <div class="create-record-info">
                    <div class="info-banner">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Direct Record Creation</strong>
                            <p>Create PWD records directly without requiring an appointment or interview. Perfect for existing PWD ID holders or bulk data entry.</p>
                        </div>
                    </div>
                </div>
                
                <form id="createRecordForm">
                    <input type="hidden" name="action" value="create_direct_record">
                    
                     Personal Information 
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
                                    <option value="Other">Other</option>
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
                    
                     Address Information 
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
                                        // FIX: Check for null, empty, or "Unknown City"
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
                    
                     Geographic Location 
                    <div class="form-section">
                        <div class="section-header">
                            <h4><i class="fas fa-map"></i> Geographic Location (Optional)</h4>
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
                    
                     Contact Information 
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
                    
                     Disability Information 
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
                    
                     Medical Information 
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
                    
                     Emergency Contact 
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
                    
                     Employment Information 
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
                    
                     Government IDs 
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
                    
                     Record Status 
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
                                    <option value="issued">Issued - ID already issued (for existing holders)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
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
    <script>
        let createLocationMap;
        let createLocationMarker;
        let editLocationMap;
        let editLocationMarker;
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize maps when modals are shown
        });
        
        // Initialize create location map
        function initializeCreateLocationMap() {
            const mapElement = document.getElementById('createLocationMap');
            if (!mapElement || createLocationMap) return;
            
            // Default center (Santo Tomas City, Batangas)
            const defaultLat = 14.1078;
            const defaultLng = 121.1414;
            
            createLocationMap = L.map('createLocationMap').setView([defaultLat, defaultLng], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(createLocationMap);
            
            // Add click event to map
            createLocationMap.on('click', function(e) {
                setCreateLocation(e.latlng.lat, e.latlng.lng);
            });
        }
        
        // Initialize edit location map
        function initializeEditLocationMap() {
            const mapElement = document.getElementById('editLocationMap');
            if (!mapElement || editLocationMap) return;
            
            // Default center (Santo Tomas City, Batangas)
            const defaultLat = 14.1078;
            const defaultLng = 121.1414;
            
            editLocationMap = L.map('editLocationMap').setView([defaultLat, defaultLng], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(editLocationMap);
            
            // Add click event to map
            editLocationMap.on('click', function(e) {
                setEditLocation(e.latlng.lat, e.latlng.lng);
            });
        }
        
        // Set location on create map
        function setCreateLocation(lat, lng) {
            // Remove existing marker
            if (createLocationMarker) {
                createLocationMap.removeLayer(createLocationMarker);
            }
            
            // Add new marker
            createLocationMarker = L.marker([lat, lng]).addTo(createLocationMap);
            
            // Update input fields
            document.getElementById('createLatitude').value = lat.toFixed(6);
            document.getElementById('createLongitude').value = lng.toFixed(6);
            
            // Show success message
            showNotification('Location set successfully!', 'success');
        }
        
        // Set location on edit map
        function setEditLocation(lat, lng) {
            // Remove existing marker
            if (editLocationMarker) {
                editLocationMap.removeLayer(editLocationMarker);
            }
            
            // Add new marker
            editLocationMarker = L.marker([lat, lng]).addTo(editLocationMap);
            
            // Update input fields
            document.getElementById('editLatitude').value = lat.toFixed(6);
            document.getElementById('editLongitude').value = lng.toFixed(6);
            
            // Show success message
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
                
                // Hide manual barangay input
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
                
                // Allow manual city/province editing
                document.getElementById('createCity').readOnly = false;
                document.getElementById('createProvince').readOnly = false;
                
                showNotification('Manual barangay entry enabled', 'info');
            } else {
                manualDiv.style.display = 'none';
                manualInput.required = false;
                barangaySelect.required = true;
                manualInput.value = '';
                
                // Reset to readonly
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
        
        function displayRecordDetails(record) {
            const modalBody = document.getElementById('recordModalBody');
            const modalTitle = document.getElementById('recordModalTitle');
            
            modalTitle.textContent = `PWD Record: ${record.pwd_id_number}`;
            
            // --- NEW FIX: Clean up city/province data ---
            let city = record.city_municipality;
            let province = record.province;

            if (!city || city === 'Unknown City') {
                city = 'Santo Tomas City';
            }
            if (!province || province === 'Unknown Province') {
                province = 'Batangas';
            }
            // --- END OF FIX ---

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
            // --- NEW FIX: Clean up city/province data ---
            let city = record.city_municipality;
            let province = record.province;

            if (!city || city === 'Unknown City') {
                city = 'Santo Tomas City';
            }
            if (!province || province === 'Unknown Province') {
                province = 'Batangas';
            }
            // --- END OF FIX ---

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
            document.getElementById('editCity').value = city; // Use the fixed 'city' variable
            document.getElementById('editProvince').value = province; // Use the fixed 'province' variable
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
            document.getElementById('issueAction').value = 'issue_id'; // Set action
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
            document.getElementById('issueAction').value = 'renew_or_activate_id'; // Set action
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
            // Build the export URL with all current filters
            const params = new URLSearchParams();
            params.append('export', '1');
            
            // Get filter values
            const status = document.getElementById('status').value;
            const barangay = document.getElementById('barangay').value;
            const disabilityType = document.getElementById('disability_type').value;
            const gender = document.getElementById('gender').value;
            const ageGroup = document.getElementById('age_group').value;
            const employment = document.getElementById('employment_status').value;
            const search = document.getElementById('search').value;
            
            // Add filters to params
            if (status) params.append('status', status);
            if (barangay) params.append('barangay', barangay);
            if (disabilityType) params.append('disability_type', disabilityType);
            if (gender) params.append('gender', gender);
            if (ageGroup) params.append('age_group', ageGroup);
            if (employment) params.append('employment_status', employment);
            if (search) params.append('search', search);
            
            // Trigger download
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
            // The 'action' is now set in the hidden input by issueID() or renewOrActivateID()
            
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

        // Export records as PDF
        function exportRecordsPDF() {
            // Build the export URL with all current filters
            const params = new URLSearchParams();
            params.append('export', 'pdf'); // Use the 'pdf' trigger
            
            // Get filter values
            const status = document.getElementById('status').value;
            const barangay = document.getElementById('barangay').value;
            const disabilityType = document.getElementById('disability_type').value;
            const gender = document.getElementById('gender').value;
            const ageGroup = document.getElementById('age_group').value;
            const employment = document.getElementById('employment_status').value;
            const search = document.getElementById('search').value;
            
            // Add filters to params
            if (status) params.append('status', status);
            if (barangay) params.append('barangay', barangay);
            if (disabilityType) params.append('disability_type', disabilityType);
            if (gender) params.append('gender', gender);
            if (ageGroup) params.append('age_group', ageGroup);
            if (employment) params.append('employment_status', employment);
            if (search) params.append('search', search);
            
            // Trigger download
            window.location.href = `records.php?${params.toString()}`;
        }

        // Show create record modal
        function showCreateRecordModal() {
            // Reset form
            document.getElementById('createRecordForm').reset();
            
            // Set default values
            document.getElementById('createCity').value = 'Santo Tomas City';
            document.getElementById('createProvince').value = 'Batangas';
            document.getElementById('createPostalCode').value = '4234';
            
            // Hide manual barangay input
            document.getElementById('createManualBarangay').style.display = 'none';
            document.getElementById('createBarangayManual').required = false;
            document.getElementById('createBarangayId').required = true;
            
            showModal('createRecordModal');
            
            // Initialize map after modal is shown
            setTimeout(() => {
                initializeCreateLocationMap();
            }, 300);
        }

        // Reset create form
        function resetCreateForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                const form = document.getElementById('createRecordForm');
                if (form) {
                    form.reset();
                    
                    // Reset default values
                    document.getElementById('createCity').value = 'Santo Tomas City';
                    document.getElementById('createProvince').value = 'Batangas';
                    document.getElementById('createPostalCode').value = '4234';
                    
                    // Hide manual barangay input
                    document.getElementById('createManualBarangay').style.display = 'none';
                    document.getElementById('createBarangayManual').required = false;
                    document.getElementById('createBarangayId').required = true;
                    
                    // Clear location
                    clearLocationCreate();
                    
                    showNotification('Form has been reset', 'info');
                }
            }
        }

        // Handle create record form submission
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
            
            // Check barangay selection
            const barangaySelect = document.getElementById('createBarangayId');
            const manualBarangay = document.getElementById('createBarangayManual');
            const barangayName = document.getElementById('createBarangayName');
            
            if (!barangaySelect.value && !manualBarangay.value) {
                showNotification('Please select a barangay or enter it manually.', 'error');
                isValid = false;
                missingFields.push('barangay');
            } else if (manualBarangay.value) {
                // Set manual barangay name
                barangayName.value = manualBarangay.value;
            }
            
            if (!isValid) {
                showNotification('Please fill in all required fields: ' + missingFields.join(', '), 'error');
                return false;
            }
            
            // Show loading state
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
                // Restore button state
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
            
            return false;
        });
    </script>
    
    <style>
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
    </style>
</body>
</html>
