<?php
require_once 'config.php';
requireAdminLogin();

$admin = getCurrentAdmin($pdo);
$success_message = '';
$error_message = '';

// Get current settings
$current_settings = [];
try {
    $stmt = $pdo->prepare("
        SELECT setting_key, setting_value 
        FROM admin_user_settings 
        WHERE admin_user_id = ?
    ");
    $stmt->execute([$_SESSION['admin_user_id']]);
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error loading settings: " . $e->getMessage());
}

// Set defaults if not exists
$settings = [
    'email_notifications' => $current_settings['email_notifications'] ?? '1',
    'appointment_alerts' => $current_settings['appointment_alerts'] ?? '1',
    'feedback_alerts' => $current_settings['feedback_alerts'] ?? '1',
    'dark_mode' => $current_settings['dark_mode'] ?? '0',
];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form values (checkboxes return '1' if checked, null if unchecked)
        $new_settings = [
            'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
            'appointment_alerts' => isset($_POST['appointment_alerts']) ? '1' : '0',
            'feedback_alerts' => isset($_POST['feedback_alerts']) ? '1' : '0',
            'dark_mode' => isset($_POST['dark_mode']) ? '1' : '0',
        ];

        // Update or insert each setting
        foreach ($new_settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO admin_user_settings (admin_user_id, setting_key, setting_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$_SESSION['admin_user_id'], $key, $value, $value]);
        }

        // Update current settings array
        $settings = $new_settings;

        $success_message = 'Settings saved successfully!';
        logAdminActivity($pdo, 'update', 'settings', 'preferences', $_SESSION['admin_user_id'], $new_settings);
    } catch (PDOException $e) {
        error_log("Error saving settings: " . $e->getMessage());
        $error_message = 'Failed to save settings. Please try again.';
    }
}

$page_title = 'Settings';
include 'includes/header.php';
?>

<div class="admin-sidebar" id="adminSidebar">
    <?php include 'includes/sidebar.php'; ?>
</div>

<div class="main-content" id="mainContent" data-theme="<?php echo $settings['dark_mode'] === '1' ? 'dark' : 'light'; ?>">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Configure your preferences and system settings</p>
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

    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
        </div>
        <div class="card-content">
            <form method="POST" action="" id="settingsForm">
                <div class="settings-group">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Email Notifications</h4>
                            <p class="text-muted">Receive email alerts for important events</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_notifications" 
                                       <?php echo $settings['email_notifications'] === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>New Appointment Alerts</h4>
                            <p class="text-muted">Get notified when new appointments are scheduled</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="appointment_alerts" 
                                       <?php echo $settings['appointment_alerts'] === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Feedback Notifications</h4>
                            <p class="text-muted">Receive alerts for new feedback submissions</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="feedback_alerts" 
                                       <?php echo $settings['feedback_alerts'] === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="dashboard-card" style="margin-top: 20px;">
        <div class="card-header">
            <h3><i class="fas fa-palette"></i> Appearance</h3>
        </div>
        <div class="card-content">
            <form method="POST" action="" id="appearanceForm">
                <div class="settings-group">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Dark Mode</h4>
                            <p class="text-muted">Switch to dark theme for better viewing at night</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="dark_mode" id="darkModeToggle"
                                       <?php echo $settings['dark_mode'] === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Appearance
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="dashboard-card" style="margin-top: 20px;">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> System Information</h3>
        </div>
        <div class="card-content">
            <div class="info-row">
                <div class="info-label">
                    <i class="fas fa-code-branch"></i> Version
                </div>
                <div class="info-value">
                    1.0.0
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">
                    <i class="fas fa-server"></i> PHP Version
                </div>
                <div class="info-value">
                    <?php echo phpversion(); ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">
                    <i class="fas fa-database"></i> Database
                </div>
                <div class="info-value">
                    <?php 
                    try {
                        echo 'MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn();
                    } catch (PDOException $e) {
                        echo 'MySQL';
                    }
                    ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">
                    <i class="fas fa-clock"></i> Last Login
                </div>
                <div class="info-value">
                    <?php echo isset($admin['last_login']) ? date('M d, Y g:i A', strtotime($admin['last_login'])) : 'N/A'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-group {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: var(--bg-primary);
    border-radius: var(--border-radius);
}

.setting-info h4 {
    margin: 0 0 4px 0;
    color: var(--text-primary);
    font-size: 1rem;
}

.setting-info p {
    margin: 0;
    font-size: 0.85rem;
}

.setting-control {
    flex-shrink: 0;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: var(--primary-color);
}

input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

input:disabled + .toggle-slider {
    opacity: 0.5;
    cursor: not-allowed;
}

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

/* Dark Mode Styles */
[data-theme="dark"] {
    --bg-primary: #1e293b;
    --bg-secondary: #0f172a;
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --border-color: #334155;
}

[data-theme="dark"] .admin-header,
[data-theme="dark"] .admin-sidebar {
    background: #0f172a;
    border-color: #334155;
}

[data-theme="dark"] .dashboard-card,
[data-theme="dark"] .data-card,
[data-theme="dark"] .stat-card {
    background: #1e293b;
    border-color: #334155;
}

[data-theme="dark"] .setting-item {
    background: #0f172a;
}

[data-theme="dark"] .nav-link:hover {
    background: #1e293b;
}

[data-theme="dark"] .nav-link.active {
    background: var(--primary-color);
}

[data-theme="dark"] input,
[data-theme="dark"] select,
[data-theme="dark"] textarea {
    background: #0f172a;
    border-color: #334155;
    color: var(--text-primary);
}

[data-theme="dark"] .data-table th {
    background: #0f172a;
}

[data-theme="dark"] .data-table tr:hover {
    background: #0f172a;
}

[data-theme="dark"] .alert-success {
    background: #064e3b;
    color: #d1fae5;
    border-color: #065f46;
}

[data-theme="dark"] .alert-error {
    background: #7f1d1d;
    color: #fee2e2;
    border-color: #991b1b;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Apply saved dark mode on page load
    const mainContent = document.getElementById('mainContent');
    if (mainContent && mainContent.dataset.theme === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
    }

    // Handle dark mode toggle with live preview
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.body.setAttribute('data-theme', 'dark');
            } else {
                document.body.removeAttribute('data-theme');
            }
        });
    }

    // Show success notification after form submission
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => {
                successAlert.remove();
            }, 300);
        }, 3000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
