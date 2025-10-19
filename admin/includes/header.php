<?php
if (!isset($admin)) {
    $admin = getCurrentAdmin($pdo);
}

// Get notification count
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'");
    $notification_count = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $notification_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-brand">
                <i class="fas fa-wheelchair"></i>
                <span>PDAO Helps</span>
            </div>
        </div>
        <!--
        <div class="header-right">
            <div class="header-notifications">
                <button class="notification-btn" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="notification-badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </button>
                
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <button class="mark-all-read">Mark all as read</button>
                    </div>
                    <div class="notification-list">
                        <div class="notification-item unread">
                            <i class="fas fa-calendar-check"></i>
                            <div class="notification-content">
                                <p>New appointment request</p>
                                <span>2 minutes ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->

            
            
            <div class="header-user">
                <div class="user-avatar">
                    <?php // Use the null coalescing operator (??) to provide a default value and prevent errors ?>
                    <?php echo strtoupper(substr($admin['full_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <?php // Using ?? '' provides an empty string if the key doesn't exist, preventing the warning. ?>
                    <div class="user-name"><?php echo htmlspecialchars($admin['full_name'] ?? 'Admin User'); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($admin['role'] ?? ''); ?></div>
                </div>
                <div class="user-dropdown">
                    <button class="dropdown-toggle" id="userDropdownToggle">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <!--<a href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a> -->
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="text-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-content" id="mainContent">
