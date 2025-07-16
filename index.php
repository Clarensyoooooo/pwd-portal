<?php
require_once 'config.php';
$current_user = getCurrentUser($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWD Portal - Empowering the PWD Community</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="top-bar">
            <div class="container">
                <div class="contact-info">
                    <span><i class="fas fa-phone"></i> Hotline: 8888-1000</span>
                    <span><i class="fas fa-envelope"></i> info@pwd.gov.ph</span>
                </div>
                <div class="header-actions">
                    <?php if ($current_user): ?>
                        <div class="user-menu">
                            <span class="user-greeting">Hello, <?php echo htmlspecialchars($current_user['first_name']); ?>!</span>
                            <button class="btn-logout" onclick="logout()">Logout</button>
                        </div>
                    <?php else: ?>
                        <button class="btn-login" onclick="showLoginModal()">Login</button>
                        <button class="btn-register" onclick="showRegisterModal()">Register</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <nav class="navbar">
            <div class="container">
                <div class="nav-brand">
                    <img src="assets/logo.png" alt="PWD Logo" class="logo">
                    <span class="brand-text">PWD Portal</span>
                </div>
                <ul class="nav-menu">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#programs">Programs</a></li>
                    <li><a href="#requirements">Requirements</a></li>
                    <li><a href="#organizations">Organizations</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
                <div class="mobile-menu-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>Empowering the PWD Community</h1>
                    <p>Access essential services and support for Persons with Disabilities. 
                    Book appointments for PWD ID application, track your appointment status, and discover programs designed to enhance your quality of life.</p>
                </div>
                <div class="hero-banner">
                    <div class="promo-badge">
                        <div class="badge-content">
                            <span class="badge-text">SPECIAL OFFER</span>
                            <span class="badge-discount">DISCOUNTS & BENEFITS</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PWD ID Application Process -->
    <section class="application-process" id="services">
        <div class="container">
            <h2>PWD ID Application Process</h2>
            <div class="process-steps">
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Fill Up Form</h3>
                    <p>Complete the PWD ID application form with your personal information and disability details.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Set Appointment</h3>
                    <p>Choose a convenient date and time to visit our office for document verification.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3>Visit Office</h3>
                    <p>Bring your requirements and completed form for verification and processing.</p>
                </div>
            </div>
            <div class="process-action">
                <button class="btn-primary" onclick="startApplication()">Start Application</button>
            </div>
        </div>
    </section>

    <!-- Track Appointment -->
    <section class="track-appointment">
        <div class="container">
            <div class="track-header">
                <h2>Track Your Appointment</h2>
                <p>Check the status of your PWD appointment</p>
            </div>
            
            <div class="tracking-layout">
                <!-- Left Side - Requirements and Input -->
                <div class="tracking-left">
                    <div class="tracking-input-section">
                        <h3>Appointment Reference Number</h3>
                        <div class="tracking-form">
                            <input type="text" placeholder="Enter your reference number" id="trackingNumber">
                            <button class="btn-track" onclick="trackAppointment()">Track</button>
                        </div>
                        <p class="tracking-help">Enter your reference number to check the status of your appointment.</p>
                    </div>

                    <div class="requirements-checklist" id="requirements">
                        <h3><i class="fas fa-clipboard-list"></i> PWD ID Requirements</h3>
                        <ul class="requirements-list">
                            <li><i class="fas fa-circle"></i> Medical certificate from licensed physician</li>
                            <li><i class="fas fa-circle"></i> Barangay certificate of residency</li>
                            <li><i class="fas fa-circle"></i> 2 recent 1x1 ID pictures</li>
                            <li><i class="fas fa-circle"></i> Valid government-issued ID</li>
                            <li><i class="fas fa-circle"></i> Birth certificate</li>
                        </ul>
                    </div>
                </div>

                <!-- Right Side - Appointment Status -->
                <div class="tracking-right">
                    <div class="appointment-status-section" id="appointmentStatusSection" style="display: none;">
                        <h3>Appointment Status</h3>
                        
                        <!-- Status Progress -->
                        <div class="status-progress" id="statusProgress">
                            <!-- Will be populated by JavaScript -->
                        </div>

                        <!-- Appointment Details -->
                        <div class="appointment-details-card" id="appointmentDetailsCard">
                            <!-- Will be populated by JavaScript -->
                        </div>

                        <!-- SMS Verification Section -->
                        <div class="sms-verification-section" id="smsVerificationSection" style="display: none;">
                            <h4>SMS Verification Required</h4>
                            <p>Please enter the 6-digit code sent to your phone:</p>
                            <div class="sms-verification-form">
                                <input type="text" id="smsVerificationCode" placeholder="Enter 6-digit code" maxlength="6">
                                <button class="btn-verify" onclick="verifySMS()">Verify</button>
                            </div>
                        </div>

                        <!-- Final Confirmation Box -->
                        <div class="final-confirmation" id="finalConfirmation" style="display: none;">
                            <!-- Will be populated by JavaScript -->
                        </div>

                        <!-- Appointment Timeline -->
                        <div class="appointment-timeline" id="appointmentTimeline">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Default message when no tracking -->
                    <div class="no-tracking-message" id="noTrackingMessage">
                        <div class="no-tracking-content">
                            <i class="fas fa-search"></i>
                            <h3>Enter Reference Number</h3>
                            <p>Please enter your appointment reference number to view your appointment status and details.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Programs and Initiatives -->
    <section class="programs-section" id="programs">
        <div class="container">
            <h2>Programs and Initiatives</h2>
            <div class="programs-grid">
                <div class="program-card">
                    <div class="program-icon">
                        <i class="fas fa-wheelchair"></i>
                    </div>
                    <h3>Mobility Assistance Program</h3>
                    <ul>
                        <li>Wheelchair provision</li>
                        <li>Mobility aids</li>
                        <li>Transportation assistance</li>
                        <li>Home accessibility modifications</li>
                    </ul>
                    <button class="btn-secondary" onclick="showProgramDetails('mobility')">Learn More</button>
                </div>
                <div class="program-card">
                    <div class="program-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Educational Support</h3>
                    <ul>
                        <li>Scholarships and grants</li>
                        <li>Learning materials</li>
                        <li>Special education programs</li>
                        <li>Inclusive education advocacy</li>
                    </ul>
                    <button class="btn-secondary" onclick="showProgramDetails('education')">Learn More</button>
                </div>
                <div class="program-card">
                    <div class="program-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <h3>Livelihood Training</h3>
                    <ul>
                        <li>Skills development workshops</li>
                        <li>Job placement assistance</li>
                        <li>Business startup support</li>
                        <li>Entrepreneurship training</li>
                    </ul>
                    <button class="btn-secondary" onclick="showProgramDetails('livelihood')">Learn More</button>
                </div>
                <div class="program-card">
                    <div class="program-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <h3>Healthcare Access</h3>
                    <ul>
                        <li>Medical assistance programs</li>
                        <li>Therapy services</li>
                        <li>Health insurance support</li>
                        <li>Specialized medical care</li>
                    </ul>
                    <button class="btn-secondary" onclick="showProgramDetails('healthcare')">Learn More</button>
                </div>
                <div class="program-card">
                    <div class="program-icon">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <h3>Assistive Technology</h3>
                    <ul>
                        <li>Hearing aids and devices</li>
                        <li>Communication tools</li>
                        <li>Computer access software</li>
                        <li>Smart home technology</li>
                    </ul>
                    <button class="btn-secondary" onclick="showProgramDetails('technology')">Learn More</button>
                </div>
                <div class="program-card">
                    <div class="program-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Community Integration</h3>
                    <ul>
                        <li>Social activities and events</li>
                        <li>Support groups</li>
                        <li>Advocacy programs</li>
                        <li>Awareness campaigns</li>
                    </ul>
                    <button class="btn-secondary" onclick="showProgramDetails('community')">Learn More</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Partner Organizations -->
    <section class="partners-section" id="organizations">
        <div class="container">
            <h2>Partner Organizations</h2>
            <p class="partners-intro">We collaborate with various government agencies and organizations to provide comprehensive support for the PWD community.</p>
            <div class="partners-grid">
                <div class="partner-logo">
                    <div class="partner-placeholder">
                        <i class="fas fa-hospital"></i>
                        <span>Department of Health</span>
                    </div>
                </div>
                <div class="partner-logo">
                    <div class="partner-placeholder">
                        <i class="fas fa-school"></i>
                        <span>Department of Education</span>
                    </div>
                </div>
                <div class="partner-logo">
                    <div class="partner-placeholder">
                        <i class="fas fa-hands-helping"></i>
                        <span>DSWD</span>
                    </div>
                </div>
                <div class="partner-logo">
                    <div class="partner-placeholder">
                        <i class="fas fa-plane"></i>
                        <span>Department of Tourism</span>
                    </div>
                </div>
                <div class="partner-logo">
                    <div class="partner-placeholder">
                        <i class="fas fa-universal-access"></i>
                        <span>NCDA</span>
                    </div>
                </div>
                <div class="partner-logo">
                    <div class="partner-placeholder">
                        <i class="fas fa-medkit"></i>
                        <span>PhilHealth</span>
                    </div>
                </div>
                <div class="partner-logo">
                    <div class="partner-placeholder">
                        <i class="fas fa-shield-alt"></i>
                        <span>SSS</span>
                    </div>
                </div>
                <div class="partner-logo">
                    <div class="partner-placeholder">
                        <i class="fas fa-building"></i>
                        <span>GSIS</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How do I book an appointment for PWD ID application?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>You can book an appointment by registering or logging into your account, then clicking "Start Application" and following the 3-step process: Fill up the form, set your preferred appointment date and time, then visit our office with all required documents.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>What documents do I need to bring for my appointment?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Please bring: Medical certificate from a licensed physician, Barangay certificate of residency, 2 recent 1x1 ID pictures, Valid government-issued ID, and Birth certificate. All documents should be original copies with photocopies.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How can I track my appointment status?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Use the "Track Your Appointment" section above and enter your appointment reference number. You'll see real-time updates on your appointment status, timeline, and next steps.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>What is the SMS verification process?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>After booking your appointment, you'll receive a 6-digit verification code via SMS. Enter this code in the tracking section to confirm your appointment. This ensures the security of your booking.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How long does it take to process a PWD ID?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Processing typically takes 7-14 business days after your appointment, depending on document verification and approval. You'll receive updates via SMS and can track progress using your reference number.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Can I reschedule my appointment?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, you can reschedule your appointment by contacting our hotline at 8888-1000 or visiting our office. Please provide your reference number when requesting a reschedule.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Feedback Section -->
    <section class="feedback-section" id="contact">
        <div class="container">
            <h2>Share Your Feedback</h2>
            <p class="feedback-intro">Your feedback helps us improve our services. Please share your experience or suggestions with us.</p>
            
            <div class="feedback-layout">
                <div class="feedback-form-container">
                    <form class="feedback-form" id="feedbackForm" onsubmit="handleFeedback(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="feedbackName">Name</label>
                                <input type="text" id="feedbackName" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="feedbackEmail">Email</label>
                                <input type="email" id="feedbackEmail" name="email" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feedbackSubject">Subject</label>
                            <input type="text" id="feedbackSubject" name="subject" required>
                        </div>
                        <div class="form-group">
                            <label for="feedbackRating">Rate Your Experience</label>
                            <div class="rating-container">
                                <div class="star-rating">
                                    <input type="radio" id="star5" name="rating" value="5">
                                    <label for="star5" title="Excellent"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star4" name="rating" value="4">
                                    <label for="star4" title="Good"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star3" name="rating" value="3">
                                    <label for="star3" title="Average"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star2" name="rating" value="2">
                                    <label for="star2" title="Poor"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star1" name="rating" value="1">
                                    <label for="star1" title="Very Poor"><i class="fas fa-star"></i></label>
                                </div>
                                <span class="rating-text">Click to rate</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feedbackMessage">Message</label>
                            <textarea id="feedbackMessage" name="message" rows="5" required placeholder="Please share your feedback, suggestions, or concerns..."></textarea>
                        </div>
                        <button type="submit" class="btn-primary">Submit Feedback</button>
                    </form>
                </div>
                
                <div class="feedback-info">
                    <div class="contact-card">
                        <h3><i class="fas fa-phone"></i> Contact Information</h3>
                        <div class="contact-details">
                            <p><i class="fas fa-phone"></i> Hotline: 8888-1000</p>
                            <p><i class="fas fa-envelope"></i> Email: info@pwd.gov.ph</p>
                            <p><i class="fas fa-map-marker-alt"></i> Address: 123 Government Center, Manila</p>
                            <p><i class="fas fa-clock"></i> Office Hours: Mon-Fri, 8:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                    
                    <div class="feedback-stats">
                        <h3><i class="fas fa-chart-bar"></i> Service Statistics</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-number">15,000+</span>
                                <span class="stat-label">PWD IDs Issued</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">98%</span>
                                <span class="stat-label">Customer Satisfaction</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">24/7</span>
                                <span class="stat-label">Online Support</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">50+</span>
                                <span class="stat-label">Partner Organizations</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="about">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Description</h3>
                    <p>The PWD Portal empowers Persons with Disabilities by providing easy access to essential services, benefits, and support programs. Our mission is to create an inclusive society where PWDs can participate fully in community life.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Mission</h3>
                    <p>To uphold the rights of Persons with Disabilities by improving their quality of life through inclusive programs, accessible services, and equal opportunities for full participation in society.</p>
                </div>
                <div class="footer-section">
                    <h3>Vision</h3>
                    <p>A society where Persons with Disabilities are valued, empowered, and fully integrated into all aspects of community life with dignity, respect, and equal opportunities.</p>
                </div>
                <div class="footer-section">
                    <h3>Legal Bases</h3>
                    <ul>
                        <li><strong>RA 7277</strong> - Magna Carta for Persons with Disability</li>
                        <li><strong>RA 9442</strong> - Amending RA 7277</li>
                        <li><strong>RA 10070</strong> - Implementation of the PWD ID System</li>
                        <li><strong>RA 11228</strong> - Mandatory PhilHealth Coverage for PWDs</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-contact">
                    <h3>We are always happy to assist you</h3>
                    <div class="contact-details">
                        <p><i class="fas fa-envelope"></i> info@pwd.gov.ph</p>
                        <p><i class="fas fa-phone"></i> Hotline: 8888-1000</p>
                        <p><i class="fas fa-map-marker-alt"></i> 123 Government Center, Manila</p>
                        <p><i class="fas fa-globe"></i> www.pwd.gov.ph</p>
                    </div>
                </div>
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#services">Book Appointment</a></li>
                        <li><a href="#programs">Programs</a></li>
                        <li><a href="#requirements">Requirements</a></li>
                        <li><a href="#contact">Contact Us</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2024 PWD Portal. All rights reserved. | Developed with ❤️ for the PWD Community</p>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Login to Your Account</h2>
                <span class="close" onclick="closeModal('loginModal')">&times;</span>
            </div>
            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label for="loginEmail">Email</label>
                    <input type="email" id="loginEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" name="password" required>
                </div>
                <button type="submit" class="btn-primary">Login</button>
                <p class="modal-footer-text">
                    Don't have an account? <a href="#" onclick="switchToRegister()">Register here</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Your Account</h2>
                <span class="close" onclick="closeModal('registerModal')">&times;</span>
            </div>
            <form id="registerForm" onsubmit="handleRegister(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <input type="text" id="firstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <input type="text" id="lastName" name="last_name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="registerEmail">Email</label>
                    <input type="email" id="registerEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required placeholder="+63 912 345 6789">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="registerPassword">Password</label>
                        <input type="password" id="registerPassword" name="password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirm_password" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="dateOfBirth">Date of Birth</label>
                    <input type="date" id="dateOfBirth" name="date_of_birth">
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" placeholder="Complete address..."></textarea>
                </div>
                <div class="form-group">
                    <label for="disabilityType">Type of Disability</label>
                    <select id="disabilityType" name="disability_type">
                        <option value="">Select disability type</option>
                        <option value="Physical Disability">Physical Disability</option>
                        <option value="Visual Impairment">Visual Impairment</option>
                        <option value="Hearing Impairment">Hearing Impairment</option>
                        <option value="Intellectual Disability">Intellectual Disability</option>
                        <option value="Psychosocial Disability">Psychosocial Disability</option>
                        <option value="Multiple Disabilities">Multiple Disabilities</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="emergencyContactName">Emergency Contact Name</label>
                        <input type="text" id="emergencyContactName" name="emergency_contact_name" placeholder="Full name">
                    </div>
                    <div class="form-group">
                        <label for="emergencyContactPhone">Emergency Contact Phone</label>
                        <input type="tel" id="emergencyContactPhone" name="emergency_contact_phone" placeholder="+63 912 345 6789">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Register</button>
                <p class="modal-footer-text">
                    Already have an account? <a href="#" onclick="switchToLogin()">Login here</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Appointment Booking Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Book Your Appointment</h2>
                <span class="close" onclick="closeModal('appointmentModal')">&times;</span>
            </div>
            <form id="appointmentForm" onsubmit="handleAppointmentBooking(event)">
                <div class="form-group">
                    <label for="appointmentType">Appointment Type</label>
                    <select id="appointmentType" name="appointment_type" required>
                        <option value="">Select appointment type</option>
                        <option value="new_application">New PWD ID Application</option>
                        <option value="renewal">PWD ID Renewal</option>
                        <option value="replacement">PWD ID Replacement</option>
                        <option value="update">Update Information</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="preferredDate">Preferred Date</label>
                        <input type="date" id="preferredDate" name="preferred_date" required>
                    </div>
                    <div class="form-group">
                        <label for="preferredTime">Preferred Time</label>
                        <select id="preferredTime" name="preferred_time" required>
                            <option value="">Select time</option>
                            <option value="09:00:00">9:00 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="14:00:00">2:00 PM</option>
                            <option value="15:00:00">3:00 PM</option>
                            <option value="16:00:00">4:00 PM</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="appointmentNotes">Additional Notes (Optional)</label>
                    <textarea id="appointmentNotes" name="notes" rows="3" placeholder="Any special requirements or notes..."></textarea>
                </div>
                <button type="submit" class="btn-primary">Book Appointment</button>
            </form>
        </div>
    </div>

    <!-- Program Details Modal -->
    <div id="programModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="programModalTitle">Program Details</h2>
                <span class="close" onclick="closeModal('programModal')">&times;</span>
            </div>
            <div id="programModalContent" class="program-modal-body">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Pass PHP data to JavaScript
        window.currentUser = <?php echo json_encode($current_user); ?>;
    </script>
</body>
</html>
