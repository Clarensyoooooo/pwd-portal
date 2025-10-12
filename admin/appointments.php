<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'appointments.view');

$admin = getCurrentAdmin($pdo);

// Handle export request
if (isset($_GET['export']) && $_GET['export'] == '1') {
    requirePermission($pdo, 'appointments.view');
    
    // Get filter parameters
    $status_filter = $_GET['status'] ?? '';
    $date_filter = $_GET['date'] ?? '';
    $date_range = $_GET['date_range'] ?? '';
    $appointment_type_filter = $_GET['appointment_type'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build WHERE conditions
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "a.status = ?";
        $params[] = $status_filter;
    }
    
    if ($appointment_type_filter) {
        $where_conditions[] = "a.appointment_type = ?";
        $params[] = $appointment_type_filter;
    }
    
    if ($date_filter) {
        $where_conditions[] = "DATE(a.preferred_date) = ?";
        $params[] = $date_filter;
    }
    
    if ($date_range) {
        switch ($date_range) {
            case 'today':
                $where_conditions[] = "DATE(a.preferred_date) = CURDATE()";
                break;
            case 'tomorrow':
                $where_conditions[] = "DATE(a.preferred_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'this_week':
                $where_conditions[] = "YEARWEEK(a.preferred_date) = YEARWEEK(CURDATE())";
                break;
            case 'next_week':
                $where_conditions[] = "YEARWEEK(a.preferred_date) = YEARWEEK(DATE_ADD(CURDATE(), INTERVAL 1 WEEK))";
                break;
            case 'this_month':
                $where_conditions[] = "YEAR(a.preferred_date) = YEAR(CURDATE()) AND MONTH(a.preferred_date) = MONTH(CURDATE())";
                break;
        }
    }
    
    if ($search) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR a.reference_number LIKE ? OR u.phone LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="PWD_Appointments_Export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write report header
    fputcsv($output, ['PWD Appointments Report']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Exported by:', $admin['full_name']]);
    fputcsv($output, []);
    
    // Write data header
    fputcsv($output, [
        'Reference Number',
        'First Name',
        'Last Name',
        'Phone',
        'Email',
        'Address',
        'Disability Type',
        'Appointment Type',
        'Preferred Date',
        'Preferred Time',
        'Status',
        'Created Date',
        'Interview Status',
        'PWD ID Number',
        'Record Status',
        'Notes'
    ]);
    
    // Get appointments data
    $stmt = $pdo->prepare("
        SELECT 
            a.reference_number,
            u.first_name,
            u.last_name,
            u.phone,
            u.email,
            u.address,
            u.disability_type,
            a.appointment_type,
            a.preferred_date,
            a.preferred_time,
            a.status,
            a.created_at,
            ir.status as interview_status,
            pr.pwd_id_number,
            pr.status as record_status,
            a.notes
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN interview_records ir ON a.id = ir.appointment_id
        LEFT JOIN pwd_records pr ON a.id = pr.appointment_id
        {$where_clause}
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    
    // Write data rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['reference_number'] ?? '',
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['phone'] ?? '',
            $row['email'] ?? '',
            $row['address'] ?? '',
            $row['disability_type'] ?? '',
            ucwords(str_replace('_', ' ', $row['appointment_type'] ?? '')),
            $row['preferred_date'] ?? '',
            $row['preferred_time'] ?? '',
            ucfirst($row['status'] ?? ''),
            $row['created_at'] ?? '',
            $row['interview_status'] ? ucfirst($row['interview_status']) : 'Not Started',
            $row['pwd_id_number'] ?? 'Not Issued',
            $row['record_status'] ? ucfirst($row['record_status']) : 'No Record',
            $row['notes'] ?? ''
        ]);
    }
    
    fclose($output);
    
    // Log the export activity
    logAdminActivity($pdo, 'export', 'appointments', 'appointments_export', null, [
        'filters' => array_filter([
            'status' => $status_filter,
            'appointment_type' => $appointment_type_filter,
            'date_range' => $date_range,
            'date' => $date_filter,
            'search' => $search
        ])
    ]);
    
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'start_interview':
            handleStartInterview();
            break;
        case 'mark_completed':
            handleMarkCompleted();
            break;
        case 'update_appointment':
            handleUpdateAppointment();
            break;
        case 'cancel_appointment':
            handleCancelAppointment();
            break;
        case 'reschedule_appointment':
            handleRescheduleAppointment();
            break;
        case 'get_appointment_details':
            handleGetAppointmentDetails();
            break;
        case 'delete_appointment':
            handleDeleteAppointment();
            break;
        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Helper function to check if appointment can be started
