<?php
require_once 'config.php';

if (isAdminLoggedIn()) {
    // Log activity
    logAdminActivity($pdo, 'logout', 'auth');
    
    // Destroy session
    session_destroy();
}

header('Location: login.php');
exit();
?>
