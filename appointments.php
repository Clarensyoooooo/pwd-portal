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
            $emailSubject = "Your Appointment Verification Code – PWD Portal";
            $emailBody = "
                <h2>Appointment Verification</h2>
                <p>Hi {$user_data['first_name']} {$user_data['last_name']},</p>
                <p>Your verification code is:</p>
                <h3 style='font-size:22px; color:#007bff;'>{$sms_code}</h3>
                <p>Reference Number: <strong>{$reference_number}</strong></p>
                <p>Preferred Schedule: {$preferred_date} at {$_POST['preferred_time']}</p>
                <p>This is a copy of the verification code sent to your email address.</p>
                <br>
                <p>– PDAO Helps, City of Sto. Tomas</p>
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
            $emailSubject = "Your Appointment Verification Code – PWD Portal";
            $emailBody = "
                <h2>Appointment Verification</h2>
                <p>Hi {$_POST['first_name']} {$_POST['last_name']},</p>
                <p>Your verification code is:</p>
                <h3 style='font-size:22px; color:#007bff;'>{$sms_code}</h3>
                <p>Reference Number: <strong>{$reference_number}</strong></p>
                <p>Preferred Schedule: {$preferred_date} at {$_POST['preferred_time']}</p>
                <p>This is a copy of the verification code sent to your email address.</p>
                <p>Your documents have been successfully uploaded for review.</p>
                <br>
                <p>– PDAO Helps, City of Sto. Tomas</p>
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
?>
