-- FRESH ADMIN SCHEMA - Complete Clean Slate Approach
-- This script completely removes all admin tables and recreates everything

-- ============================================
-- STEP 1: NUCLEAR OPTION - DROP EVERYTHING
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop all admin-related tables (order doesn't matter with FK checks off)
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS admin_permissions;
DROP TABLE IF EXISTS admin_roles;
DROP TABLE IF EXISTS pwd_records;
DROP TABLE IF EXISTS barangay_boundaries;
DROP TABLE IF EXISTS interview_records;
DROP TABLE IF EXISTS gis_import_logs;
DROP TABLE IF EXISTS admin_activity_logs;

-- Remove role_id column from admin_users if it exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'admin_users' 
     AND column_name = 'role_id' 
     AND table_schema = DATABASE()) > 0,
    'ALTER TABLE admin_users DROP COLUMN role_id',
    'SELECT "Column role_id does not exist"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop the PWD ID generation function if it exists
DROP FUNCTION IF EXISTS generate_pwd_id;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- STEP 2: CREATE FRESH ADMIN SYSTEM
-- ============================================

-- 1. Admin Roles Table
CREATE TABLE admin_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Admin Permissions Table
CREATE TABLE admin_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Role-Permission Mapping
CREATE TABLE role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
);

-- 4. Add role_id to admin_users
ALTER TABLE admin_users ADD COLUMN role_id INT NULL;
ALTER TABLE admin_users ADD FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE SET NULL;

-- 5. PWD Records Table
CREATE TABLE pwd_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NULL,
    pwd_id_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Personal Information
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20),
    date_of_birth DATE NOT NULL,
    place_of_birth VARCHAR(200),
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated', 'Divorced') NOT NULL,
    
    -- Contact Information
    address_line1 VARCHAR(200) NOT NULL,
    address_line2 VARCHAR(200),
    barangay VARCHAR(100) NOT NULL,
    city_municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10),
    phone_number VARCHAR(20),
    email_address VARCHAR(255),
    
    -- Geographic Information (EPSG:4326)
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    geojson_data JSON,
    
    -- Disability Information
    disability_type VARCHAR(100) NOT NULL,
    disability_cause VARCHAR(100),
    disability_description TEXT,
    assistive_device VARCHAR(200),
    
    -- Medical Information
    medical_condition TEXT,
    medication TEXT,
    attending_physician VARCHAR(200),
    
    -- Emergency Contact
    emergency_contact_name VARCHAR(100),
    emergency_contact_relationship VARCHAR(50),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_address TEXT,
    
    -- Employment Information
    employment_status ENUM('Employed', 'Unemployed', 'Self-employed', 'Student', 'Retired') DEFAULT 'Unemployed',
    occupation VARCHAR(100),
    employer_name VARCHAR(200),
    monthly_income DECIMAL(10, 2),
    
    -- Benefits and Services
    sss_number VARCHAR(20),
    philhealth_number VARCHAR(20),
    tin_number VARCHAR(20),
    
    -- Record Status
    status ENUM('draft', 'pending_validation', 'validated', 'issued', 'expired', 'revoked') DEFAULT 'draft',
    validation_date TIMESTAMP NULL,
    issue_date TIMESTAMP NULL,
    expiry_date DATE NULL,
    
    -- Audit Trail
    created_by INT NOT NULL,
    validated_by INT NULL,
    issued_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES admin_users(id),
    FOREIGN KEY (validated_by) REFERENCES admin_users(id),
    FOREIGN KEY (issued_by) REFERENCES admin_users(id),
    
    INDEX idx_pwd_id (pwd_id_number),
    INDEX idx_location (latitude, longitude),
    INDEX idx_status (status),
    INDEX idx_created_date (created_at)
);

