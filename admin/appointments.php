<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'appointments.view');

$admin = getCurrentAdmin($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'start_interview':
            handleStartInterview();
            break;
        case 'update_appointment':
            handleUpdateAppointment();
            break;
        case 'cancel_appointment':
            handleCancelAppointment();
            break;
        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Get appointments with filters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(a.preferred_date) = ?";
    $params[] = $date_filter;
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR a.reference_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.phone, u.email,
           ir.id as interview_id, ir.status as interview_status
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN interview_records ir ON a.id = ir.appointment_id
    {$where_clause}
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

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
            INSERT INTO interview_records (appointment_id, interviewer_id, status) 
            VALUES (?, ?, 'in_progress')
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
            'interview_id' => $interview_id
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to start interview: ' . $e->getMessage()], 500);
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
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = ? WHERE id = ?");
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

function handleCancelAppointment() {
    global $pdo;
    requirePermission($pdo, 'appointments.cancel');
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($appointment_id)) {
        adminJsonResponse(['error' => 'Appointment ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', notes = ? WHERE id = ?");
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
            <h1><i class="fas fa-calendar-check"></i> Appointments</h1>
            <div class="page-actions">
                <button class="btn btn-primary" onclick="refreshAppointments()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Filters -->
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
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Name or reference number..." 
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
                <span class="record-count"><?php echo count($appointments); ?> appointments</span>
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
                            <th>Interview</th>
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
                                    <?php if ($appointment['interview_id']): ?>
                                        <span class="status-badge status-<?php echo $appointment['interview_status']; ?>">
                                            <?php echo ucfirst($appointment['interview_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not started</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if (hasPermission($pdo, 'appointments.interview') && !$appointment['interview_id'] && $appointment['status'] !== 'cancelled'): ?>
                                            <button class="btn btn-sm btn-success" onclick="startInterview(<?php echo $appointment['id']; ?>)">
                                                <i class="fas fa-comments"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($appointment['interview_id']): ?>
                                            <a href="interview.php?id=<?php echo $appointment['interview_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission($pdo, 'appointments.edit')): ?>
                                            <button class="btn btn-sm btn-warning" onclick="editAppointment(<?php echo $appointment['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission($pdo, 'appointments.cancel') && $appointment['status'] !== 'cancelled'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
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
        </div>
    </main>
    
    <!-- Modals -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Appointment Details</h3>
                <button class="modal-close" onclick="closeModal('appointmentModal')">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
    
    <script src="assets/admin.js"></script>
    <script>
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
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to start interview', 'error');
                });
            }
        }
        
        function viewAppointment(appointmentId) {
            // Implementation for viewing appointment details
            showModal('appointmentModal');
        }
        
        function editAppointment(appointmentId) {
            // Implementation for editing appointment
            showModal('appointmentModal');
        }
        
        function cancelAppointment(appointmentId) {
            const reason = prompt('Please provide a reason for cancellation:');
            if (reason) {
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
        
        function refreshAppointments() {
            location.reload();
        }
    </script>
</body>
</html>
