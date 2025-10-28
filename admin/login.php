<?php
require_once 'config.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT au.*, ar.name as role_name, ar.display_name as role_display_name 
                FROM admin_users au 
                LEFT JOIN admin_roles ar ON au.role_id = ar.id 
                WHERE au.username = ? AND au.is_active = 1
            ");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                
                // --- START: SINGLE SESSION LOGIC ---
                
                // 1. Regenerate session ID for security and to get a new ID
                session_regenerate_id(true); 
                
                // 2. Get the new, current session ID
                $current_session_id = session_id();
    
                // 3. Set the session variables
                $_SESSION['admin_user_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role_name'];
                $_SESSION['active_session_id'] = $current_session_id; // Store for checking

                // 4. Update the database with the new active session ID AND last_login
                // This invalidates all other sessions for this user.
                $stmt = $pdo->prepare("
                    UPDATE admin_users 
                    SET active_session_id = ?, last_login = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$current_session_id, $admin['id']]);
                
                // --- END: SINGLE SESSION LOGIC ---
                
                // Log activity
                logAdminActivity($pdo, 'login', 'auth');
                
                header('Location: index.php');
                exit();
            } else {
                // Add a specific error for inactive accounts
                if ($admin && !$admin['is_active']) {
                    $error = 'Your account is inactive. Please contact an administrator.';
                } else {
                    $error = 'Invalid username or password';
                }
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PWD Portal</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>PWD Portal Admin</h1>
                <p>Sign in to access the admin panel</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            
            <div class="login-footer">
                <p>Default credentials: admin / password</p>
                <a href="../index.php">← Back to Public Portal</a>
            </div>
        </div>
    </div>
</body>
</html>
