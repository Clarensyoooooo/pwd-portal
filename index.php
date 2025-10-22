<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWD Portal - Empowering the PWD Community</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Styles for input validation feedback */
        .form-group {
            position: relative;
            /* Adjust bottom margin to make space for the error message */
            margin-bottom: 2rem; 
        }
        .input-error-message {
            color: #ef4444; /* Red-500 */
            font-size: 0.875rem; /* 14px */
            font-weight: 500;
            position: absolute;
            bottom: -1.5rem; /* Position it below the input field */
            left: 0;
            width: 100%;
        }
        input.input-error, select.input-error, textarea.input-error {
            border-color: #ef4444 !important; /* Make error border prominent */
            box-shadow: 0 0 0 1px #ef4444;
        }
    </style>
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

    

<!-- Programs Application Section -->
 <section class="programs-section" id="programs">
    <div class="container">
        <h2>Apply for Our Programs</h2>
        <p class="section-intro">Join our programs and get the support you need. Fill up the form below to apply.</p>
        
        <div class="programs-apply-container">
            <div class="programs-list-container">
                <h3>Available Programs</h3>
                <div id="availableProgramsList" class="programs-list">
                    <div class="loading">Loading programs...</div>
                </div>
            </div>
            
            <div class="program-application-form-container">
                <div id="programApplicationForm" style="display: none;">
                    <h3 id="selectedProgramTitle">Program Application</h3>
                    <p id="selectedProgramDescription" style="margin-bottom: 20px; color: #555; line-height: 1.6;"></p>
                    <form id="applyProgramForm" onsubmit="handleProgramApplication(event)">
                        <input type="hidden" id="selectedProgramId" name="program_id">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="appFirstName">First Name *</label>
                                <input type="text" id="appFirstName" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="appLastName">Last Name *</label>
                                <input type="text" id="appLastName" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="appEmail">Email *</label>
                                <input type="email" id="appEmail" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="appPhone">Phone *</label>
                                <input type="tel" id="appPhone" name="phone" required placeholder="+63 912 345 6789">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="appDOB">Date of Birth *</label>
                                <input type="date" id="appDOB" name="date_of_birth" required>
                            </div>
                            <div class="form-group">
                                <label for="appDisability">Disability Type *</label>
                                <select id="appDisability" name="disability_type" required>
                                    <option value="">Select disability type</option>
                                    <option value="Physical Disability">Physical Disability</option>
                                    <option value="Visual Impairment">Visual Impairment</option>
                                    <option value="Hearing Impairment">Hearing Impairment</option>
                                    <option value="Intellectual Disability">Intellectual Disability</option>
                                    <option value="Psychosocial Disability">Psychosocial Disability</option>
                                    <option value="Multiple Disabilities">Multiple Disabilities</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="appAddress">Complete Address *</label>
                            <textarea id="appAddress" name="address" rows="2" required placeholder="Street, Barangay, City, Province"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="appAdditionalInfo">Additional Information</label>
                            <textarea id="appAdditionalInfo" name="additional_info" rows="3" placeholder="Any additional information or special requirements..."></textarea>
                        </div>
                        
                        <div id="programRequirements" class="program-requirements-info"></div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="clearProgramSelection()">Cancel</button>
                            <button type="submit" class="btn-primary">Submit Application</button>
                        </div>
                    </form>
                </div>
                
                <div id="programSelectionPrompt" class="program-selection-prompt">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Select a Program</h3>
                    <p>Choose a program from the list to view details and apply.</p>
                </div>
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
                        <p>Click "Start Application" and follow the process: Accept terms, indicate if you already have a PWD ID, then proceed with either renewal/update or new application.</p>
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
    <input type="hidden" name="action" value="submit_feedback">
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
                <p>&copy; 2025 PWD Portal. All rights reserved. | Developed with ❤️ for the PWD Community</p>
            </div>
        </div>
    </footer>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Terms and Conditions & Privacy Policy</h2>
                <span class="close" onclick="closeModal('termsModal')">&times;</span>
            </div>
            <div class="terms-content">
                <div class="terms-section">
                    <h3><i class="fas fa-file-contract"></i> Terms and Conditions</h3>
                    <div class="terms-scroll">
                        <h4>1. Acceptance of Terms</h4>
                        <p>By using the PWD Portal and booking an appointment, you agree to comply with and be bound by these Terms and Conditions. If you do not agree with any part of these terms, please do not use our services.</p>
                        
                        <h4>2. Appointment Booking</h4>
                        <p>- You must provide accurate and complete information when booking an appointment.</p>
                        <p>- Each email address can only have one active (pending or confirmed) appointment at a time.</p>
                        <p>- You will receive an SMS verification code to confirm your appointment.</p>
                        <p>- Appointments are subject to availability and confirmation.</p>
                        
                        <h4>3. Required Documents</h4>
                        <p>You must bring all required documents to your scheduled appointment, including:</p>
                        <p>- Medical certificate from a licensed physician</p>
                        <p>- Barangay certificate of residency</p>
                        <p>- 2 recent 1x1 ID pictures</p>
                        <p>- Valid government-issued ID</p>
                        <p>- Birth certificate</p>
                        
                        <h4>4. Cancellation and Rescheduling</h4>
                        <p>- You may cancel or reschedule your appointment by contacting our hotline at 8888-1000.</p>
                        <p>- We reserve the right to cancel appointments if required documents are not presented.</p>
                        <p>- Failure to attend your scheduled appointment may result in restrictions on future bookings.</p>
                        
                        <h4>5. Service Limitations</h4>
                        <p>- The PWD Portal is intended for legitimate PWD ID applications only.</p>
                        <p>- We reserve the right to verify the authenticity of all submitted information.</p>
                        <p>- Processing time may vary depending on the completeness of documents and verification requirements.</p>
                    </div>
                </div>
                
                <div class="terms-section">
                    <h3><i class="fas fa-user-shield"></i> Privacy Policy</h3>
                    <div class="terms-scroll">
                        <h4>1. Information We Collect</h4>
                        <p>We collect the following personal information:</p>
                        <p>- Full name, date of birth, and contact information (email, phone)</p>
                        <p>- Address and emergency contact details</p>
                        <p>- Disability type and medical information</p>
                        <p>- Appointment preferences and notes</p>
                        
                        <h4>2. How We Use Your Information</h4>
                        <p>Your information is used to:</p>
                        <p>- Process your PWD ID application</p>
                        <p>- Schedule and manage appointments</p>
                        <p>- Send appointment reminders and updates via SMS</p>
                        <p>- Maintain records as required by law (RA 7277, RA 9442, RA 10070)</p>
                        <p>- Improve our services and user experience</p>
                        
                        <h4>3. Data Protection</h4>
                        <p>- We implement appropriate security measures to protect your personal information.</p>
                        <p>- Your data is stored securely and accessed only by authorized personnel.</p>
                        <p>- We comply with the Data Privacy Act of 2012 (RA 10173).</p>
                        <p>- Your medical and disability information is treated with strict confidentiality.</p>
                        
                        <h4>4. Data Sharing</h4>
                        <p>We may share your information with:</p>
                        <p>- Government agencies as required by law for PWD ID processing</p>
                        <p>- Healthcare providers for verification purposes</p>
                        <p>- Partner organizations involved in PWD programs and services</p>
                        <p>- We will never sell your personal information to third parties.</p>
                        
                        <h4>5. Your Rights</h4>
                        <p>You have the right to:</p>
                        <p>- Access and review your personal information</p>
                        <p>- Request corrections to inaccurate information</p>
                        <p>- Object to the processing of your data</p>
                        <p>- Request deletion of your data (subject to legal requirements)</p>
                        <p>- File a complaint with the National Privacy Commission</p>
                        
                        <h4>6. Contact for Privacy Concerns</h4>
                        <p>For privacy-related questions or concerns, contact us at:</p>
                        <p>Email: privacy@pwd.gov.ph</p>
                        <p>Hotline: 8888-1000</p>
                        <p>Data Protection Officer: dpo@pwd.gov.ph</p>
                    </div>
                </div>
                
                <div class="terms-acceptance">
                    <label class="checkbox-container">
                        <input type="checkbox" id="termsCheckbox">
                        <span class="checkmark"></span>
                        <span class="checkbox-label">I have read and agree to the Terms and Conditions and Privacy Policy</span>
                    </label>
                    <button class="btn-primary btn-block" onclick="acceptTerms()">Accept and Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PWD Status Check Modal -->
    <div id="pwdStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Do you already have a PWD ID?</h2>
                <span class="close" onclick="closeModal('pwdStatusModal')">&times;</span>
            </div>
            <div class="pwd-status-content">
                <p>Please select the option that applies to you:</p>
                <div class="status-options">
                    <button class="status-option-btn" onclick="selectPWDStatus(true)">
                        <i class="fas fa-id-card"></i>
                        <span>Yes, I have a PWD ID</span>
                        <small>For renewal or updating information</small>
                    </button>
                    <button class="status-option-btn" onclick="selectPWDStatus(false)">
                        <i class="fas fa-user-plus"></i>
                        <span>No, I'm a new applicant</span>
                        <small>For first-time PWD ID application</small>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Existing PWD Email Verification Modal -->
    <div id="existingPWDModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Verify Your Email</h2>
                <span class="close" onclick="closeModal('existingPWDModal')">&times;</span>
            </div>
            <form id="existingPWDForm" onsubmit="verifyExistingPWD(event)">
                <div class="form-group">
                    <label for="existingEmail">Email Address *</label>
                    <input type="email" id="existingEmail" name="email" required placeholder="Enter your registered email">
                    <small>Enter the email address you used when you first registered for your PWD ID</small>
                </div>
                <button type="submit" class="btn-primary btn-block">Verify Email</button>
            </form>
        </div>
    </div>

    <!-- Renewal/Update Appointment Modal -->
    <div id="renewalUpdateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Book Renewal/Update Appointment</h2>
                <span class="close" onclick="closeModal('renewalUpdateModal')">&times;</span>
            </div>
            <form id="renewalUpdateForm" onsubmit="handleRenewalUpdate(event)">
                <div class="form-section">
                    <h3>Your Information</h3>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" id="renewalName"readonly>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="renewalEmail" name="email" readonly>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" id="renewalPhone" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Appointment Purpose</h3>
                    <div class="form-group">
                        <label for="renewalType">Purpose *</label>
                        <select id="renewalType" name="appointment_type" required>
                            <option value="">Select purpose</option>
                            <option value="renewal">PWD ID Renewal</option>
                            <option value="update">Update Information</option>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Schedule</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="renewalPreferredDate">Preferred Date *</label>
                            <input type="date" id="renewalPreferredDate" name="preferred_date" required>
                        </div>
                        <div class="form-group">
                            <label for="renewalTime">Preferred Time *</label>
                            <select id="renewalTime" name="preferred_time" required>
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
                        <label for="renewalNotes">Additional Notes (Optional)</label>
                        <textarea id="renewalNotes" name="notes" rows="3" placeholder="Any special requirements or concerns..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-primary btn-block">Book Appointment</button>
            </form>
        </div>
    </div>

    <!-- New Applicant Progress Form Modal -->
    <div id="newApplicantModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>New PWD ID Application</h2>
                <span class="close" onclick="closeModal('newApplicantModal')">&times;</span>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressBar"></div>
                </div>
                <div class="progress-steps">
                    <div class="step-indicator active" id="stepIndicator1">
                        <span class="step-number">1</span>
                        <span class="step-label">Personal Info</span>
                    </div>
                    <div class="step-indicator" id="stepIndicator2">
                        <span class="step-number">2</span>
                        <span class="step-label">Disability Info</span>
                    </div>
                    <div class="step-indicator" id="stepIndicator3">
                        <span class="step-number">3</span>
                        <span class="step-label">Emergency Contact</span>
                    </div>
                    <div class="step-indicator" id="stepIndicator4">
                        <span class="step-number">4</span>
                        <span class="step-label">Schedule</span>
                    </div>
                </div>
                <p class="progress-text" id="progressText">Step 1 of 4</p>
            </div>

            <form id="newApplicantForm" onsubmit="handleNewApplication(event)">
                <!-- Step 1: Personal Information -->
                <div id="step1" class="form-step">
                    <h3>Personal Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="newApplicantFirstName">First Name *</label>
                            <input type="text" id="newApplicantFirstName" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="newApplicantLastName">Last Name *</label>
                            <input type="text" id="newApplicantLastName" name="last_name" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="newApplicantEmail">Email *</label>
                            <input type="email" id="newApplicantEmail" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="newApplicantPhone">Phone Number *</label>
                            <input type="tel" id="newApplicantPhone" name="phone" required placeholder="+63 912 345 6789">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="newApplicantDOB">Date of Birth *</label>
                        <input type="date" id="newApplicantDOB" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label for="newApplicantAddress">Complete Address *</label>
                        <textarea id="newApplicantAddress" name="address" rows="2" required placeholder="Street, Barangay, City/Municipality, Province"></textarea>
                    </div>
                </div>

                <!-- Step 2: Disability Information -->
                <div id="step2" class="form-step" style="display: none;">
                    <h3>Disability Information</h3>
                    <div class="form-group">
                        <label for="newDisabilityType">Type of Disability *</label>
                        <select id="newDisabilityType" name="disability_type" required>
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
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p><strong>Required Documents:</strong> Please prepare the following documents for your appointment:</p>
                        <ul>
                            <li>Medical certificate from a licensed physician</li>
                            <li>Barangay certificate of residency</li>
                            <li>2 recent 1x1 ID pictures</li>
                            <li>Valid government-issued ID</li>
                            <li>Birth certificate</li>
                        </ul>
                    </div>
                </div>

                <!-- Step 3: Emergency Contact -->
                <div id="step3" class="form-step" style="display: none;">
                    <h3>Emergency Contact Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="newEmergencyName">Emergency Contact Name</label>
                            <input type="text" id="newEmergencyName" name="emergency_contact_name" placeholder="Full name">
                        </div>
                        <div class="form-group">
                            <label for="newEmergencyPhone">Emergency Contact Phone</label>
                            <input type="tel" id="newEmergencyPhone" name="emergency_contact_phone" placeholder="+63 912 345 6789">
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-user-shield"></i>
                        <p>Emergency contact information is optional but highly recommended for your safety and convenience.</p>
                    </div>
                </div>

                <!-- Step 4: Schedule Appointment -->
                <div id="step4" class="form-step" style="display: none;">
                    <h3>Schedule Your Appointment</h3>
                    <div class="form-group">
                        <label>Appointment Type</label>
                        <input type="text" value="New Application" readonly class="readonly-input">
                        <input type="hidden" name="appointment_type" value="new_application">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="newApplicantPreferredDate">Preferred Date *</label>
                            <input type="date" id="newApplicantPreferredDate" name="preferred_date" required>
                        </div>
                        <div class="form-group">
                            <label for="newApplicantPreferredTime">Preferred Time *</label>
                            <select id="newApplicantPreferredTime" name="preferred_time" required>
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
                        <label for="newApplicantNotes">Additional Notes (Optional)</label>
                        <textarea id="newApplicantNotes" name="notes" rows="3" placeholder="Any special requirements or notes..."></textarea>
                    </div>
                    <div class="info-box success">
                        <i class="fas fa-check-circle"></i>
                        <p><strong>Almost done!</strong> Review your information and click "Submit Application" to complete your booking. You will receive an SMS verification code to confirm your appointment.</p>
                    </div>
                </div>

                <!-- Form Navigation -->
                <div class="form-navigation">
                    <button type="button" class="btn-secondary" id="prevStepBtn" onclick="prevStep()" style="display: none;">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" class="btn-primary" id="nextStepBtn" onclick="nextStep()">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn-primary" id="submitNewApplicationBtn" style="display: none;">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
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
</body>
</html>
