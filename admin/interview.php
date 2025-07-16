<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'appointments.interview');

$admin = getCurrentAdmin($pdo);
$interview_id = $_GET['id'] ?? '';

if (empty($interview_id)) {
    header('Location: appointments.php');
    exit();
}

// Get interview details
$stmt = $pdo->prepare("
    SELECT ir.*, a.*, u.first_name, u.last_name, u.phone, u.email, u.date_of_birth, u.address, u.disability_type,
           au.full_name as interviewer_name
    FROM interview_records ir
    JOIN appointments a ON ir.appointment_id = a.id
    JOIN users u ON a.user_id = u.id
    LEFT JOIN admin_users au ON ir.interviewer_id = au.id
    WHERE ir.id = ?
");
$stmt->execute([$interview_id]);
$interview = $stmt->fetch();

if (!$interview) {
    header('Location: appointments.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_interview') {
        handleUpdateInterview();
    } elseif ($action === 'create_pwd_record') {
        handleCreatePWDRecord();
    }
}

function handleUpdateInterview() {
    global $pdo, $interview_id;
    
    $interview_notes = $_POST['interview_notes'] ?? '';
    $documents_verified = $_POST['documents_verified'] ?? [];
    $eligibility_assessment = $_POST['eligibility_assessment'] ?? '';
    $recommendations = $_POST['recommendations'] ?? '';
    $status = $_POST['status'] ?? 'in_progress';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE interview_records 
            SET interview_notes = ?, documents_verified = ?, eligibility_assessment = ?, 
                recommendations = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $interview_notes,
            json_encode($documents_verified),
            $eligibility_assessment,
            $recommendations,
            $status,
            $interview_id
        ]);
        
        logAdminActivity($pdo, 'edit', 'interview', 'interview_record', $interview_id);
        
        $success_message = 'Interview updated successfully!';
        
    } catch (PDOException $e) {
        $error_message = 'Failed to update interview: ' . $e->getMessage();
    }
}