-- 6. Barangay Boundaries Table (THE IMPORTANT ONE!)
CREATE TABLE barangay_boundaries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barangay_code VARCHAR(50) UNIQUE,
    barangay_name VARCHAR(100) NOT NULL,
    city_municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100),
    region VARCHAR(100),
    area_sqkm DECIMAL(10, 4),
    population INT,
    pwd_count INT DEFAULT 0,
    
    -- Spatial data for your polygon GeoJSON
    geometry GEOMETRY,
    geojson_data JSON,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_barangay_name (barangay_name),
    INDEX idx_city_municipality (city_municipality),
    INDEX idx_pwd_count (pwd_count)
);

-- 7. Interview Records Table
CREATE TABLE interview_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    interviewer_id INT NOT NULL,
    interview_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    interview_notes TEXT,
    documents_verified JSON,
    eligibility_assessment TEXT,
    recommendations TEXT,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (interviewer_id) REFERENCES admin_users(id),
    INDEX idx_appointment (appointment_id),
    INDEX idx_interviewer (interviewer_id),
    INDEX idx_status (status)
);

-- 8. GIS Import Logs Table
CREATE TABLE gis_import_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    file_size INT,
    records_imported INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    import_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_log TEXT,
    imported_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (imported_by) REFERENCES admin_users(id),
    INDEX idx_status (import_status),
    INDEX idx_imported_by (imported_by)
);

-- 9. Activity Logs Table
CREATE TABLE admin_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_admin_activity (admin_user_id, created_at),
    INDEX idx_module_action (module, action)
);

-- ============================================
-- STEP 3: POPULATE WITH FRESH DATA
-- ============================================

-- Insert Admin Roles
INSERT INTO admin_roles (name, display_name, description) VALUES
('super_admin', 'Super Administrator', 'Full system access with all permissions'),
('data_entry_staff', 'Data Entry Staff', 'Can enter and manage PWD records and conduct interviews'),
('feedback_manager', 'Feedback Manager', 'Can view and respond to feedback submissions'),
('report_analyst', 'Report Analyst', 'Can generate and view reports and analytics'),
('appointment_manager', 'Appointment Manager', 'Can manage appointments and scheduling');

-- Insert Admin Permissions
INSERT INTO admin_permissions (name, display_name, module, description) VALUES
-- Dashboard
('dashboard.view', 'View Dashboard', 'dashboard', 'Access to main dashboard'),
('dashboard.analytics', 'View Analytics', 'dashboard', 'Access to detailed analytics'),

-- Appointments
('appointments.view', 'View Appointments', 'appointments', 'View appointment list'),
('appointments.edit', 'Edit Appointments', 'appointments', 'Modify appointment details'),
('appointments.interview', 'Conduct Interviews', 'appointments', 'Start and manage interview process'),
('appointments.cancel', 'Cancel Appointments', 'appointments', 'Cancel appointments'),

-- PWD Records
('records.view', 'View PWD Records', 'records', 'View PWD record list'),
('records.create', 'Create PWD Records', 'records', 'Create new PWD records'),
('records.edit', 'Edit PWD Records', 'records', 'Modify PWD records'),
('records.validate', 'Validate PWD Records', 'records', 'Validate and approve PWD records'),
('records.issue', 'Issue PWD IDs', 'records', 'Issue official PWD IDs'),
('records.delete', 'Delete PWD Records', 'records', 'Delete PWD records'),

-- Feedback
('feedback.view', 'View Feedback', 'feedback', 'View feedback submissions'),
('feedback.respond', 'Respond to Feedback', 'feedback', 'Reply to feedback'),
('feedback.manage', 'Manage Feedback', 'feedback', 'Full feedback management'),

-- Reports
('reports.view', 'View Reports', 'reports', 'Access to reports'),
('reports.export', 'Export Reports', 'reports', 'Export report data'),
('reports.advanced', 'Advanced Reports', 'reports', 'Access to advanced reporting features'),

-- GIS
('gis.view', 'View GIS Map', 'gis', 'Access to GIS mapping'),
('gis.import', 'Import GIS Data', 'gis', 'Import GeoJSON and spatial data'),
('gis.export', 'Export GIS Data', 'gis', 'Export spatial data'),

