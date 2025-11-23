<?php
require_once 'config.php';
require_once 'resend_email.php'; 
/**
 * Handles a single file upload, validation, and moving.
 *
 * @param array $file The file array from $_FILES (e.g., $_FILES['doc_id_picture']).
 * @param string $uploadDir The directory to move the file to.
 * @param string $newBaseName The unique base name for the file (e.g., reference number + file type).
 * @param array $allowedMimes Allowed MIME types (e.g., ['image/jpeg', 'application/pdf']).
 * @param int $maxSize Maximum file size in bytes.
 * @return string The new, secured filename.
 * @throws Exception If upload fails, is invalid, or file is not found.
 */
function handleFileUpload($file, $uploadDir, $newBaseName, $allowedMimes, $maxSize) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid parameters for file upload.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new Exception('No file was sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('File exceeded upload size limit.');
        default:
            throw new Exception('Unknown file upload error.');
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('File exceeded ' . ($maxSize / 1024 / 1024) . 'MB size limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (false === in_array($mime, $allowedMimes)) {
        throw new Exception('Invalid file format. Allowed: ' . implode(', ', $allowedMimes));
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = $newBaseName . '.' . strtolower($extension);
    $targetPath = $uploadDir . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move uploaded file.');
    }

    return $newFilename; // Return the new name to be stored in the DB
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_available_times':
    handleCheckAvailableTimes();
    break;
        case 'get_fully_booked_dates': // <--- ADD THIS
            handleGetFullyBookedDates();
            break;
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
        // CORRECTED LOGIC:
        // 1. Check for an official PWD record first.
        $pwdStmt = $pdo->prepare("SELECT id, first_name, last_name FROM pwd_records WHERE email_address = ?");
        $pwdStmt->execute([$email]);
        $pwd_record = $pwdStmt->fetch();

        // 2. Separately, find the user_id from the 'users' table to check for pending appointments.
        $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $userStmt->execute([$email]);
        $user = $userStmt->fetch();
        $user_id = $user['id'] ?? 0; // Use user ID if it exists, otherwise 0.

        // 3. Check for any pending/confirmed appointments linked to this email's user_id.
        $appointmentStmt = $pdo->prepare("
            SELECT id, reference_number, status, preferred_date 
            FROM appointments 
            WHERE user_id = ? AND status IN ('pending', 'confirmed')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $appointmentStmt->execute([$user_id]);
        $appointment = $appointmentStmt->fetch();
        
        // 4. Determine the response based on the findings.
        if ($appointment) {
            // Priority 1: If there's an active appointment, report it.
            jsonResponse([
                'available' => false,
                'reason' => 'pending_appointment',
                'message' => 'This email has a pending or confirmed appointment.',
                'appointment' => [
                    'reference_number' => $appointment['reference_number'],
                    'status' => $appointment['status'],
                    'date' => $appointment['preferred_date']
                ]
            ]);
        } elseif ($pwd_record) {
            // Priority 2: If no appointment, but an official PWD record exists, they can't create a new application.
            jsonResponse([
                'available' => false,
                'reason' => 'pwd_exists',
                'message' => 'This email is already registered. Please use the existing PWD option.',
                'user' => [
                    'name' => $pwd_record['first_name'] . ' ' . $pwd_record['last_name']
                ]
            ]);
        } else {
            // Email is not tied to an official record or an active appointment. It's available.
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
        // CORRECTED: Check if PWD record exists in pwd_records table
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email_address AS email, phone_number AS phone, date_of_birth, 
                   CONCAT_WS(', ', address_line1, address_line2, barangay, city_municipality) AS address, 
                   disability_type, emergency_contact_name, emergency_contact_phone
            FROM pwd_records 
            WHERE email_address = ?
        ");
        $stmt->execute([$email]);
        $pwd_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pwd_user) {
            jsonResponse(['error' => 'No PWD record found with this email. Please register as a new applicant.'], 404);
        }
        
        // To check for pending appointments, we must find the corresponding user_id from the 'users' table
        $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $userStmt->execute([$email]);
        $user = $userStmt->fetch();

        if ($user) {
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
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'PWD record verified successfully',
            'user' => $pwd_user
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Verification failed: ' . $e->getMessage()], 500);
    }
}

function handleRenewalUpdate() {
    global $pdo;

    // === START HONEYPOT CHECK ===
if (!empty($_POST['website_url'])) {
    // It's a bot. Silently pretend to succeed.
    error_log("Honeypot triggered on New App Form by IP: " . $_SERVER['REMOTE_ADDR']);
    
    // Send a fake success message
    echo json_encode([
        'success' => true,
        'message' => 'Your application has been submitted!' 
    ]);
    return; // Stop any further code
}

    $required_fields = ['email', 'appointment_type', 'preferred_date', 'preferred_time'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }

    $email = $_POST['email'];
    $appointment_type = $_POST['appointment_type'];
    
    // ... [All date validation code remains the same] ...
    $preferred_date = $_POST['preferred_date'];
    $today = date('Y-m-d');
    if ($preferred_date <= $today) { /* ... error ... */ }
    $maxDate = date('Y-m-d', strtotime('+2 months'));
    if ($preferred_date > $maxDate) { /* ... error ... */ }
    $preferred_date_day = date('N', strtotime($preferred_date));
    if ($preferred_date_day >= 6) { /* ... error ... */ }

    // ADD THIS CHECK BEFORE DB TRANSACTION
    if (isDateFullyBooked($preferred_date)) {
        jsonResponse(['error' => 'Sorry, this date just became fully booked. Please select another date.'], 400);
    }

    try {
        $pdo->beginTransaction();
        
        // This block now creates a consistent $user_data variable
        $stmt = $pdo->prepare("SELECT id, phone, first_name, last_name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $user_id = null;

        if (!$user_data) {
            // Self-healing: Re-create the user record if it was deleted.
            $pwdStmt = $pdo->prepare("SELECT first_name, last_name, phone_number, date_of_birth, address_line1, disability_type FROM pwd_records WHERE email_address = ?");
            $pwdStmt->execute([$email]);
            $pwd_record = $pwdStmt->fetch();

            if (!$pwd_record) {
                $pdo->rollBack();
                jsonResponse(['error' => 'Critical error: PWD record not found during renewal booking.'], 404);
                return;
            }

            $userInsertStmt = $pdo->prepare(
                "INSERT INTO users (first_name, last_name, email, phone, password_hash, date_of_birth, address, disability_type, is_verified) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)"
            );
            $userInsertStmt->execute([
                $pwd_record['first_name'], $pwd_record['last_name'], $email,
                $pwd_record['phone_number'], password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                $pwd_record['date_of_birth'], $pwd_record['address_line1'], $pwd_record['disability_type']
            ]);

            $user_id = $pdo->lastInsertId();
            
            // Re-fetch the newly created user to have a consistent $user_data array
            $stmt = $pdo->prepare("SELECT id, phone, first_name, last_name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        } else {
            $user_id = $user_data['id'];
        }
        
        $phone = $user_data['phone'];
        $reference_number = generateReferenceNumber();
        $sms_code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare(
            "INSERT INTO appointments (user_id, reference_number, appointment_type, preferred_date, preferred_time, notes, sms_verification_code) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $user_id, $reference_number, $appointment_type,
            $_POST['preferred_date'], $_POST['preferred_time'],
            $_POST['notes'] ?? null, $sms_code
        ]);
        
        $appointment_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // --- FAULT-TOLERANT SMS BLOCK ---
        try {
            sendSMSVerification($phone, $sms_code);
            $stmt = $pdo->prepare("UPDATE appointments SET sms_verification_sent = TRUE WHERE id = ?");
            $stmt->execute([$appointment_id]);
        } catch (Exception $sms_error) {
            // SMS sending failed, but we will continue.
        }
        
        // ✅ START: YOUR EMAIL SNIPPET GOES HERE
        // It is now safe to send the email.
        try {
          $emailSubject = "Appointment Verification - PDAO Portal";

$emailBody = "
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background-color: #ffffff;'>
    
    <div style='background-color: #0056b3; padding: 25px; text-align: center;'>
        <h2 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;'>Appointment Verification</h2>
    </div>

    <div style='padding: 30px; color: #333333;'>
        <p style='font-size: 16px; margin-top: 0;'>Dear <strong>{$user_data['first_name']} {$user_data['last_name']}</strong>,</p>
        
        <p style='font-size: 15px; line-height: 1.6; color: #555555;'>
            Thank you for using the PDAO Portal. To verify and confirm your appointment booking, please use the code below.
        </p>
        
        <div style='background-color: #f8f9fa; border: 2px dashed #0056b3; border-radius: 8px; padding: 20px; margin: 25px 0; text-align: center;'>
            <span style='display: block; font-size: 13px; text-transform: uppercase; color: #888888; letter-spacing: 1px; margin-bottom: 10px;'>Your Verification Code</span>
            <span style='font-size: 32px; font-weight: bold; color: #0056b3; letter-spacing: 4px; font-family: monospace;'>{$sms_code}</span>
        </div>

        <div style='background-color: #fcfcfc; border-left: 4px solid #0056b3; padding: 15px; margin-bottom: 25px;'>
            <p style='margin: 5px 0; font-size: 14px;'><strong>Reference Number:</strong> <span style='color: #333;'>{$reference_number}</span></p>
            <p style='margin: 5px 0; font-size: 14px;'><strong>Scheduled Date:</strong> <span style='color: #333;'>{$preferred_date}</span></p>
            <p style='margin: 5px 0; font-size: 14px;'><strong>Scheduled Time:</strong> <span style='color: #333;'>{$_POST['preferred_time']}</span></p>
        </div>

        <p style='font-size: 15px; line-height: 1.6; color: #555555;'>
            Please keep your reference number handy. You will need it to track your appointment status or upon visiting the office.
        </p>

        <br>
        <p style='font-size: 15px; color: #333; margin-bottom: 5px;'>Sincerely,</p>
        <p style='font-size: 15px; font-weight: bold; color: #0056b3; margin-top: 0;'>PDAO Helps Team</p>
        <p style='font-size: 13px; color: #777; margin-top: 0;'>City Government of Sto. Tomas</p>
    </div>

    <div style='background-color: #f4f6f8; padding: 20px; text-align: center; border-top: 1px solid #eeeeee;'>
        <p style='font-size: 12px; color: #999999; margin: 0;'>
            This is an automated message. Please do not reply to this email.<br>
            If you did not request this appointment, please ignore this message.
        </p>
        <p style='font-size: 12px; color: #999999; margin-top: 10px;'>
            &copy; 2025 PDAO Helps. All rights reserved.
        </p>
    </div>
</div>
";
            // Ensure you have a function called sendResendEmail or change this to your email function name
             sendResendEmail($user_data['email'], $emailSubject, $emailBody);
        } catch (Exception $email_error) {
            // Email failed, but we will still show success to the user.
        }
        // ✅ END OF BLOCK

        jsonResponse([
            'success' => true,
            'message' => 'Appointment booked successfully! Verification code sent to ' . $email,
            'appointment' => [
                'id' => $appointment_id,
                'reference_number' => $reference_number,
                'preferred_date' => $_POST['preferred_date'],
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

    // === START HONEYPOT CHECK ===
    if (!empty($_POST['website_url'])) {
        error_log("Honeypot triggered on New App Form by IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode(['success' => true, 'message' => 'Your application has been submitted!']);
        return;
    }

    // === 1. VALIDATE POST DATA ===
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'address', 'disability_type', 'preferred_date', 'preferred_time'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }

    if (empty($_POST['terms_accepted']) || $_POST['terms_accepted'] !== 'true') {
        jsonResponse(['error' => 'You must accept the terms and conditions to proceed'], 400);
    }

    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Please enter a valid email address'], 400);
    }

    // ... (All other POST validations: DOB, appointment date, phone) ...
    try {
        $dob = $_POST['date_of_birth'];
        $dobDate = new DateTime($dob);
        $today = new DateTime();
        $minDate = (new DateTime())->sub(new DateInterval('P120Y'));
        if ($dobDate > $today) jsonResponse(['error' => 'Date of birth cannot be in the future'], 400);
        if ($dobDate < $minDate) jsonResponse(['error' => 'Please enter a valid date of birth'], 400);
    } catch (Exception $e) {
         jsonResponse(['error' => 'Invalid date of birth format'], 400);
    }
    try {
        $preferred_date = $_POST['preferred_date'];
        $preferredDateTime = new DateTime($preferred_date);
        $tomorrow = (new DateTime('today'))->add(new DateInterval('P1D'));
        $maxDate = (new DateTime('today'))->add(new DateInterval('P2M'));
        if ($preferredDateTime < $tomorrow) jsonResponse(['error' => 'Appointment date must be in the future'], 400);
        if ($preferredDateTime > $maxDate) jsonResponse(['error' => 'Appointment date cannot be more than 2 months from today'], 400);
        $preferred_date_day = $preferredDateTime->format('N');
        if ($preferred_date_day >= 6) jsonResponse(['error' => 'Appointments are not available on weekends'], 400);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Invalid appointment date format'], 400);
    }
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    if (strlen($phone) < 10 || strlen($phone) > 11) {
        jsonResponse(['error' => 'Please enter a valid 10-11 digit phone number'], 400);
    }

    // === 2. VALIDATE FILE DATA (PRE-CHECK) ===
    // Check if all required files are present in the $_FILES array
    $required_files = ['doc_id_picture', 'doc_birth_certificate', 'doc_medical_certificate', 'doc_voters_certificate', 'doc_registration_form'];
    foreach ($required_files as $fileKey) {
        if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] == UPLOAD_ERR_NO_FILE) {
            jsonResponse(['error' => "Missing required file: " . $fileKey], 400);
        }
    }

    // ADD THIS CHECK BEFORE DB TRANSACTION
    if (isDateFullyBooked($preferred_date)) {
        jsonResponse(['error' => 'Sorry, this date just became fully booked. Please select another date.'], 400);
    }

    // === 3. START DATABASE TRANSACTION AND FILE PROCESSING ===
    try {
        $pdo->beginTransaction();

        // Check for existing PWD record
        $stmt = $pdo->prepare("SELECT id FROM pwd_records WHERE email_address = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(['error' => 'This email is already registered in our PWD records. Please book a renewal/update.'], 400);
        }

        // Check for pending appointment
        $stmt = $pdo->prepare("
            SELECT a.id, a.reference_number FROM appointments a 
            JOIN users u ON a.user_id = u.id 
            WHERE u.email = ? AND a.status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$_POST['email']]);
        if ($pendingAppointment = $stmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(['error' => 'This email already has a pending appointment (Ref: ' . $pendingAppointment['reference_number'] . ').'], 400);
        }

        // Create new user
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, phone, date_of_birth, address, disability_type, emergency_contact_name, emergency_contact_phone, is_verified) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
        ");
        $stmt->execute([
            $_POST['first_name'], $_POST['last_name'], $_POST['email'], $phone,
            $_POST['date_of_birth'], $_POST['address'], $_POST['disability_type'],
            $_POST['emergency_contact_name'] ?? null, $_POST['emergency_contact_phone'] ?? null
        ]);
        $user_id = $pdo->lastInsertId();

        // Generate reference number and SMS code
        $reference_number = generateReferenceNumber();
        $sms_code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

        // === 4. PROCESS FILE UPLOADS ===
        $uploadDir = 'uploads/applicant_docs/'; // Make sure this directory exists and is writable!
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5 MB

        $uploadedFileNames = []; // To store new filenames for DB insert

        // Define our file keys and their base names
        $fileMap = [
            'doc_id_picture' => $reference_number . '_id_picture',
            'doc_birth_certificate' => $reference_number . '_birth_certificate',
            'doc_medical_certificate' => $reference_number . '_medical_certificate',
            'doc_voters_certificate' => $reference_number . '_voters_certificate',
            'doc_registration_form' => $reference_number . '_registration_form',
        ];

        foreach ($fileMap as $fileKey => $newBaseName) {
            if (!empty($_FILES[$fileKey])) {
                // The handleFileUpload function will throw an exception on failure
                $newFilename = handleFileUpload(
                    $_FILES[$fileKey],
                    $uploadDir,
                    $newBaseName,
                    $allowedMimes,
                    $maxSize
                );
                $uploadedFileNames[$fileKey] = $newFilename;
            } else {
                // This should have been caught by our pre-check, but as a safeguard:
                throw new Exception("Required file $fileKey is missing.");
            }
        }

        // === 5. CREATE APPOINTMENT (NOW WITH FILE PATHS) ===
        $stmt = $pdo->prepare("
            INSERT INTO appointments (
                user_id, reference_number, appointment_type, 
                preferred_date, preferred_time, notes, sms_verification_code,
                doc_id_picture, doc_birth_certificate, doc_medical_certificate,
                doc_voters_certificate, doc_registration_form
            ) 
            VALUES (?, ?, 'new_application', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $reference_number,
            $preferred_date,
            $_POST['preferred_time'],
            $_POST['notes'] ?? null,
            $sms_code,
            // Add the new filenames
            $uploadedFileNames['doc_id_picture'],
            $uploadedFileNames['doc_birth_certificate'],
            $uploadedFileNames['doc_medical_certificate'],
            $uploadedFileNames['doc_voters_certificate'],
            $uploadedFileNames['doc_registration_form']
        ]);

        $appointment_id = $pdo->lastInsertId();

        // === 6. COMMIT AND SEND NOTIFICATIONS ===
        $pdo->commit(); // Commit the database changes FIRST.

        // --- START FAULT-TOLERANT NOTIFICATIONS ---
        try {
            sendSMSVerification($phone, $sms_code);
            $stmt = $pdo->prepare("UPDATE appointments SET sms_verification_sent = TRUE WHERE id = ?");
            $stmt->execute([$appointment_id]);
        } catch (Exception $sms_error) {
            error_log("handleNewApplication SMS Error: " . $sms_error->getMessage());
        }

        try {
           $emailSubject = "Application Verification - PDAO Portal";

$emailBody = "
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background-color: #ffffff;'>
    
    <div style='background-color: #0056b3; padding: 25px; text-align: center;'>
        <h2 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;'>Application Received</h2>
    </div>

    <div style='padding: 30px; color: #333333;'>
        <p style='font-size: 16px; margin-top: 0;'>Dear <strong>{$_POST['first_name']} {$_POST['last_name']}</strong>,</p>
        
        <p style='font-size: 15px; line-height: 1.6; color: #555555;'>
            Thank you for submitting your new PWD application. To verify your email and confirm your appointment, please use the code below.
        </p>
        
        <div style='background-color: #f8f9fa; border: 2px dashed #0056b3; border-radius: 8px; padding: 20px; margin: 25px 0; text-align: center;'>
            <span style='display: block; font-size: 13px; text-transform: uppercase; color: #888888; letter-spacing: 1px; margin-bottom: 10px;'>Your Verification Code</span>
            <span style='font-size: 32px; font-weight: bold; color: #0056b3; letter-spacing: 4px; font-family: monospace;'>{$sms_code}</span>
        </div>

        <div style='background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center; border: 1px solid #c3e6cb;'>
            <strong>✓ Success:</strong> Your documents have been uploaded and queued for review.
        </div>

        <div style='background-color: #fcfcfc; border-left: 4px solid #0056b3; padding: 15px; margin-bottom: 25px;'>
            <p style='margin: 5px 0; font-size: 14px;'><strong>Reference Number:</strong> <span style='color: #333;'>{$reference_number}</span></p>
            <p style='margin: 5px 0; font-size: 14px;'><strong>Scheduled Date:</strong> <span style='color: #333;'>{$preferred_date}</span></p>
            <p style='margin: 5px 0; font-size: 14px;'><strong>Scheduled Time:</strong> <span style='color: #333;'>{$_POST['preferred_time']}</span></p>
        </div>

        <p style='font-size: 15px; line-height: 1.6; color: #555555;'>
            Please keep your reference number handy. You will need it to track the status of your application on our portal.
        </p>

        <br>
        <p style='font-size: 15px; color: #333; margin-bottom: 5px;'>Sincerely,</p>
        <p style='font-size: 15px; font-weight: bold; color: #0056b3; margin-top: 0;'>PDAO Helps Team</p>
        <p style='font-size: 13px; color: #777; margin-top: 0;'>City Government of Sto. Tomas</p>
    </div>

    <div style='background-color: #f4f6f8; padding: 20px; text-align: center; border-top: 1px solid #eeeeee;'>
        <p style='font-size: 12px; color: #999999; margin: 0;'>
            This is an automated message. Please do not reply to this email.<br>
            If you did not request this application, please ignore this message.
        </p>
        <p style='font-size: 12px; color: #999999; margin-top: 10px;'>
            &copy; 2025 PDAO Helps. All rights reserved.
        </p>
    </div>
</div>
";
            sendResendEmail($_POST['email'], $emailSubject, $emailBody);
        } catch (Exception $email_error) {
            error_log("handleNewApplication Email Error: " . $email_error->getMessage());
        }
        // --- END FAULT-TOLERANT NOTIFICATIONS ---

        jsonResponse([
            'success' => true,
            'message' => 'Application submitted successfully! Your documents are uploaded and a verification code has been sent to your email.',
            'appointment' => [
                'id' => $appointment_id,
                'reference_number' => $reference_number,
                'preferred_date' => $preferred_date,
                'preferred_time' => $_POST['preferred_time']
            ]
        ]);

    } catch (Exception $e) { // Catch both PDOException and file upload Exceptions
        $pdo->rollBack();
        // Send a more specific error message
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
            jsonResponse(['error' => 'Email already verified'], 400);
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
            'message' => 'Email verified successfully! Your appointment is now confirmed.'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Email verification failed: ' . $e->getMessage()], 500);
    }
}

// In appointments.php, add these functions at the bottom:

function handleGetFullyBookedDates() {
    global $pdo;
    
    try {
        // Count appointments per day (pending or confirmed)
        // We group by date and only return dates where count >= 10
        $stmt = $pdo->prepare("
            SELECT preferred_date, COUNT(*) as total_bookings 
            FROM appointments 
            WHERE status IN ('pending', 'confirmed') 
            GROUP BY preferred_date 
            HAVING total_bookings >= 10
        ");
        $stmt->execute();
        
        // Fetch column returns a simple array of dates ['2025-11-20', '2025-11-21']
        $fullDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        jsonResponse([
            'success' => true,
            'full_dates' => $fullDates
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to fetch dates'], 500);
    }
}

// Helper function to check limit before saving
function isDateFullyBooked($date) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE preferred_date = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$date]);
    $count = $stmt->fetchColumn();
    
    return $count >= 10; // Returns true if full
}

function handleCheckAvailableTimes() {
    global $pdo;
    
    $date = $_POST['date'] ?? '';
    
    // Define how many people can book the SAME hour (e.g., 2 people per slot)
    // Since you have slots 9,10,11, 2,3,4 (6 slots) * 2 people = 12 max capacity.
    // This works well with your 10/day limit.
    $maxPerSlot = 2; 

    try {
        $stmt = $pdo->prepare("
            SELECT preferred_time, COUNT(*) as count 
            FROM appointments 
            WHERE preferred_date = ? AND status IN ('pending', 'confirmed')
            GROUP BY preferred_time
        ");
        $stmt->execute([$date]);
        $bookings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Returns ['09:00:00' => 1, '10:00:00' => 2]

        $fullTimes = [];
        foreach ($bookings as $time => $count) {
            if ($count >= $maxPerSlot) {
                $fullTimes[] = $time; // This time is full
            }
        }

        jsonResponse(['success' => true, 'full_times' => $fullTimes]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to check times'], 500);
    }
}

function isTimeSlotFull($date, $time) {
    global $pdo;
    $maxPerSlot = 2; // Make sure this matches your JS limit

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE preferred_date = ? 
        AND preferred_time = ? 
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$date, $time]);
    $count = $stmt->fetchColumn();

    return $count >= $maxPerSlot;
}

?>
