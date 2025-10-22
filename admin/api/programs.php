<?php
require_once '../config.php';
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
case 'export_applications':
    handleExportApplications();
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


function submitProgramApplication() {
    global $pdo;
    
    $program_id = $_POST['program_id'] ?? null;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = $_POST['date_of_birth'] ?? null;
    $address = trim($_POST['address'] ?? '');
    $disability_type = trim($_POST['disability_type'] ?? '');
    $additional_info = trim($_POST['additional_info'] ?? '');
    
    // Validate required fields
    if (!$program_id || !$first_name || !$last_name || !$email || !$phone || !$dob || !$address) {
        adminJsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        adminJsonResponse(['success' => false, 'error' => 'Invalid email address'], 400);
    }
    
    // Validate date of birth format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        adminJsonResponse(['success' => false, 'error' => 'Invalid date format'], 400);
    }
    
    try {
        // Check if program exists and is active
        $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = ? AND status = 'active'");
        $stmt->execute([$program_id]);
        if (!$stmt->fetch()) {
            adminJsonResponse(['success' => false, 'error' => 'Program not found or is no longer accepting applications'], 404);
        }
        
        // Check for duplicate application
        $stmt = $pdo->prepare("
            SELECT id FROM program_applications 
            WHERE program_id = ? AND email = ?
        ");
        $stmt->execute([$program_id, $email]);
        if ($stmt->fetch()) {
            adminJsonResponse(['success' => false, 'error' => 'You have already applied for this program'], 400);
        }
        
        // Insert application
        $stmt = $pdo->prepare("
            INSERT INTO program_applications 
            (program_id, first_name, last_name, email, phone, date_of_birth, 
             address, disability_type, additional_info, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
        ");
        
        $stmt->execute([
            $program_id, $first_name, $last_name, $email, $phone, $dob,
            $address, $disability_type, $additional_info ?: null
        ]);
        
        adminJsonResponse(['success' => true, 'message' => 'Application submitted successfully']);
    } catch (PDOException $e) {
        error_log("Database error in submitProgramApplication: " . $e->getMessage());
        
        // Check for specific errors
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




