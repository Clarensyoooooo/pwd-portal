<?php
require_once 'config.php';

header('Content-Type: application/json');

// Handle AJAX feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'submit_feedback') {
        handleFeedbackSubmission();
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} else {
    jsonResponse(['error' => 'Invalid request method'], 405);
}

function handleFeedbackSubmission() {
    global $pdo;
    
    // === START HONEYPOT CHECK ===
    if (!empty($_POST['website_url'])) {
        error_log("Honeypot triggered by IP: " . $_SERVER['REMOTE_ADDR']);
        jsonResponse([
            'success' => true,
            'message' => 'Thank you for your feedback! We appreciate your input and will review it shortly.'
        ]);
        return;
    }
    // === END HONEYPOT CHECK ===
    
    // --- Get IP and define local IPs ---
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $local_ips = ['127.0.0.1', '::1']; // '::1' is the IPv6 localhost
    
    // === START: VALIDATION (This part was missing) ===
    $required_fields = ['name', 'email', 'subject', 'message'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field '{$field}' is required"], 400);
            return;
        }
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email address'], 400);
        return;
    }
    
    $rating = null;
    if (!empty($_POST['rating'])) {
        $rating = (int)$_POST['rating'];
        if ($rating < 1 || $rating > 5) {
            jsonResponse(['error' => 'Rating must be between 1 and 5'], 400);
            return;
        }
    }
    // === END: VALIDATION ===
    
    // === START IP-BASED RATE-LIMIT CHECK ===
    if (!in_array($ip_address, $local_ips)) {
        // Only run this check if the user is NOT on localhost
        try {
            $stmt = $pdo->prepare("
                SELECT id FROM feedback 
                WHERE ip_address = ? 
                  AND created_at > (NOW() - INTERVAL 10 MINUTE)
            ");
            
            $stmt->execute([ $ip_address ]);
            
            if ($stmt->fetch()) {
                jsonResponse(['error' => 'You have submitted feedback too recently. Please wait a few minutes.'], 429);
                return;
            }
            
        } catch (PDOException $e) {
            error_log("Feedback rate-limit check error: " . $e->getMessage());
            jsonResponse(['error' => 'Failed to verify feedback. Please try again later.'], 500);
            return;
        }
    }
    // === END IP-BASED RATE-LIMIT CHECK ===
    
    try {
        // === FIX IS HERE ===
        // 1. Added `ip_address` to the query
        // 2. Added one more `?` to VALUES
        $stmt = $pdo->prepare("
            INSERT INTO feedback (name, email, subject, message, rating, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // 3. Added `$ip_address` to the execute array
        $result = $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['subject'],
            $_POST['message'],
            $rating,
            $ip_address
        ]);
        
        if ($result) {
            jsonResponse([
                'success' => true,
                'message' => 'Thank you for your feedback! We appreciate your input and will review it shortly.'
            ]);
        } else {
            jsonResponse(['error' => 'Failed to submit feedback'], 500);
        }
        
    } catch (PDOException $e) {
        error_log("Feedback submission error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to submit feedback. Please try again later.'], 500);
    }
}

// The duplicate function that was here has been removed.
?>