<?php
require_once '../config.php';

// Handle AJAX requests for community authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'verify_pwd_record':
            verifyPWDRecord();
            break;
        case 'register_community':
            registerCommunityUser();
            break;
        case 'login_community':
            loginCommunityUser();
            break;
        case 'logout_community':
            logoutCommunityUser();
            break;
            case 'cancel_appointment':
            cancelCommunityAppointment();
            break;
        // NEW: Action to get user's history
        case 'get_community_history':
            getCommunityHistory();
            break;
        case 'get_current_user':
            getCurrentCommunityUser();
            break;

            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Verify PWD Record Number
function verifyPWDRecord() {
    global $pdo;
    
    if (empty($_POST['pwd_record_number'])) {
        jsonResponse(['error' => 'PWD record number is required'], 400);
    }
    
    try {
        // MODIFIED: Changed SQL to match your schema
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, 
                   email_address AS email, 
                   phone_number AS phone, 
                   date_of_birth, 
                   CONCAT_WS(', ', NULLIF(address_line1, ''), NULLIF(barangay, ''), NULLIF(city_municipality, '')) AS address, 
                   disability_type, 
                   status AS pwd_id_status, 
                   issue_date AS pwd_id_issue_date, 
                   expiry_date AS pwd_id_expiry_date 
            FROM pwd_records 
            WHERE pwd_id_number = ?
        ");
        $stmt->execute([$_POST['pwd_record_number']]);
        $record = $stmt->fetch();
        
        if (!$record) {
            jsonResponse(['error' => 'PWD record not found. Please check your record number.'], 404);
        }
        
        // Check if already has a community account
        $stmt = $pdo->prepare("SELECT id FROM community_users WHERE pwd_record_id = ?");
        $stmt->execute([$record['id']]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            jsonResponse(['error' => 'This PWD record already has a community account. Please login instead.'], 400);
        }
        
        // MODIFIED: Changed PWD ID status check from 'active' to 'issued'
        if ($record['pwd_id_status'] !== 'issued') {
            jsonResponse(['error' => 'Your PWD ID is not currently issued or active. Please contact support.'], 400);
        }
        
        jsonResponse([
            'success' => true,
            'record' => $record,
            'message' => 'PWD record verified successfully'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Verification failed: ' . $e->getMessage()], 500);
    }
}

// Register Community User
function registerCommunityUser() {
    global $pdo;
    
    $required_fields = ['pwd_record_id', 'password', 'confirm_password'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }
    
    if ($_POST['password'] !== $_POST['confirm_password']) {
        jsonResponse(['error' => 'Passwords do not match'], 400);
    }
    
    if (strlen($_POST['password']) < 8) {
        jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
    }
    
    try {
        // Get PWD record
        $stmt = $pdo->prepare("SELECT * FROM pwd_records WHERE id = ?");
        $stmt->execute([$_POST['pwd_record_id']]);
        $pwd_record = $stmt->fetch();
        
        if (!$pwd_record) {
            jsonResponse(['error' => 'PWD record not found'], 404);
        }
        
        // Create community user
        $stmt = $pdo->prepare("
            INSERT INTO community_users (pwd_record_id, email, password_hash, first_name, last_name) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        // MODIFIED: Changed $pwd_record['email'] to $pwd_record['email_address']
        $stmt->execute([
            $_POST['pwd_record_id'],
            $pwd_record['email_address'], 
            password_hash($_POST['password'], PASSWORD_BCRYPT),
            $pwd_record['first_name'],
            $pwd_record['last_name']
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Create session
        // MODIFIED: Changed $pwd_record['email'] to $pwd_record['email_address']
        $_SESSION['community_user_id'] = $user_id;
        $_SESSION['community_user_email'] = $pwd_record['email_address'];
        $_SESSION['community_user_name'] = $pwd_record['first_name'] . ' ' . $pwd_record['last_name'];
        
        jsonResponse([
            'success' => true,
            'message' => 'Community account created successfully!',
            'user' => [
                'id' => $user_id,
                'name' => $_SESSION['community_user_name'],
                'email' => $pwd_record['email_address'] // MODIFIED
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
    }
}

// Login Community User
function loginCommunityUser() {
    global $pdo;
    
    if (empty($_POST['email']) || empty($_POST['password'])) {
        jsonResponse(['error' => 'Email and password are required'], 400);
    }
    
    try {
        // MODIFIED: Aliased status and expiry_date columns
        $stmt = $pdo->prepare("
            SELECT cu.*, 
                   pr.status AS pwd_id_status, 
                   pr.expiry_date AS pwd_id_expiry_date, 
                   pr.disability_type 
            FROM community_users cu
            JOIN pwd_records pr ON cu.pwd_record_id = pr.id
            WHERE cu.email = ? AND cu.is_active = TRUE
        ");
        $stmt->execute([$_POST['email']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($_POST['password'], $user['password_hash'])) {
            jsonResponse(['error' => 'Invalid email or password'], 401);
        }
        
        // MODIFIED: Changed PWD ID status check from 'active' to 'issued'
        if ($user['pwd_id_status'] !== 'issued') {
            jsonResponse(['error' => 'Your PWD ID is no longer issued or active. Please contact support.'], 403);
        }
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE community_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Create session
        $_SESSION['community_user_id'] = $user['id'];
        $_SESSION['community_user_email'] = $user['email'];
        $_SESSION['community_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['pwd_record_id'] = $user['pwd_record_id'];
        
        jsonResponse([
            'success' => true,
            'message' => 'Login successful!',
            'user' => [
                'id' => $user['id'],
                'name' => $_SESSION['community_user_name'],
                'email' => $user['email']
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Login failed: ' . $e->getMessage()], 500);
    }
}


// Logout Community User
function logoutCommunityUser() {
    // MODIFIED: Use unset() instead of session_destroy()
    // This only removes community keys and leaves admin keys alone.
    unset($_SESSION['community_user_id']);
    unset($_SESSION['community_user_email']);
    unset($_SESSION['community_user_name']);
    unset($_SESSION['pwd_record_id']); // Also clear this, which is set on login

    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}

// Get Current Community User
function getCurrentCommunityUser() {
    if (isset($_SESSION['community_user_id'])) {
        jsonResponse([
            'success' => true,
            'user' => [
                'id' => $_SESSION['community_user_id'],
                'name' => $_SESSION['community_user_name'],
                'email' => $_SESSION['community_user_email']
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'user' => null]);
    }
}

// Helper function to check if user is logged in
function isCommunityUserLoggedIn() {
    return isset($_SESSION['community_user_id']);
}

// Helper function to get current community user
function getCurrentCommunityUserData() {
    global $pdo;
    
    if (!isCommunityUserLoggedIn()) {
        return null;
    }
    
    // MODIFIED: Updated SELECT query to fetch and alias all columns needed by dashboard.php
    $stmt = $pdo->prepare("
        SELECT cu.*, 
               pr.pwd_id_number AS pwd_record_number, 
               pr.status AS pwd_id_status, 
               pr.issue_date AS pwd_id_issue_date, 
               pr.expiry_date AS pwd_id_expiry_date, 
               pr.disability_type, 
               pr.date_of_birth,
               pr.phone_number AS phone,
               pr.email_address AS email 
        FROM community_users cu
        JOIN pwd_records pr ON cu.pwd_record_id = pr.id
        WHERE cu.id = ?
    ");
    $stmt->execute([$_SESSION['community_user_id']]);
    return $stmt->fetch();
}


// --- NEW FUNCTIONS TO GET HISTORY ---

function getCommunityHistory() {
    global $pdo;
    if (!isCommunityUserLoggedIn() || empty($_SESSION['community_user_email'])) {
        jsonResponse(['error' => 'Not authenticated'], 401);
        return;
    }

    $email = $_SESSION['community_user_email'];

    try {
        // This function is now corrected
        $appointments = getCommunityAppointmentHistory($pdo, $email); 
        
        // This function was already correct
        $programs = getCommunityProgramHistory($pdo, $email);

        jsonResponse([
            'success' => true,
            'appointments' => $appointments,
            'programs' => $programs
        ]);

    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to fetch history: ' . $e->getMessage()], 500);
    }
}

// --- THIS IS THE CORRECTED FUNCTION ---
function getCommunityAppointmentHistory($pdo, $email) {
    // Fetches from 'appointments' table by JOINING 'users' table on email
    $stmt = $pdo->prepare("
        SELECT app.reference_number, app.appointment_type, app.preferred_date, app.preferred_time, app.status
        FROM appointments app
        JOIN users u ON app.user_id = u.id
        WHERE u.email = ? 
        ORDER BY app.preferred_date DESC
    ");
    $stmt->execute([$email]);
    return $stmt->fetchAll();
}

function getCommunityProgramHistory($pdo, $email) {
    // This query was already correct as program_applications has an email column
    $stmt = $pdo->prepare("
        SELECT pa.created_at, pa.status, p.title 
        FROM program_applications pa
        JOIN programs p ON pa.program_id = p.id
        WHERE pa.email = ? 
        ORDER BY pa.created_at DESC
    ");
    $stmt->execute([$email]);
    return $stmt->fetchAll();
}


// --- NEW FUNCTION TO CANCEL APPOINTMENT ---

function cancelCommunityAppointment() {
    global $pdo;
    if (!isCommunityUserLoggedIn() || empty($_SESSION['community_user_email'])) {
        jsonResponse(['error' => 'Not authenticated'], 401);
        return;
    }

    $email = $_SESSION['community_user_email'];
    $reference_number = $_POST['reference_number'] ?? '';
    $reason = $_POST['reason'] ?? 'No reason provided';

    if (empty($reference_number)) {
        jsonResponse(['error' => 'Reference number is required'], 400);
    }

    try {
        // First, get the user_id from the users table based on the session email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['error' => 'User not found for this session'], 404);
            return;
        }
        $user_id = $user['id'];

        // Now, update the appointment, ensuring it belongs to this user
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled', 
                notes = CONCAT('Cancelled by user: ', ?, '\n', IFNULL(notes, '')) 
            WHERE reference_number = ? AND user_id = ? AND (status = 'pending' OR status = 'confirmed')
        ");
        
        $stmt->execute([$reason, $reference_number, $user_id]);

        if ($stmt->rowCount() > 0) {
            jsonResponse(['success' => true, 'message' => 'Appointment successfully cancelled']);
        } else {
            jsonResponse(['error' => 'Could not cancel appointment. It may already be completed or does not belong to you.'], 400);
        }

    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

?>