function canStartInterview($appointment) {
    $today = date('Y-m-d');
    $appointment_date = date('Y-m-d', strtotime($appointment['preferred_date']));
    
    return $appointment['status'] === 'confirmed' 
        && $appointment_date === $today 
        && !$appointment['interview_id']
        && $appointment['appointment_type'] === 'new_application';
}

// Helper function to check if renewal/update can be completed
function canMarkCompleted($appointment) {
    return in_array($appointment['appointment_type'], ['renewal', 'update_information'])
        && $appointment['status'] === 'confirmed'
        && $appointment['pwd_id_number'];
}

// Get appointments with enhanced filters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$date_range = $_GET['date_range'] ?? '';
$appointment_type_filter = $_GET['appointment_type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($appointment_type_filter) {
    $where_conditions[] = "a.appointment_type = ?";
    $params[] = $appointment_type_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(a.preferred_date) = ?";
    $params[] = $date_filter;
}

if ($date_range) {
    switch ($date_range) {
        case 'today':
            $where_conditions[] = "DATE(a.preferred_date) = CURDATE()";
            break;
        case 'tomorrow':
            $where_conditions[] = "DATE(a.preferred_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $where_conditions[] = "YEARWEEK(a.preferred_date) = YEARWEEK(CURDATE())";
            break;
        case 'next_week':
            $where_conditions[] = "YEARWEEK(a.preferred_date) = YEARWEEK(DATE_ADD(CURDATE(), INTERVAL 1 WEEK))";
            break;
        case 'this_month':
            $where_conditions[] = "YEAR(a.preferred_date) = YEAR(CURDATE()) AND MONTH(a.preferred_date) = MONTH(CURDATE())";
            break;
    }
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR a.reference_number LIKE ? OR u.phone LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    {$where_clause}
");
$count_stmt->execute($params);
$total_appointments = $count_stmt->fetch()['total'];
$total_pages = ceil($total_appointments / $per_page);

// Get appointments - Fixed JOIN to use appointment_id
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.phone, u.email, u.address, u.disability_type,
           ir.id as interview_id, ir.status as interview_status,
           pr.pwd_id_number, pr.status as record_status, pr.id as record_id
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN interview_records ir ON a.id = ir.appointment_id
    LEFT JOIN pwd_records pr ON a.id = pr.appointment_id
    {$where_clause}
    ORDER BY a.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get appointment types for filter
$types_stmt = $pdo->query("SELECT DISTINCT appointment_type FROM appointments ORDER BY appointment_type");
$appointment_types = $types_stmt->fetchAll(PDO::FETCH_COLUMN);

function handleStartInterview() {
    global $pdo;
    requirePermission($pdo, 'appointments.interview');
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    
    if (empty($appointment_id)) {
        adminJsonResponse(['error' => 'Appointment ID is required'], 400);
    }
    
    try {
        // Check if interview already exists
        $stmt = $pdo->prepare("SELECT id FROM interview_records WHERE appointment_id = ?");
        $stmt->execute([$appointment_id]);
        if ($stmt->fetch()) {
            adminJsonResponse(['error' => 'Interview already started for this appointment'], 400);
        }
        
        // Create interview record
        $stmt = $pdo->prepare("
            INSERT INTO interview_records (appointment_id, interviewer_id, status, interview_date) 
            VALUES (?, ?, 'in_progress', NOW())
        ");
        $stmt->execute([$appointment_id, $_SESSION['admin_user_id']]);
        
        $interview_id = $pdo->lastInsertId();
        
        // Update appointment status
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        logAdminActivity($pdo, 'interview', 'appointments', 'appointment', $appointment_id);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Interview started successfully',
            'interview_id' => $interview_id,
            'redirect_url' => "interview.php?id={$interview_id}"
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to start interview: ' . $e->getMessage()], 500);
    }
}

function handleMarkCompleted() {
    global $pdo;
    requirePermission($pdo, 'appointments.edit');
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    
    if (empty($appointment_id)) {
        adminJsonResponse(['error' => 'Appointment ID is required'], 400);
    }
    
    try {
        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, u.id as user_id, pr.id as record_id
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN pwd_records pr ON a.id = pr.appointment_id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            adminJsonResponse(['error' => 'Appointment not found'], 404);
        }
        
        // Verify it's a renewal or update
        if (!in_array($appointment['appointment_type'], ['renewal', 'update_information'])) {
            adminJsonResponse(['error' => 'This action is only available for renewal/update appointments'], 400);
        }
        
        // Check if PWD record exists
        if (!$appointment['record_id']) {
            adminJsonResponse(['error' => 'No PWD record found for this appointment'], 400);
        }
        
        // Mark appointment as completed
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'completed', 
                notes = CONCAT(COALESCE(notes, ''), '\n', 'Marked as completed by admin on ', NOW()),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$appointment_id]);
        
        logAdminActivity($pdo, 'complete', 'appointments', 'appointment', $appointment_id, [
            'appointment_type' => $appointment['appointment_type'],
            'record_id' => $appointment['record_id']
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Appointment marked as completed',
            'redirect_url' => "records.php?highlight={$appointment['record_id']}"
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to mark as completed: ' . $e->getMessage()], 500);
    }
}

function handleUpdateAppointment() {
    global $pdo;
    requirePermission($pdo, 'appointments.edit');
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($appointment_id) || empty($status)) {
        adminJsonResponse(['error' => 'Appointment ID and status are required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $notes, $appointment_id]);
        
        logAdminActivity($pdo, 'edit', 'appointments', 'appointment', $appointment_id, [
            'status' => $status,
            'notes' => $notes
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Appointment updated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to update appointment: ' . $e->getMessage()], 500);
    }
}

function handleRescheduleAppointment() {
    global $pdo;
    requirePermission($pdo, 'appointments.edit');
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    $new_date = $_POST['new_date'] ?? '';
    $new_time = $_POST['new_time'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($appointment_id) || empty($new_date) || empty($new_time)) {
        adminJsonResponse(['error' => 'All fields are required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET preferred_date = ?, preferred_time = ?, notes = CONCAT(COALESCE(notes, ''), '\nRescheduled: ', ?), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_date, $new_time, $reason, $appointment_id]);
        
        logAdminActivity($pdo, 'reschedule', 'appointments', 'appointment', $appointment_id, [
            'new_date' => $new_date,
            'new_time' => $new_time,
            'reason' => $reason
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Appointment rescheduled successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to reschedule appointment: ' . $e->getMessage()], 500);
    }
}

function handleCancelAppointment() {
    global $pdo;
    requirePermission($pdo, 'appointments.cancel');
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($appointment_id)) {
        adminJsonResponse(['error' => 'Appointment ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), '\nCancelled: ', ?), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reason, $appointment_id]);
        
        logAdminActivity($pdo, 'cancel', 'appointments', 'appointment', $appointment_id, [
            'reason' => $reason
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Appointment cancelled successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to cancel appointment: ' . $e->getMessage()], 500);
    }
}

function handleDeleteAppointment() {
    global $pdo;
    requirePermission($pdo, 'appointments.delete');
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    
    if (empty($appointment_id)) {
        adminJsonResponse(['error' => 'Appointment ID is required'], 400);
    }
    
    try {
        // Verify the appointment is cancelled before allowing deletion
        $stmt = $pdo->prepare("SELECT status FROM appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            adminJsonResponse(['error' => 'Appointment not found'], 404);
        }
        
        if ($appointment['status'] !== 'cancelled') {
            adminJsonResponse(['error' => 'Only cancelled appointments can be deleted'], 400);
        }
        
        // Delete related records first (if any)
        $pdo->prepare("DELETE FROM interview_records WHERE appointment_id = ?")->execute([$appointment_id]);
        
        // Delete the appointment
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        logAdminActivity($pdo, 'delete', 'appointments', 'appointment', $appointment_id);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Appointment deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to delete appointment: ' . $e->getMessage()], 500);
    }
}

function handleGetAppointmentDetails() {
    global $pdo;
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    
    if (empty($appointment_id)) {
        adminJsonResponse(['error' => 'Appointment ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.first_name, u.last_name, u.phone, u.email, u.address, u.disability_type,
                   ir.id as interview_id, ir.status as interview_status, ir.interview_notes,
                   ir.eligibility_assessment, ir.recommendations, ir.documents_verified,
                   pr.pwd_id_number, pr.status as record_status,
                   au.full_name as interviewer_name
            FROM appointments a 
            JOIN users u ON a.user_id = u.id 
            LEFT JOIN interview_records ir ON a.id = ir.appointment_id
            LEFT JOIN pwd_records pr ON a.id = pr.appointment_id
            LEFT JOIN admin_users au ON ir.interviewer_id = au.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            adminJsonResponse(['error' => 'Appointment not found'], 404);
        }
        
        adminJsonResponse([
            'success' => true,
            'appointment' => $appointment
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to get appointment details: ' . $e->getMessage()], 500);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-calendar-check"></i> Appointments</h1>
                <p>Manage appointment scheduling and interviews</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" onclick="exportAppointments()">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="btn btn-primary" onclick="refreshAppointments()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Today's and Tomorrow's Appointments Cards -->
        <div class="quick-access-cards">
            <?php
            // Get today's appointments
            $today_query = "
                SELECT a.*, u.first_name, u.last_name, u.phone, u.email,
                       ir.id as interview_id, ir.status as interview_status,
                       pr.pwd_id_number, pr.status as record_status
                FROM appointments a 
                JOIN users u ON a.user_id = u.id 
                LEFT JOIN interview_records ir ON a.id = ir.appointment_id
                LEFT JOIN pwd_records pr ON a.id = pr.appointment_id
                WHERE DATE(a.preferred_date) = CURDATE() 
                AND a.status NOT IN ('cancelled')
                ORDER BY a.preferred_time ASC
                LIMIT 5
            ";
            $today_appointments = $pdo->query($today_query)->fetchAll();
            
            // Get tomorrow's appointments
            $tomorrow_query = "
                SELECT a.*, u.first_name, u.last_name, u.phone, u.email,
                       ir.id as interview_id, ir.status as interview_status,
                       pr.pwd_id_number, pr.status as record_status
                FROM appointments a 
                JOIN users u ON a.user_id = u.id 
                LEFT JOIN interview_records ir ON a.id = ir.appointment_id
                LEFT JOIN pwd_records pr ON a.id = pr.appointment_id
                WHERE DATE(a.preferred_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                AND a.status NOT IN ('cancelled')
                ORDER BY a.preferred_time ASC
                LIMIT 5
            ";
            $tomorrow_appointments = $pdo->query($tomorrow_query)->fetchAll();
            ?>
            
            <div class="quick-card today-card">
                <div class="quick-card-header">
                    <div class="quick-card-title">
                        <i class="fas fa-calendar-day"></i>
                        <h3>Today's Appointments</h3>
                    </div>
                    <div class="quick-card-count">
                        <?php echo count($today_appointments); ?>
                    </div>
                </div>
                <div class="quick-card-body">
                    <?php if (empty($today_appointments)): ?>
                        <div class="no-appointments">
                            <i class="fas fa-calendar-check"></i>
                            <p>No appointments scheduled for today</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($today_appointments as $apt): ?>
                            <div class="quick-appointment-item">
                                <div class="appointment-time">
                                    <?php echo date('g:i A', strtotime($apt['preferred_time'])); ?>
                                </div>
                                <div class="appointment-info">
                                    <div class="appointment-name">
                                        <?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?>
                                    </div>
                                    <div class="appointment-type">
                                        <?php echo ucwords(str_replace('_', ' ', $apt['appointment_type'])); ?>
                                    </div>
                                </div>
                                <div class="appointment-status">
                                    <?php
                                    // Determine actual progress status
                                    if ($apt['record_status'] === 'issued') {
                                        echo '<span class="progress-badge completed"><i class="fas fa-check-circle"></i> Completed</span>';
                                    } elseif ($apt['record_status'] === 'validated') {
                                        echo '<span class="progress-badge validated"><i class="fas fa-id-card"></i> Validated</span>';
                                    } elseif ($apt['interview_status'] === 'completed') {
                                        echo '<span class="progress-badge interview-done"><i class="fas fa-comments"></i> Interview Done</span>';
                                    } elseif ($apt['interview_id']) {
                                        echo '<span class="progress-badge in-progress"><i class="fas fa-clock"></i> In Progress</span>';
                                    } else {
                                        echo '<span class="progress-badge pending"><i class="fas fa-calendar-clock"></i> Scheduled</span>';
                                    }
                                    ?>
                                </div>
                                <div class="appointment-actions">
                                    <?php if ($apt['status'] !== 'cancelled'): ?>
                                        <?php if (in_array($apt['appointment_type'], ['renewal', 'update_information']) && $apt['pwd_id_number']): ?>
                                            <button class="btn btn-xs btn-success" onclick="markCompleted(<?php echo $apt['id']; ?>)" title="Mark as Completed">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                        <?php elseif ($apt['interview_id'] && $apt['record_status'] !== 'issued'): ?>
                                            <a href="interview.php?id=<?php echo $apt['interview_id']; ?>" class="btn btn-xs btn-primary" title="Continue">
                                                <i class="fas fa-arrow-right"></i>
                                            </a>
                                        <?php elseif (canStartInterview($apt)): ?>
                                            <button class="btn btn-xs btn-success" onclick="startInterview(<?php echo $apt['id']; ?>)" title="Start">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($today_appointments) >= 5): ?>
                            <div class="view-all-link">
                                <a href="appointments.php?date_range=today">View all today's appointments</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="quick-card tomorrow-card">
                <div class="quick-card-header">
                    <div class="quick-card-title">
                        <i class="fas fa-calendar-plus"></i>
                        <h3>Tomorrow's Appointments</h3>
                    </div>
                    <div class="quick-card-count">
                        <?php echo count($tomorrow_appointments); ?>
                    </div>
                </div>
                <div class="quick-card-body">
                    <?php if (empty($tomorrow_appointments)): ?>
                        <div class="no-appointments">
                            <i class="fas fa-calendar-check"></i>
                            <p>No appointments scheduled for tomorrow</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tomorrow_appointments as $apt): ?>
                            <div class="quick-appointment-item">
                                <div class="appointment-time">
                                    <?php echo date('g:i A', strtotime($apt['preferred_time'])); ?>
                                </div>
                                <div class="appointment-info">
                                    <div class="appointment-name">
                                        <?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?>
                                    </div>
                                    <div class="appointment-type">
                                        <?php echo ucwords(str_replace('_', ' ', $apt['appointment_type'])); ?>
                                    </div>
                                </div>
                                <div class="appointment-status">
                                    <span class="progress-badge scheduled">
                                        <i class="fas fa-calendar-day"></i> Scheduled
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($tomorrow_appointments) >= 5): ?>
                            <div class="view-all-link">
                                <a href="appointments.php?date_range=tomorrow">View all tomorrow's appointments</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <?php
            $stats_query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN DATE(preferred_date) = CURDATE() THEN 1 ELSE 0 END) as today
                FROM appointments
            ";
            $stats_result = $pdo->query($stats_query)->fetch();
            ?>
            
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['total']); ?></h3>
                    <p>Total Appointments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['pending']); ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon validated">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['confirmed']); ?></h3>
                    <p>Confirmed</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['today']); ?></h3>
                    <p>Today's Appointments</p>
                </div>
            </div>
        </div>
        
        <!-- Enhanced Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="appointment_type">Type</label>
                    <select name="appointment_type" id="appointment_type">
                        <option value="">All Types</option>
                        <?php foreach ($appointment_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                    <?php echo $appointment_type_filter === $type ? 'selected' : ''; ?>>
                                <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date_range">Date Range</label>
                    <select name="date_range" id="date_range">
                        <option value="">All Dates</option>
                        <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="tomorrow" <?php echo $date_range === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                        <option value="this_week" <?php echo $date_range === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="next_week" <?php echo $date_range === 'next_week' ? 'selected' : ''; ?>>Next Week</option>
                        <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date">Specific Date</label>
                    <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Name, reference, or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="appointments.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Appointments Table -->
        <div class="data-card">
            <div class="card-header">
                <h3>Appointment List</h3>
                <span class="record-count"><?php echo number_format($total_appointments); ?> appointments</span>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Applicant</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['reference_number']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        Created: <?php echo date('M j, Y', strtotime($appointment['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($appointment['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['phone']); ?></td>
                                <td>
                                    <span class="type-badge type-<?php echo $appointment['appointment_type']; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $appointment['appointment_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($appointment['preferred_date'])); ?></strong>
                                    <br>
                                    <small><?php echo date('g:i A', strtotime($appointment['preferred_time'])); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress-indicators">
                                        <?php 
                                        // Determine the actual progress status
                                        if ($appointment['record_status'] === 'issued'): ?>
                                            <span class="progress-badge completed">
                                                <i class="fas fa-check-circle"></i> Completed
                                            </span>
                                        <?php elseif ($appointment['record_status'] === 'validated'): ?>
                                            <span class="progress-badge validated">
                                                <i class="fas fa-id-card"></i> Record Validated
                                            </span>
                                        <?php elseif ($appointment['record_status'] === 'draft'): ?>
                                            <span class="progress-badge record-created">
                                                <i class="fas fa-file-alt"></i> Record Created
                                            </span>
                                        <?php elseif ($appointment['interview_status'] === 'completed'): ?>
                                            <span class="progress-badge interview-completed">
                                                <i class="fas fa-comments"></i> Interview Completed
                                            </span>
                                        <?php elseif ($appointment['interview_id']): ?>
                                            <span class="progress-badge interview-progress">
                                                <i class="fas fa-clock"></i> Interview: <?php echo ucfirst($appointment['interview_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="progress-badge awaiting">
                                                <i class="fas fa-calendar-clock"></i> Awaiting Processing
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Details - Always available -->
                                        <button class="btn btn-sm btn-primary" onclick="viewAppointment(<?php echo $appointment['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($appointment['status'] !== 'cancelled'): ?>
                                            <!-- Edit - Only if not cancelled and not completed -->
                                            <?php if (hasPermission($pdo, 'appointments.edit') && $appointment['record_status'] !== 'issued'): ?>
                                                <button class="btn btn-sm btn-warning" onclick="editAppointment(<?php echo $appointment['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Reschedule - Only if not cancelled, not completed, and record not issued -->
                                            <?php if (hasPermission($pdo, 'appointments.edit') && !in_array($appointment['status'], ['completed', 'cancelled']) && $appointment['record_status'] !== 'issued'): ?>
                                                <button class="btn btn-sm btn-info" onclick="rescheduleAppointment(<?php echo $appointment['id']; ?>)" title="Reschedule">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Mark as Completed - Only for renewal/update appointments with existing PWD records -->
                                            <?php if (hasPermission($pdo, 'appointments.edit') && canMarkCompleted($appointment)): ?>
                                                <button class="btn btn-sm btn-success" onclick="markCompleted(<?php echo $appointment['id']; ?>)" title="Mark as Completed">
                                                    <i class="fas fa-check-double"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Start Interview - Only for new applications -->
                                            <?php if (hasPermission($pdo, 'appointments.interview') && canStartInterview($appointment)): ?>
                                                <button class="btn btn-sm btn-success" onclick="startInterview(<?php echo $appointment['id']; ?>)" title="Start Interview">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Continue Interview - Only if interview exists and record not completed -->
                                            <?php if ($appointment['interview_id'] && $appointment['record_status'] !== 'issued'): ?>
                                                <a href="interview.php?id=<?php echo $appointment['interview_id']; ?>" class="btn btn-sm btn-secondary" title="Continue Interview">
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Cancel - Only if not cancelled and record not issued -->
                                            <?php if (hasPermission($pdo, 'appointments.cancel') && $appointment['record_status'] !== 'issued'): ?>
                                                <button class="btn btn-sm btn-danger" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)" title="Cancel">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- For cancelled appointments, only show delete option if permitted -->
                                            <?php if (hasPermission($pdo, 'appointments.delete')): ?>
                                                <button class="btn btn-sm btn-danger" onclick="deleteAppointment(<?php echo $appointment['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>No appointments found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        (<?php echo number_format($total_appointments); ?> total appointments)
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
    
    <!-- View Appointment Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3 id="appointmentModalTitle">Appointment Details</h3>
                <button class="modal-close" onclick="closeModal('appointmentModal')">&times;</button>
            </div>
            <div class="modal-body" id="appointmentModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
    
    <!-- Edit Appointment Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Appointment</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="editAppointmentId" name="appointment_id">
                    <div class="form-group">
                        <label for="editStatus">Status</label>
                        <select id="editStatus" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editNotes">Notes</label>
                        <textarea id="editNotes" name="notes" rows="4" placeholder="Add notes about this appointment..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Appointment
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reschedule Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reschedule Appointment</h3>
                <button class="modal-close" onclick="closeModal('rescheduleModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="rescheduleForm">
                    <input type="hidden" id="rescheduleAppointmentId" name="appointment_id">
                    <div class="form-group">
                        <label for="newDate">New Date</label>
                        <input type="date" id="newDate" name="new_date" required>
                    </div>
                    <div class="form-group">
                        <label for="newTime">New Time</label>
                        <input type="time" id="newTime" name="new_time" required>
                    </div>
                    <div class="form-group">
                        <label for="rescheduleReason">Reason for Rescheduling</label>
                        <textarea id="rescheduleReason" name="reason" rows="3" placeholder="Explain why this appointment is being rescheduled..." required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-alt"></i> Reschedule
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('rescheduleModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/admin.js"></script>
    <script>
        // Start interview
        function startInterview(appointmentId) {
            if (confirm('Start interview for this appointment?')) {
                fetch('appointments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=start_interview&appointment_id=${appointmentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        if (data.redirect_url) {
                            setTimeout(() => window.location.href = data.redirect_url, 1000);
                        } else {
                            setTimeout(() => location.reload(), 1000);
                        }
                    } else {
                        showNotification(data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to start interview', 'error');
                });
            }
        }
        
        // Mark as completed (for renewal/update)
        function markCompleted(appointmentId) {
            if (confirm('Mark this renewal/update appointment as completed? This will redirect to the PWD records page.')) {
                fetch('appointments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_completed&appointment_id=${appointmentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        if (data.redirect_url) {
                            setTimeout(() => window.location.href = data.redirect_url, 1000);
                        } else {
                            setTimeout(() => location.reload(), 1000);
                        }
                    } else {
                        showNotification(data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to mark as completed', 'error');
                });
            }
        }
        
        // View appointment details
        function viewAppointment(appointmentId) {
            fetch('appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_appointment_details&appointment_id=${appointmentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayAppointmentDetails(data.appointment);
                    showModal('appointmentModal');
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to load appointment details', 'error');
            });
        }
        
        function displayAppointmentDetails(appointment) {
            const modalTitle = document.getElementById('appointmentModalTitle');
            const modalBody = document.getElementById('appointmentModalBody');
            
            modalTitle.textContent = `Appointment: ${appointment.reference_number}`;
            
            // Parse documents verified
            let documentsVerified = [];
            try {
                documentsVerified = appointment.documents_verified ? JSON.parse(appointment.documents_verified) : [];
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
            
            modalBody.innerHTML = `
                <div class="appointment-details">
                    <div class="details-grid">
                        <div class="detail-section">
                            <h4><i class="fas fa-user"></i> Applicant Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Full Name:</span>
                                    <span class="value">${appointment.first_name} ${appointment.last_name}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Email:</span>
                                    <span class="value">${appointment.email}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Phone:</span>
                                    <span class="value">${appointment.phone}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Address:</span>
                                    <span class="value">${appointment.address || 'Not provided'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Disability Type:</span>
                                    <span class="value">${appointment.disability_type || 'Not specified'}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-calendar-check"></i> Appointment Details</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Reference:</span>
                                    <span class="value">${appointment.reference_number}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Type:</span>
                                    <span class="value">${appointment.appointment_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Date:</span>
                                    <span class="value">${formatDate(appointment.preferred_date)}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Time:</span>
                                    <span class="value">${formatTime(appointment.preferred_time)}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Status:</span>
                                    <span class="value"><span class="status-badge status-${appointment.status}">${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Created:</span>
                                    <span class="value">${formatDateTime(appointment.created_at)}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${appointment.interview_id ? `
                        <div class="detail-section">
                            <h4><i class="fas fa-comments"></i> Interview Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Status:</span>
                                    <span class="value"><span class="status-badge status-${appointment.interview_status}">${appointment.interview_status.charAt(0).toUpperCase() + appointment.interview_status.slice(1)}</span></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Interviewer:</span>
                                    <span class="value">${appointment.interviewer_name || 'Not assigned'}</span>
                                </div>
                                ${appointment.interview_notes ? `
                                <div class="detail-row">
                                    <span class="label">Notes:</span>
                                    <span class="value">${appointment.interview_notes}</span>
                                </div>
                                ` : ''}
                                ${appointment.eligibility_assessment ? `
                                <div class="detail-row">
                                    <span class="label">Assessment:</span>
                                    <span class="value">${appointment.eligibility_assessment}</span>
                                </div>
                                ` : ''}
                                ${appointment.recommendations ? `
                                <div class="detail-row">
                                    <span class="label">Recommendations:</span>
                                    <span class="value">${appointment.recommendations}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        ` : ''}
                        
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
                        
                        ${appointment.pwd_id_number ? `
                        <div class="detail-section">
                            <h4><i class="fas fa-id-card"></i> PWD Record</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">PWD ID:</span>
                                    <span class="value">${appointment.pwd_id_number}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Status:</span>
                                    <span class="value"><span class="status-badge status-${appointment.record_status}">${appointment.record_status.charAt(0).toUpperCase() + appointment.record_status.slice(1)}</span></span>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${appointment.notes ? `
                        <div class="detail-section full-width">
                            <h4><i class="fas fa-sticky-note"></i> Notes</h4>
                            <div class="notes-content">
                                ${appointment.notes.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }
        
        // Edit appointment
        function editAppointment(appointmentId) {
            // First get appointment details
            fetch('appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_appointment_details&appointment_id=${appointmentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const appointment = data.appointment;
                    document.getElementById('editAppointmentId').value = appointmentId;
                    document.getElementById('editStatus').value = appointment.status;
                    document.getElementById('editNotes').value = appointment.notes || '';
                    showModal('editModal');
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to load appointment details', 'error');
            });
        }
        
        // Reschedule appointment
        function rescheduleAppointment(appointmentId) {
            document.getElementById('rescheduleAppointmentId').value = appointmentId;
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('newDate').min = today;
            
            showModal('rescheduleModal');
        }
        
        // Cancel appointment
        function cancelAppointment(appointmentId) {
            const reason = prompt('Please provide a reason for cancellation:');
            if (reason) {
                if (confirm('Are you sure you want to cancel this appointment?')) {
                    fetch('appointments.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=cancel_appointment&appointment_id=${appointmentId}&reason=${encodeURIComponent(reason)}`
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
                        showNotification('Failed to cancel appointment', 'error');
                    });
                }
            }
        }

        // Delete appointment (for cancelled appointments only)
        function deleteAppointment(appointmentId) {
            if (confirm('Are you sure you want to permanently delete this cancelled appointment? This action cannot be undone.')) {
                fetch('appointments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_appointment&appointment_id=${appointmentId}`
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
                    showNotification('Failed to delete appointment', 'error');
                });
            }
        }
        
        // Export appointments
        function exportAppointments() {
            // Build the export URL with all current filters
            const params = new URLSearchParams();
            params.append('export', '1');
            
            // Get filter values
            const status = document.getElementById('status').value;
            const appointmentType = document.getElementById('appointment_type').value;
            const dateRange = document.getElementById('date_range').value;
            const specificDate = document.getElementById('date').value;
            const search = document.getElementById('search').value;
            
            // Add filters to params
            if (status) params.append('status', status);
            if (appointmentType) params.append('appointment_type', appointmentType);
            if (dateRange) params.append('date_range', dateRange);
            if (specificDate) params.append('date', specificDate);
            if (search) params.append('search', search);
            
            // Trigger download
            window.location.href = `appointments.php?${params.toString()}`;
        }
        
        // Refresh appointments
        function refreshAppointments() {
            location.reload();
        }
        
        // Helper functions
        function formatTime(timeString) {
            const time = new Date('1970-01-01T' + timeString + 'Z');
            return time.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
                timeZone: 'UTC'
            });
        }
        
        // Form submissions
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_appointment');
            
            fetch('appointments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('editModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to update appointment', 'error');
            });
        });
        
        document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'reschedule_appointment');
            
            fetch('appointments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('rescheduleModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to reschedule appointment', 'error');
            });
        });
    </script>
    
    <style>
        .quick-access-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .quick-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .today-card {
            border-left: 4px solid #10b981;
        }
        
        .tomorrow-card {
            border-left: 4px solid #3b82f6;
        }
        
        .quick-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px 16px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .quick-card-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .quick-card-title i {
            font-size: 1.25rem;
            color: #2c5aa0;
        }
        
        .quick-card-title h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .quick-card-count {
            background: #2c5aa0;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            min-width: 32px;
            text-align: center;
        }
        
        .quick-card-body {
            padding: 0;
            max-height: 320px;
            overflow-y: auto;
        }
        
        .no-appointments {
            padding: 40px 24px;
            text-align: center;
            color: #64748b;
        }
        
        .no-appointments i {
            font-size: 2rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        
        .no-appointments p {
            margin: 0;
            font-size: 0.9rem;
        }
        
        .quick-appointment-item {
            display: flex;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.2s ease;
        }
        
        .quick-appointment-item:hover {
            background-color: #f8fafc;
        }
        
        .quick-appointment-item:last-child {
            border-bottom: none;
        }
        
        .appointment-time {
            font-weight: 600;
            color: #2c5aa0;
            font-size: 0.9rem;
            min-width: 70px;
            flex-shrink: 0;
        }
        
        .appointment-info {
            flex: 1;
            margin-left: 16px;
        }
        
        .appointment-name {
            font-weight: 500;
            color: #1e293b;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        
        .appointment-type {
            font-size: 0.8rem;
            color: #64748b;
        }
        
        .appointment-status {
            margin-left: 12px;
            flex-shrink: 0;
        }
        
        .appointment-actions {
            margin-left: 12px;
            flex-shrink: 0;
        }
        
        .progress-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }
        
        .progress-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .progress-badge.validated {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .progress-badge.record-created {
            background: #fef3c7;
            color: #92400e;
        }
        
        .progress-badge.interview-completed {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .progress-badge.interview-progress,
        .progress-badge.in-progress {
            background: #fef3c7;
            color: #92400e;
        }
        
        .progress-badge.interview-done {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .progress-badge.awaiting,
        .progress-badge.pending {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .progress-badge.scheduled {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .view-all-link {
            padding: 12px 24px;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
        }
        
        .view-all-link a {
            color: #2c5aa0;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .view-all-link a:hover {
            text-decoration: underline;
        }
        
        .btn-xs {
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
        }
        
        /* Enhanced progress indicators for main table */
        .progress-indicators .progress-badge {
            font-size: 0.8rem;
            padding: 6px 10px;
            border-radius: 6px;
        }
        
        @media (max-width: 1024px) {
            .quick-access-cards {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
        
        @media (max-width: 768px) {
            .quick-appointment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 16px;
            }
            
            .appointment-time {
                min-width: auto;
            }
            
            .appointment-info {
                margin-left: 0;
                width: 100%;
            }
            
            .appointment-status,
            .appointment-actions {
                margin-left: 0;
                align-self: flex-end;
            }
        }
        
        .progress-indicators {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .progress-badge.interview-in_progress {
            background: #fef3c7;
            color: #92400e;
        }
        
        .progress-badge.interview-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .progress-badge.record-draft {
            background: #fef3c7;
            color: #92400e;
        }
        
        .progress-badge.record-validated {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .progress-badge.record-issued {
            background: #d1fae5;
            color: #065f46;
        }
        
        .large-modal .modal-content {
            max-width: 900px;
        }
        
        .appointment-details {
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
        
        .detail-section.full-width {
            grid-column: 1 / -1;
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
        
        .notes-content {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            line-height: 1.5;
        }
        
        .documents-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
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
            
            .pagination {
                flex-direction: column;
                gap: 12px;
            }
            
            .progress-indicators {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .documents-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