-- User Management
('users.view', 'View Users', 'users', 'View admin user list'),
('users.create', 'Create Users', 'users', 'Create new admin users'),
('users.edit', 'Edit Users', 'users', 'Modify admin user details'),
('users.delete', 'Delete Users', 'users', 'Delete admin users'),
('users.roles', 'Manage Roles', 'users', 'Assign and manage user roles'),

-- System
('system.settings', 'System Settings', 'system', 'Access to system configuration'),
('system.logs', 'View System Logs', 'system', 'Access to system activity logs');

-- Assign ALL permissions to Super Admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM admin_roles r 
CROSS JOIN admin_permissions p 
WHERE r.name = 'super_admin';

-- Assign specific permissions to other roles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM admin_roles r, admin_permissions p 
WHERE r.name = 'data_entry_staff' 
AND p.name IN ('dashboard.view', 'appointments.view', 'appointments.edit', 'appointments.interview', 'records.view', 'records.create', 'records.edit', 'gis.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM admin_roles r, admin_permissions p 
WHERE r.name = 'feedback_manager' 
AND p.name IN ('dashboard.view', 'feedback.view', 'feedback.respond', 'feedback.manage', 'reports.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM admin_roles r, admin_permissions p 
WHERE r.name = 'report_analyst' 
AND p.name IN ('dashboard.view', 'dashboard.analytics', 'reports.view', 'reports.export', 'reports.advanced', 'gis.view', 'gis.export', 'appointments.view', 'records.view', 'feedback.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM admin_roles r, admin_permissions p 
WHERE r.name = 'appointment_manager' 
AND p.name IN ('dashboard.view', 'appointments.view', 'appointments.edit', 'appointments.cancel', 'records.view');

-- Update existing admin user with Super Admin role
UPDATE admin_users SET role_id = (SELECT id FROM admin_roles WHERE name = 'super_admin') WHERE username = 'admin';

-- Create PWD ID generation function
DELIMITER //
CREATE FUNCTION generate_pwd_id() RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE new_id VARCHAR(50);
    DECLARE id_exists INT DEFAULT 1;
    DECLARE counter INT DEFAULT 1;
    
    WHILE id_exists > 0 DO
        SET new_id = CONCAT('PWD-', YEAR(NOW()), '-', LPAD(counter, 6, '0'));
        SELECT COUNT(*) INTO id_exists FROM pwd_records WHERE pwd_id_number = new_id;
        SET counter = counter + 1;
    END WHILE;
    
    RETURN new_id;
END//
DELIMITER ;

-- Insert sample PWD records
INSERT INTO pwd_records (
    pwd_id_number, first_name, last_name, date_of_birth, gender, civil_status,
    address_line1, barangay, city_municipality, province, postal_code,
    phone_number, email_address, latitude, longitude,
    disability_type, disability_cause, status, created_by
) VALUES 
('PWD-2025-000001', 'Maria', 'Santos', '1985-03-15', 'Female', 'Married',
 '123 Rizal Street', 'Poblacion', 'Manila', 'Metro Manila', '1000',
 '+63 912 345 6789', 'maria.santos@email.com', 14.5995, 120.9842,
 'Physical Disability', 'Congenital', 'validated', 1),
 
('PWD-2025-000002', 'Jose', 'Reyes', '1978-07-22', 'Male', 'Single',
 '456 Bonifacio Avenue', 'San Antonio', 'Quezon City', 'Metro Manila', '1100',
 '+63 923 456 7890', 'jose.reyes@email.com', 14.6760, 121.0437,
 'Visual Impairment', 'Accident', 'issued', 1),
 
('PWD-2025-000003', 'Ana', 'Cruz', '1992-11-08', 'Female', 'Single',
 '789 Mabini Street', 'Barangay 1', 'Makati', 'Metro Manila', '1200',
 '+63 934 567 8901', 'ana.cruz@email.com', 14.5547, 121.0244,
 'Hearing Impairment', 'Congenital', 'validated', 1);

-- Success message
SELECT 'FRESH ADMIN SCHEMA CREATED SUCCESSFULLY! 🎉' as Status;
