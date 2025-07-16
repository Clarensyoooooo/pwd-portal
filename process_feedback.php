<?php
require_once 'config.php';

// Handle AJAX feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'submit_feedback') {
        handleFeedbackSubmission();
    } else {
        // Handle regular form submission (fallback)
        handleRegularFeedback();
    }
}

function handleFeedbackSubmission() {
    global $pdo;
    
    $required_fields = ['name', 'email', 'subject', 'message'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Field {$field} is required"], 400);
        }
    }
    
    // Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email address'], 400);
    }
    
    // Validate rating if provided
    $rating = null;
    if (!empty($_POST['rating'])) {
        $rating = (int)$_POST['rating'];
        if ($rating < 1 || $rating > 5) {
            jsonResponse(['error' => 'Rating must be between 1 and 5'], 400);
        }
    }
    
    try {
        $user_id = null;
        if (isLoggedIn()) {
            $user_id = $_SESSION['user_id'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO feedback (user_id, name, email, subject, message, rating) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $_POST['name'],
            $_POST['email'],
            $_POST['subject'],
            $_POST['message'],
            $rating
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Thank you for your feedback! We appreciate your input and will review it shortly.'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to submit feedback: ' . $e->getMessage()], 500);
    }
}

function handleRegularFeedback() {
    global $pdo;
    
    // Fallback for regular form submission
    if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['subject']) || empty($_POST['message'])) {
        header('Location: index.php#contact?error=missing_fields');
        exit();
    }
    
    try {
        $user_id = null;
        if (isLoggedIn()) {
            $user_id = $_SESSION['user_id'];
        }
        
        $rating = !empty($_POST['rating']) ? (int)$_POST['rating'] : null;
        
        $stmt = $pdo->prepare("
            INSERT INTO feedback (user_id, name, email, subject, message, rating) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $_POST['name'],
            $_POST['email'],
            $_POST['subject'],
            $_POST['message'],
            $rating
        ]);
        
        header('Location: index.php#contact?success=feedback_submitted');
        exit();
        
    } catch (PDOException $e) {
        header('Location: index.php#contact?error=submission_failed');
        exit();
    }
}
?>
