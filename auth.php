<?php
require_once 'config.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register':
            handleRegister();
            break;
        case 'login':
            handleLogin();
            break;
        case 'logout':
            handleLogout();
            break;
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

function handleRegister() {
    global $pdo;
    
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'password', 'confirm_password'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }
    
    if ($_POST['password'] !== $_POST['confirm_password']) {
        jsonResponse(['error' => 'Passwords do not match'], 400);
    }
    
    if (strlen($_POST['password']) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Email already registered'], 400);
    }
    
    try {
        $verification_token = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, phone, password_hash, date_of_birth, address, disability_type, emergency_contact_name, emergency_contact_phone, verification_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            hashPassword($_POST['password']),
            $_POST['date_of_birth'] ?? null,
            $_POST['address'] ?? null,
            $_POST['disability_type'] ?? null,
            $_POST['emergency_contact_name'] ?? null,
            $_POST['emergency_contact_phone'] ?? null,
            $verification_token
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Auto-login after registration
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $_POST['email'];
        $_SESSION['user_name'] = $_POST['first_name'] . ' ' . $_POST['last_name'];
        
        jsonResponse([
            'success' => true,
            'message' => 'Registration successful!',
            'user' => [
                'id' => $user_id,
                'name' => $_SESSION['user_name'],
                'email' => $_POST['email']
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
    }
}

function handleLogin() {
    global $pdo;
    
    if (empty($_POST['email']) || empty($_POST['password'])) {
        jsonResponse(['error' => 'Email and password are required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $user = $stmt->fetch();
        
        if (!$user || !verifyPassword($_POST['password'], $user['password_hash'])) {
            jsonResponse(['error' => 'Invalid email or password'], 401);
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        jsonResponse([
            'success' => true,
            'message' => 'Login successful!',
            'user' => [
                'id' => $user['id'],
                'name' => $_SESSION['user_name'],
                'email' => $user['email']
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Login failed: ' . $e->getMessage()], 500);
    }
}

function handleLogout() {
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}
?>
