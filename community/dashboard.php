<?php
require_once '../config.php';
require_once 'auth.php';

// Check if user is logged in
if (!isCommunityUserLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$user = getCurrentCommunityUserData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Dashboard - PWD Portal</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .community-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .community-header h1 { margin: 0; font-size: 2.5em; }
        .community-header p { margin: 10px 0 0 0; opacity: 0.9; }

        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr 3fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        .sidebar-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            height: fit-content;
        }

        /* Profile Sidebar */
        .user-profile-section {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            margin: 0 auto 15px;
        }
        .user-profile-section h3 { margin: 0 0 5px 0; color: #333; }
        .user-profile-section p { margin: 0; color: #666; font-size: 0.9em; }

        .pwd-status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-top: 10px;
        }
        .pwd-status-badge.active { background: #d4edda; color: #155724; }
        .pwd-status-badge.expired, .pwd-status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        /* Sidebar Menu */
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { margin: 0; }
        .sidebar-menu a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #f5f5f5;
            border-left-color: #667eea;
            color: #667eea;
        }
        .sidebar-menu i { width: 20px; margin-right: 10px; }

        /* Main Content */
        .main-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .content-section { display: none; }
        .content-section.active { display: block; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }
        .info-item label {
            display: block;
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .info-item p { margin: 0; color: #333; font-size: 1.1em; }

        /* PWD ID Card */
        .pwd-id-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .pwd-id-card h3 { margin: 0 0 20px 0; font-size: 1.3em; }
        .pwd-id-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .pwd-id-detail { padding: 15px; background: rgba(255,255,255,0.1); border-radius: 6px; }
        .pwd-id-detail label { display: block; font-size: 0.85em; opacity: 0.9; margin-bottom: 5px; }
        .pwd-id-detail p { margin: 0; font-size: 1.1em; font-weight: 600; }

        /* Logout Button */
        .logout-btn {
            background: #dc3545; color: white; border: none;
            padding: 10px 20px; border-radius: 6px; cursor: pointer;
            font-size: 1em; transition: background 0.3s ease;
            width: 100%; margin-top: 20px;
        }
        .logout-btn:hover { background: #c82333; }
        
        /* Community Updates Styles */
        .community-updates {
            background: #fdfdfd;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .community-updates h3 { margin-top: 0; color: #667eea; }
        .update-item { border-bottom: 1px solid #f0f0f0; padding: 15px 0; }
        .update-item:last-child { border-bottom: none; padding-bottom: 0; }
        .update-item:first-of-type { padding-top: 0; }
        .update-item h4 { margin: 0 0 5px 0; color: #333; }
        .update-item p { margin: 0 0 5px 0; color: #555; line-height: 1.6; }
        .update-item small { color: #999; font-style: italic; }
        hr.section-divider { border: 0; height: 1px; background: #eee; margin: 30px 0; }

        /* Feedback Form Styles */
        .feedback-form-internal .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .feedback-form-internal .form-group { margin-bottom: 20px; }
        .feedback-form-internal label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .feedback-form-internal input[type="text"],
        .feedback-form-internal input[type="email"],
        .feedback-form-internal textarea {
            width: 100%; padding: 12px; border: 1px solid #ddd;
            border-radius: 6px; box-sizing: border-box; 
        }
        .feedback-form-internal input[readonly] { background: #f5f5f5; cursor: not-allowed; }
        .feedback-form-internal .btn-primary {
            background: #667eea; color: white; border: none;
            padding: 12px 20px; border-radius: 6px; cursor: pointer;
            font-size: 1em; font-weight: 600; transition: background 0.3s ease;
        }
        .feedback-form-internal .btn-primary:hover { background: #5a6ed0; }
        
        /* Star Rating styles */
        .rating-container { display: flex; align-items: center; gap: 15px; }
        .star-rating { display: inline-block; }
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 1.5rem; color: #ccc; cursor: pointer;
            padding: 0 2px; float: right; transition: color 0.2s ease;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #f59e0b; }
        .star-rating input:checked + label:hover,
        .star-rating input:checked + label:hover ~ label,
        .star-rating label:hover ~ input:checked ~ label { color: #f59e0b; }
        .rating-text { color: #555; font-weight: 500; }

        /* History List Styles */
        .history-list-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .history-item {
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow 0.3s ease;
        }
        .history-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .history-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #fff;
            border-bottom: 1px solid #eee;
        }
        .history-item-header h4 { margin: 0; color: #333; }
        .history-item-body {
            padding: 20px;
            font-size: 0.95em;
            color: #555;
            line-height: 1.6;
        }
        .history-item-body p { margin: 0 0 10px 0; }
        .history-item-body p:last-child { margin-bottom: 0; }
        
        /* Status Badge Styles */
        .status-badge-sm {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge-sm.status-pending, .status-badge-sm.status-submitted, .status-badge-sm.status-under_review {
            background-color: #fef3c7; color: #a16207;
        }
        .status-badge-sm.status-confirmed, .status-badge-sm.status-approved, .status-badge-sm.status-issued, .status-badge-sm.status-completed {
            background-color: #dcfce7; color: #166534;
        }
        .status-badge-sm.status-cancelled, .status-badge-sm.status-rejected, .status-badge-sm.status-expired, .status-badge-sm.status-inactive {
            background-color: #fee2e2; color: #991b1b;
        }
        .loading-placeholder, .no-history {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 40px;
        }
        
        /* NEW: Assistance Message Style */
        .assistance-message {
            background: #f0f5ff;
            border: 1px solid #d6e4ff;
            color: #334;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        /* NEW: History Item Actions */
        .history-item-actions {
            padding: 10px 20px;
            background: #fff;
            border-top: 1px solid #eee;
            text-align: right;
        }
        .btn-cancel {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        .btn-cancel:hover { background: #dc2626; }
        .btn-cancel[disabled] {
            background: #fca5a5;
            cursor: not-allowed;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container { grid-template-columns: 1fr; }
            .info-grid { grid-template-columns: 1fr; }
            .pwd-id-details { grid-template-columns: 1fr; }
            .community-header h1 { font-size: 1.8em; }
            .feedback-form-internal .form-row { grid-template-columns: 1fr; }
            .history-item-header { flex-direction: column; align-items: flex-start; gap: 5px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="top-bar">
            <div class="container">
                <div class="contact-info">
                    <span><i class="fas fa-phone"></i> Hotline: 8888-1000</span>
                    <span><i class="fas fa-envelope"></i> info@pwd.gov.ph</span>
                </div>
            </div>
        </div>
        <nav class="navbar">
            <div class="container">
                <div class="nav-brand">
                    <img src="../assets/logo.png" alt="PWD Logo" class="logo">
                    <span class="brand-text">PWD Portal</span>
                </div>
                <ul class="nav-menu">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="#" onclick="logout()">Logout</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <section class="community-header">
        <div class="container">
            <h1>Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
            <p>Your PWD Community Portal - Exclusive content and features for verified members</p>
        </div>
    </section>

    <div class="container">
        <div class="dashboard-container">
            <div class="sidebar-card">
                <div class="user-profile-section">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <?php
                        $status = strtolower($user['pwd_id_status']);
                        $status_class = 'inactive'; 
                        
                        if ($status === 'issued') {
                            $status_class = 'active';
                        } elseif ($status === 'expired' || $status === 'inactive') {
                            $status_class = 'expired';
                        }
                    ?>
                    <span class="pwd-status-badge <?php echo $status_class; ?>">
                        <i class="fas fa-check-circle"></i> <?php echo ucfirst($user['pwd_id_status']); ?>
                    </span>
                </div>

                <ul class="sidebar-menu">
                    <li><a href="#" onclick="showSection('profile')" class="menu-link active" data-section="profile"><i class="fas fa-user-circle"></i> My Profile</a></li>
                    <li><a href="#" onclick="showSection('pwd-id')" class="menu-link" data-section="pwd-id"><i class="fas fa-id-card"></i> PWD ID Information</a></li>
                    <li><a href="#" onclick="showSection('appointments')" class="menu-link" data-section="appointments"><i class="fas fa-calendar"></i> My Appointments</a></li>
                    <li><a href="#" onclick="showSection('programs')" class="menu-link" data-section="programs"><i class="fas fa-graduation-cap"></i> My Programs</a></li>
                    <li><a href="#" onclick="showSection('feedback')" class="menu-link" data-section="feedback"><i class="fas fa-comment-dots"></i> Submit Feedback</a></li>
                </ul>

                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>

            <div class="main-content">
                <div id="profile" class="content-section active">
                
                    <div class="community-updates">
                        <h3><i class="fas fa-bullhorn"></i> Community Updates</h3>
                        <div class="update-item">
                            <h4>New Livelihood Program Available</h4>
                            <p>We are excited to launch our new "Livelihood Training Program". Check the "My Programs" tab or visit the main portal for details on how to apply. Applications are open until Nov 30, 2025.</p>
                            <small>Posted on: October 28, 2025</small>
                        </div>
                        <div class="update-item">
                            <h4>Holiday Office Schedule</h4>
                            <p>Please be advised that our office will be closed on November 1 (All Saints' Day). Regular operations will resume on November 2.</p>
                            <small>Posted on: October 27, 2025</small>
                        </div>
                    </div>

                    <hr class="section-divider">

                    <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>First Name</label>
                            <p><?php echo htmlspecialchars($user['first_name']); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Last Name</label>
                            <p><?php echo htmlspecialchars($user['last_name']); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Email Address</label>
                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Phone Number</label>
                            <p><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Date of Birth</label>
                            <p><?php echo htmlspecialchars($user['date_of_birth'] ? date('F d, Y', strtotime($user['date_of_birth'])) : 'Not provided'); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Disability Type</label>
                            <p><?php echo htmlspecialchars($user['disability_type'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>
                </div>

                <div id="pwd-id" class="content-section">
                    <h2><i class="fas fa-id-card"></i> PWD ID Information</h2>
                    <div class="pwd-id-card">
                        <h3>Your PWD ID Details</h3>
                        <div class="pwd-id-details">
                            <div class="pwd-id-detail">
                                <label>PWD Record Number</label>
                                <p><?php echo htmlspecialchars($user['pwd_record_number']); ?></p>
                            </div>
                            <div class="pwd-id-detail">
                                <label>Status</label>
                                <p><?php echo ucfirst($user['pwd_id_status']); ?></p>
                            </div>
                            <div class="pwd-id-detail">
                                <label>Issue Date</label>
                                <p><?php echo htmlspecialchars($user['pwd_id_issue_date'] ? date('F d, Y', strtotime($user['pwd_id_issue_date'])) : 'N/A'); ?></p>
                            </div>
                            <div class="pwd-id-detail">
                                <label>Expiry Date</label>
                                <p><?php echo htmlspecialchars($user['pwd_id_expiry_date'] ? date('F d, Y', strtotime($user['pwd_id_expiry_date'])) : 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="appointments" class="content-section">
                    <h2><i class="fas fa-calendar"></i> My Appointments</h2>
                    
                    <div class="assistance-message">
                        <p>To reschedule or for personal assistance, our staff is happy to help! Please call us at (043) 784 8022. You may also cancel your appointment directly using the button below.</p>
                    </div>

                    <div id="appointmentHistoryList" class="history-list-container">
                        </div>
                </div>

                <div id="programs" class="content-section">
                    <h2><i class="fas fa-graduation-cap"></i> My Programs</h2>
                    <div id="programHistoryList" class="history-list-container">
                        </div>
                </div>

                <div id="feedback" class="content-section">
                    <h2><i class="fas fa-comment-dots"></i> Submit Feedback</h2>
                    <p>Your feedback is valuable to us. Please share your experience, suggestions, or any issues you've encountered.</p>
                    
                    <form class="feedback-form-internal" id="communityFeedbackForm" onsubmit="handleCommunityFeedback(event)">
                        <input type="hidden" name="action" value="submit_feedback">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="feedbackName">Name</label>
                                <input type="text" id="feedbackName" name="name" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="feedbackEmail">Email</label>
                                <input type="email" id="feedbackEmail" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feedbackSubject">Subject</label>
                            <input type="text" id="feedbackSubject" name="subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Rate Your Experience</label>
                            <div class="rating-container">
                                <div class="star-rating">
                                    <input type="radio" id="star5_comm" name="rating" value="5"><label for="star5_comm" title="Excellent"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star4_comm" name="rating" value="4"><label for="star4_comm" title="Good"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star3_comm" name="rating" value="3"><label for="star3_comm" title="Average"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star2_comm" name="rating" value="2"><label for="star2_comm" title="Poor"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star1_comm" name="rating" value="1"><label for="star1_comm" title="Very Poor"><i class="fas fa-star"></i></label>
                                </div>
                                <span class="rating-text" id="communityRatingText">Click to rate</span>
                            </div>
                        </div>

                        <div style="opacity: 0; position: absolute; left: -5000px;" aria-hidden="true">
                            <label for="website_url">Website</label>
                            <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="feedbackMessage">Message</label>
                            <textarea id="feedbackMessage" name="message" rows="5" required placeholder="Please share your feedback, suggestions, or concerns..."></textarea>
                        </div>
                        <button type="submit" class="btn-primary">Submit Feedback</button>
                    </form>
                </div>
                
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="copyright">
                <p>&copy; 2025 PWD Portal. All rights reserved. | Developed with ❤️ for the PWD Community</p>
            </div>
        </div>
    </footer>

    <script>
        // --- Notification Function ---
        function showNotification(message, type = "info") {
            const existingNotifications = document.querySelectorAll(".notification");
            existingNotifications.forEach((notification) => notification.remove());

            const notification = document.createElement("div");
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
            `;
            notification.style.cssText = `
                position: fixed; top: 100px; right: 20px; z-index: 10000;
                padding: 15px 20px; border-radius: 5px; color: white;
                font-weight: 500; max-width: 400px; transform: translateX(100%);
                transition: transform 0.3s ease; box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            `;
            const colors = { success: "#10b981", error: "#ef4444", info: "#2c5aa0" };
            notification.style.backgroundColor = colors[type] || colors.info;
            document.body.appendChild(notification);
            setTimeout(() => { notification.style.transform = "translateX(0)"; }, 100);
            setTimeout(() => {
                if (notification.parentElement) {
                notification.style.transform = "translateX(100%)";
                setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // --- Tab Navigation ---
        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelectorAll('.menu-link').forEach(link => {
                link.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
            document.querySelector(`.menu-link[data-section="${sectionId}"]`).classList.add('active');
            return false;
        }

        // --- Logout Function ---
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                const formData = new FormData();
                formData.append('action', 'logout_community');
                fetch('auth.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { window.location.href = '../index.php'; })
                    .catch(error => {
                        console.error('Error:', error);
                        window.location.href = '../index.php';
                    });
            }
        }

        // --- Feedback Form ---
        async function handleCommunityFeedback(event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.textContent;
            
            submitButton.disabled = true;
            submitButton.textContent = "Submitting...";
            const formData = new FormData(form);
            
            try {
                const response = await fetch("../process_feedback.php", { method: "POST", body: formData });
                const result = await response.json();
                if (result.success) {
                    showNotification(result.message, "success");
                    form.reset();
                    document.getElementById('communityRatingText').textContent = 'Click to rate';
                    form.querySelectorAll('input[type="radio"]').forEach(radio => radio.checked = false);
                } else {
                    showNotification(result.error, "error");
                }
            } catch (error) {
                showNotification("Failed to submit feedback. Please try again.", "error");
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        }

        // --- Star Rating ---
        function initializeCommunityStarRating() {
            const starInputs = document.querySelectorAll('#communityFeedbackForm .star-rating input[type="radio"]');
            const ratingText = document.getElementById("communityRatingText");
            starInputs.forEach((input) => {
                input.addEventListener("change", function () {
                    const rating = this.value;
                    const ratingTexts = { 1: "Very Poor", 2: "Poor", 3: "Average", 4: "Good", 5: "Excellent" };
                    if (ratingText) {
                        ratingText.textContent = ratingTexts[rating] || "Click to rate";
                    }
                });
            });
        }
        
        // --- History Loading Functions ---
        
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" });
        }

        function formatTime(timeString) {
            if (!timeString) return '';
            const [hours, minutes] = timeString.split(":");
            const date = new Date();
            date.setHours(Number.parseInt(hours), Number.parseInt(minutes));
            return date.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit", hour12: true });
        }
        
        // MODIFIED: Updated getStatusClass to handle all statuses
        function getStatusClass(status) {
            const s = (status || 'pending').toLowerCase().replace(/_/g, '');
            if (['confirmed', 'approved', 'issued', 'completed'].includes(s)) return 'status-confirmed';
            if (['cancelled', 'rejected', 'expired', 'inactive'].includes(s)) return 'status-cancelled';
            return 'status-pending'; // for pending, submitted, underreview
        }

        // MODIFIED: Updated populateAppointmentHistory to add Cancel button
        function populateAppointmentHistory(appointments) {
            const container = document.getElementById('appointmentHistoryList');
            if (!appointments || appointments.length === 0) {
                container.innerHTML = '<div class="no-history">No appointment history found.</div>';
                return;
            }
            container.innerHTML = appointments.map(app => {
                const status = (app.status || 'pending').toLowerCase();
                let actions = '';
                // Only show cancel button if status is pending or confirmed
                if (status === 'pending' || status === 'confirmed') {
                    actions = `
                        <div class="history-item-actions">
                            <button class="btn-cancel" onclick="requestAppointmentCancellation('${app.reference_number}')">
                                <i class="fas fa-times"></i> Cancel Appointment
                            </button>
                        </div>
                    `;
                }

                return `
                <div class="history-item">
                    <div class="history-item-header">
                        <h4>${app.appointment_type.replace('_', ' ')} Application</h4>
                        <span class="status-badge-sm ${getStatusClass(app.status)}">${app.status}</span>
                    </div>
                    <div class="history-item-body">
                        <p><strong>Reference:</strong> ${app.reference_number}</p>
                        <p><strong>Scheduled Date:</strong> ${formatDate(app.preferred_date)} at ${formatTime(app.preferred_time)}</p>
                    </div>
                    ${actions}
                </div>
                `;
            }).join('');
        }

        function populateProgramHistory(programs) {
            const container = document.getElementById('programHistoryList');
            if (!programs || programs.length === 0) {
                container.innerHTML = '<div class="no-history">No program application history found.</div>';
                return;
            }
            container.innerHTML = programs.map(prog => `
                <div class="history-item">
                    <div class="history-item-header">
                        <h4>${prog.title}</h4>
                        <span class="status-badge-sm ${getStatusClass(prog.status)}">${prog.status}</span>
                    </div>
                    <div class="history-item-body">
                        <p><strong>Date Applied:</strong> ${formatDate(prog.created_at)}</p>
                    </div>
                </div>
            `).join('');
        }
        
        // NEW: Function to handle cancellation request
        async function requestAppointmentCancellation(referenceNumber) {
            const reason = prompt("Please enter a reason for cancelling this appointment (this will be seen by the admin):");
            
            if (reason === null) {
                // User clicked "Cancel" on the prompt
                return;
            }

            if (reason.trim() === "") {
                showNotification("A reason is required to cancel.", "error");
                return;
            }

            // Disable the button to prevent double-clicks
            const button = event.target;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

            const formData = new FormData();
            formData.append('action', 'cancel_appointment');
            formData.append('reference_number', referenceNumber);
            formData.append('reason', reason);

            try {
                const response = await fetch('auth.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, "success");
                    loadUserHistory(); // Refresh the appointment list
                } else {
                    showNotification(result.error, "error");
                    button.disabled = false; // Re-enable button on failure
                    button.innerHTML = '<i class="fas fa-times"></i> Cancel Appointment';
                }
            } catch (error) {
                showNotification("An error occurred. Please try again.", "error");
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-times"></i> Cancel Appointment';
            }
        }


        async function loadUserHistory() {
            const appContainer = document.getElementById('appointmentHistoryList');
            const progContainer = document.getElementById('programHistoryList');
            
            appContainer.innerHTML = '<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading appointments...</div>';
            progContainer.innerHTML = '<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading program applications...</div>';

            const formData = new FormData();
            formData.append('action', 'get_community_history');

            try {
                const response = await fetch('auth.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    populateAppointmentHistory(result.appointments);
                    populateProgramHistory(result.programs);
                } else {
                    appContainer.innerHTML = '<div class="no-history">Error loading appointments.</div>';
                    progContainer.innerHTML = '<div class="no-history">Error loading program applications.</div>';
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                console.error("Error fetching history:", error);
                appContainer.innerHTML = '<div class="no-history">Error loading appointments.</div>';
                progContainer.innerHTML = '<div class="no-history">Error loading program applications.</div>';
                showNotification('Could not connect to server to get history.', 'error');
            }
        }

        // --- Initialize all scripts on page load ---
        document.addEventListener('DOMContentLoaded', function() {
            initializeCommunityStarRating();
            loadUserHistory(); // Load history on page load
        });

    </script>
</body>
</html>