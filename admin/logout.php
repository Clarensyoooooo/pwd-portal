<?php
require_once 'config.php';
// $pdo is assumed to be available from config.php

if (isAdminLoggedIn()) {
    // Log activity BEFORE destroying session data
    logAdminActivity($pdo, 'logout', 'auth');
    
    // --- START: Clear Active Session ID ---
    // Clear the active session ID from the database
    try {
        $stmt = $pdo->prepare("UPDATE admin_users SET active_session_id = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['admin_user_id']]);
    } catch (PDOException $e) {
        error_log("Failed to clear session ID on logout: " . $e->getMessage());
    }
    // --- END: Clear Active Session ID ---

    // Destroy session
    session_destroy();
}

header('Location: login.php');
exit();
?>