<?php
require_once 'config.php';
requireAdminLogin($pdo);

$admin = getCurrentAdmin($pdo);
$success_message = '';
$error_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        
        
        if (empty($full_name) || empty($email)) {
            $error_message = 'Full name and email are required.';
        } else {
            try {
                // Check if email is already used by another user
                $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['admin_user_id']]);
                
                if ($stmt->fetch()) {
                    $error_message = 'Email is already in use by another account.';
                } else {
                    // Update profile
                    $stmt = $pdo->prepare("
                        UPDATE admin_users 
                        SET full_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmt->execute([$full_name, $email, $_SESSION['admin_user_id']]);
                    
                    logAdminActivity($pdo, 'update', 'profile', 'user', $_SESSION['admin_user_id'], ['updated_fields' => ['full_name', 'email']]);
                    $success_message = 'Profile updated successfully!';
                    
                    // Refresh admin data
                    $admin = getCurrentAdmin($pdo);
                }
            } catch (PDOException $e) {
                $error_message = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'New password must be at least 6 characters long.';
        } else {
            // Verify current password
            if (password_verify($current_password, $admin['password'])) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admin_users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['admin_user_id']]);
                    
                    logAdminActivity($pdo, 'update', 'profile', 'password', $_SESSION['admin_user_id']);
                    $success_message = 'Password changed successfully!';
                } catch (PDOException $e) {
                    $error_message = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Current password is incorrect.';
            }
        }
    }
}

$page_title = 'My Profile';
include 'includes/header.php';
?>



<main class="dashboard-container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <p>Manage your account settings and preferences</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- Profile Information Card -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Profile Information</h3>
            </div>
            <div class="card-content">
                <form method="POST" action="" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                    </div>

                   

                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-group">
                            <i class="fas fa-at"></i>
                            <input type="text" value="<?php echo htmlspecialchars($admin['username']); ?>" disabled>
                        </div>
                        <small class="text-muted">Username cannot be changed</small>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <div class="input-group">
                            <i class="fas fa-shield-alt"></i>
                            <input type="text" value="<?php echo htmlspecialchars($admin['role_display_name'] ?? 'No Role'); ?>" disabled>
                        </div>
                        <small class="text-muted">Role is assigned by administrators</small>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
            </div>
            <div class="card-content">
                <form method="POST" action="" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <div class="input-group">
                            <i class="fas fa-key"></i>
                            <input type="password" id="new_password" name="new_password" 
                                   minlength="6" required>
                        </div>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <div class="input-group">
                            <i class="fas fa-key"></i>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   minlength="6" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="fas fa-sync-alt"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Account Information Card -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Account Information</h3>
            </div>
            <div class="card-content">
                <div class="info-row">
                    <div class="info-label">
                        <i class="fas fa-calendar-plus"></i> Account Created
                    </div>
                    <div class="info-value">
                        <?php echo date('M d, Y g:i A', strtotime($admin['created_at'])); ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">
                        <i class="fas fa-clock"></i> Last Updated
                    </div>
                    <div class="info-value">
                        <?php echo $admin['updated_at'] ? date('M d, Y g:i A', strtotime($admin['updated_at'])) : 'Never'; ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">
                        <i class="fas fa-sign-in-alt"></i> Last Login
                    </div>
                    <div class="info-value">
                        <?php echo $admin['last_login'] ? date('M d, Y g:i A', strtotime($admin['last_login'])) : 'Never'; ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">
                        <i class="fas fa-toggle-on"></i> Account Status
                    </div>
                    <div class="info-value">
                        <?php if ($admin['is_active']): ?>
                            <span class="status-badge status-validated">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-cancelled">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Summary Card -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Your Activity</h3>
            </div>
            <div class="card-content">
                <?php
                try {
                    // Get user's recent activity count
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_actions,
                            COUNT(DISTINCT DATE(created_at)) as active_days,
                            MAX(created_at) as last_action
                        FROM admin_activity_logs
                        WHERE admin_user_id = ?
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ");
                    $stmt->execute([$_SESSION['admin_user_id']]);
                    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-tasks"></i> Actions (30 days)
                        </div>
                        <div class="info-value">
                            <?php echo number_format($activity['total_actions']); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-calendar-day"></i> Active Days
                        </div>
                        <div class="info-value">
                            <?php echo number_format($activity['active_days']); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-clock"></i> Last Activity
                        </div>
                        <div class="info-value">
                            <?php 
                            if ($activity['last_action']) {
                                $time_ago = time() - strtotime($activity['last_action']);
                                if ($time_ago < 60) {
                                    echo 'Just now';
                                } elseif ($time_ago < 3600) {
                                    echo floor($time_ago / 60) . ' minutes ago';
                                } elseif ($time_ago < 86400) {
                                    echo floor($time_ago / 3600) . ' hours ago';
                                } else {
                                    echo floor($time_ago / 86400) . ' days ago';
                                }
                            } else {
                                echo 'No activity';
                            }
                            ?>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <a href="logs.php?user_id=<?php echo $_SESSION['admin_user_id']; ?>" class="btn btn-outline btn-block">
                            <i class="fas fa-history"></i> View Full Activity Log
                        </a>
                    </div>

                <?php
                } catch (PDOException $e) {
                    echo '<p class="text-muted">Unable to load activity summary</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.info-label i {
    color: var(--primary-color);
}

.info-value {
    font-weight: 500;
    color: var(--text-primary);
}

.text-danger {
    color: var(--danger-color) !important;
}

.dashboard-grid {
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
}

@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Password confirmation validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        return false;
    }
});

// Form submission feedback
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const button = this.querySelector('button[type="submit"]');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
});

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const button = this.querySelector('button[type="submit"]');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
});
</script>

<?php include 'includes/footer.php'; ?>