function handleCreatePWDRecord() {
    global $pdo, $interview;
    requirePermission($pdo, 'records.create');
    
    try {
        $pdo->beginTransaction();
        
        // Generate PWD ID
        $pwd_id = generatePWDId($pdo);
        
        // Create PWD record
        $stmt = $pdo->prepare("
            INSERT INTO pwd_records (
                appointment_id, pwd_id_number, first_name, middle_name, last_name, suffix,
                date_of_birth, place_of_birth, gender, civil_status,
                address_line1, address_line2, barangay, city_municipality, province, postal_code,
                phone_number, email_address, latitude, longitude,
                disability_type, disability_cause, disability_description, assistive_device,
                medical_condition, medication, attending_physician,
                emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, emergency_contact_address,
                employment_status, occupation, employer_name, monthly_income,
                sss_number, philhealth_number, tin_number,
                status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $interview['appointment_id'],
            $pwd_id,
            $_POST['first_name'],
            $_POST['middle_name'] ?? null,
            $_POST['last_name'],
            $_POST['suffix'] ?? null,
            $_POST['date_of_birth'],
            $_POST['place_of_birth'] ?? null,
            $_POST['gender'],
            $_POST['civil_status'],
            $_POST['address_line1'],
            $_POST['address_line2'] ?? null,
            $_POST['barangay'],
            $_POST['city_municipality'],
            $_POST['province'],
            $_POST['postal_code'] ?? null,
            $_POST['phone_number'],
            $_POST['email_address'],
            !empty($_POST['latitude']) ? $_POST['latitude'] : null,
            !empty($_POST['longitude']) ? $_POST['longitude'] : null,
            $_POST['disability_type'],
            $_POST['disability_cause'] ?? null,
            $_POST['disability_description'] ?? null,
            $_POST['assistive_device'] ?? null,
            $_POST['medical_condition'] ?? null,
            $_POST['medication'] ?? null,
            $_POST['attending_physician'] ?? null,
            $_POST['emergency_contact_name'] ?? null,
            $_POST['emergency_contact_relationship'] ?? null,
            $_POST['emergency_contact_phone'] ?? null,
            $_POST['emergency_contact_address'] ?? null,
            $_POST['employment_status'] ?? 'Unemployed',
            $_POST['occupation'] ?? null,
            $_POST['employer_name'] ?? null,
            !empty($_POST['monthly_income']) ? $_POST['monthly_income'] : null,
            $_POST['sss_number'] ?? null,
            $_POST['philhealth_number'] ?? null,
            $_POST['tin_number'] ?? null,
            'draft',
            $_SESSION['admin_user_id']
        ]);
        
        $record_id = $pdo->lastInsertId();
        
        // Update interview status
        $stmt = $pdo->prepare("UPDATE interview_records SET status = 'completed' WHERE id = ?");
        $stmt->execute([$interview['id']]);
        
        // Update appointment status
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $stmt->execute([$interview['appointment_id']]);
        
        $pdo->commit();
        
        logAdminActivity($pdo, 'create', 'records', 'pwd_record', $record_id, ['pwd_id' => $pwd_id]);
        
        header("Location: records.php?id={$record_id}&created=1");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Failed to create PWD record: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-comments"></i> Interview Session</h1>
                <p>Conducting interview for <?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></p>
            </div>
            <div class="page-actions">
                <a href="appointments.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Interview Progress -->
        <div class="interview-progress">
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-number">1</div>
                    <div class="step-label">Interview Started</div>
                </div>
                <div class="step <?php echo $interview['status'] === 'completed' ? 'completed' : 'active'; ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">Document Verification</div>
                </div>
                <div class="step <?php echo $interview['status'] === 'completed' ? 'active' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-label">PWD Record Creation</div>
                </div>
            </div>
        </div>
        
        <!-- Interview Tabs -->
        <div class="interview-container">
            <div class="interview-tabs">
                <button class="tab-btn active" onclick="switchTab('applicant-info')">
                    <i class="fas fa-user"></i> Applicant Info
                </button>
                <button class="tab-btn" onclick="switchTab('interview-notes')">
                    <i class="fas fa-clipboard-list"></i> Interview Notes
                </button>
                <button class="tab-btn" onclick="switchTab('pwd-record')">
                    <i class="fas fa-id-card"></i> PWD Record
                </button>
            </div>
            
            <!-- Applicant Information Tab -->
            <div id="applicant-info" class="tab-content active">
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        <div class="info-details">
                            <div class="detail-row">
                                <span class="label">Full Name:</span>
                                <span class="value"><?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Date of Birth:</span>
                                <span class="value"><?php echo $interview['date_of_birth'] ? date('F j, Y', strtotime($interview['date_of_birth'])) : 'Not provided'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Email:</span>
                                <span class="value"><?php echo htmlspecialchars($interview['email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Phone:</span>
                                <span class="value"><?php echo htmlspecialchars($interview['phone']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Address:</span>
                                <span class="value"><?php echo htmlspecialchars($interview['address'] ?? 'Not provided'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Disability Type:</span>
                                <span class="value"><?php echo htmlspecialchars($interview['disability_type'] ?? 'Not specified'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-calendar-check"></i> Appointment Details</h3>
                        <div class="info-details">
                            <div class="detail-row">
                                <span class="label">Reference Number:</span>
                                <span class="value"><?php echo htmlspecialchars($interview['reference_number']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Appointment Type:</span>
                                <span class="value"><?php echo ucwords(str_replace('_', ' ', $interview['appointment_type'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Scheduled Date:</span>
                                <span class="value"><?php echo date('F j, Y', strtotime($interview['preferred_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Scheduled Time:</span>
                                <span class="value"><?php echo date('g:i A', strtotime($interview['preferred_time'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Interview Date:</span>
                                <span class="value"><?php echo date('F j, Y g:i A', strtotime($interview['interview_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Interviewer:</span>
                                <span class="value"><?php echo htmlspecialchars($interview['interviewer_name']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Interview Notes Tab -->
            <div id="interview-notes" class="tab-content">
                <form method="POST" class="interview-form">
                    <input type="hidden" name="action" value="update_interview">
                    
                    <div class="form-section">
                        <h3><i class="fas fa-clipboard-check"></i> Document Verification</h3>
                        <div class="document-checklist">
                            <?php 
                            $verified_docs = json_decode($interview['documents_verified'] ?? '[]', true);
                            $required_docs = [
                                'medical_certificate' => 'Medical Certificate',
                                'barangay_certificate' => 'Barangay Certificate',
                                'id_pictures' => '2x2 ID Pictures',
                                'valid_id' => 'Valid Government ID',
                                'birth_certificate' => 'Birth Certificate'
                            ];
                            ?>
                            
                            <?php foreach ($required_docs as $key => $label): ?>
                                <div class="document-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="documents_verified[]" value="<?php echo $key; ?>" 
                                               <?php echo in_array($key, $verified_docs) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <?php echo $label; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-notes-medical"></i> Interview Notes</h3>
                        <textarea name="interview_notes" rows="6" placeholder="Record your observations, questions asked, and applicant responses..."><?php echo htmlspecialchars($interview['interview_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-user-check"></i> Eligibility Assessment</h3>
                        <textarea name="eligibility_assessment" rows="4" placeholder="Assess the applicant's eligibility for PWD benefits and services..."><?php echo htmlspecialchars($interview['eligibility_assessment'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-lightbulb"></i> Recommendations</h3>
                        <textarea name="recommendations" rows="4" placeholder="Provide recommendations for services, accommodations, or next steps..."><?php echo htmlspecialchars($interview['recommendations'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-flag"></i> Interview Status</h3>
                        <select name="status" required>
                            <option value="in_progress" <?php echo $interview['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $interview['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $interview['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Interview Notes
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- PWD Record Creation Tab -->
            <div id="pwd-record" class="tab-content">
                <?php if ($interview['status'] === 'completed'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Interview completed! PWD record has been created.
                    </div>
                <?php else: ?>
                    <form method="POST" class="pwd-record-form" id="pwdRecordForm">
                        <input type="hidden" name="action" value="create_pwd_record">
                        
                        <!-- Personal Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Personal Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" required 
                                           value="<?php echo htmlspecialchars($interview['first_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="middle_name">Middle Name</label>
                                    <input type="text" id="middle_name" name="middle_name">
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" required 
                                           value="<?php echo htmlspecialchars($interview['last_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="suffix">Suffix</label>
                                    <select id="suffix" name="suffix">
                                        <option value="">None</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth *</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" required 
                                           value="<?php echo $interview['date_of_birth']; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="place_of_birth">Place of Birth</label>
                                    <input type="text" id="place_of_birth" name="place_of_birth" 
                                           placeholder="City, Province">
                                </div>
                                <div class="form-group">
                                    <label for="gender">Gender *</label>
                                    <select id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="civil_status">Civil Status *</label>
                                    <select id="civil_status" name="civil_status" required>
                                        <option value="">Select Status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                        <option value="Divorced">Divorced</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Address Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="address_line1">Address Line 1 *</label>
                                    <input type="text" id="address_line1" name="address_line1" required 
                                           placeholder="House/Unit Number, Street Name"
                                           value="<?php echo htmlspecialchars($interview['address'] ?? ''); ?>">
                                </div>
                                <div class="form-group full-width">
                                    <label for="address_line2">Address Line 2</label>
                                    <input type="text" id="address_line2" name="address_line2" 
                                           placeholder="Building, Subdivision, etc.">
                                </div>
                                <div class="form-group">
                                    <label for="barangay">Barangay *</label>
                                    <input type="text" id="barangay" name="barangay" required>
                                </div>
                                <div class="form-group">
                                    <label for="city_municipality">City/Municipality *</label>
                                    <input type="text" id="city_municipality" name="city_municipality" required>
                                </div>
                                <div class="form-group">
                                    <label for="province">Province *</label>
                                    <input type="text" id="province" name="province" required>
                                </div>
                                <div class="form-group">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" 
                                           pattern="[0-9]{4}" placeholder="1234">
                                </div>
                            </div>
                            
                            <!-- Location Picker -->
                            <div class="location-section">
                                <h4><i class="fas fa-crosshairs"></i> Geographic Location (Optional)</h4>
                                <p class="help-text">Click on the map to set the exact location for GIS mapping</p>
                                <div class="location-inputs">
                                    <div class="form-group">
                                        <label for="latitude">Latitude</label>
                                        <input type="number" id="latitude" name="latitude" step="0.00000001" 
                                               placeholder="14.5995" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="longitude">Longitude</label>
                                        <input type="number" id="longitude" name="longitude" step="0.00000001" 
                                               placeholder="120.9842" readonly>
                                    </div>
                                    <button type="button" class="btn btn-outline" onclick="getCurrentLocation()">
                                        <i class="fas fa-location-arrow"></i> Use Current Location
                                    </button>
                                </div>
                                <div id="locationMap" class="location-map"></div>
                            </div>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-phone"></i> Contact Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="phone_number">Phone Number *</label>
                                    <input type="tel" id="phone_number" name="phone_number" required 
                                           value="<?php echo htmlspecialchars($interview['phone']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email_address">Email Address</label>
                                    <input type="email" id="email_address" name="email_address" 
                                           value="<?php echo htmlspecialchars($interview['email']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Disability Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-wheelchair"></i> Disability Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="disability_type">Type of Disability *</label>
                                    <select id="disability_type" name="disability_type" required>
                                        <option value="">Select Disability Type</option>
                                        <option value="Physical Disability">Physical Disability</option>
                                        <option value="Visual Impairment">Visual Impairment</option>
                                        <option value="Hearing Impairment">Hearing Impairment</option>
                                        <option value="Intellectual Disability">Intellectual Disability</option>
                                        <option value="Psychosocial Disability">Psychosocial Disability</option>
                                        <option value="Multiple Disabilities">Multiple Disabilities</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="disability_cause">Cause of Disability</label>
                                    <select id="disability_cause" name="disability_cause">
                                        <option value="">Select Cause</option>
                                        <option value="Congenital">Congenital</option>
                                        <option value="Accident">Accident</option>
                                        <option value="Illness">Illness</option>
                                        <option value="Injury">Injury</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group full-width">
                                    <label for="disability_description">Disability Description</label>
                                    <textarea id="disability_description" name="disability_description" rows="3" 
                                              placeholder="Detailed description of the disability..."></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="assistive_device">Assistive Devices Used</label>
                                    <input type="text" id="assistive_device" name="assistive_device" 
                                           placeholder="Wheelchair, hearing aid, white cane, etc.">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Medical Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-stethoscope"></i> Medical Information</h3>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="medical_condition">Medical Condition</label>
                                    <textarea id="medical_condition" name="medical_condition" rows="3" 
                                              placeholder="Current medical conditions and diagnoses..."></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="medication">Current Medications</label>
                                    <textarea id="medication" name="medication" rows="2" 
                                              placeholder="List current medications and dosages..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="attending_physician">Attending Physician</label>
                                    <input type="text" id="attending_physician" name="attending_physician" 
                                           placeholder="Dr. Juan Dela Cruz">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Emergency Contact -->
                        <div class="form-section">
                            <h3><i class="fas fa-phone-alt"></i> Emergency Contact</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="emergency_contact_name">Contact Name</label>
                                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" 
                                           placeholder="Full name of emergency contact">
                                </div>
                                <div class="form-group">
                                    <label for="emergency_contact_relationship">Relationship</label>
                                    <select id="emergency_contact_relationship" name="emergency_contact_relationship">
                                        <option value="">Select Relationship</option>
                                        <option value="Spouse">Spouse</option>
                                        <option value="Parent">Parent</option>
                                        <option value="Child">Child</option>
                                        <option value="Sibling">Sibling</option>
                                        <option value="Relative">Relative</option>
                                        <option value="Friend">Friend</option>
                                        <option value="Guardian">Guardian</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="emergency_contact_phone">Contact Phone</label>
                                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" 
                                           placeholder="+63 912 345 6789">
                                </div>
                                <div class="form-group full-width">
                                    <label for="emergency_contact_address">Contact Address</label>
                                    <textarea id="emergency_contact_address" name="emergency_contact_address" rows="2" 
                                              placeholder="Complete address of emergency contact..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Employment Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-briefcase"></i> Employment Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="employment_status">Employment Status</label>
                                    <select id="employment_status" name="employment_status">
                                        <option value="Unemployed">Unemployed</option>
                                        <option value="Employed">Employed</option>
                                        <option value="Self-employed">Self-employed</option>
                                        <option value="Student">Student</option>
                                        <option value="Retired">Retired</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="occupation">Occupation</label>
                                    <input type="text" id="occupation" name="occupation" 
                                           placeholder="Job title or profession">
                                </div>
                                <div class="form-group">
                                    <label for="employer_name">Employer Name</label>
                                    <input type="text" id="employer_name" name="employer_name" 
                                           placeholder="Company or organization name">
                                </div>
                                <div class="form-group">
                                    <label for="monthly_income">Monthly Income (PHP)</label>
                                    <input type="number" id="monthly_income" name="monthly_income" 
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Government IDs -->
                        <div class="form-section">
                            <h3><i class="fas fa-id-card-alt"></i> Government IDs</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="sss_number">SSS Number</label>
                                    <input type="text" id="sss_number" name="sss_number" 
                                           placeholder="XX-XXXXXXX-X">
                                </div>
                                <div class="form-group">
                                    <label for="philhealth_number">PhilHealth Number</label>
                                    <input type="text" id="philhealth_number" name="philhealth_number" 
                                           placeholder="XX-XXXXXXXXX-X">
                                </div>
                                <div class="form-group">
                                    <label for="tin_number">TIN Number</label>
                                    <input type="text" id="tin_number" name="tin_number" 
                                           placeholder="XXX-XXX-XXX-XXX">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-plus-circle"></i> Create PWD Record
                            </button>
                            <button type="button" class="btn btn-outline" onclick="resetForm()">
                                <i class="fas fa-undo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
    <script>
        let map;
        let marker;
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            initializeLocationMap();
            
            // Pre-select disability type if available
            const disabilityType = '<?php echo $interview['disability_type'] ?? ''; ?>';
            if (disabilityType) {
                document.getElementById('disability_type').value = disabilityType;
            }
        });
        
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tab buttons
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
            
            // Initialize map if switching to PWD record tab
            if (tabName === 'pwd-record' && map) {
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
        }
        
        // Initialize location map
        function initializeLocationMap() {
            // Default to Manila coordinates
            const defaultLat = 14.5995;
            const defaultLng = 120.9842;
            
            map = L.map('locationMap').setView([defaultLat, defaultLng], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add click event to map
            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                // Update input fields
                document.getElementById('latitude').value = lat.toFixed(8);
                document.getElementById('longitude').value = lng.toFixed(8);
                
                // Add or update marker
                if (marker) {
                    map.removeLayer(marker);
                }
                
                marker = L.marker([lat, lng]).addTo(map)
                    .bindPopup(`Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`)
                    .openPopup();
            });
        }
        
        // Get current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Update input fields
                    document.getElementById('latitude').value = lat.toFixed(8);
                    document.getElementById('longitude').value = lng.toFixed(8);
                    
                    // Update map view
                    map.setView([lat, lng], 15);
                    
                    // Add or update marker
                    if (marker) {
                        map.removeLayer(marker);
                    }
                    
                    marker = L.marker([lat, lng]).addTo(map)
                        .bindPopup(`Current Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`)
                        .openPopup();
                        
                    showNotification('Location updated successfully!', 'success');
                }, function(error) {
                    showNotification('Unable to get current location: ' + error.message, 'error');
                });
            } else {
                showNotification('Geolocation is not supported by this browser.', 'error');
            }
        }
        
        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.getElementById('pwdRecordForm').reset();
                
                // Clear map marker
                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                }
                
                // Clear location inputs
                document.getElementById('latitude').value = '';
                document.getElementById('longitude').value = '';
                
                showNotification('Form has been reset.', 'info');
            }
        }
        
        // Form validation
        document.getElementById('pwdRecordForm')?.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error');
                    isValid = false;
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill in all required fields.', 'error');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            showLoading(submitBtn);
        });
        
        // Auto-populate address fields based on location
        function reverseGeocode(lat, lng) {
            // This would typically use a geocoding service
            // For now, we'll just show the coordinates
            console.log(`Reverse geocoding for: ${lat}, ${lng}`);
        }
    </script>
    
    <style>
        .interview-progress {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .step.completed .step-number {
            background: #10b981;
            color: white;
        }
        
        .step.active .step-number {
            background: #2c5aa0;
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: #64748b;
            text-align: center;
        }
        
        .step.completed .step-label,
        .step.active .step-label {
            color: #1e293b;
            font-weight: 500;
        }
        
        .interview-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .interview-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .tab-btn {
            flex: 1;
            padding: 16px 20px;
            background: none;
            border: none;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            background: #f8fafc;
            color: #2c5aa0;
        }
        
        .tab-btn.active {
            background: #2c5aa0;
            color: white;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .info-card h3 {
            color: #2c5aa0;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-row .label {
            font-weight: 500;
            color: #64748b;
            min-width: 120px;
        }
        
        .detail-row .value {
            color: #1e293b;
            text-align: right;
            flex: 1;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h3 {
            color: #2c5aa0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }
        
        .form-group input.error,
        .form-group select.error,
        .form-group textarea.error {
            border-color: #ef4444;
        }
        
        .document-checklist {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
        }
        
        .document-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #2c5aa0;
        }
        
        .location-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .location-section h4 {
            color: #374151;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .help-text {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 16px;
        }
        
        .location-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 12px;
            margin-bottom: 16px;
            align-items: end;
        }
        
        .location-map {
            height: 300px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .interview-tabs {
                flex-direction: column;
            }
            
            .tab-content {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .location-inputs {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
