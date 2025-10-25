<?php
// Enhanced Database Configuration
// After
$host = getenv('MYSQLHOST') ?: 'localhost';
$dbname = getenv('MYSQLDATABASE') ?: 'pwd_portal';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function generateReferenceNumber() {
    $year = date('Y');
    $month = date('m');
    $day = date('d');
    $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return "PWD-{$year}-{$month}-{$day}-{$random}";
}

function sendSMSVerification($phone, $code) {
    // For development - use a fixed test code
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        // Development mode - use fixed code for easy testing
        error_log("SMS Verification Code for {$phone}: {$code} (TEST MODE)");
        return true;
    }
    
    // In a real application, integrate with SMS service like Twilio
    // For now, we'll just log it
    error_log("SMS Verification Code for {$phone}: {$code}");
    return true;
}

// Response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>
