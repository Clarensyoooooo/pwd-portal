<?php
require_once 'config.php';
requireAdminLogin($pdo);
requirePermission($pdo, 'appointments.interview');
require_once 'spatial_functions.php';

$admin = getCurrentAdmin($pdo);
$interview_id = $_GET['id'] ?? '';

// Show placeholder if no interview ID is provided
if (empty($interview_id)) {
    include 'includes/header.php';
    include 'includes/sidebar.php';
    ?>
    <main class="main-content">
        <div class="empty-state-container">
            <div class="empty-state-card">
                <div class="empty-state-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h2>No Interview Selected</h2>
                <p>Please select an interview from the appointments page to begin conducting an interview session.</p>
                <div class="empty-state-actions">
                    <a href="appointments.php" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> View Appointments
                    </a>
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>
    
    <style>
        .empty-state-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 150px);
            padding: 40px 20px;
        }
        
        .empty-state-card {
            background: white;
            border-radius: 16px;
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .empty-state-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 3rem;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .empty-state-card h2 {
            color: #1f2937;
            font-size: 2rem;
            margin-bottom: 16px;
        }
        
        .empty-state-card p {
            color: #6b7280;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .empty-state-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
    
    <script src="assets/admin.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// Get interview details
$stmt = $pdo->prepare("
    SELECT ir.*, a.*, u.first_name, u.last_name, u.phone, u.email, u.date_of_birth, u.address, u.disability_type,
           au.full_name as interviewer_name,
           pr.id as record_id, pr.pwd_id_number, pr.status as record_status
    FROM interview_records ir
    JOIN appointments a ON ir.appointment_id = a.id
    JOIN users u ON a.user_id = u.id
    LEFT JOIN admin_users au ON ir.interviewer_id = au.id
    LEFT JOIN pwd_records pr ON a.id = pr.appointment_id
    WHERE ir.id = ?
");
$stmt->execute([$interview_id]);
$interview = $stmt->fetch();

if (!$interview) {
    include 'includes/header.php';
    include 'includes/sidebar.php';
    ?>
    <main class="main-content">
        <div class="empty-state-container">
            <div class="empty-state-card">
                <div class="empty-state-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h2>Interview Not Found</h2>
                <p>The interview you're looking for doesn't exist or may have been removed.</p>
                <div class="empty-state-actions">
                    <a href="appointments.php" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> View Appointments
                    </a>
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>
    
    <style>
        .empty-state-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 150px);
            padding: 40px 20px;
        }
        
        .empty-state-card {
            background: white;
            border-radius: 16px;
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .empty-state-icon {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 3rem;
            color: white;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }
        
        .empty-state-card h2 {
            color: #1f2937;
            font-size: 2rem;
            margin-bottom: 16px;
        }
        
        .empty-state-card p {
            color: #6b7280;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .empty-state-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
    
    <script src="assets/admin.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// Get barangay list for dropdown
$barangay_stmt = $pdo->prepare("
    SELECT id, barangay_name, city_municipality, province 
    FROM barangay_boundaries 
    ORDER BY barangay_name ASC
");
$barangay_stmt->execute();
$barangays = $barangay_stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_interview') {
        handleUpdateInterview();
    } elseif ($action === 'create_pwd_record') {
        handleCreatePWDRecord();
    }  else {
        $error_message = "Invalid action: " . $action;
    }
}

$active_tab = $_POST['active_tab'] ?? 'applicant-info';

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
    global $pdo, $interview, $barangays;
    requirePermission($pdo, 'records.create');
    
    try {
        // --- 1. VALIDATION BLOCK (Personal Information) ---
        $errors = [];

        // Field: first_name (Required, Text)
        $first_name = trim($_POST['first_name'] ?? '');
        if (empty($first_name)) {
            $errors[] = 'First Name is required.';
        } elseif (strlen($first_name) > 100) {
            $errors[] = 'First Name is too long (max 100 chars).';
        }

        // Field: last_name (Required, Text)
        $last_name = trim($_POST['last_name'] ?? '');
        if (empty($last_name)) {
            $errors[] = 'Last Name is required.';
        } elseif (strlen($last_name) > 100) {
            $errors[] = 'Last Name is too long (max 100 chars).';
        }

        // Field: middle_name (Optional, Text)
        $middle_name = trim($_POST['middle_name'] ?? '');
        if (empty($middle_name)) {
            $middle_name = null; // Set to NULL if empty
        } elseif (strlen($middle_name) > 100) {
            $errors[] = 'Middle Name is too long (max 100 chars).';
        }

        // Field: suffix (Optional, Whitelist)
        $allowed_suffixes = ['', 'Jr.', 'Sr.', 'II', 'III', 'IV']; // '' is for "None"
        $suffix = trim($_POST['suffix'] ?? '');
        if (!in_array($suffix, $allowed_suffixes)) {
            $errors[] = 'Invalid Suffix selected.';
        }
        if (empty($suffix)) {
            $suffix = null; // Set to NULL if "None"
        }

        // Field: date_of_birth (Required, Date, Past)
        $dob_string = trim($_POST['date_of_birth'] ?? '');
        if (empty($dob_string)) {
            $errors[] = 'Date of Birth is required.';
        } else {
            $date_format = 'Y-m-d';
            $d = DateTime::createFromFormat($date_format, $dob_string);
            // Check if format is correct AND it's a real date (e.g., no 2025-02-31)
            if (!$d || $d->format($date_format) !== $dob_string) {
                $errors[] = 'Invalid Date of Birth format. Please use YYYY-MM-DD.';
            } elseif ($d > new DateTime()) {
                // Check if the date is in the future
                $errors[] = 'Date of Birth cannot be in the future.';
            }
        }

        // Field: place_of_birth (Optional, Text)
        $place_of_birth = trim($_POST['place_of_birth'] ?? '');
        if (empty($place_of_birth)) {
            $place_of_birth = null;
        } elseif (strlen($place_of_birth) > 255) {
            $errors[] = 'Place of Birth is too long (max 255 chars).';
        }

        // Field: gender (Required, Whitelist)
        $allowed_genders = ['Male', 'Female', 'Other'];
        $gender = trim($_POST['gender'] ?? '');
        if (empty($gender)) {
            $errors[] = 'Gender is required.';
        } elseif (!in_array($gender, $allowed_genders)) {
            $errors[] = 'Invalid Gender selected.';
        }
        
        // Field: civil_status (Required, Whitelist)
        $allowed_civil_statuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
        $civil_status = trim($_POST['civil_status'] ?? '');
        if (empty($civil_status)) {
            $errors[] = 'Civil Status is required.';
        } elseif (!in_array($civil_status, $allowed_civil_statuses)) {
            $errors[] = 'Invalid Civil Status selected.';
        }

        // --- 1b. VALIDATION BLOCK (Address Information) ---

        // Field: address_line1 (Required, Text)
        $address_line1 = trim($_POST['address_line1'] ?? '');
        if (empty($address_line1)) {
            $errors[] = 'Address Line 1 is required.';
        } elseif (strlen($address_line1) > 255) {
            $errors[] = 'Address Line 1 is too long (max 255 chars).';
        }

        // Field: address_line2 (Optional, Text)
        $address_line2 = trim($_POST['address_line2'] ?? '');
        if (empty($address_line2)) {
            $address_line2 = null;
        } elseif (strlen($address_line2) > 255) {
            $errors[] = 'Address Line 2 is too long (max 255 chars).';
        }

        // Fields: barangay_id OR barangay_manual (One is required)
        $selected_barangay_id = $_POST['barangay_id'] ?? '';
        $barangay_manual = trim($_POST['barangay_manual'] ?? '');
        $barangay_name = ''; // This will hold our final, clean value
        $barangay_info = null;

        if (!empty($selected_barangay_id)) {
            // User selected from dropdown, this is preferred.
            $barangay_stmt = $pdo->prepare("SELECT * FROM barangay_boundaries WHERE id = ?");
            $barangay_stmt->execute([$selected_barangay_id]);
            $barangay_info = $barangay_stmt->fetch();
            
            if (!$barangay_info) {
                $errors[] = 'Invalid Barangay selected.';
            } else {
                $barangay_name = $barangay_info['barangay_name'];
            }
        } elseif (!empty($barangay_manual)) {
            // User entered manually
            $barangay_name = $barangay_manual;
            if (strlen($barangay_name) > 100) {
                 $errors[] = 'Barangay (Manual Entry) is too long (max 100 chars).';
            }
        } else {
            // Neither was provided
            $errors[] = 'Barangay is required. Please select from the list or enter manually.';
        }

        // Fields: city_municipality & province (Required, Text)
        // These are auto-filled, but we still validate the final value.
        $city_municipality = $barangay_info['city_municipality'] ?? $_POST['city_municipality'] ?? 'Santo Tomas City';
        $province = $barangay_info['province'] ?? $_POST['province'] ?? 'Batangas';
        
        if (empty($city_municipality)) {
            $errors[] = 'City/Municipality is required.';
        }
        if (empty($province)) {
            $errors[] = 'Province is required.';
        }

        // Field: postal_code (Optional, 4-digit number)
        $postal_code = trim($_POST['postal_code'] ?? '');
        if (empty($postal_code)) {
            $postal_code = null;
        } elseif (!ctype_digit($postal_code) || strlen($postal_code) != 4) {
            $errors[] = 'Postal Code must be a 4-digit number.';
        }

       // Fields: latitude & longitude (NOW REQUIRED)
        $latitude_str = trim($_POST['latitude'] ?? '');
        $longitude_str = trim($_POST['longitude'] ?? '');
        $latitude = null;
        $longitude = null;

        if (empty($latitude_str) || empty($longitude_str)) {
            $errors[] = 'Geographic Location is required. Please click on the map to set the coordinates.';
        } elseif (!is_numeric($latitude_str) || !is_numeric($longitude_str)) {
            $errors[] = 'Latitude and Longitude must be valid numbers (from map).';
        } else {
            // It's not empty AND it's numeric, so set the float value
            $latitude = floatval($latitude_str);
            $longitude = floatval($longitude_str);
        }

        // --- 1c. VALIDATION BLOCK (Contact Information) ---

        // Field: phone_number (Required, Text)
        $phone_number = trim($_POST['phone_number'] ?? '');
        if (empty($phone_number)) {
            $errors[] = 'Phone Number is required.';
        } elseif (strlen($phone_number) > 20) {
            // Allows for country codes, spaces, etc.
            $errors[] = 'Phone Number is too long (max 20 chars).';
        }
        
        // Field: email_address (Optional, Email Format)
        $email_address = trim($_POST['email_address'] ?? '');
        if (empty($email_address)) {
            $email_address = null;
        } elseif (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email Address is not in a valid format.';
        } elseif (strlen($email_address) > 100) {
             $errors[] = 'Email Address is too long (max 100 chars).';
        }

        // --- 1d. VALIDATION BLOCK (Disability Information) ---

        // Field: disability_type (Required, Whitelist)
        $allowed_disability_types = [
            'Physical Disability', 'Visual Impairment', 'Hearing Impairment', 
            'Intellectual Disability', 'Psychosocial Disability', 
            'Multiple Disabilities', 'Other'
        ];
        $disability_type = trim($_POST['disability_type'] ?? '');
        if (empty($disability_type)) {
            $errors[] = 'Type of Disability is required.';
        } elseif (!in_array($disability_type, $allowed_disability_types)) {
            $errors[] = 'Invalid Type of Disability selected.';
        }

        // Field: disability_cause (Optional, Whitelist)
        $allowed_disability_causes = ['', 'Congenital', 'Accident', 'Illness', 'Injury', 'Other']; // '' for "Select Cause"
        $disability_cause = trim($_POST['disability_cause'] ?? '');
        if (!in_array($disability_cause, $allowed_disability_causes)) {
            $errors[] = 'Invalid Cause of Disability selected.';
        }
        if (empty($disability_cause)) {
            $disability_cause = null;
        }

        // Field: disability_description (Optional, Textarea)
        $disability_description = trim($_POST['disability_description'] ?? '');
        if (empty($disability_description)) {
            $disability_description = null;
        } elseif (strlen($disability_description) > 1000) { // 1000 chars for a textarea
            $errors[] = 'Disability Description is too long (max 1000 chars).';
        }

        // Field: assistive_device (Optional, Text)
        $assistive_device = trim($_POST['assistive_device'] ?? '');
        if (empty($assistive_device)) {
            $assistive_device = null;
        } elseif (strlen($assistive_device) > 255) {
            $errors[] = 'Assistive Devices field is too long (max 255 chars).';
        }

        // --- 1e. VALIDATION BLOCK (Medical Information) ---

        // Field: medical_condition (Optional, Textarea)
        $medical_condition = trim($_POST['medical_condition'] ?? '');
        if (empty($medical_condition)) {
            $medical_condition = null;
        } elseif (strlen($medical_condition) > 1000) {
            $errors[] = 'Medical Condition description is too long (max 1000 chars).';
        }

        // Field: medication (Optional, Textarea)
        $medication = trim($_POST['medication'] ?? '');
        if (empty($medication)) {
            $medication = null;
        } elseif (strlen($medication) > 1000) {
            $errors[] = 'Current Medications list is too long (max 1000 chars).';
        }

        // Field: attending_physician (Optional, Text)
        $attending_physician = trim($_POST['attending_physician'] ?? '');
        if (empty($attending_physician)) {
            $attending_physician = null;
        } elseif (strlen($attending_physician) > 100) {
            $errors[] = 'Attending Physician name is too long (max 100 chars).';
        }

        // --- 1f. VALIDATION BLOCK (Emergency Contact) ---

        // Field: emergency_contact_name (Optional, Text)
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        if (empty($emergency_contact_name)) {
            $emergency_contact_name = null;
        } elseif (strlen($emergency_contact_name) > 100) {
            $errors[] = 'Emergency Contact Name is too long (max 100 chars).';
        }

        // Field: emergency_contact_relationship (Optional, Whitelist)
        $allowed_relationships = [
            '', 'Spouse', 'Parent', 'Child', 'Sibling', 'Relative', 'Friend', 'Guardian', 'Other'
        ];
        $emergency_contact_relationship = trim($_POST['emergency_contact_relationship'] ?? '');
        if (!in_array($emergency_contact_relationship, $allowed_relationships)) {
            $errors[] = 'Invalid Emergency Contact Relationship selected.';
        }
        if (empty($emergency_contact_relationship)) {
            $emergency_contact_relationship = null;
        }
        
        // Field: emergency_contact_phone (Optional, Text)
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
        if (empty($emergency_contact_phone)) {
            $emergency_contact_phone = null;
        } elseif (strlen($emergency_contact_phone) > 20) {
            $errors[] = 'Emergency Contact Phone is too long (max 20 chars).';
        }

        // Field: emergency_contact_address (Optional, Textarea)
        $emergency_contact_address = trim($_POST['emergency_contact_address'] ?? '');
        if (empty($emergency_contact_address)) {
            $emergency_contact_address = null;
        } elseif (strlen($emergency_contact_address) > 500) {
            $errors[] = 'Emergency Contact Address is too long (max 500 chars).';
        }

        // --- 1g. VALIDATION BLOCK (Employment Information) ---

        // Field: employment_status (Optional, Whitelist, has default)
        $allowed_employment_statuses = ['Unemployed', 'Employed', 'Self-employed', 'Student', 'Retired'];
        // Default to 'Unemployed' if not provided
        $employment_status = trim($_POST['employment_status'] ?? 'Unemployed'); 
        if (!in_array($employment_status, $allowed_employment_statuses)) {
            $errors[] = 'Invalid Employment Status selected.';
        }

        // Field: occupation (Optional, Text)
        $occupation = trim($_POST['occupation'] ?? '');
        if (empty($occupation)) {
            $occupation = null;
        } elseif (strlen($occupation) > 100) {
            $errors[] = 'Occupation field is too long (max 100 chars).';
        }
        
        // Field: employer_name (Optional, Text)
        $employer_name = trim($_POST['employer_name'] ?? '');
        if (empty($employer_name)) {
            $employer_name = null;
        } elseif (strlen($employer_name) > 100) {
            $errors[] = 'Employer Name field is too long (max 100 chars).';
        }

        // Field: monthly_income (Optional, Numeric)
        $monthly_income_str = trim($_POST['monthly_income'] ?? '');
        $monthly_income = null;
        if (!empty($monthly_income_str)) {
            if (!is_numeric($monthly_income_str)) {
                $errors[] = 'Monthly Income must be a valid number.';
            } elseif (floatval($monthly_income_str) < 0) {
                 $errors[] = 'Monthly Income cannot be negative.';
            } else {
                $monthly_income = floatval($monthly_income_str);
            }
        }

        // --- 1h. VALIDATION BLOCK (Government IDs) ---

        // Field: sss_number (Optional, Text)
        $sss_number = trim($_POST['sss_number'] ?? '');
        if (empty($sss_number)) {
            $sss_number = null;
        } elseif (strlen($sss_number) > 20) {
            $errors[] = 'SSS Number is too long (max 20 chars).';
        }
        
        // Field: philhealth_number (Optional, Text)
        $philhealth_number = trim($_POST['philhealth_number'] ?? '');
        if (empty($philhealth_number)) {
            $philhealth_number = null;
        } elseif (strlen($philhealth_number) > 20) {
            $errors[] = 'PhilHealth Number is too long (max 20 chars).';
        }

        // Field: tin_number (Optional, Text)
        $tin_number = trim($_POST['tin_number'] ?? '');
        if (empty($tin_number)) {
            $tin_number = null;
        } elseif (strlen($tin_number) > 20) {
            $errors[] = 'TIN Number is too long (max 20 chars).';
        }

        // --- 2. CHECK FOR ERRORS ---
        if (!empty($errors)) {
            // If there are any errors, combine them and stop the function
            throw new Exception(implode('<br>', $errors));
        }

        // --- 3. PROCEED WITH DATABASE LOGIC ---
        // (All variables like $first_name, $gender, etc. are now clean and validated)

        $pdo->beginTransaction();
        
        // Generate PWD ID
        $year = date('Y');
        $unique_part = substr(strtoupper(bin2hex(random_bytes(4))), 0, 6); 
        $pwd_id = "PWD-{$year}-" . $unique_part;
        
        // Get barangay details
        $selected_barangay_id = $_POST['barangay_id'] ?? '';
        $barangay_info = null;
        
        if ($selected_barangay_id) {
            $barangay_stmt = $pdo->prepare("SELECT * FROM barangay_boundaries WHERE id = ?");
            $barangay_stmt->execute([$selected_barangay_id]);
            $barangay_info = $barangay_stmt->fetch();
        }
        
        $barangay_name = $barangay_info['barangay_name'] ?? $_POST['barangay_manual'] ?? '';
        $city_municipality = $barangay_info['city_municipality'] ?? 'Santo Tomas City';
        $province = $barangay_info['province'] ?? 'Batangas';
        
        // Get coordinates if provided
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        
        // Validate required fields (this is a simplified check, our $errors array is more robust)
        if (empty($first_name) || empty($last_name) || empty($barangay_name)) {
            throw new Exception('Required fields are missing (First Name, Last Name, Barangay).');
        }
        
        // Create PWD record
        $stmt = $pdo->prepare("
            INSERT INTO pwd_records (
                appointment_id, pwd_id_number, first_name, middle_name, last_name, suffix,
                date_of_birth, place_of_birth, gender, civil_status, barangay_id,
                address_line1, address_line2, barangay, city_municipality, province, postal_code,
                latitude, longitude,
                phone_number, email_address,
                disability_type, disability_cause, disability_description, assistive_device,
                medical_condition, medication, attending_physician,
                emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, emergency_contact_address,
                employment_status, occupation, employer_name, monthly_income,
                sss_number, philhealth_number, tin_number,
                status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Prepare parameters array with CLEANED variables
        $params = [
            $interview['appointment_id'],                           // 1
            $pwd_id,                                               // 2
            $first_name,                                           // 3 (Cleaned)
            $middle_name,                                          // 4 (Cleaned)
            $last_name,                                            // 5 (Cleaned)
            $suffix,                                               // 6 (Cleaned)
            $dob_string,                                           // 7 (Validated)
            $place_of_birth,                                       // 8 (Cleaned)
            $gender,                                               // 9 (Validated)
            $civil_status,                                         // 10 (Validated)
            $selected_barangay_id,                                // 11 (NEW! The link to the map)
            // --- Address Info (Validated) ---
            $address_line1,                                        // 11
            $address_line2,                                        // 12
            $barangay_name,                                        // 13
            $city_municipality,                                    // 14
            $province,                                             // 15
            $postal_code,                                          // 16
            $latitude,                                             // 17
            $longitude,                                            // 18
            // --- Contact Info (Validated) ---
            $phone_number,                                         // 19
            $email_address,                                        // 20
            // --- Disability Info (Validated) ---
            $disability_type,                                      // 21
            $disability_cause,                                     // 22
            $disability_description,                               // 23
            $assistive_device,                                     // 24
            // --- Medical Info (Validated) ---
            $medical_condition,                                    // 25
            $medication,                                           // 26
            $attending_physician,                                  // 27
            // --- Emergency Contact (Validated) ---
            $emergency_contact_name,                               // 28
            $emergency_contact_relationship,                       // 29
            $emergency_contact_phone,                              // 30
            $emergency_contact_address,                            // 31
            // --- Employment Info (Validated) ---
            $employment_status,                                    // 32
            $occupation,                                           // 33
            $employer_name,                                        // 34
            $monthly_income,                                       // 35
            // --- Government IDs (Validated) ---
            $sss_number,                                           // 36
            $philhealth_number,                                    // 37
            $tin_number,                                           // 38
            'draft',                                               // 39
            $_SESSION['admin_user_id']                             // 40
        ];
        
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to insert PWD record');
        }
        
        $record_id = $pdo->lastInsertId();
        
        // vvv ADD THIS ONE LINE vvv
        assignSinglePwdToBarangay($pdo, $record_id, $latitude, $longitude);
        // ^^^ END OF NEW LINE ^^^
        
        // Update interview status
        $stmt = $pdo->prepare("UPDATE interview_records SET status = 'completed', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$interview['id']]);
        
        // Update appointment status
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$interview['appointment_id']]);
        
        logAdminActivity($pdo, 'create', 'records', 'pwd_record', $record_id, ['pwd_id' => $pwd_id]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "PWD record created successfully! PWD ID: {$pwd_id}";
        header("Location: records.php?highlight={$record_id}");
        exit();
        
    } catch (Exception $e) {
        // ONLY roll back IF a transaction was started
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        global $error_message;
        // The $error_message will now contain our list of validation errors
        $error_message = 'Failed to create PWD record:<br><div style="text-align: left; padding-left: 20px;">' . $e->getMessage() . '</div>';
        error_log("PWD Record Creation Error: " . $e->getMessage());
        error_log("POST data: " . print_r($_POST, true));
    }
}

// vvv PASTE THIS NEW FUNCTION vvv

/**
 * Finds and assigns a single PWD record to its correct barangay
 * and updates the barangay's PWD count.
 *
 * @param PDO $pdo The database connection.
 * @param int $pwd_id The ID of the PWD record just created.
 * @param float $latitude The latitude of the new PWD.
 * @param float $longitude The longitude of the new PWD.
 * @return bool True on success, false on failure or no match.
 */
function assignSinglePwdToBarangay($pdo, $pwd_id, $latitude, $longitude) {
    // If there are no coordinates, we can't do anything.
    if (empty($latitude) || empty($longitude)) {
        return false;
    }

    try {
        // Get all barangay boundaries
        $stmt = $pdo->query("SELECT id, geojson_data FROM barangay_boundaries WHERE geojson_data IS NOT NULL");
        $barangays = $stmt->fetchAll();
        
        $assigned_barangay_id = null;
        
        // Loop through each barangay to find a match
        foreach ($barangays as $barangay) {
            // This is your magic function from spatial_functions.php
            if (isPointInBarangay($latitude, $longitude, $barangay['geojson_data'])) {
                $assigned_barangay_id = $barangay['id'];
                break; // Found a match, stop looping
            }
        }
        
        // If we found a matching barangay, update the records
        if ($assigned_barangay_id) {
            // 1. Assign the barangay to the PWD record
            $update_pwd = $pdo->prepare("UPDATE pwd_records SET barangay_id = ? WHERE id = ?");
            $update_pwd->execute([$assigned_barangay_id, $pwd_id]);
            
            // 2. Increment the count for that barangay
            $update_brgy = $pdo->prepare("UPDATE barangay_boundaries SET pwd_count = pwd_count + 1 WHERE id = ?");
            $update_brgy->execute([$assigned_barangay_id]);
            
            return true;
        }
        
        return false; // No matching barangay found
        
    } catch (Exception $e) {
        // Log the error but don't stop the whole process
        error_log("Error in assignSinglePwdToBarangay: " . $e->getMessage());
        return false;
    }
}

// ^^^ END OF NEW FUNCTION ^^^
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Session - PWD Portal Admin</title>
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
                <p>Conducting interview for <strong><?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></strong></p>
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
        <?php echo $error_message; ?> </div>
<?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
         
        <div class="interview-progress">
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-number">1</div>
                    <div class="step-label">Interview Started</div>
                </div>
                <div class="step <?php echo $interview['status'] === 'completed' || $interview['record_id'] ? 'completed' : 'active'; ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">Document Verification</div>
                </div>
                <div class="step <?php echo $interview['record_id'] ? 'completed' : ($interview['status'] === 'completed' ? 'active' : ''); ?>">
                    <div class="step-number">3</div>
                    <div class="step-label">PWD Record Creation</div>
                </div>
                <div class="step <?php echo $interview['record_status'] === 'validated' ? 'active' : ($interview['record_status'] === 'issued' ? 'completed' : ''); ?>">
                    <div class="step-number">4</div>
                    <div class="step-label">ID Processing</div>
                </div>
            </div>
        </div>
        
         
        <div class="status-overview-card">
            <div class="status-grid">
                <div class="status-item">
                    <div class="status-icon interview">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="status-content">
                        <span class="status-label">Interview Status</span>
                        <span class="status-badge status-<?php echo $interview['status']; ?>">
                            <?php echo ucfirst($interview['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon appointment">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="status-content">
                        <span class="status-label">Reference</span>
                        <span class="status-value"><?php echo htmlspecialchars($interview['reference_number']); ?></span>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon time">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="status-content">
                        <span class="status-label">Interview Date</span>
                        <span class="status-value"><?php echo date('M j, Y g:i A', strtotime($interview['interview_date'])); ?></span>
                    </div>
                </div>
                
                <?php if ($interview['record_id']): ?>
                    <div class="status-item">
                        <div class="status-icon record">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="status-content">
                            <span class="status-label">PWD Record</span>
                            <a href="records.php?highlight=<?php echo $interview['record_id']; ?>" class="status-link">
                                <?php echo htmlspecialchars($interview['pwd_id_number']); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
         
        <div class="interview-container">
            <div class="interview-tabs">
                <button class="tab-btn <?php echo $active_tab === 'applicant-info' ? 'active' : ''; ?>" onclick="switchTab('applicant-info')">
                    <i class="fas fa-user"></i> Applicant Info
                </button>
                <button class="tab-btn <?php echo $active_tab === 'interview-notes' ? 'active' : ''; ?>" onclick="switchTab('interview-notes')">
                    <i class="fas fa-clipboard-list"></i> Interview Notes
                </button>
                <?php if (!$interview['record_id']): ?>
                    <button class="tab-btn <?php echo $active_tab === 'pwd-record' ? 'active' : ''; ?>" onclick="switchTab('pwd-record')">
                        <i class="fas fa-id-card"></i> Create PWD Record
                    </button>
                <?php else: ?>
                    <button class="tab-btn <?php echo $active_tab === 'record-summary' ? 'active' : ''; ?>" onclick="switchTab('record-summary')">
                        <i class="fas fa-id-card"></i> Record Summary
                    </button>
                <?php endif; ?>
            </div>
            
             
            <div id="applicant-info" class="tab-content active">
                <div class="applicant-overview">
                    <div class="applicant-card">
                        <div class="applicant-header">
                            <div class="applicant-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="applicant-details">
                                <h3><?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></h3>
                                <p class="applicant-type"><?php echo ucwords(str_replace('_', ' ', $interview['appointment_type'])); ?> Applicant</p>
                            </div>
                        </div>
                        
                        <div class="info-grid">
                            <div class="info-section">
                                <h4><i class="fas fa-user"></i> Personal Information</h4>
                                <div class="info-list">
                                    <div class="info-item">
                                        <span class="label">Date of Birth:</span>
                                        <span class="value"><?php echo $interview['date_of_birth'] ? date('F j, Y', strtotime($interview['date_of_birth'])) : 'Not provided'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Email:</span>
                                        <span class="value"><?php echo htmlspecialchars($interview['email']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Phone:</span>
                                        <span class="value"><?php echo htmlspecialchars($interview['phone']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Address:</span>
                                        <span class="value"><?php echo htmlspecialchars($interview['address'] ?? 'Not provided'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Disability Type:</span>
                                        <span class="value">
                                            <?php if ($interview['disability_type']): ?>
                                                <span class="disability-badge"><?php echo htmlspecialchars($interview['disability_type']); ?></span>
                                            <?php else: ?>
                                                Not specified
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4><i class="fas fa-calendar-check"></i> Appointment Details</h4>
                                <div class="info-list">
                                    <div class="info-item">
                                        <span class="label">Scheduled Date:</span>
                                        <span class="value"><?php echo date('F j, Y', strtotime($interview['preferred_date'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Scheduled Time:</span>
                                        <span class="value"><?php echo date('g:i A', strtotime($interview['preferred_time'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Interviewer:</span>
                                        <span class="value"><?php echo htmlspecialchars($interview['interviewer_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Created:</span>
                                        <span class="value"><?php echo date('F j, Y g:i A', strtotime($interview['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
              
            <div id="interview-notes" class="tab-content">
                <div class="interview-form-container">
                    <form method="POST" class="interview-form">
    <input type="hidden" name="action" value="update_interview">
    <input type="hidden" name="active_tab" value="interview-notes">
    
    <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-clipboard-check"></i> Document Verification</h3>
                                <p class="section-description">Check off all documents that have been verified and are complete</p>
                            </div>
                            
                            <div class="document-checklist">
                                <?php 
                                $verified_docs = json_decode($interview['documents_verified'] ?? '[]', true);
                                $required_docs = [
                                    'medical_certificate' => ['Medical Certificate', 'Medical assessment from licensed physician'],
                                    'barangay_certificate' => ['Barangay Certificate', 'Certificate of residency from barangay'],
                                    'id_pictures' => ['2x2 ID Pictures', 'Recent passport-size photographs'],
                                    'valid_id' => ['Valid Government ID', 'Any government-issued identification'],
                                    'birth_certificate' => ['Birth Certificate', 'PSA-issued birth certificate'],
                                    'disability_assessment' => ['Disability Assessment Report', 'Professional disability evaluation'],
                                    'income_certificate' => ['Certificate of Indigency', 'If applicable for financial assistance']
                                ];
                                ?>
                                
                                <?php foreach ($required_docs as $key => $doc_info): ?>
                                    <div class="document-item <?php echo in_array($key, $verified_docs) ? 'verified' : ''; ?>">
                                        <label class="document-label">
                                            <input type="checkbox" name="documents_verified[]" value="<?php echo $key; ?>" 
                                                   <?php echo in_array($key, $verified_docs) ? 'checked' : ''; ?>>
                                            <div class="document-content">
                                                <div class="document-title"><?php echo $doc_info[0]; ?></div>
                                                <div class="document-description"><?php echo $doc_info[1]; ?></div>
                                            </div>
                                            <div class="document-status">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-notes-medical"></i> Interview Notes</h3>
                                <p class="section-description">Record observations, questions asked, and applicant responses</p>
                            </div>
                            <div class="form-group">
                                <textarea name="interview_notes" rows="6" class="form-textarea" 
                                          placeholder="Document the interview conversation, observations about the applicant's condition, and any relevant details..."><?php echo htmlspecialchars($interview['interview_notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-user-check"></i> Eligibility Assessment</h3>
                                <p class="section-description">Evaluate the applicant's eligibility for PWD benefits and services</p>
                            </div>
                            <div class="form-group">
                                <textarea name="eligibility_assessment" rows="4" class="form-textarea" 
                                          placeholder="Assess eligibility based on disability type, documentation, and legal requirements..."><?php echo htmlspecialchars($interview['eligibility_assessment'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-lightbulb"></i> Recommendations</h3>
                                <p class="section-description">Provide recommendations for services, accommodations, or next steps</p>
                            </div>
                            <div class="form-group">
                                <textarea name="recommendations" rows="4" class="form-textarea" 
                                          placeholder="Recommend appropriate services, accommodations, or referrals based on the applicant's needs..."><?php echo htmlspecialchars($interview['recommendations'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-flag"></i> Interview Status</h3>
                                <p class="section-description">Update the current status of this interview</p>
                            </div>
                            <div class="form-group">
                                <select name="status" class="form-select" required>
    <?php if ($interview['record_id']): ?>
        <option value="completed" selected>Completed</option>
    <?php else: ?>
        <option value="in_progress" <?php echo $interview['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
        <option value="cancelled" <?php echo $interview['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
    <?php endif; ?>
</select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save Interview Notes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
             
            <?php if (!$interview['record_id']): ?>
                <div id="pwd-record" class="tab-content">
                    <div class="record-form-container">
                        <div class="form-header">
                            <h3><i class="fas fa-id-card"></i> Create PWD Record</h3>
                            <p>Complete the PWD record creation process for this applicant</p>
                        </div>
                        
                        <form method="POST" class="pwd-record-form" id="pwdRecordForm">
                            <input type="hidden" name="action" value="create_pwd_record">
                            
                            <!-- Personal Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h4><i class="fas fa-user"></i> Personal Information</h4>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="first_name">First Name *</label>
                                        <input type="text" id="first_name" name="first_name" class="form-input" required 
                                               value="<?php echo htmlspecialchars($interview['first_name']); ?>">
                                    </div>
                                    <div class="form-group">
                                       <label for="middle_name">Middle Name <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="middle_name" name="middle_name" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label for="last_name">Last Name *</label>
                                        <input type="text" id="last_name" name="last_name" class="form-input" required 
                                               value="<?php echo htmlspecialchars($interview['last_name']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="suffix">Suffix <span class="optional-label">(Optional)</span></label>
                                        <select id="suffix" name="suffix" class="form-select">
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
                                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-input" required 
                                               value="<?php echo $interview['date_of_birth']; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="place_of_birth">Place of Birth</label>
                                        <input type="text" id="place_of_birth" name="place_of_birth" class="form-input" 
                                               placeholder="City, Province">
                                    </div>
                                    <div class="form-group">
                                        <label for="gender">Gender *</label>
                                        <select id="gender" name="gender" class="form-select" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                           <!-- <option value="Other">Other</option> -->
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="civil_status">Civil Status *</label>
                                        <select id="civil_status" name="civil_status" class="form-select" required>
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
                                <div class="section-header">
                                    <h4><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label for="address_line1">Address Line 1 *</label>
                                        <input type="text" id="address_line1" name="address_line1" class="form-input" required 
                                               placeholder="House/Unit Number, Street Name"
                                               value="<?php echo htmlspecialchars($interview['address'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="address_line2">Address Line 2 <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="address_line2" name="address_line2" class="form-input" 
                                               placeholder="Building, Subdivision, etc.">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="barangay_id">Barangay *</label>
                                        <select id="barangay_id" name="barangay_id" class="form-select" required onchange="updateCityProvince()">
                                            <option value="">Select Barangay</option>
                                            <?php 
                                            foreach ($barangays as $barangay): 
                                                // FIX: Check for null, empty, or "Unknown City"
                                                $city = $barangay['city_municipality'];
                                                $province = $barangay['province'];
                                                
                                                if (empty($city) || $city === 'Unknown City') {
                                                    $city = 'Santo Tomas City';
                                                }
                                                if (empty($province) || $province === 'Unknown Province') {
                                                    $province = 'Batangas';
                                                }
                                            ?>
                                                <option value="<?php echo $barangay['id']; ?>" 
                                                        data-city="<?php echo htmlspecialchars($city); ?>"
                                                        data-province="<?php echo htmlspecialchars($province); ?>">
                                                    <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" id="manual-barangay" style="display: none;">
                                        <label for="barangay_manual">Barangay (Manual Entry)</label>
                                        <input type="text" id="barangay_manual" name="barangay_manual" class="form-input" 
                                               placeholder="Enter barangay name manually">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="city_municipality">City/Municipality *</label>
                                        <input type="text" id="city_municipality" name="city_municipality" class="form-input" required 
                                               value="Santo Tomas City" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="province">Province *</label>
                                        <input type="text" id="province" name="province" class="form-input" required 
                                               value="Batangas" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="postal_code">Postal Code</label>
                                        <input type="text" id="postal_code" name="postal_code" class="form-input" 
                                               pattern="[0-9]{4}" placeholder="4234" value="4234">
                                    </div>
                                </div>
                                
                               

                            <!-- Geographic Location -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h4><i class="fas fa-map"></i> Geographic Location*</h4>
                                    <p class="section-description">Click on the map to set the exact location for GIS mapping feature</p>
                                </div>
                                
                                <div class="location-container">
                                    <div class="location-inputs">
                                       <div class="form-group">
    <label for="latitude">Latitude *</label> 
    <input type="number" id="latitude" name="latitude" class="form-input" 
           step="0.000001" placeholder="14.0000" readonly>
</div>
<div class="form-group">
    <label for="longitude">Longitude *</label>
    <input type="number" id="longitude" name="longitude" class="form-input" 
           step="0.000001" placeholder="121.0000" readonly>
</div>
                                       <div class="location-actions">
                                            <button type="button" class="btn btn-outline btn-sm" id="expandMapBtn" onclick="toggleMapExpand()">
                                                <i class="fas fa-expand-arrows-alt"></i> Expand Map
                                            </button>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="getCurrentLocation()">
                                                <i class="fas fa-crosshairs"></i> Use My Location
                                            </button>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="clearLocation()">
                                                <i class="fas fa-times"></i> Clear Location
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="map-container" id="locationContainer" required>
                                        <div id="locationMap" class="location-map"></div>
                                    </div>
                                    
                                   
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h4><i class="fas fa-phone"></i> Contact Information</h4>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="phone_number">Phone Number *</label>
                                        <input type="tel" id="phone_number" name="phone_number" class="form-input" required 
                                               value="<?php echo htmlspecialchars($interview['phone']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="email_address">Email Address* </label>
                                        <input type="email" id="email_address" name="email_address" class="form-input" required
                                               value="<?php echo htmlspecialchars($interview['email']); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Disability Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h4><i class="fas fa-wheelchair"></i> Disability Information</h4>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="disability_type">Type of Disability *</label>
                                        <select id="disability_type" name="disability_type" class="form-select" required>
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
                                        <label for="disability_cause">Cause of Disability* </label>
                                        <select id="disability_cause" name="disability_cause" class="form-select">
                                            <option value="">Select Cause</option>
                                            <option value="Congenital">Congenital</option>
                                            <option value="Accident">Accident</option>
                                            <option value="Illness">Illness</option>
                                            <option value="Injury">Injury</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="disability_description">Disability Description <span class="optional-label">(Optional)</span></label>
                                        <textarea id="disability_description" name="disability_description" rows="3" class="form-textarea" 
                                                  placeholder="Detailed description of the disability..."></textarea>
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="assistive_device">Assistive Devices Used <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="assistive_device" name="assistive_device" class="form-input" 
                                               placeholder="Wheelchair, hearing aid, white cane, etc.">
                                    </div>
                                </div>
                            </div>

                            <!-- Medical Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h4><i class="fas fa- stethoscope"></i> Medical Information</h4>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label for="medical_condition">Medical Condition <span class="optional-label">(Optional)</span></label>
                                        <textarea id="medical_condition" name="medical_condition" rows="3" class="form-textarea" 
                                                  placeholder="Current medical conditions and diagnoses..."></textarea>
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="medication">Current Medications <span class="optional-label">(Optional)</span></label>
                                        <textarea id="medication" name="medication" rows="2" class="form-textarea" 
                                                  placeholder="List current medications and dosages..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="attending_physician">Attending Physician <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="attending_physician" name="attending_physician" class="form-input" 
                                               placeholder="Dr. Juan Dela Cruz">
                                    </div>
                                </div>
                            </div>

                            <!-- Emergency Contact -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h4><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="emergency_contact_name">Contact Name <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-input" 
                                               placeholder="Full name of emergency contact">
                                    </div>
                                    <div class="form-group">
                                        <label for="emergency_contact_relationship">Relationship <span class="optional-label">(Optional)</span></label>
                                        <select id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-select">
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
                                        <label for="emergency_contact_phone">Contact Phone <span class="optional-label">(Optional)</span></label>
                                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-input" 
                                               placeholder="+63 912 345 6789">
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="emergency_contact_address">Contact Address <span class="optional-label">(Optional)</span></label>
                                        <textarea id="emergency_contact_address" name="emergency_contact_address" rows="2" class="form-textarea" 
                                                  placeholder="Complete address of emergency contact..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Employment Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h4><i class="fas fa-briefcase"></i> Employment Information</h4>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="employment_status">Employment Status <span class="optional-label">(Optional)</span></label>
                                        <select id="employment_status" name="employment_status" class="form-select">
                                            <option value="Unemployed">Unemployed</option>
                                            <option value="Employed">Employed</option>
                                            <option value="Self-employed">Self-employed</option>
                                            <option value="Student">Student</option>
                                            <option value="Retired">Retired</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="occupation">Occupation <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="occupation" name="occupation" class="form-input" 
                                               placeholder="Job title or profession">
                                    </div>
                                    <div class="form-group">
                                        <label for="employer_name">Employer Name <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="employer_name" name="employer_name" class="form-input" 
                                               placeholder="Company or organization name">
                                    </div>
                                    <div class="form-group">
                                        <label for="monthly_income">Monthly Income (PHP) <span class="optional-label">(Optional)</span></label>
                                        <input type="number" id="monthly_income" name="monthly_income" class="form-input" 
                                               min="0" step="0.01" placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            <!-- Government IDs -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h4><i class="fas fa-id-card-alt"></i> Government IDs</h4>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="sss_number">SSS Number <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="sss_number" name="sss_number" class="form-input" 
                                               placeholder="XX-XXXXXXX-X">
                                    </div>
                                    <div class="form-group">
                                        <label for="philhealth_number">PhilHealth Number <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="philhealth_number" name="philhealth_number" class="form-input" 
                                               placeholder="XX-XXXXXXXXX-X">
                                    </div>
                                    <div class="form-group">
                                        <label for="tin_number">TIN Number <span class="optional-label">(Optional)</span></label>
                                        <input type="text" id="tin_number" name="tin_number" class="form-input" 
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
                    </div>
                </div>
            <?php else: ?>
                 Record Summary Tab 
                <div id="record-summary" class="tab-content">
                    <div class="record-summary-container">
                        <div class="summary-card">
                            <div class="summary-header">
                                <div class="summary-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <div class="summary-title">
                                    <h3>PWD Record Created Successfully</h3>
                                    <p>The PWD record has been created and is ready for processing</p>
                                </div>
                                <div class="summary-status">
                                    <span class="status-badge status-<?php echo $interview['record_status']; ?>">
                                        <?php echo ucfirst($interview['record_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="summary-content">
                                <div class="summary-grid">
                                    <div class="summary-item">
                                        <div class="summary-label">PWD ID Number</div>
                                        <div class="summary-value"><?php echo htmlspecialchars($interview['pwd_id_number']); ?></div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">Record Status</div>
                                        <div class="summary-value">
                                            <span class="status-badge status-<?php echo $interview['record_status']; ?>">
                                                <?php echo ucfirst($interview['record_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="summary-item full-width">
                                        <div class="summary-label">Next Steps</div>
                                        <div class="summary-value">
                                            <?php if ($interview['record_status'] === 'draft'): ?>
                                                <div class="next-steps">
                                                    <i class="fas fa-arrow-right"></i>
                                                    <span>Record needs validation before ID can be issued</span>
                                                </div>
                                            <?php elseif ($interview['record_status'] === 'validated'): ?>
                                                <div class="next-steps">
                                                    <i class="fas fa-arrow-right"></i>
                                                    <span>Record is validated and ready for ID issuance</span>
                                                </div>
                                            <?php elseif ($interview['record_status'] === 'issued'): ?>
                                                <div class="next-steps">
                                                    <i class="fas fa-check-circle"></i>
                                                    <span>PWD ID has been issued and is ready for pickup</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="summary-actions">
                                <a href="records.php?highlight=<?php echo $interview['record_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Full Record
                                </a>
                                <a href="appointments.php" class="btn btn-outline">
                                    <i class="fas fa-list"></i> Back to Appointments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
    <script>
        let locationMap;
        let locationMarker;
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Pre-select disability type if available
            const disabilityType = '<?php echo $interview['disability_type'] ?? ''; ?>';
            if (disabilityType) {
                const disabilitySelect = document.getElementById('disability_type');
                if (disabilitySelect) {
                    disabilitySelect.value = disabilityType;
                }
            }
            
            // Initialize map
            initializeLocationMap();
        });
        
        // Initialize location map
        function initializeLocationMap() {
            const mapElement = document.getElementById('locationMap');
            if (!mapElement) return;
            
            // Default center (Santo Tomas City, Batangas)
            const defaultLat = 14.1078;
            const defaultLng = 121.1414;
            
            locationMap = L.map('locationMap').setView([defaultLat, defaultLng], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(locationMap);
            
            // Add click event to map
            locationMap.on('click', function(e) {
                setLocation(e.latlng.lat, e.latlng.lng);
            });
        }
        
        // Set location on map
        function setLocation(lat, lng) {
            // Remove existing marker
            if (locationMarker) {
                locationMap.removeLayer(locationMarker);
            }
            
            // Add new marker
            locationMarker = L.marker([lat, lng]).addTo(locationMap);
            
            // Update input fields
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lng.toFixed(6);
            
            // Show success message
            showNotification('Location set successfully!', 'success');
        }
        
        // Get current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    setLocation(lat, lng);
                    locationMap.setView([lat, lng], 16);
                    
                    showNotification('Current location detected!', 'success');
                }, function(error) {
                    showNotification('Unable to get current location: ' + error.message, 'error');
                });
            } else {
                showNotification('Geolocation is not supported by this browser', 'error');
            }
        }
        
        // Clear location
        function clearLocation() {
            if (locationMarker) {
                locationMap.removeLayer(locationMarker);
                locationMarker = null;
            }
            
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';
            
            showNotification('Location cleared', 'info');
        }

        // NEW FUNCTION: Toggle Map Expand
        function toggleMapExpand() {
            const container = document.getElementById('locationContainer');
            const button = document.getElementById('expandMapBtn');
            const icon = button.querySelector('i');

            const isExpanded = container.classList.toggle('map-expanded');

            if (isExpanded) {
                // Move the button inside the container so it's visible
                container.appendChild(button); 
                button.innerHTML = '<i class="fas fa-compress-arrows-alt"></i> Compress Map';
            } else {
                // Move the button back to its original place
                const actionsContainer = document.querySelector('.location-actions');
                actionsContainer.prepend(button); // Puts it at the top of the list
                button.innerHTML = '<i class="fas fa-expand-arrows-alt"></i> Expand Map';
            }

            // IMPORTANT: Tell Leaflet to recalculate its size
            setTimeout(() => {
                locationMap.invalidateSize();
            }, 100); // Small delay to let CSS animations finish
        }
        
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
            
            // Refresh map if switching to PWD record tab
            if (tabName === 'pwd-record' && locationMap) {
                setTimeout(() => {
                    locationMap.invalidateSize();
                }, 100);
            }
        }
        
        // Update city and province based on barangay selection
        function updateCityProvince() {
            const barangaySelect = document.getElementById('barangay_id');
            const selectedOption = barangaySelect.options[barangaySelect.selectedIndex];
            
            if (selectedOption.value) {
                const city = selectedOption.getAttribute('data-city');
                const province = selectedOption.getAttribute('data-province');
                
                document.getElementById('city_municipality').value = city;
                document.getElementById('province').value = province;
                
                // Hide manual barangay input
                document.getElementById('manual-barangay').style.display = 'none';
                document.getElementById('barangay_manual').required = false;
            }
        }
        
        // Toggle manual barangay entry
        function toggleManualBarangay() {
            const manualDiv = document.getElementById('manual-barangay');
            const barangaySelect = document.getElementById('barangay_id');
            const manualInput = document.getElementById('barangay_manual');
            
            if (manualDiv.style.display === 'none') {
                manualDiv.style.display = 'block';
                manualInput.required = true;
                barangaySelect.required = false;
                barangaySelect.value = '';
                
                // Allow manual city/province editing
                document.getElementById('city_municipality').readOnly = false;
                document.getElementById('province').readOnly = false;
                
                showNotification('Manual barangay entry enabled', 'info');
            } else {
                manualDiv.style.display = 'none';
                manualInput.required = false;
                barangaySelect.required = true;
                manualInput.value = '';
                
                // Reset to readonly
                document.getElementById('city_municipality').readOnly = true;
                document.getElementById('province').readOnly = true;
                document.getElementById('city_municipality').value = 'Santo Tomas City';
                document.getElementById('province').value = 'Batangas';
                
                showNotification('Switched back to barangay dropdown', 'info');
            }
        }
        
        // Complete interview without creating record
        function completeInterview() {
            if (confirm('Complete this interview without creating a PWD record? You can create the record later if needed.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="complete_interview">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                const form = document.getElementById('pwdRecordForm');
                if (form) {
                    form.reset();
                    
                    // Reset address fields
                    document.getElementById('city_municipality').value = 'Santo Tomas City';
                    document.getElementById('province').value = 'Batangas';
                    document.getElementById('postal_code').value = '4234';
                    
                    // Hide manual barangay input
                    document.getElementById('manual-barangay').style.display = 'none';
                    document.getElementById('barangay_manual').required = false;
                    document.getElementById('barangay_id').required = true;
                    
                    // Clear location
                    clearLocation();
                    
                    showNotification('Form has been reset', 'info');
                }
            }
        }
        
        // Form validation
        const pwdForm = document.getElementById('pwdRecordForm');
        if (pwdForm) {
            pwdForm.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                let missingFields = [];
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('error');
                        missingFields.push(field.name || field.id);
                        isValid = false;
                    } else {
                        field.classList.remove('error');
                    }
                });
                
                // Check barangay selection
                const barangaySelect = document.getElementById('barangay_id');
                const manualBarangay = document.getElementById('barangay_manual');
                
                if (!barangaySelect.value && !manualBarangay.value) {
                    showNotification('Please select a barangay or enter it manually.', 'error');
                    isValid = false;
                    missingFields.push('barangay');
                }
                
                if (!isValid) {
                    e.preventDefault();
                    showNotification('Please fill in all required fields: ' + missingFields.join(', '), 'error');
                    return false;
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Record...';
                }
                
                return true;
            });
        }
    </script>
    
    <style>
        .interview-progress {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
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
            height: 3px;
            background: rgba(255, 255, 255, 0.3);
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .step.completed .step-number {
            background: #10b981;
            color: white;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
        }
        
        .step.active .step-number {
            background: #f59e0b;
            color: white;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);
        }
        
        .step-label {
            font-size: 0.9rem;
            text-align: center;
            opacity: 0.8;
        }
        
        .step.completed .step-label,
        .step.active .step-label {
            opacity: 1;
            font-weight: 500;
        }
        
        .status-overview-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .status-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .status-icon.interview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .status-icon.appointment {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .status-icon.time {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .status-icon.record {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .status-content {
            flex: 1;
        }
        
        .status-label {
            display: block;
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .status-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .status-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        
        .status-link:hover {
            text-decoration: underline;
        }
        
        .interview-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .interview-tabs {
            display: flex;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tab-btn {
            flex: 1;
            padding: 20px 24px;
            background: none;
            border: none;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
        }
        
        .tab-btn:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        .tab-btn.active {
            background: white;
            color: #2563eb;
            border-bottom: 3px solid #2563eb;
        }
        
        .tab-content {
            display: none;
            padding: 32px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .applicant-overview {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .applicant-card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #e5e7eb;
        }
        
        .applicant-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .applicant-avatar {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .applicant-details h3 {
            margin: 0 0 4px 0;
            color: #1f2937;
            font-size: 1.5rem;
        }
        
        .applicant-type {
            color: #6b7280;
            margin: 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        .info-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }
        
        .info-section h4 {
            color: #374151;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item .label {
            font-weight: 500;
            color: #6b7280;
            min-width: 120px;
            font-size: 0.9rem;
        }
        
        .info-item .value {
            color: #1f2937;
            text-align: right;
            flex: 1;
            font-size: 0.9rem;
        }
        
        .disability-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .interview-form-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-section {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-header {
            margin-bottom: 20px;
        }
        
        .section-header h3,
        .section-header h4 {
            color: #1f2937;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-description {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .document-checklist {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }
        
        .document-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .document-item.verified {
            border-color: #10b981;
            background: #ecfdf5;
        }
        
        .document-label {
            display: flex;
            align-items: center;
            padding: 16px;
            cursor: pointer;
            gap: 16px;
        }
        
        .document-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #10b981;
        }
        
        .document-content {
            flex: 1;
        }
        
        .document-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .document-description {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .document-status {
            color: #10b981;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .document-item.verified .document-status {
            opacity: 1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
            background: white;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-input.error,
        .form-select.error,
        .form-textarea.error {
            border-color: #ef4444;
        }
        
        .record-form-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-header h3 {
            color: #1f2937;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .form-header p {
            color: #6b7280;
            margin: 0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .location-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            align-items: start;
        }
        
        .location-inputs {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .location-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .map-container {
            position: relative;
        }
        
        .location-map {
            height: 300px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        
        .map-instructions {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 1000;
        }
        
        .address-helper {
            margin-top: 20px;
            padding: 16px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        
        .helper-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0369a1;
            font-size: 0.9rem;
        }
        
        .helper-info i {
            color: #0ea5e9;
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .record-summary-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .summary-card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 32px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        
        .summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .summary-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .summary-title {
            flex: 1;
            text-align: left;
            margin-left: 20px;
        }
        
        .summary-title h3 {
            margin: 0 0 4px 0;
            color: #1f2937;
        }
        
        .summary-title p {
            margin: 0;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .summary-status {
            flex-shrink: 0;
        }
        
        .summary-content {
            margin-bottom: 24px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .summary-item {
            background: white;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .summary-item.full-width {
            grid-column: 1 / -1;
        }
        
        .summary-label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 4px;
            display: block;
        }
        
        .summary-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .next-steps {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #059669;
        }
        
        .summary-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .status-grid {
                grid-template-columns: 1fr;
            }
            
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
            
            .form-actions {
                flex-direction: column;
            }
            
            .location-container {
                grid-template-columns: 1fr;
            }
            
            .location-actions {
                flex-direction: row;
            }
            
            .summary-header {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }
            
            .summary-title {
                text-align: center;
                margin-left: 0;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-actions {
                flex-direction: column;
            }
            
            .document-checklist {
                grid-template-columns: 1fr;
            }
        }
        /* ... existing styles ... */
        
        /* Expandable Map Styles */
        .map-container.map-expanded {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            z-index: 9998;
            padding: 20px;
            /* Use vh/vw for full screen dimensions */
            width: 100vw; 
            height: 100vh;
        }
        
        .map-container.map-expanded .location-map {
            height: 100%; /* Fill the expanded container */
            border: none;
        }

        /* Move the expand button to the top right when expanded */
        .map-container.map-expanded #expandMapBtn {
            position: absolute;
            top: 30px;
            right: 30px;
            z-index: 9999;
            background: white;
        }
        
        @media (max-width: 768px) {
            .map-container.map-expanded {
                padding: 10px;
            }
            .map-container.map-expanded #expandMapBtn {
                top: 20px;
                right: 20px;
            }
        }

        /* ... existing styles ... */
        
.optional-label {
    font-size: 0.8rem;
    color: #6b7280; /* A soft gray color */
    font-weight: 400; /* Normal weight, not bold */
    margin-left: 6px;
}
    </style>
</body>
</html>
