<?php
require_once 'config.php';
requireAdminLogin();

$admin = getCurrentAdmin($pdo);
$success_message = '';
$error_message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For now, this is a placeholder for future settings
    $success_message = 'Settings saved successfully!';
    logAdminActivity($pdo, 'update', 'settings', 'preferences', $_SESSION['admin_user_id']);
}

$page_title = 'Settings';
include 'includes/header.php';
?>

<div class="admin-sidebar" id="adminSidebar">
    <?php include 'includes/sidebar.php'; ?>
</div>

<div class="main-content">
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
            <form method="POST" action="">
                <div class="settings-group">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Email Notifications</h4>
                            <p class="text-muted">Receive email alerts for important events</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_notifications" checked>
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
                                <input type="checkbox" name="appointment_alerts" checked>
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
                                <input type="checkbox" name="feedback_alerts" checked>
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
            <div class="settings-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <h4>Dark Mode</h4>
                        <p class="text-muted">Switch to dark theme (Coming soon)</p>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="dark_mode" disabled>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <h4>Compact View</h4>
                        <p class="text-muted">Use compact layout for tables and lists (Coming soon)</p>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="compact_view" disabled>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
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
</style>

<?php include 'includes/footer.php'; ?>
