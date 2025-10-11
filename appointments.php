<?php
require_once 'config.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_email':
            handleCheckEmail();
            break;
        case 'check_email_detailed':
            handleCheckEmailDetailed();
            break;
        case 'verify_existing_pwd':
            handleVerifyExistingPWD();
            break;
        case 'book_renewal_update':
            handleRenewalUpdate();
            break;
        case 'book_new_application':
            handleNewApplication();
            break;
        case 'track_appointment':
            handleTrackAppointment();
            break;
        case 'verify_sms':
            handleSMSVerification();
            break;
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

function handleCheckEmail() {
    global $pdo;
    
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    try {
        // Check if user has pending or confirmed appointments
        $stmt = $pdo->prepare("
            SELECT a.id, a.reference_number, a.status, a.preferred_date 
            FROM appointments a 
            JOIN users u ON a.user_id = u.id 
            WHERE u.email = ? AND a.status IN ('pending', 'confirmed')
            ORDER BY a.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $appointment = $stmt->fetch();
        
        if ($appointment) {
            jsonResponse([
                'available' => false,
                'message' => 'This email already has a pending appointment.',
                'appointment' => [
                    'reference_number' => $appointment['reference_number'],
                    'status' => $appointment['status'],
                    'date' => $appointment['preferred_date']
                ]
            ]);
        } else {
            jsonResponse([
                'available' => true,
                'message' => 'Email is available for booking.'
            ]);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to check email: ' . $e->getMessage()], 500);
    }
}

function handleCheckEmailDetailed() {
    global $pdo;
    
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    try {
        // First, check if user exists in PWD records
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // User exists in PWD records - check for pending appointments
            $stmt = $pdo->prepare("
                SELECT id, reference_number, status, preferred_date 
                FROM appointments 
                WHERE user_id = ? AND status IN ('pending', 'confirmed')
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user['id']]);
            $appointment = $stmt->fetch();
            
            if ($appointment) {
                // Has pending appointment
                jsonResponse([
                    'available' => false,
                    'reason' => 'pending_appointment',
                    'message' => 'This email has a pending appointment.',
                    'appointment' => [
                        'reference_number' => $appointment['reference_number'],
                        'status' => $appointment['status'],
                        'date' => $appointment['preferred_date']
                    ]
                ]);
            } else {
                // PWD exists but no pending appointment - should use existing PWD flow
                jsonResponse([
                    'available' => false,
                    'reason' => 'pwd_exists',
                    'message' => 'This email is already registered. Please use the existing PWD option.',
                    'user' => [
                        'name' => $user['first_name'] . ' ' . $user['last_name']
                    ]
                ]);
            }
        } else {
            // Email is completely new
            jsonResponse([
                'available' => true,
                'message' => 'Email is available for new registration.'
            ]);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to check email: ' . $e->getMessage()], 500);
    }
}

function handleVerifyExistingPWD() {
    global $pdo;
    
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    try {
        // Check if user exists with this email
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, phone, date_of_birth, 
                   address, disability_type, emergency_contact_name, emergency_contact_phone
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'No PWD record found with this email. Please register as a new applicant.'], 404);
        }
        
        // Check if user has pending appointment
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE user_id = ? AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$user['id']]);
        $pendingCount = $stmt->fetchColumn();
        
        if ($pendingCount > 0) {
            jsonResponse(['error' => 'You already have a pending appointment. Please complete or cancel it first.'], 400);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'PWD record verified successfully',
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Verification failed: ' . $e->getMessage()], 500);
    }
}

function handleRenewalUpdate() {
    global $pdo;
    
    $required_fields = ['user_id', 'appointment_type', 'preferred_date', 'preferred_time'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }
    
    $user_id = $_POST['user_id'];
    $appointment_type = $_POST['appointment_type'];
    
    // Validate appointment type for existing PWDs
    if (!in_array($appointment_type, ['renewal', 'update'])) {
        jsonResponse(['error' => 'Invalid appointment type for existing PWD'], 400);
    }
    
    $preferred_date = $_POST['preferred_date'];
    $today = date('Y-m-d');
    
    // Validate date is in the future
    if ($preferred_date <= $today) {
        jsonResponse(['error' => 'Appointment date must be in the future'], 400);
    }
    
    // Validate date is not more than 2 months from today
    $maxDate = date('Y-m-d', strtotime('+2 months'));
    if ($preferred_date > $maxDate) {
        jsonResponse(['error' => 'Appointment date cannot be more than 2 months from today'], 400);
    }

    // Validate date is not a weekend
    $preferred_date_day = date('N', strtotime($preferred_date));
    if ($preferred_date_day >= 6) { // 6 is Saturday, 7 is Sunday
        jsonResponse(['error' => 'Appointments are not available on weekends. Please select a weekday.'], 400);
    }

    try {
        $pdo->beginTransaction();
        
        // Verify user exists
        $stmt = $pdo->prepare("SELECT id, phone FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $pdo->rollBack();
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        // Generate reference number and SMS code
        $reference_number = generateReferenceNumber();
        $sms_code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Create appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments (user_id, reference_number, appointment_type, preferred_date, preferred_time, notes, sms_verification_code) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $reference_number,
            $appointment_type,
            $preferred_date,
            $_POST['preferred_time'],
            $_POST['notes'] ?? null,
            $sms_code
        ]);
        
        $appointment_id = $pdo->lastInsertId();
        
        // Send SMS verification
        sendSMSVerification($user['phone'], $sms_code);
        
        // Update SMS sent status
        $stmt = $pdo->prepare("UPDATE appointments SET sms_verification_sent = TRUE WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Appointment booked successfully! SMS verification sent to ' . $user['phone'],
            'appointment' => [
                'id' => $appointment_id,
                'reference_number' => $reference_number,
                'preferred_date' => $preferred_date,
                'preferred_time' => $_POST['preferred_time']
            ]
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Failed to book appointment: ' . $e->getMessage()], 500);
    }
}

