<?php
require_once 'config.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'book_appointment':
            handleBookAppointment();
            break;
        case 'track_appointment':
            handleTrackAppointment();
            break;
        case 'verify_sms':
            handleSMSVerification();
            break;
        case 'update_requirements':
            handleUpdateRequirements();
            break;
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_user_appointments':
            getUserAppointments();
            break;
        case 'get_available_slots':
            getAvailableSlots();
            break;
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

function handleBookAppointment() {
    global $pdo;
    
    requireLogin();
    
    $required_fields = ['appointment_type', 'preferred_date', 'preferred_time'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }
    
    // Validate date is not in the past
    $preferred_date = $_POST['preferred_date'];
    if (strtotime($preferred_date) < strtotime('today')) {
        jsonResponse(['error' => 'Appointment date cannot be in the past'], 400);
    }
    
    // Check if user already has a pending appointment
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE user_id = ? AND status IN ('pending', 'confirmed') LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'You already have a pending appointment. Please complete or cancel it first.'], 400);
    }
    
    try {
        $reference_number = generateReferenceNumber();
        $sms_code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("
            INSERT INTO appointments (user_id, reference_number, appointment_type, preferred_date, preferred_time, notes, sms_verification_code) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $reference_number,
            $_POST['appointment_type'],
            $preferred_date,
            $_POST['preferred_time'],
            $_POST['notes'] ?? null,
            $sms_code
        ]);
        
        $appointment_id = $pdo->lastInsertId();
        
        // Get user phone for SMS
        $user = getCurrentUser($pdo);
        
        // Send SMS verification (in real app, use actual SMS service)
        sendSMSVerification($user['phone'], $sms_code);
        
        // Update SMS sent status
        $stmt = $pdo->prepare("UPDATE appointments SET sms_verification_sent = TRUE WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Appointment booked successfully! SMS verification sent.',
            'appointment' => [
                'id' => $appointment_id,
                'reference_number' => $reference_number,
                'preferred_date' => $preferred_date,
                'preferred_time' => $_POST['preferred_time']
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to book appointment: ' . $e->getMessage()], 500);
    }
}

function handleTrackAppointment() {
    global $pdo;
    
    $reference_number = $_POST['reference_number'] ?? '';
    
    if (empty($reference_number)) {
        jsonResponse(['error' => 'Reference number is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.first_name, u.last_name, u.phone, u.email 
            FROM appointments a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.reference_number = ?
        ");
        $stmt->execute([$reference_number]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            jsonResponse(['error' => 'Appointment not found'], 404);
        }
        
        // Format the response
        $response = [
            'success' => true,
            'appointment' => [
                'id' => $appointment['id'],
                'reference_number' => $appointment['reference_number'],
                'applicant_name' => $appointment['first_name'] . ' ' . $appointment['last_name'],
                'contact_number' => $appointment['phone'],
                'email' => $appointment['email'],
                'appointment_type' => $appointment['appointment_type'],
                'preferred_date' => $appointment['preferred_date'],
                'preferred_time' => $appointment['preferred_time'],
                'actual_date' => $appointment['actual_date'],
                'actual_time' => $appointment['actual_time'],
                'status' => $appointment['status'],
                'notes' => $appointment['notes'],
                'requirements' => [
                    'medical_certificate' => (bool)$appointment['medical_certificate'],
                    'barangay_certificate' => (bool)$appointment['barangay_certificate'],
                    'id_pictures' => (bool)$appointment['id_pictures'],
                    'valid_id' => (bool)$appointment['valid_id'],
                    'birth_certificate' => (bool)$appointment['birth_certificate']
                ],
                'sms_verification_sent' => (bool)$appointment['sms_verification_sent'],
                'sms_verified_at' => $appointment['sms_verified_at'],
                'confirmed_at' => $appointment['confirmed_at'],
                'completed_at' => $appointment['completed_at'],
                'created_at' => $appointment['created_at'],
                'updated_at' => $appointment['updated_at']
            ]
        ];
        
        jsonResponse($response);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to track appointment: ' . $e->getMessage()], 500);
    }
}

function handleSMSVerification() {
    global $pdo;
    
    $reference_number = $_POST['reference_number'] ?? '';
    $verification_code = $_POST['verification_code'] ?? '';
    
    if (empty($reference_number) || empty($verification_code)) {
        jsonResponse(['error' => 'Reference number and verification code are required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE reference_number = ? AND sms_verification_code = ?");
        $stmt->execute([$reference_number, $verification_code]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            jsonResponse(['error' => 'Invalid verification code'], 400);
        }
        
        if ($appointment['sms_verified_at']) {
            jsonResponse(['error' => 'SMS already verified'], 400);
        }
        
        // Update verification status and confirm appointment
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET sms_verified_at = NOW(), confirmed_at = NOW(), status = 'confirmed', actual_date = preferred_date, actual_time = preferred_time 
            WHERE id = ?
        ");
        $stmt->execute([$appointment['id']]);
        
        jsonResponse([
            'success' => true,
            'message' => 'SMS verified successfully! Your appointment is now confirmed.'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'SMS verification failed: ' . $e->getMessage()], 500);
    }
}

function handleUpdateRequirements() {
    global $pdo;
    
    requireLogin();
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    $requirements = $_POST['requirements'] ?? [];
    
    if (empty($appointment_id)) {
        jsonResponse(['error' => 'Appointment ID is required'], 400);
    }
    
    try {
        // Verify appointment belongs to current user
        $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND user_id = ?");
        $stmt->execute([$appointment_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Appointment not found'], 404);
        }
        
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET medical_certificate = ?, barangay_certificate = ?, id_pictures = ?, valid_id = ?, birth_certificate = ?, requirements_submitted = TRUE 
            WHERE id = ?
        ");
        
        $stmt->execute([
            isset($requirements['medical_certificate']) ? 1 : 0,
            isset($requirements['barangay_certificate']) ? 1 : 0,
            isset($requirements['id_pictures']) ? 1 : 0,
            isset($requirements['valid_id']) ? 1 : 0,
            isset($requirements['birth_certificate']) ? 1 : 0,
            $appointment_id
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Requirements updated successfully!'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to update requirements: ' . $e->getMessage()], 500);
    }
}

function getUserAppointments() {
    global $pdo;
    
    requireLogin();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM appointments 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $appointments = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'appointments' => $appointments
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to get appointments: ' . $e->getMessage()], 500);
    }
}

function getAvailableSlots() {
    global $pdo;
    
    $date = $_GET['date'] ?? '';
    
    if (empty($date)) {
        jsonResponse(['error' => 'Date is required'], 400);
    }
    
    try {
        // Get booked slots for the date
        $stmt = $pdo->prepare("
            SELECT actual_time FROM appointments 
            WHERE actual_date = ? AND status IN ('confirmed', 'completed')
        ");
        $stmt->execute([$date]);
        $booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Available time slots (9 AM to 4 PM, 1-hour intervals)
        $all_slots = [
            '09:00:00', '10:00:00', '11:00:00', 
            '14:00:00', '15:00:00', '16:00:00'
        ];
        
        $available_slots = array_diff($all_slots, $booked_slots);
        
        jsonResponse([
            'success' => true,
            'available_slots' => array_values($available_slots)
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to get available slots: ' . $e->getMessage()], 500);
    }
}
?>
