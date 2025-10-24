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
        // This field should be empty. If it's filled, it's a bot.
        // We will "pretend" it was a success to trick the bot.
        error_log("Honeypot triggered by IP: " . $_SERVER['REMOTE_ADDR']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Thank you for your feedback! We appreciate your input and will review it shortly.'
        ]);
        return; // Stop any further code execution
    }
    // === END HONEYPOT CHECK ===
    
    // Validate required fields
    $required_fields = ['name', 'email', 'subject', 'message'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field '{$field}' is required"], 400);
            return;
        }
    }
    
    // Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email address'], 400);
        return;
    }
    
    // Validate rating if provided
    $rating = null;
    if (!empty($_POST['rating'])) {
        $rating = (int)$_POST['rating'];
        if ($rating < 1 || $rating > 5) {
            jsonResponse(['error' => 'Rating must be between 1 and 5'], 400);
            return;
        }
    }

    // === START RATE-LIMIT CHECK ===
    // Check if this email submitted ANY feedback in the last 10 minutes
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM feedback 
            WHERE email = ? 
              AND created_at > (NOW() - INTERVAL 10 MINUTE)
        ");
        
        $stmt->execute([
            $_POST['email']
        ]);
        
        $existing = $stmt->fetch();
        
        if ($existing) {
            // It's a recent submission from this email.
            // Send a "Too Many Requests" error.
            jsonResponse(['error' => 'You have submitted feedback too recently. Please wait a few minutes.'], 429);
            return;
        }
        
    } catch (PDOException $e) {
        error_log("Feedback rate-limit check error: " . $e->getMessage());
        // For safety, let's block submission if the check itself errors.
        jsonResponse(['error' => 'Failed to verify feedback. Please try again later.'], 500);
        return;
    }
    // === END RATE-LIMIT CHECK ===
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO feedback (name, email, subject, message, rating, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['subject'],
            $_POST['message'],
            $rating
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