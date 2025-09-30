<?php
if (!isset($_SESSION)) {
    session_start();
}

// Get current admin data if logged in
$admin = null;
if (isset($_SESSION['admin_user_id'])) {
    try {
        $admin = getCurrentAdmin($pdo);
    } catch (Exception $e) {
        // Handle error silently
        $admin = [
            'full_name' => 'Admin User',
            'role_display_name' => 'Administrator'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>PWD Portal Admin</title>
    
     FontAwesome 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
     Leaflet CSS 
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
     Chart.js 
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
     Admin CSS 
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>

<header class="admin-header">
    <div class="header-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-brand">
            <i class="fas fa-shield-alt"></i>
            <span>PWD Portal Admin</span>
        </div>
    </div>
    
    <div class="header-right">
        <div class="header-notifications">
            <button class="notification-btn" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
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
                            <p>New appointment scheduled</p>
                            <span>5 minutes ago</span>
                        </div>
                    </div>
                    <div class="notification-item unread">
                        <i class="fas fa-comment"></i>
                        <div class="notification-content">
                            <p>New feedback received</p>
                            <span>1 hour ago</span>
                        </div>
                    </div>
                    <div class="notification-item">
                        <i class="fas fa-check-circle"></i>
                        <div class="notification-content">
                            <p>PWD record validated</p>
                            <span>2 hours ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="header-user">
            <div class="user-avatar" onclick="toggleUserDropdown(event)">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info" onclick="toggleUserDropdown(event)" style="cursor: pointer;">
                <span class="user-name"><?php echo htmlspecialchars($admin['full_name'] ?? 'Admin'); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($admin['role_display_name'] ?? 'Administrator'); ?></span>
            </div>
            <div class="user-dropdown">
                <button class="dropdown-toggle" onclick="toggleUserDropdown(event)">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu" id="userDropdown">
                    <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>
