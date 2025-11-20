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
                session_regenerate_id(true); 
                $current_session_id = session_id();
    
                $_SESSION['admin_user_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role_name'];
                $_SESSION['active_session_id'] = $current_session_id;

                $stmt = $pdo->prepare("
                    UPDATE admin_users 
                    SET active_session_id = ?, last_login = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$current_session_id, $admin['id']]);
                // --- END: SINGLE SESSION LOGIC ---
                
                logAdminActivity($pdo, 'login', 'auth');
                
                header('Location: index.php');
                exit();
            } else {
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
    <title>Admin Login - PDAO Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e40af; 
            --light-blue: #3b82f6;
            --text-color: #333;
            --bg-color: #1e3a8a; 
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            /* INCREASED WIDTH: Made wide enough so the 25% form isn't too small */
            width: 1100px; 
            height: 600px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            display: flex;
            overflow: hidden; 
        }

        /* Left Side - Image */
        .login-image {
            /* CHANGED: flex: 3 makes it take up 3 parts (approx 75%) */
            flex: 3; 
            background-image: url('https://i.imgur.com/IrEnt7N.png');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .login-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(30, 58, 138, 0.3);
            mix-blend-mode: multiply;
        }

        /* Right Side - Form */
        .login-form-wrapper {
            /* CHANGED: flex: 1 makes it take up 1 part (approx 25%) */
            flex: 1; 
            /* REDUCED PADDING: Reduced from 40px to 25px to fit the narrower space */
            padding: 25px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: var(--text-color);
            min-width: 280px; /* Prevents it from getting unbreakably small */
        }

        /* Header Section */
        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-circle {
            width: 50px; /* Made slightly smaller */
            height: 50px;
            background-color: var(--primary-blue);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .login-header h1 {
            color: var(--primary-blue);
            font-size: 20px; /* Slightly smaller font */
            font-weight: 700;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #666;
            font-size: 12px;
        }

        /* Inputs */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
            color: #444;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 12px;
        }

        .input-group input {
            width: 100%;
            padding: 10px 10px 10px 35px; 
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.3s;
        }

        .input-group input:focus {
            border-color: var(--primary-blue);
        }

        /* Button */
        .btn-primary {
            width: 100%;
            padding: 10px;
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background-color: #1e3a8a;
        }

        .login-footer {
            margin-top: 15px;
            text-align: center;
        }

        .login-footer a {
            color: #666;
            text-decoration: none;
            font-size: 12px;
            transition: color 0.3s;
        }

        .login-footer a:hover {
            color: var(--primary-blue);
        }

        .alert {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-image"></div>

        <div class="login-form-wrapper">
            <div class="login-header">
                <div class="logo-circle">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>PDAO Admin</h1>
                <p>Sign in to access the admin panel</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Enter username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="login-footer">
                <a href="../index.php">← Back to Public Portal</a>
            </div>
        </div>
    </div>

</body>
</html>