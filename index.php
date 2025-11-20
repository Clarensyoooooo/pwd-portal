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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>

    /* --- 1. GRAYSCALE MODE (Applied to Wrapper Only) --- */
.grayscale-mode {
    filter: grayscale(100%);
    /* No transform or position changes here! */
}

/* --- 2. HIGH CONTRAST MODE (Smarter Version) --- */

/* A. The Body Background (So the edges of the screen turn black) */
body.body-high-contrast {
    background-color: #000 !important;
}

/* B. The Content Wrapper (Where the text lives) */
.high-contrast-mode {
    background-color: #000 !important;
    color: #fff !important;
}

/* Specific High Contrast Elements */
.high-contrast-mode h1, 
.high-contrast-mode h2, 
.high-contrast-mode h3, 
.high-contrast-mode p, 
.high-contrast-mode li,
.high-contrast-mode span {
    color: #fff !important;
}

/* Links - Bright Yellow for visibility */
.high-contrast-mode a {
    color: #ffff00 !important;
    text-decoration: underline !important;
}

/* Buttons - Black with Yellow/White Borders */
.high-contrast-mode button, 
.high-contrast-mode .btn-primary, 
.high-contrast-mode .btn-secondary {
    background-color: #000 !important;
    color: #ffff00 !important;
    border: 2px solid #ffff00 !important; /* Essential for visibility */
    box-shadow: none !important;
}

/* Inputs - Must have borders to be seen on black */
.high-contrast-mode input, 
.high-contrast-mode textarea, 
.high-contrast-mode select {
    background-color: #000 !important;
    color: #fff !important;
    border: 2px solid #fff !important;
}

/* Cards/Sections - Remove white backgrounds and add borders */
.high-contrast-mode .card, 
.high-contrast-mode .step, 
.high-contrast-mode .modal-content,
.high-contrast-mode header,
.high-contrast-mode footer {
    background-color: #000 !important;
    border: 1px solid #fff !important; /* Defines the edges */
    box-shadow: none !important;
}

/* Fix Icons */
.high-contrast-mode i {
    color: #ffff00 !important;
}

/* --- 3. ENSURE WIDGET IS VISIBLE IN HIGH CONTRAST --- */
/* When body is in high contrast, update the widget style too */
body.body-high-contrast #accessBtn {
    background-color: #000 !important;
    border: 3px solid #ffff00 !important;
    color: #ffff00 !important;
}
body.body-high-contrast .access-menu {
    background-color: #000 !important;
    border: 2px solid #ffff00 !important;
}
body.body-high-contrast .access-menu button {
    border: 1px solid #fff !important;
    color: #fff !important;
}

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

        /* --- ADDED FOR SMOOTH & ACCURATE SCROLLING --- */
        html {
            scroll-behavior: smooth;
            /* ADJUST THIS VALUE: 
              This should be the height of your fixed navigation bar.
              I've set it to 100px as a placeholder. 
              You may need to make it larger or smaller to match perfectly.
            */
            scroll-padding-top: 100px; 
        }

        /* --- ADDED FOR PRIVACY AGREEMENT CHECKBOX --- */
        .form-group.privacy-agreement {
            margin-bottom: 1.5rem;
            padding-top: 0.5rem; /* Add some space above */
        }
        .privacy-agreement .checkbox-label {
            font-size: 0.9rem;
            color: #333;
            line-height: 1.4;
        }
        .privacy-agreement .checkbox-label a {
            color: #007bff; /* Primary link color */
            text-decoration: underline;
            font-weight: 500;
            cursor: pointer;
        }
        .privacy-agreement .checkbox-label a:hover {
            color: #0056b3;
        }

        /* --- ADDED FOR MULTI-STEP PROGRESS BAR --- */
        .progress-steps {
            display: flex; 
            align-items: flex-start;
            width: 100%;
        }

        .step-indicator .step-label {
            font-size: 0.8rem;
            color: #888;
            font-weight: 500;
            
            /* --- NEW FIXES BELOW --- */
            
            /* Set a fixed line-height */
            line-height: 1.2rem; 
            
            /* Force the label area to be tall enough for 2 lines.
               This makes all labels align perfectly, even the wrapped one. */
            min-height: 2.4rem; 
        }
        
        .step-indicator .step-number {
            height: 30px;
            width: 30px;
            line-height: 28px; /* Adjust for border */
            border-radius: 50%;
            background-color: #eee;
            border: 1px solid #ccc;
            color: #888;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-bottom: 5px; /* Space between number and label */
        }

        .step-indicator .step-label {
            font-size: 0.8rem;
            color: #888;
            font-weight: 500;
        }

        /* --- Active Step Styles --- */
        .step-indicator.active .step-number {
            background-color: #007bff; /* Primary color */
            border-color: #007bff;
            color: #fff;
        }
        .step-indicator.active .step-label {
            color: #007bff; /* Primary color */
            font-weight: 700;
        }

        /* --- Style the text below the bar --- */
        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #555;
            width: 100%;
        }
        
        /* Generic Checkbox Styles (re-used from your modal) */
        .checkbox-container {
            display: flex;
            align-items: center;
            position: relative;
            padding-left: 30px; /* Space for the checkmark */
            margin-bottom: 12px;
            cursor: pointer;
            font-size: 1rem;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        .checkbox-container input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 22px;
            width: 22px;
            background-color: #eee;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .checkbox-container:hover input ~ .checkmark {
            background-color: #ccc;
        }
        .checkbox-container input:checked ~ .checkmark {
            background-color: #007bff; /* Primary color */
            border-color: #007bff;
        }
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        .checkbox-container input:checked ~ .checkmark:after {
            display: block;
        }
        .checkbox-container .checkmark:after {
            left: 8px;
            top: 4px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 3px 3px 0;
            -webkit-transform: rotate(45deg);
            -ms-transform: rotate(45deg);
            transform: rotate(45deg);
        }

        /* --- ADDED FOR PRIVACY CHECKBOX ERROR --- */
        .privacy-error-message {
            color: #ef4444; /* Red-500 */
            font-size: 0.875rem; /* 14px */
            font-weight: 500;
            display: none; /* Hidden by default */
            margin-top: 8px; /* Space below the checkbox label */
        }

        /* Accessibility Widget - Final Fixed Version */