function handleNewApplication() {
    global $pdo;
    
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'address', 'disability_type', 'preferred_date', 'preferred_time'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }
    
    // Validate terms acceptance
    if (empty($_POST['terms_accepted']) || $_POST['terms_accepted'] !== 'true') {
        jsonResponse(['error' => 'You must accept the terms and conditions to proceed'], 400);
    }
    
    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Please enter a valid email address'], 400);
    }
    
    // Validate date of birth using DateTime for robustness
    try {
        $dob = $_POST['date_of_birth'];
        $dobDate = new DateTime($dob);
        $today = new DateTime();
        $minDate = (new DateTime())->sub(new DateInterval('P120Y'));
        
        if ($dobDate > $today) {
            jsonResponse(['error' => 'Date of birth cannot be in the future'], 400);
        }
        
        if ($dobDate < $minDate) {
            jsonResponse(['error' => 'Please enter a valid date of birth (not more than 120 years ago)'], 400);
        }
    } catch (Exception $e) {
         jsonResponse(['error' => 'Invalid date of birth format'], 400);
    }

    
    // Validate appointment date using DateTime
    try {
        $preferred_date = $_POST['preferred_date'];
        $preferredDateTime = new DateTime($preferred_date);
        $tomorrow = (new DateTime('today'))->add(new DateInterval('P1D'));
        $maxDate = (new DateTime('today'))->add(new DateInterval('P2M'));
        
        if ($preferredDateTime < $tomorrow) {
            jsonResponse(['error' => 'Appointment date must be in the future'], 400);
        }
        
        if ($preferredDateTime > $maxDate) {
            jsonResponse(['error' => 'Appointment date cannot be more than 2 months from today'], 400);
        }

        // Validate date is not a weekend
        $preferred_date_day = $preferredDateTime->format('N'); // 1 (for Monday) through 7 (for Sunday)
        if ($preferred_date_day >= 6) { // 6 is Saturday, 7 is Sunday
            jsonResponse(['error' => 'Appointments are not available on weekends. Please select a weekday.'], 400);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Invalid appointment date format'], 400);
    }
    
    // Validate phone number
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    if (strlen($phone) < 10 || strlen($phone) > 11) {
        jsonResponse(['error' => 'Please enter a valid 10-11 digit phone number'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if email already exists in PWD records
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            $pdo->rollBack();
            jsonResponse(['error' => 'This email is already registered in our PWD records. Please select "Yes, I have a PWD ID" to book a renewal or update appointment.'], 400);
        }
        
        // Check if email already has pending appointment
        $stmt = $pdo->prepare("
            SELECT a.id, a.reference_number FROM appointments a 
            JOIN users u ON a.user_id = u.id 
            WHERE u.email = ? AND a.status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$_POST['email']]);
        $pendingAppointment = $stmt->fetch();
        
        if ($pendingAppointment) {
            $pdo->rollBack();
            jsonResponse(['error' => 'This email already has a pending appointment (Ref: ' . $pendingAppointment['reference_number'] . '). Please complete or cancel it first.'], 400);
        }
        
        // Create new user
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, phone, date_of_birth, address, disability_type, emergency_contact_name, emergency_contact_phone, is_verified) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
        ");
        
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $phone,
            $_POST['date_of_birth'],
            $_POST['address'],
            $_POST['disability_type'],
            $_POST['emergency_contact_name'] ?? null,
            $_POST['emergency_contact_phone'] ?? null
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Generate reference number and SMS code
        $reference_number = generateReferenceNumber();
        $sms_code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Create appointment with 'new_application' type
        $stmt = $pdo->prepare("
            INSERT INTO appointments (user_id, reference_number, appointment_type, preferred_date, preferred_time, notes, sms_verification_code) 
            VALUES (?, ?, 'new_application', ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $reference_number,
            $preferred_date,
            $_POST['preferred_time'],
            $_POST['notes'] ?? null,
            $sms_code
        ]);
        
        $appointment_id = $pdo->lastInsertId();
        
        // Send SMS verification
        sendSMSVerification($phone, $sms_code);
        
        // Update SMS sent status
        $stmt = $pdo->prepare("UPDATE appointments SET sms_verification_sent = TRUE WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Application submitted successfully! SMS verification sent to ' . $phone,
            'appointment' => [
                'id' => $appointment_id,
                'reference_number' => $reference_number,
                'preferred_date' => $preferred_date,
                'preferred_time' => $_POST['preferred_time']
            ]
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Failed to submit application: ' . $e->getMessage()], 500);
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
?>
