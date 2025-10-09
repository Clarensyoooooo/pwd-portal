<?php
require_once 'config.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'verify_pwd':
            handleVerifyPWD();
            break;
        case 'check_email':
            handleCheckEmail();
            break;
        case 'book_appointment':
            handleBookAppointment();
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

function handleVerifyPWD() {
    global $pdo;
    
    $pwdId = $_POST['pwd_id_number'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $dob = $_POST['date_of_birth'] ?? '';
    
    if (empty($firstName) || empty($lastName) || empty($dob)) {
        jsonResponse(['error' => 'Please provide all required information'], 400);
    }
    
    try {
        // Build query based on provided information
        $query = "SELECT * FROM users WHERE first_name = ? AND last_name = ? AND date_of_birth = ?";
        $params = [$firstName, $lastName, $dob];
        
        // If PWD ID provided, include it in search
        if (!empty($pwdId)) {
            $query .= " AND pwd_id_number = ?";
            $params[] = $pwdId;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $user = $stmt->fetch();
        
        if ($user) {
            jsonResponse([
                'success' => true,
                'message' => 'PWD record verified successfully',
                'pwd_data' => [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'date_of_birth' => $user['date_of_birth'],
                    'address' => $user['address'],
                    'disability_type' => $user['disability_type'],
                    'pwd_id_number' => $user['pwd_id_number']
                ]
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => 'No matching PWD record found. Please check your information or apply as a new applicant.'
            ], 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Verification failed: ' . $e->getMessage()], 500);
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

function handleBookAppointment() {
    global $pdo;
    
    $pwdStatus = $_POST['pwd_status'] ?? '';
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'appointment_type', 'preferred_date', 'preferred_time'];
    
    // Validate required fields based on PWD status
    if ($pwdStatus === 'new') {
        $required_fields = array_merge($required_fields, ['address', 'disability_type']);
    }
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }
    
    // Validate terms acceptance
    if (empty($_POST['terms_accepted']) || $_POST['terms_accepted'] !== 'true') {
        jsonResponse(['error' => 'You must accept the terms and conditions to proceed'], 400);
    }
    
    // Validate date is not in the past
    $preferred_date = $_POST['preferred_date'];
    if (strtotime($preferred_date) < strtotime('today')) {
        jsonResponse(['error' => 'Appointment date cannot be in the past'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if it's an existing PWD with user_id
        if (!empty($_POST['user_id'])) {
            $user_id = $_POST['user_id'];
            
            // Check if user has pending appointment
            $stmt = $pdo->prepare("
                SELECT id FROM appointments 
                WHERE user_id = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$user_id]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                jsonResponse(['error' => 'You already have a pending appointment. Please complete or cancel it first.'], 400);
            }
        } else {
            // Check if email already has pending appointment
            $stmt = $pdo->prepare("
                SELECT a.id FROM appointments a 
                JOIN users u ON a.user_id = u.id 
                WHERE u.email = ? AND a.status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                jsonResponse(['error' => 'This email already has a pending appointment. Please complete or cancel it first.'], 400);
            }
            
            // Create or find user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $user_id = $user['id'];
                
                // Update user information
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, address = ?, 
                        disability_type = ?, emergency_contact_name = ?, emergency_contact_phone = ?, 
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['phone'],
                    $_POST['date_of_birth'],
                    $_POST['address'] ?? '',
                    $_POST['disability_type'] ?? '',
                    $_POST['emergency_contact_name'] ?? null,
                    $_POST['emergency_contact_phone'] ?? null,
                    $user_id
                ]);
            } else {
                // Create new user without authentication fields
                $stmt = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, email, phone, date_of_birth, address, disability_type, emergency_contact_name, emergency_contact_phone, is_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['date_of_birth'],
                    $_POST['address'] ?? '',
                    $_POST['disability_type'] ?? '',
                    $_POST['emergency_contact_name'] ?? null,
                    $_POST['emergency_contact_phone'] ?? null
                ]);
                
                $user_id = $pdo->lastInsertId();
            }
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
            $_POST['appointment_type'],
            $preferred_date,
            $_POST['preferred_time'],
            $_POST['notes'] ?? null,
            $sms_code
        ]);
        
        $appointment_id = $pdo->lastInsertId();
        
        // Send SMS verification
        sendSMSVerification($_POST['phone'], $sms_code);
        
        // Update SMS sent status
        $stmt = $pdo->prepare("UPDATE appointments SET sms_verification_sent = TRUE WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Appointment booked successfully! SMS verification sent to ' . $_POST['phone'],
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