.accessibility-widget {
    position: fixed;
    bottom: 20px;    /* Keeps it 20px from the bottom */
    right: 20px;     /* Keeps it 20px from the right */
    z-index: 999999; /* Super high priority so it stays on top of everything */
    display: block;  /* Ensures it renders */
}

/* The Toggle Button (Icon) */
#accessBtn {
    background-color: #0056b3;
    color: white;
    border: 2px solid white; /* Adds a nice pop */
    border-radius: 50%;
    width: 60px;
    height: 60px;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
}

#accessBtn:hover {
    transform: scale(1.1); /* Slight zoom effect on hover */
    background-color: #004494;
}

/* The Menu Box */
.access-menu {
    display: none;
    position: absolute;
    bottom: 75px; /* Opens slightly above the button */
    right: 0;
    background: white;
    border: 1px solid #ccc;
    padding: 15px;
    border-radius: 12px;
    width: 220px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    z-index: 1000000; /* Even higher than the widget button */
}

/* Show the menu when toggled */
.access-menu.show {
    display: block;
    animation: fadeIn 0.3s ease;
}

/* Simple Fade In Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
    </style>
</head>
<body>
       <div class="accessibility-widget">
        <button id="accessBtn" aria-label="Accessibility Options" onclick="toggleAccessMenu()">
            <i class="fas fa-universal-access"></i>
        </button>
        <div class="access-menu" id="accessMenu">
            <h4>Accessibility Tools</h4>
            <button onclick="resizeText(1)">A+ Increase Text</button>
            <button onclick="resizeText(-1)">A- Decrease Text</button>
            <button onclick="toggleHighContrast()">High Contrast</button>
            <button onclick="toggleGrayscale()">Grayscale</button>
            <button onclick="resetAccess()">Reset</button>
        </div>
    </div>

    <div id="main-content-wrapper">
    <header class="header">
        <div class="top-bar">
            <div class="container">
                <div class="contact-info">
                    <span><i class="fas fa-phone"></i> Hotline: (043) 784 8022</span>
                    <span><i class="fas fa-envelope"></i> pdaostotomas2025@gmail.com</span>
                </div>
            </div>
        </div>
        <nav class="navbar">
            <div class="container">
                <div class="nav-brand">
                    <a href="#home" style="text-decoration: none; color: inherit; display: flex; align-items: center;">
                        <img src="https://scontent.fpag2-1.fna.fbcdn.net/v/t39.30808-6/517703539_122107885826930992_4646467853699166888_n.jpg?_nc_cat=106&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=Yw_DNQ5vGH0Q7kNvwEsWhNq&_nc_oc=Adljo4KPlVphPW3G9CnkQRb0Z3aaHcgX8zQE_y5qK-hgqPkLh1WT0kh3sy3hKfhtwk0&_nc_zt=23&_nc_ht=scontent.fpag2-1.fna&_nc_gid=SUDbPVtLvofOOZMoOsPBgQ&oh=00_AfcFrGTtmNYVpAXXRwjD0P-qQF5oNRJlcrJJQJYCdYM4eQ&oe=690A91D2" alt="PWD Logo" class="logo">
                        <span class="brand-text">PDAO Helps</span>
                    </a>
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

    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>Serbisyong Alalay para sa PWD Community</h1>
                    <p>Madali at maasahang serbisyo para sa ating PWDs!
Mag-set ng appointment para sa iyong PWD ID application, i-track ang status ng request mo, at alamin ang iba’t ibang programa na tutulong sa’yo para mas mapaganda ang kalidad ng iyong buhay.</p>
                </div>
                <div class="hero-sidebar">
        <iframe src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fprofile.php%3Fid%3D61577929784498%26ref%3Dembed_page%23&tabs=timeline&width=340&height=500&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&appId" width="340" height="500" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
        </div>
    </section>

    <section class="application-process" id="services">
        <div class="container">
            <h2>PWD ID Application Process</h2>
            <div class="process-steps">
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Fill Up Form</h3>
                    <p>I-fill out ang PWD ID application form gamit ang iyong personal information at disability details.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Set Appointment</h3>
                    <p>Pumili ng convenient na date at time para makapunta sa aming office para sa document verification.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3>Visit Office</h3>
                    <p>Dalhin ang iyong requirements at completed form para sa verification at processing.</p>
                </div>
            </div>
            <div class="process-action">
                <button class="btn-primary" onclick="startApplication()">Start Application</button>
            </div>
        </div>
    </section>

    <section class="track-appointment" id="requirements">
        <div class="container">
            <div class="track-header">
                <h2>Track Your Appointment</h2>
                <p>Check the status of your PWD appointment</p>
            </div>
            
            <div class="tracking-layout">
                <div class="tracking-left">
                    <div class="tracking-input-section">
                        <h3>Appointment Reference Number</h3>
                        <div class="tracking-form">
                            <input type="text" placeholder="Enter your reference number" id="trackingNumber">
                            <button class="btn-track" onclick="trackAppointment()">Track</button>
                        </div>
                        <p class="tracking-help">Ilagay ang iyong reference number upang masuri ang estado ng iyong appointment.</p>
                    </div>

                    <div class="requirements-checklist">
                        <h3><i class="fas fa-clipboard-list"></i> PWD ID Requirements</h3>
                        <ul class="requirements-list">
                            <li><i class="fas fa-circle"></i> 4pcs recent 1x1 ID pictures</li>
                            <li><i class="fas fa-circle"></i> Birth certificate </li>
                            <li><i class="fas fa-circle"></i> Certificate of Disability</li>
                            <li><i class="fas fa-circle"></i> Blood typing</li>
                            <li><i class="fas fa-circle"></i> Family Baseline</li>
                            <li><i class="fas fa-circle"></i> Application Form</li>
                            <strong>For Guardians/Representatives:</strong>
                   <li> <p>Kung ikaw ay nag-a-apply para sa ibang tao, maaaring kailanganin mong magpasa ng patunay ng guardianship o isang notarized authorization letter.</p>
                            </li>
                            <li></i><a href="assets/forms/PWD-Application-Form-Non-Apparent.pdf" class="btn-primary" download></i> Download Application Form</a></li>
                        </ul>

                        
                    </div>
                </div>

                <div class="tracking-right">
                    <div class="appointment-status-section" id="appointmentStatusSection" style="display: none;">
                        <h3>Appointment Status</h3>
                        
                        <div class="status-progress" id="statusProgress">
                            </div>

                        <div class="appointment-details-card" id="appointmentDetailsCard">
                            </div>

                        <div class="sms-verification-section" id="smsVerificationSection" style="display: none;">
                            <h4>Email Verification Required</h4>
                            <p>Please enter the 6-digit code sent to your email:</p>
                            <div class="sms-verification-form">
                                <input type="text" id="smsVerificationCode" placeholder="Enter 6-digit code" maxlength="6">
                                <button class="btn-verify" onclick="verifySMS()">Verify</button>
                            </div>
                        </div>

                        <div class="final-confirmation" id="finalConfirmation" style="display: none;">
                            </div>

                        <div class="appointment-timeline" id="appointmentTimeline">
                            </div>
                    </div>

                    <div class="no-tracking-message" id="noTrackingMessage">
                        <div class="no-tracking-content">
                            <i class="fas fa-search"></i>
                            <h3>Enter Reference Number</h3>
                            <p>Mangyaring ilagay ang iyong appointment reference number upang makita ang estado at mga detalye ng iyong appointment.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    

<section class="programs-section" id="programs">
    <div class="container">
        <h2>Apply for Our Programs</h2>
        <p class="section-intro">Sumali sa aming mga programa at makatanggap ng kinakailangang suporta. Punan ang form sa ibaba upang mag-apply.</p>
        
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
    <input type="tel" 
           id="appPhone" 
           name="phone" 
           required 
           placeholder="09XXXXXXXXX" 
           maxlength="11"
           pattern="09[0-9]{9}"
           title="Please enter a valid 11-digit number starting with 09.">
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
                        
                        <div class="form-group privacy-agreement">
        <label class="checkbox-container">
            <input type="checkbox" id="programPrivacyCheckbox" name="privacy_policy" value="agreed" >
            <span class="checkmark"></span>
            <span class="checkbox-label">
                I have read and agree to the 
                <a href="javascript:void(0)" onclick="openModal('dataPrivacyDisplayModal')">Data Privacy Statement</a>.
            </span>
        </label>
        <div id="programPrivacyError" class="privacy-error-message">
                                You must agree to the Data Privacy Statement to continue.
                            </div>
    </div>
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="clearProgramSelection()">Cancel</button>

                            <div style="opacity: 0; position: absolute; left: -5000px;" aria-hidden="true">
        <label for="program_website">Website</label>
        <input type="text" id="program_website" name="website_url" tabindex="-1" autocomplete="off">
    </div>

                            <button type="submit" class="btn-primary">Submit Application</button>
                        </div>
                    </form>
                </div>
                
                <div id="programSelectionPrompt" class="program-selection-prompt">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Select a Program</h3>
                    <p>Pumili ng programa mula sa listahan upang makita ang mga detalye at makapag-apply.</p>
                </div>
            </div>
        </div>
    </div>
</section>

    <section class="partners-section" id="organizations">
    <div class="container">
        <h2>Related Agencies</h2>
        <p class="partners-intro">
            Nakikipagtulungan kami sa mga pangunahing ahensya ng pamahalaan upang matiyak ang masusing serbisyo at suporta para sa PWD community.
        </p>
        
        <div class="partners-grid">
            <div class="partner-logo">
                <a href="https://www.doh.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/33/Department_of_Health_%28DOH%29_PHL.svg/2048px-Department_of_Health_%28DOH%29_PHL.svg.png" alt="Department of Health Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://www.deped.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Seal_of_the_Department_of_Education_of_the_Philippines.png/1024px-Seal_of_the_Department_of_Education_of_the_Philippines.png" alt="Department of Education Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://www.dswd.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/7/76/Seal_of_the_Department_of_Social_Welfare_and_Development.svg/1105px-Seal_of_the_Department_of_Social_Welfare_and_Development.svg.png" alt="DSWD Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://www.tourism.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e8/Department_of_Tourism_%28DOT%29.svg/2048px-Department_of_Tourism_%28DOT%29.svg.png" alt="Department of Tourism Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://ncda.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://ncda.gov.ph/wp-content/uploads/2023/02/NCDA-Logo-with-bigger-white-background.fw_.png" alt="NCDA Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://www.philhealth.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://images.seeklogo.com/logo-png/33/2/philhealth-logo-png_seeklogo-338250.png" alt="PhilHealth Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://www.sss.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://images.seeklogo.com/logo-png/32/1/republic-of-the-philippines-social-security-system-logo-png_seeklogo-326505.png" alt="SSS Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://www.gsis.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/1f/Government_Service_Insurance_System_%28Philippines%29_%28logo%29.svg/1633px-Government_Service_Insurance_System_%28Philippines%29_%28logo%29.svg.png" alt="GSIS Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://www.tesda.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://tesdamimaropa.com/wp-content/uploads/2016/09/cropped-Tesda-Logo.png" alt="TESDA Logo">
                </a>
            </div>
            <div class="partner-logo">
                <a href="https://www.dole.gov.ph" target="_blank" rel="noopener noreferrer">
                    <img src="https://batangmalaya.ph/wp-content/uploads/2024/01/RGB-removebg.png" alt="DOLE Logo">
                </a>
            </div>
        </div>
    </div>
</section>


    <section class="faq-section">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-list">

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Ano-ano ang mga requirements para sa PWD ID?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Ito ang kumpletong checklist ng mga kailangan mong ihanda para sa iyong application:</p>
                        <ul style="list-style-type: disc; margin-left: 20px; padding-left: 1rem;">
                            <li><strong>4pcs (1x1) ID pictures</strong> (na may pangalan at pirma o thumb mark sa likod)</li>
                            <li><strong>Birth Certificate</strong> (galing sa PSA o local Civil Registry)</li>
                            <li><strong>Certificate of Disability</strong> (galing sa Competent Medical Practitioner)</li>
                            <li><strong>Blood Typing</strong> (galing sa Laboratory Clinic)</li>
                            <li><strong>Family Baseline</strong> (makukuha sa inyong Barangay Hall)</li>
                            <li><strong>Application form</strong> (makukuha sa CSWD Office)</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Saan ko kukunin ang mga requirements na ito?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Narito kung saan mo pwedeng makuha ang bawat dokumento:</p>
                        <ul style="list-style-type: disc; margin-left: 20px; padding-left: 1rem;">
                            <li><strong>Sa Barangay Hall:</strong> Family Baseline</li>
                            <li><strong>Sa CSWD Office:</strong> Application Form</li>
                            <li><strong>Sa Laboratory Clinic:</strong> Blood Typing</li>
                            <li><strong>Sa Medical Practitioner (Doktor):</strong> Certificate of Disability</li>
                            <li><strong>Sa PSA / Local Civil Registrar:</strong> Birth Certificate</li>
                            <li><strong>Sariling handa (Client):</strong> 4pcs 1x1 ID pictures</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Sino ang pwedeng ma-categorize as PWD?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Ang isang tao ay kino-consider na PWD kung sila ay may long-term physical, mental, intellectual, o sensory impairments. Kapag ito, kasama ang iba't ibang hadlang (barriers) sa paligid, ay nakakapigil sa kanilang puno at epektibong pakikilahok sa society.</p>
                        <p style="margin-top: 10px;">Ang iyong <strong>Certificate of Disability</strong> galing sa isang lisensyadong medical practitioner ang magpapatunay nito para sa iyong ID application.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Paano ako mag-book ng appointment dito sa website?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Madali lang! I-click ang <strong>"Start Application"</strong> button. Sundin ang mga steps: 1) Tanggapin ang Terms and Conditions, 2) Piliin kung may PWD ID ka na (para sa renewal) o kung bago kang applicant, at 3) Punan ang form ng iyong impormasyon at i-set ang schedule.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Paano ko i-track ang status ng appointment ko?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Gamitin ang <strong>"Track Your Appointment"</strong> section na makikita sa itaas (sa ilalim ng "PWD ID Application Process"). Ilagay mo lang ang iyong reference number na na-email sa iyo, at makikita mo na ang real-time updates sa iyong appointment.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

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
                        <div style="opacity: 0; position: absolute; left: -5000px;" aria-hidden="true">
                            <label for="website_url">Website</label>
                            <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="feedbackMessage">Message</label>
                            <textarea id="feedbackMessage" name="message" rows="5" required placeholder="Please share your feedback, suggestions, or concerns..."></textarea>
                        </div>
                        <div class="form-group privacy-agreement">
                            <label class="checkbox-container">
                                <input type="checkbox" id="feedbackPrivacyCheckbox" name="privacy_policy" value="agreed">
                                <span class="checkmark"></span>
                                <span class="checkbox-label">
                                    I have read and agree to the 
                                    <a href="javascript:void(0)" onclick="openModal('dataPrivacyDisplayModal')">Data Privacy Statement</a>.
                                </span>
                            </label>
                            <div id="feedbackPrivacyError" class="privacy-error-message">
                                You must agree to the Data Privacy Statement to continue.
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">Submit Feedback</button>
                    </form>
                </div>
                
                <div class="feedback-info">
                    <div class="contact-card">
                        <h3><i class="fas fa-phone"></i> Contact Information</h3>
                        <div class="contact-details">
                             <p><i class="fas fa-phone"></i> Hotline: (043) 784 8022</p>
                            <p><i class="fas fa-envelope"></i> Email: pdaostotomas2025@gmail.com</p>
                            <p><i class="fas fa-map-marker-alt"></i> Address: CSWD Building. Pob 1, City of Sto. Tomas</p>
                            <p><i class="fas fa-clock"></i> Office Hours: Mon-Fri, 8:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                    
                    
                </div>
            </div>
        </div>
    </section>

    <footer class="footer" id="about">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Description</h3>
                    <p>PDAO Helps empowers Persons with Disabilities by providing easy access to essential services, benefits, and support programs. Our mission is to create an inclusive society where PWDs can participate fully in community life.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/profile.php?id=61577929784498&ref=embed_page" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
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
                        <p><i class="fas fa-envelope"></i> pdaostotomas2025@gmail.com</p>
                        <p><i class="fas fa-phone"></i> Hotline: (043) 784 8022</p>
                        <p><i class="fas fa-map-marker-alt"></i> CSWD Building. Pob 1, City of Sto. Tomas</p>
                        <p><i class="fas fa-globe"></i> www.pdaohelps.online</p>
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
                <p>&copy; 2025 PDAO Helps. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div id="termsModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Data Privacy Statement</h2>
                <span class="close" onclick="closeModal('termsModal')">&times;</span>
            </div>
            <div class="terms-content">
                
                <div class="terms-scroll" style="max-height: 400px; overflow-y: auto; padding-right: 15px; margin-bottom: 20px;">
                    <p>The City Government of Sto. Tomas, Batangas values and respects your right to data privacy. We are committed to protecting the personal data we collect and process in accordance with Republic Act No. 10173 – the Data Privacy Act of 2012 (DPA), its Implementing Rules and Regulations (IRR), and relevant issuances of the National Privacy Commission (NPC).</p>
                
                    <h3 style="margin-top: 20px; margin-bottom: 10px;">Purpose of Processing</h3>
                    <p>Any and all processing of personal data by the City Government of Sto. Tomas shall be carried out only for legitimate purposes and/or in compliance with legal obligations under applicable laws, while implementing appropriate organizational, physical, and technical security measures to protect the confidentiality, integrity, and availability of personal data.</p>
                    <p>We may process your personal data in order to:</p>
                    <ul style="list-style-type: decimal; margin-left: 20px; padding-left: 1rem; line-height: 1.6;">
                        <li>Comply with laws, regulations, and government issuances.</li>
                        <li>Respond to requests from public authorities or other government offices.</li>
                        <li>Comply with valid legal processes issued by competent authorities.</li>
                        <li>Protect the rights, safety, property, and privacy of the City Government of Sto. Tomas, its employees, or the general public.</li>
                        <li>Enable the LGU to pursue remedies or minimize damages in case of legal claims.</li>
                        <li>Respond to emergencies affecting public safety or welfare.</li>
                        <li>Ensure compliance with internal policies, government procedures, and standards.</li>
                    </ul>

                    <h3 style="margin-top: 20px; margin-bottom: 10px;">Data Retention</h3>
                    <p>Personal data shall be retained only for as long as necessary to fulfill the declared, specific, and legitimate purpose, or until the processing relevant to such purpose has been completed. Once no longer needed, data shall be securely disposed of or anonymized.</p>

                    <h3 style="margin-top: 20px; margin-bottom: 10px;">Data Breach and Incident Response</h3>
                    <p>In case of a personal data breach, the City Government of Sto. Tomas shall:</p>
                    <ul style="list-style-type: decimal; margin-left: 20px; padding-left: 1rem; line-height: 1.6;">
                        <li>Report the breach to the Data Protection Officer (DPO) within 24 hours.</li>
                        <li>Activate the Data Breach Response Team (DBRT) to assess, contain, and restore system integrity.</li>
                        <li>Notify the National Privacy Commission (NPC), the Philippine Statistics Authority (PSA) (if CBMS-related), and affected data subjects, within the period prescribed by law.</li>
                        <li>Implement corrective actions to prevent recurrence and mitigate possible harm.</li>
                    </ul>

                    <h3 style="margin-top: 20px; margin-bottom: 10px;">Rights of Data Subjects</h3>
                    <p>As provided under the Data Privacy Act, you have the right to:</p>
                    <ul style="list-style-type: decimal; margin-left: 20px; padding-left: 1rem; line-height: 1.6;">
                        <li>Be informed of how your personal data is collected, processed, and protected.</li>
                        <li>Access your personal data under the custody of the City Government of Sto. Tomas.</li>
                        <li>Object to processing, or withdraw your consent (subject to limitations under the law).</li>
                        <li>Request correction of inaccurate or outdated personal data.</li>
                        <li>Request deletion or blocking of personal data that is no longer necessary, unlawfully obtained, or processed without your consent.</li>
                        <li>File a complaint and claim compensation in case of proven damages due to mishandling, misuse, malicious disclosure, or improper disposal of your personal data.</li>
                    </ul>
                </div>
                <div class="terms-acceptance">
                    <label class="checkbox-container">
                        <input type="checkbox" id="termsCheckbox">
                        <span class="checkmark"></span>
                        <span class="checkbox-label">I have read and agree to the Data Privacy Statement</span>
                    </label>
                    <button class="btn-primary btn-block" onclick="acceptTerms()">Accept and Continue</button>
                </div>
            </div>
        </div>
    </div>

    <div id="dataPrivacyDisplayModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Data Privacy Statement</h2>
                <span class="close" onclick="closeModal('dataPrivacyDisplayModal')">&times;</span>
            </div>
            <div class="terms-content">
                
                <div class="terms-scroll" style="max-height: 400px; overflow-y: auto; padding-right: 15px; margin-bottom: 20px;">
                    <p>The City Government of Sto. Tomas, Batangas values and respects your right to data privacy. We are committed to protecting the personal data we collect and process in accordance with Republic Act No. 10173 – the Data Privacy Act of 2012 (DPA), its Implementing Rules and Regulations (IRR), and relevant issuances of the National Privacy Commission (NPC).</p>
                
                    <h3 style="margin-top: 20px; margin-bottom: 10px;">Purpose of Processing</h3>
                    <p>Any and all processing of personal data by the City Government of Sto. Tomas shall be carried out only for legitimate purposes and/or in compliance with legal obligations under applicable laws, while implementing appropriate organizational, physical, and technical security measures to protect the confidentiality, integrity, and availability of personal data.</p>
                    <p>We may process your personal data in order to:</p>
                    <ul style="list-style-type: decimal; margin-left: 20px; padding-left: 1rem; line-height: 1.6;">
                        <li>Comply with laws, regulations, and government issuances.</li>
                        <li>Respond to requests from public authorities or other government offices.</li>
                        <li>Comply with valid legal processes issued by competent authorities.</li>
                        <li>Protect the rights, safety, property, and privacy of the City Government of Sto. Tomas, its employees, or the general public.</li>
                        <li>Enable the LGU to pursue remedies or minimize damages in case of legal claims.</li>
                        <li>Respond to emergencies affecting public safety or welfare.</li>
                        <li>Ensure compliance with internal policies, government procedures, and standards.</li>
                    </ul>

                    <h3 style="margin-top: 20px; margin-bottom: 10px;">Data Retention</h3>
                    <p>Personal data shall be retained only for as long as necessary to fulfill the declared, specific, and legitimate purpose, or until the processing relevant to such purpose has been completed. Once no longer needed, data shall be securely disposed of or anonymized.</p>

                    <h3 style="margin-top: 20px; margin-bottom: 10px;">Data Breach and Incident Response</h3>
                    <p>In case of a personal data breach, the City Government of Sto. Tomas shall:</p>
                    <ul style="list-style-type: decimal; margin-left: 20px; padding-left: 1rem; line-height: 1.6;">
                        <li>Report the breach to the Data Protection Officer (DPO) within 24 hours.</li>
                        <li>Activate the Data Breach Response Team (DBRT) to assess, contain, and restore system integrity.</li>
                        <li>Notify the National Privacy Commission (NPC), the Philippine Statistics Authority (PSA) (if CBMS-related), and affected data subjects, within the period prescribed by law.</li>
                        <li>Implement corrective actions to prevent recurrence and mitigate possible harm.</li>
                    </ul>

                    <h3 style="margin-top: 20px; margin-bottom: 10px;">Rights of Data Subjects</h3>
                    <p>As provided under the Data Privacy Act, you have the right to:</p>
                    <ul style="list-style-type: decimal; margin-left: 20px; padding-left: 1rem; line-height: 1.6;">
                        <li>Be informed of how your personal data is collected, processed, and protected.</li>
                        <li>Access your personal data under the custody of the City Government of Sto. Tomas.</li>
                        <li>Object to processing, or withdraw your consent (subject to limitations under the law).</li>
                        <li>Request correction of inaccurate or outdated personal data.</li>
                        <li>Request deletion or blocking of personal data that is no longer necessary, unlawfully obtained, or processed without your consent.</li>
                        <li>File a complaint and claim compensation in case of proven damages due to mishandling, misuse, malicious disclosure, or improper disposal of your personal data.</li>
                    </ul>
                </div>
                </div>
        </div>
    </div>

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
                <div style="opacity: 0; position: absolute; left: -5000px;" aria-hidden="true">
        <label for="new_app_website">Website</label>
        <input type="text" id="new_app_website" name="website_url" tabindex="-1" autocomplete="off">
    </div>
                <button type="submit" class="btn-primary btn-block">Book Appointment</button>
            </form>
        </div>
    </div>

    <div id="newApplicantModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>New PWD ID Application</h2>
                <span class="close" onclick="closeModal('newApplicantModal')">&times;</span>
            </div>
            
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
                    <div class="step-indicator" id="stepIndicator5">
    <span class="step-number">5</span>
    <span class="step-label">Uploads</span>
</div>

            </div>
            <p class="progress-text" id="progressText">Step 1 of 5</p>

            <form id="newApplicantForm" onsubmit="handleNewApplication(event)" enctype="multipart/form-data">
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
    <input type="tel" 
           id="newApplicantPhone" 
           name="phone" 
           required 
           placeholder="09XXXXXXXXX" 
           maxlength="11"
           pattern="09[0-9]{9}"
           title="Please enter a valid 11-digit number starting with 09.">
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
                            <li>4pcs recent 1x1 ID pictures</li>
                            <li>Birth certificate</li>
                            <li>Certificate of Disability</li>
                            <li>Blood Typing</li>
                            <li>Family Baseline</li>
                            <li>Birth certificate</li>
                            <li>Application form</li>
                        </ul>
                    </div>
                </div>

                <div id="step3" class="form-step" style="display: none;">
                    <h3>Emergency Contact Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="newEmergencyName">Emergency Contact Name</label>
                            <input type="text" id="newEmergencyName" name="emergency_contact_name" placeholder="Full name">
                        </div>
                        <div class="form-group">
    <label for="newEmergencyPhone">Emergency Contact Phone</label>
    <input type="tel" 
           id="newEmergencyPhone" 
           name="emergency_contact_phone" 
           placeholder="09XXXXXXXXX" 
           maxlength="11"
           pattern="09[0-9]{9}"
           title="Please enter a valid 11-digit number starting with 09 (if provided).">
</div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-user-shield"></i>
                        <p>Emergency contact information is optional but highly recommended for your safety and convenience.</p>
                    </div>
                </div>

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
</div> <div id="step5" class="form-step" style="display: none;">
    <h3>Upload Requirements</h3>
    <p>Please upload all the required documents. (PDF, JPG, or PNG files only).</p>

    <div class="form-group">
        <label for="doc_id_picture">1. 1x1 ID Pictures *</label>
        <input type="file" id="doc_id_picture" name="doc_id_picture" accept=".jpg,.jpeg,.png,.pdf" required>
        <small>Required. 2 (two) "1x1" recent ID pictures. (JPG, PNG, PDF)</small>
    </div>

    <div class="form-group">
        <label for="doc_birth_certificate">2. Birth Certificate (Xerox Copy) *</label>
        <input type="file" id="doc_birth_certificate" name="doc_birth_certificate" accept=".jpg,.jpeg,.png,.pdf" required>
        <small>Required. (JPG, PNG, PDF)</small>
    </div>

    <div class="form-group">
        <label for="doc_medical_certificate">3. Certificate of Disability *</label>
        <input type="file" id="doc_medical_certificate" name="doc_medical_certificate" accept=".jpg,.jpeg,.png,.pdf" required>
        <small>Required. Must be original copy. (JPG, PNG, PDF)</small>
    </div>

    <div class="form-group">
        <label for="doc_voters_certificate">4. Voter's Certification (2025) *</label>
        <input type="file" id="doc_voters_certificate" name="doc_voters_certificate" accept=".jpg,.jpeg,.png,.pdf" required>
        <small>Required. Xerox copy. (JPG, PNG, PDF)</small>
    </div>

    <div class="form-group">
        <label for="doc_registration_form">5. PWD Registration Form *</label>
        <input type="file" id="doc_registration_form" name="doc_registration_form" accept=".jpg,.jpeg,.png,.pdf" required>
        <small>Required. You can download the form from the "Requirements" section. (JPG, PNG, PDF)</small>
    </div>

    <div class="info-box success">
        <i class="fas fa-check-circle"></i>
        <p><strong>Almost done!</strong> Review your information and click "Submit Application" to complete your booking. You will receive an email verification code to confirm your appointment.</p>
    </div>
</div> <div class="form-navigation">
    <button type="button" class="btn-secondary" id="prevStepBtn" onclick="prevStep()" style="display: none;">
        <i class="fas fa-arrow-left"></i> Previous
    </button>

                    <div style="opacity: 0; position: absolute; left: -5000px;" aria-hidden="true">
        <label for="new_app_website">Website</label>
        <input type="text" id="new_app_website" name="website_url" tabindex="-1" autocomplete="off">
    </div> 

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

    <div id="programModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="programModalTitle">Program Details</h2>
                <span class="close" onclick="closeModal('programModal')">&times;</span>
            </div>
            <div id="programModalContent" class="program-modal-body">
                </div>
        </div>
    </div>

 

    <script src="script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</body>
</html>