<?php
require_once '../config.php';
// ADD THIS LINE TO LOAD TCPDF
require_once __DIR__ . '/../../vendor/autoload.php';
header('Content-Type: application/json');
global $pdo;

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Public actions - no login required
$publicActions = ['get_programs', 'submit_program_application'];

// Check if user is admin for protected actions
if (!in_array($action, $publicActions) && !isAdminLoggedIn()) {
    adminJsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

switch ($action) {
    case 'get_programs':
        getPrograms();
        break;
    case 'get_applications':
        getApplications();
        break;
    case 'get_application':
        getApplication();
        break;
    case 'save_program':
        saveProgram();
        break;
    case 'delete_program':
        deleteProgram();
        break;
    case 'update_application_status':
        updateApplicationStatus();
        break;
    case 'submit_program_application':
        submitProgramApplication();
        break;
        // ADD THIS NEW CASE
case 'get_stats':
    getProgramStats();
    break;
        // ADD THIS NEW CASE
case 'export_programs':
    handleExportPrograms();
    break;
    // ADD THIS NEW CASE
case 'export_programs_pdf':
    handleExportProgramsPDF();
    break;
    // ADD THIS NEW CASE
case 'export_applications':
    handleExportApplications();
    break;
    // ADD THIS NEW CASE
case 'export_applications_pdf':
    handleExportApplicationsPDF();
    break;
    default:
        adminJsonResponse(['success' => false, 'error' => 'Invalid action']);
}

// REPLACE your entire getPrograms() function with this new version
function getPrograms() {
    global $pdo;
    

    try {
        // Filters & Pagination
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';

        $per_page = 10; // 10 programs per page
        $page = max(1, intval($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per_page;

        $where_conditions = [];
        $params = [];

        // Public users see only active programs
        if (!isAdminLoggedIn()) {
            $where_conditions[] = "p.status = 'active'";
        }

        if ($search) {
            $where_conditions[] = "(p.title LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($category) {
            $where_conditions[] = "p.category = ?";
            $params[] = $category;
        }
        if ($status && isAdminLoggedIn()) { // Only admins can filter by status
            $where_conditions[] = "p.status = ?";
            $params[] = $status;
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count for pagination
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM programs p {$where_clause}");
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $per_page);

        // Get paginated results
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as created_by_name
            FROM programs p
            LEFT JOIN admin_users u ON p.created_by = u.id
            {$where_clause}
            ORDER BY p.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pagination = [
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        ];

        adminJsonResponse(['success' => true, 'programs' => $programs, 'pagination' => $pagination]);
    } catch (PDOException $e) {
        error_log("Database error in getPrograms: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Database error'], 500);
    }
}

// REPLACE your entire getApplications() function with this new version
function getApplications() {
    global $pdo;

    try {
        // Filters & Pagination
        $search = $_GET['search'] ?? '';
        $program_title = $_GET['program'] ?? '';
        $status = $_GET['status'] ?? '';

        $per_page = 10; // 10 applications per page
        $page = max(1, intval($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per_page;

        $where_conditions = [];
        $params = [];

        if ($search) {
            $where_conditions[] = "(pa.first_name LIKE ? OR pa.last_name LIKE ? OR pa.email LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        if ($program_title) {
            $where_conditions[] = "p.title = ?";
            $params[] = $program_title;
        }
        if ($status) {
            $where_conditions[] = "pa.status = ?";
            $params[] = $status;
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count for pagination
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM program_applications pa
            JOIN programs p ON pa.program_id = p.id
            {$where_clause}
        ");
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $per_page);

        // Get paginated results
        $stmt = $pdo->prepare("
            SELECT pa.*, p.title as program_title
            FROM program_applications pa
            JOIN programs p ON pa.program_id = p.id
            {$where_clause}
            ORDER BY pa.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pagination = [
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        ];

        adminJsonResponse(['success' => true, 'applications' => $applications, 'pagination' => $pagination]);
    } catch (PDOException $e) {
        error_log("Database error in getApplications: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Database error'], 500);
    }
}

function getApplication() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        adminJsonResponse(['success' => false, 'error' => 'Missing application ID']);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT pa.*, p.title as program_title
            FROM program_applications pa
            JOIN programs p ON pa.program_id = p.id
            WHERE pa.id = ?
        ");
        $stmt->execute([$id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            adminJsonResponse(['success' => false, 'error' => 'Application not found'], 404);
        }
        
        adminJsonResponse(['success' => true, 'application' => $application]);
    } catch (PDOException $e) {
        error_log("Database error in getApplication: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Database error'], 500);
    }
}

function saveProgram() {
    global $pdo;
    
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $max_applicants = $_POST['max_applicants'] ?? null;
    $deadline = $_POST['application_deadline'] ?? null;
    
    if (!$title || !$category || !$description || !$requirements) {
        adminJsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
    }
    
    try {
        if ($id) {
            // Update existing program
            $stmt = $pdo->prepare("
                UPDATE programs 
                SET title = ?, category = ?, icon = ?, description = ?, 
                    requirements = ?, status = ?, max_applicants = ?, 
                    application_deadline = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $category, $icon, $description, $requirements, 
                $status, $max_applicants ?: null, $deadline ?: null, $id
            ]);
            $message = 'Program updated successfully';
        } else {
            // Create new program
            $stmt = $pdo->prepare("
                INSERT INTO programs (title, category, icon, description, requirements, 
                                     status, max_applicants, application_deadline, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $category, $icon, $description, $requirements, 
                $status, $max_applicants ?: null, $deadline ?: null, $_SESSION['admin_user_id']
            ]);
            $message = 'Program created successfully';
        }
        
        // Log activity
        logAdminActivity($pdo, $id ? 'update' : 'create', 'programs', 'program', $id, ['title' => $title]);
        
        adminJsonResponse(['success' => true, 'message' => $message]);
    } catch (PDOException $e) {
        error_log("Database error in saveProgram: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to save program'], 500);
    }
}

function deleteProgram() {
    global $pdo;
    
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        adminJsonResponse(['success' => false, 'error' => 'Missing program ID'], 400);
    }
    
    try {
        // Delete associated applications first
        $stmt = $pdo->prepare("DELETE FROM program_applications WHERE program_id = ?");
        $stmt->execute([$id]);
        
        // Delete program
        $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->execute([$id]);
        
        // Log activity
        logAdminActivity($pdo, 'delete', 'programs', 'program', $id, []);
        
        adminJsonResponse(['success' => true, 'message' => 'Program deleted successfully']);
    } catch (PDOException $e) {
        error_log("Database error in deleteProgram: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to delete program'], 500);
    }
}

function updateApplicationStatus() {
    global $pdo;
    
    $id = $_POST['id'] ?? null;
    $status = $_POST['status'] ?? null;
    $review_notes = $_POST['review_notes'] ?? null;
    
    if (!$id || !in_array($status, ['submitted', 'under_review', 'approved', 'rejected'])) {
        adminJsonResponse(['success' => false, 'error' => 'Invalid input'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE program_applications 
            SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $review_notes, $_SESSION['admin_user_id'], $id]);
        
        // Log activity
        logAdminActivity($pdo, 'update', 'programs', 'application', $id, ['status' => $status]);
        
        adminJsonResponse(['success' => true, 'message' => 'Application updated successfully']);
    } catch (PDOException $e) {
        error_log("Database error in updateApplicationStatus: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to update application'], 500);
    }
}

function handleExportPrograms() {
    global $pdo;
    
    // This export is for admins only
    if (!isAdminLoggedIn()) {
        adminJsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    
    try {
        // 1. Get filters from the URL
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';

        logAdminActivity($pdo, 'export', 'programs', 'programs_csv', null, [
            'filters' => compact('search', 'category', 'status')
        ]);
        
        $where_conditions = [];
        $params = [];
        
        if ($search) {
            $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        if ($category) {
            $where_conditions[] = "category = ?";
            $params[] = $category;
        }
        
        if ($status) {
            $where_conditions[] = "status = ?";
            $params[] = $status;
        }
        
        // Admins should see all programs, so we don't filter by 'active'
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // 2. Query the database
        $stmt = $pdo->prepare("
            SELECT * FROM programs
            {$where_clause}
            ORDER BY created_at DESC
        ");
        $stmt->execute($params);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="programs_export_' . date('Y-m-d') . '.csv"');
        
        // 4. Output the CSV
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'ID',
            'Title',
            'Category',
            'Status',
            'Description',
            'Requirements (Pipe-separated)',
            'Max Applicants',
            'Application Deadline',
            'Created At'
        ]);
        
        // Add program data
        foreach ($programs as $program) {
            fputcsv($output, [
                $program['id'],
                $program['title'],
                $program['category'],
                $program['status'],
                $program['description'],
                $program['requirements'],
                $program['max_applicants'],
                $program['application_deadline'],
                $program['created_at']
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        // If something fails, send a JSON error instead of a broken file
        error_log("Export error: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to export programs.'], 500);
    }
}

/**
 * ADD THIS ENTIRE NEW FUNCTION
 * Handles exporting filtered programs as a PDF file.
 */
function handleExportProgramsPDF() {
    global $pdo;

    if (!isAdminLoggedIn()) {
        adminJsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    try {
        // 1. Get filters (same logic as get_programs)
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';

        // ADD THIS LINE
        logAdminActivity($pdo, 'export', 'programs', 'programs_pdf', null, [
            'filters' => compact('search', 'category', 'status')
        ]);

        $where_conditions = [];
        $params = [];

        if ($search) {
            $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
        }
        if ($category) {
            $where_conditions[] = "category = ?";
            $params[] = $category;
        }
        if ($status) {
            $where_conditions[] = "status = ?";
            $params[] = $status;
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // 2. Query the database
        $stmt = $pdo->prepare("
            SELECT * FROM programs
            {$where_clause}
            ORDER BY created_at DESC
        ");
        $stmt->execute($params);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. --- PDF GENERATION ---
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admin Portal');
        $pdf->SetTitle('Programs Export');
        $pdf->SetSubject('Programs');

        // Set header and footer data
        $pdf->setHeaderData('', 0, 'Programs Export - ' . date('Y-m-d'), 'Generated by PWD Portal Admin');
        $pdf->setFooterData([0, 64, 0], [0, 64, 128]);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Add a page (P for Portrait)
        $pdf->AddPage('P', 'A4');

        // Set font
        $pdf->SetFont('helvetica', '', 9);

        // 4. Build HTML content for the PDF
        $html = '<h1>Programs Export</h1>';
        $html .= '<p>Filters applied: ';
        $html .= $category ? "Category (<strong>" . htmlspecialchars($category) . "</strong>), " : "";
        $html .= $status ? "Status (<strong>" . htmlspecialchars($status) . "</strong>), " : "";
        $html .= $search ? "Search (<strong>" . htmlspecialchars($search) . "</strong>)" : "";
        $html .= '</p>';

        $html .= '<table border="1" cellpadding="5" cellspacing="0">
            <tr style="background-color:#E0E0E0; font-weight:bold;">
                <th width="5%">ID</th>
                <th width="25%">Title</th>
                <th width="15%">Category</th>
                <th width="10%">Status</th>
                <th width="15%">Max Applicants</th>
                <th width="15%">Deadline</th>
                <th width="15%">Created On</th>
            </tr>';

        if (count($programs) > 0) {
            foreach ($programs as $program) {
                $html .= '<tr>';
                $html .= '<td>' . $program['id'] . '</td>';
                $html .= '<td>' . htmlspecialchars($program['title']) . '</td>';
                $html .= '<td>' . htmlspecialchars($program['category']) . '</td>';
                $html .= '<td>' . htmlspecialchars($program['status']) . '</td>';
                $html .= '<td>' . ($program['max_applicants'] ?: 'Unlimited') . '</td>';
                $html .= '<td>' . ($program['application_deadline'] ?: 'N/A') . '</td>';
                $html .= '<td>' . $program['created_at'] . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="7" style="text-align:center;">No programs found matching criteria.</td></tr>';
        }

        $html .= '</table>';

        // Write the HTML to the PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // 5. Output the PDF
        
        // Clean any preceding output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $filename = 'programs_export_' . date('Y-m-d') . '.pdf';
        $pdf->Output($filename, 'I'); // 'I' = send to browser inline
        exit; // Stop script execution

    } catch (PDOException $e) {
        error_log("Program PDF export error (PDO): " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to export programs as PDF.'], 500);
    } catch (Exception $e) {
        error_log("Program PDF export error (General): " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to generate PDF.'], 500);
    }
}

// ADD THIS ENTIRE NEW FUNCTION

function handleExportApplications() {
    global $pdo;

    if (!isAdminLoggedIn()) {
        adminJsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    try {
        // 1. Get filters
        $search = $_GET['search'] ?? '';
        $program_title = $_GET['program'] ?? ''; // This is the program title from the dropdown
        $status = $_GET['status'] ?? '';

        // ADD THIS LINE
        logAdminActivity($pdo, 'export', 'applications', 'applications_csv', null, [
            'filters' => compact('search', 'program_title', 'status')
        ]);

        $where_conditions = [];
        $params = [];

        if ($search) {
            // Search by name or email
            $where_conditions[] = "(pa.first_name LIKE ? OR pa.last_name LIKE ? OR pa.email LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }

        if ($program_title) {
            // Filter by the program's title (requires the JOIN)
            $where_conditions[] = "p.title = ?";
            $params[] = $program_title;
        }

        if ($status) {
            $where_conditions[] = "pa.status = ?";
            $params[] = $status;
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // 2. Query the database (We must JOIN programs to filter by p.title)
        $stmt = $pdo->prepare("
            SELECT pa.*, p.title as program_title
            FROM program_applications pa
            JOIN programs p ON pa.program_id = p.id
            {$where_clause}
            ORDER BY pa.created_at DESC
        ");
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Set CSV headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="program_applications_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // 4. Add CSV column titles
        fputcsv($output, [
            'Application ID',
            'Program',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Date of Birth',
            'Address',
            'Disability Type',
            'Additional Info',
            'Status',
            'Applied Date'
        ]);

        // 5. Add data
        foreach ($applications as $app) {
            fputcsv($output, [
                $app['id'],
                $app['program_title'],
                $app['first_name'],
                $app['last_name'],
                $app['email'],
                $app['phone'],
                $app['date_of_birth'],
                $app['address'],
                $app['disability_type'],
                $app['additional_info'],
                $app['status'],
                $app['created_at']
            ]);
        }

        fclose($output);
        exit;

    } catch (PDOException $e) {
        error_log("Application export error: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to export applications.'], 500);
    }
}

/**
 * ADD THIS ENTIRE NEW FUNCTION
 * * Handles exporting filtered program applications as a PDF file.
 */
function handleExportApplicationsPDF() {
    global $pdo;

    if (!isAdminLoggedIn()) {
        adminJsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    try {
        // 1. Get filters (same logic as CSV export)
        $search = $_GET['search'] ?? '';
        $program_title = $_GET['program'] ?? '';
        $status = $_GET['status'] ?? '';

        // ADD THIS LINE
        logAdminActivity($pdo, 'export', 'applications', 'applications_pdf', null, [
            'filters' => compact('search', 'program_title', 'status')
        ]);

        $where_conditions = [];
        $params = [];

        if ($search) {
            $where_conditions[] = "(pa.first_name LIKE ? OR pa.last_name LIKE ? OR pa.email LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        if ($program_title) {
            $where_conditions[] = "p.title = ?";
            $params[] = $program_title;
        }
        if ($status) {
            $where_conditions[] = "pa.status = ?";
            $params[] = $status;
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // 2. Query the database (same logic as CSV export)
        $stmt = $pdo->prepare("
            SELECT pa.*, p.title as program_title
            FROM program_applications pa
            JOIN programs p ON pa.program_id = p.id
            {$where_clause}
            ORDER BY pa.created_at DESC
        ");
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. --- PDF GENERATION ---
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admin Portal');
        $pdf->SetTitle('Program Applications Export');
        $pdf->SetSubject('Program Applications');

        // Set header and footer data
        $pdf->setHeaderData('', 0, 'Program Applications - ' . date('Y-m-d'), 'Generated by PWD Portal Admin');
        $pdf->setFooterData([0, 64, 0], [0, 64, 128]);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Add a page
        $pdf->AddPage('L', 'A4'); // 'L' for Landscape to fit more columns

        // Set font
        $pdf->SetFont('helvetica', '', 8);

        // 4. Build HTML content for the PDF
        $html = '<h1>Program Applications</h1>';
        $html .= '<p>Filters applied: ';
        $html .= $program_title ? "Program (<strong>" . htmlspecialchars($program_title) . "</strong>), " : "";
        $html .= $status ? "Status (<strong>" . htmlspecialchars($status) . "</strong>), " : "";
        $html .= $search ? "Search (<strong>" . htmlspecialchars($search) . "</strong>)" : "";
        $html .= '</p>';

        $html .= '<table border="1" cellpadding="5" cellspacing="0">
            <tr style="background-color:#E0E0E0; font-weight:bold;">
                <th width="5%">ID</th>
                <th width="15%">Program</th>
                <th width="15%">Name</th>
                <th width="15%">Email</th>
                <th width="10%">Phone</th>
                <th width="15%">Address</th>
                <th width="10%">Status</th>
                <th width="15%">Applied Date</th>
            </tr>';

        if (count($applications) > 0) {
            foreach ($applications as $app) {
                $html .= '<tr>';
                $html .= '<td>' . $app['id'] . '</td>';
                $html .= '<td>' . htmlspecialchars($app['program_title']) . '</td>';
                $html .= '<td>' . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($app['email']) . '</td>';
                $html .= '<td>' . htmlspecialchars($app['phone']) . '</td>';
                $html .= '<td>' . htmlspecialchars($app['address']) . '</td>';
                $html .= '<td>' . htmlspecialchars($app['status']) . '</td>';
                $html .= '<td>' . $app['created_at'] . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="8" style="text-align:center;">No applications found matching criteria.</td></tr>';
        }

        $html .= '</table>';

        // Write the HTML to the PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // 5. Output the PDF
        
        // Clean any preceding output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $filename = 'program_applications_export_' . date('Y-m-d') . '.pdf';
        
        // 'I' = send to browser inline
        // 'D' = force download
        $pdf->Output($filename, 'I');
        exit; // Stop script execution

    } catch (PDOException $e) {
        // Handle database errors
        error_log("Application PDF export error (PDO): " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to export applications as PDF.'], 500);
    } catch (Exception $e) {
        // Handle TCPDF or other errors
        error_log("Application PDF export error (General): " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Failed to generate PDF.'], 500);
    }
}

function submitProgramApplication() {
    global $pdo;

    // === START HONEYPOT CHECK ===
    if (!empty($_POST['website_url'])) {
        error_log("Honeypot triggered on Program Form by IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode([
            'success' => true,
            'message' => 'Your application has been received!' 
        ]);
        return; // Stop any further code
    }
    // === END HONEYPOT CHECK ===

    // --- Get IP and define local IPs ---
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $local_ips = ['127.0.0.1', '::1']; // '::1' is the IPv6 localhost
    
    // --- Get all POST data ---
    $program_id = $_POST['program_id'] ?? null;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = $_POST['date_of_birth'] ?? null;
    $address = trim($_POST['address'] ?? '');
    $disability_type = trim($_POST['disability_type'] ?? '');
    $additional_info = trim($_POST['additional_info'] ?? '');
    
    // === START: VALIDATION (WITH FIXES) ===
    if (!$program_id || !$first_name || !$last_name || !$email || !$phone || !$dob || !$address) {
        adminJsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
        return; // <-- ADDED RETURN
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        adminJsonResponse(['success' => false, 'error' => 'Invalid email address'], 400); // <-- FIXED TYPO 'error_'
        return; // <-- ADDED RETURN
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        adminJsonResponse(['success' => false, 'error' => 'Invalid date format'], 400);
        return; // <-- ADDED RETURN
    }
    // === END: VALIDATION ===
    
    // === START IP-BASED RATE-LIMIT CHECK ===
    if (!in_array($ip_address, $local_ips)) {
        // Only run this check if the user is NOT on localhost
        try {
            $stmt = $pdo->prepare("
                SELECT id FROM program_applications 
                WHERE ip_address = ? 
                  AND created_at > (NOW() - INTERVAL 10 MINUTE)
            ");
            
            $stmt->execute([ $ip_address ]);
            
            if ($stmt->fetch()) {
                adminJsonResponse(['error' => 'You have submitted an application too recently. Please wait a few minutes.'], 429);
                return;
            }
            
        } catch (PDOException $e) {
            error_log("Program application rate-limit check error: " . $e->getMessage());
            adminJsonResponse(['error' => 'Failed to verify application. Please try again later.'], 500);
            return;
        }
    }
    // === END IP-BASED RATE-LIMIT CHECK ===
    
   try {
        // Check if program exists, is active, AND get its applicant limit
        // <-- CHANGED THIS QUERY
        $stmt = $pdo->prepare("SELECT id, max_applicants FROM programs WHERE id = ? AND status = 'active'");
        $stmt->execute([$program_id]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC); // <-- CHANGED THIS LINE
        
        if (!$program) { // <-- CHANGED THIS LINE
            adminJsonResponse(['success' => false, 'error' => 'Program not found or is no longer accepting applications'], 404);
            return;
        }

        // === START MAX APPLICANTS CHECK (NEW CODE) ===
        // <-- ADDED THIS ENTIRE BLOCK
        if (!empty($program['max_applicants']) && $program['max_applicants'] > 0) {
            // Count current applications for this program
            $count_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM program_applications 
                WHERE program_id = ?
            ");
            $count_stmt->execute([$program_id]);
            $current_applications = $count_stmt->fetchColumn();
            
            // Compare count to the limit
            if ($current_applications >= $program['max_applicants']) {
                adminJsonResponse(['success' => false, 'error' => 'This program has reached its maximum number of applicants.'], 400);
                return;
            }
        }
        // === END MAX APPLICANTS CHECK ===
        
        // Check for duplicate application (for the same program)
        $stmt = $pdo->prepare("
            SELECT id FROM program_applications 
            WHERE program_id = ? AND email = ?
        ");
        $stmt->execute([$program_id, $email]);
        if ($stmt->fetch()) {
            adminJsonResponse(['success' => false, 'error' => 'You have already applied for this program'], 400);
            return;
        }
        
        // Insert application (This is the query I fixed last time)
        $stmt = $pdo->prepare("
            INSERT INTO program_applications 
            (program_id, first_name, last_name, email, phone, date_of_birth, 
             address, disability_type, additional_info, ip_address, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
        ");
        
        $stmt->execute([
            $program_id, $first_name, $last_name, $email, $phone, $dob,
            $address, $disability_type, $additional_info ?: null,
            $ip_address
        ]);
        
        adminJsonResponse(['success' => true, 'message' => 'Application submitted successfully']);

    } catch (PDOException $e) {
        error_log("Database error in submitProgramApplication: " . $e->getMessage());
        
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            adminJsonResponse(['success' => false, 'error' => 'You have already applied for this program'], 400);
        }
        
        adminJsonResponse(['success' => false, 'error' => 'Failed to submit application'], 500);
    }
}

// ADD THIS ENTIRE NEW FUNCTION AT THE END OF THE FILE

function getProgramStats() {
    global $pdo;

    try {
        $total_programs = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
        $active_programs = $pdo->query("SELECT COUNT(*) FROM programs WHERE status = 'active'")->fetchColumn();
        $total_applications = $pdo->query("SELECT COUNT(*) FROM program_applications")->fetchColumn();
        $submitted_applications = $pdo->query("SELECT COUNT(*) FROM program_applications WHERE status = 'submitted'")->fetchColumn();

        adminJsonResponse([
            'success' => true,
            'stats' => [
                'total_programs' => $total_programs,
                'active_programs' => $active_programs,
                'total_applications' => $total_applications,
                'submitted_applications' => $submitted_applications
            ]
        ]);

    } catch (PDOException $e) {
        error_log("Database error in getProgramStats: " . $e->getMessage());
        adminJsonResponse(['success' => false, 'error' => 'Database error'], 500);
    }
}
?>




