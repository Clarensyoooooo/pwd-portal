<aside class="admin-sidebar" id="adminSidebar">
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <?php if (hasPermission($pdo, 'appointments.view')): ?>
            <li class="nav-item">
                <a href="appointments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                    <?php if (isset($stats['pending_appointments']) && $stats['pending_appointments'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_appointments']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($pdo, 'records.view')): ?>
            <li class="nav-item">
                <a href="records.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'records.php' ? 'active' : ''; ?>">
                    <i class="fas fa-id-card"></i>
                    <span>PWD Records</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($pdo, 'interviews.view')): ?>
            <li class="nav-item">
                <a href="interview.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'interview.php' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i>
                    <span>Interview</span>
                </a>
            </li>
            <?php endif; ?>

             <?php if (hasPermission($pdo, 'programs.view')): ?>
            <li class="nav-item">
                <a href="programs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'programs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bookmark"></i>
                    <span>Programs & Applications</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($pdo, 'feedback.view')): ?>
            <li class="nav-item">
                <a href="feedback.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : ''; ?>">
                    <i class="fas fa-comment-dots"></i>
                    <span>Feedback</span>
                    <?php if (isset($stats['pending_feedback']) && $stats['pending_feedback'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_feedback']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($pdo, 'gis.view')): ?>
            <li class="nav-item">
                <a href="map.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'map.php' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>GIS Map</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($pdo, 'gis.view')): ?>
            <li class="nav-item">
                <a href="debug_spatial.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'debug_spatial.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bug"></i>
                    <span>Debug Tool</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($pdo, 'reports.view')): ?>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($pdo, 'users.view')): ?>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($pdo, 'system.logs')): ?>
            <li class="nav-item">
                <a href="logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list-alt"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../index.php" class="btn btn-outline btn-sm" target="_blank">
                <i class="fas fa-external-link-alt"></i>
                Public Portal
            </a>
        </div>
    </nav>
</aside>
