-- This script is designed to completely reset and set up the database from scratch.
-- Use with caution as it will delete all existing data.

-- Drop existing tables in reverse dependency order to avoid foreign key constraint issues
DROP TABLE IF EXISTS appointment_documents;
DROP TABLE IF EXISTS pwd_records;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS barangay_boundaries;

-- Users table for authentication
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  date_of_birth DATE,
  address TEXT,
  disability_type VARCHAR(100),
  emergency_contact_name VARCHAR(100),
  emergency_contact_phone VARCHAR(20),
  is_verified BOOLEAN DEFAULT FALSE,
  verification_token VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Appointments table
CREATE TABLE appointments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  reference_number VARCHAR(50) UNIQUE NOT NULL,
  appointment_type ENUM('new_application', 'renewal', 'replacement', 'update') DEFAULT 'new_application',
  preferred_date DATE NOT NULL,
  preferred_time TIME NOT NULL,
  actual_date DATE,
  actual_time TIME,
  status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
  notes TEXT,
  requirements_submitted BOOLEAN DEFAULT FALSE,
  medical_certificate BOOLEAN DEFAULT FALSE,
  barangay_certificate BOOLEAN DEFAULT FALSE,
  id_pictures BOOLEAN DEFAULT FALSE,
  valid_id BOOLEAN DEFAULT FALSE,
  birth_certificate BOOLEAN DEFAULT FALSE,
  sms_verification_sent BOOLEAN DEFAULT FALSE,
  sms_verification_code VARCHAR(6),
  sms_verified_at TIMESTAMP NULL,
  confirmed_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointment documents tracking
CREATE TABLE appointment_documents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  appointment_id INT NOT NULL,
  document_type VARCHAR(50) NOT NULL,
  file_name VARCHAR(255),
  file_path VARCHAR(500),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  verified_by_admin INT NULL,
  verified_at TIMESTAMP NULL,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  rejection_reason TEXT,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

-- Admin users table
CREATE TABLE admin_users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('super_admin', 'admin', 'staff') DEFAULT 'staff',
  is_active BOOLEAN DEFAULT TRUE,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Enhanced feedback table
CREATE TABLE feedback (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  rating INT CHECK (rating >= 1 AND rating <= 5),
  status ENUM('new', 'read', 'responded', 'closed') DEFAULT 'new',
  admin_response TEXT,
  responded_by INT NULL,
  responded_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (responded_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Barangay boundaries table
CREATE TABLE barangay_boundaries (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  geometry GEOMETRY NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- PWD records table
CREATE TABLE pwd_records (
  id INT PRIMARY KEY AUTO_INCREMENT,
  appointment_id INT,
  pwd_id_number VARCHAR(50) UNIQUE NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  suffix VARCHAR(10),
  date_of_birth DATE,
  place_of_birth VARCHAR(200),
  gender ENUM('Male', 'Female', 'Other'),
  civil_status ENUM('Single', 'Married', 'Widowed', 'Separated', 'Divorced'),
  address_line1 TEXT,
  address_line2 TEXT,
  barangay VARCHAR(100),
  city_municipality VARCHAR(100),
  province VARCHAR(100),
  postal_code VARCHAR(10),
  phone_number VARCHAR(20),
  email_address VARCHAR(255),
  latitude DECIMAL(10, 8),
  longitude DECIMAL(11, 8),
  disability_type VARCHAR(100),
  disability_cause VARCHAR(100),
  disability_description TEXT,
  assistive_device TEXT,
  medical_condition TEXT,
  medication TEXT,
  attending_physician VARCHAR(200),
  emergency_contact_name VARCHAR(100),
  emergency_contact_relationship VARCHAR(50),
  emergency_contact_phone VARCHAR(20),
  emergency_contact_address TEXT,
  employment_status VARCHAR(50) DEFAULT 'Unemployed',
  occupation VARCHAR(100),
  employer_name VARCHAR(200),
  monthly_income DECIMAL(10, 2),
  sss_number VARCHAR(20),
  philhealth_number VARCHAR(20),
  tin_number VARCHAR(20),
  status ENUM('draft', 'validated', 'issued', 'expired', 'revoked') DEFAULT 'draft',
  validation_date TIMESTAMP NULL,
  validated_by INT,
  issue_date TIMESTAMP NULL,
  issued_by INT,
  expiry_date DATE,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  barangay_id INT NULL, -- New column for direct barangay link
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  FOREIGN KEY (validated_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  FOREIGN KEY (issued_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  FOREIGN KEY (barangay_id) REFERENCES barangay_boundaries(id) ON DELETE SET NULL
);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@pwd.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin');

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_appointments_user_id ON appointments(user_id);
CREATE INDEX idx_appointments_reference ON appointments(reference_number);
CREATE INDEX idx_appointments_date ON appointments(preferred_date);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_feedback_status ON feedback(status);
CREATE SPATIAL INDEX idx_barangay_geometry ON barangay_boundaries(geometry);

-- Insert sample data for testing
INSERT INTO users (first_name, last_name, email, phone, password_hash, date_of_birth, address, disability_type, emergency_contact_name, emergency_contact_phone, is_verified) VALUES
('Juan', 'Dela Cruz', 'juan@email.com', '+63 912 345 6789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1990-05-15', '123 Main St, Manila', 'Physical Disability', 'Maria Dela Cruz', '+63 912 345 6790', TRUE);

INSERT INTO appointments (user_id, reference_number, appointment_type, preferred_date, preferred_time, actual_date, actual_time, status, sms_verification_sent, sms_verified_at, confirmed_at) VALUES
(1, 'PWD-2025-05-10-1042', 'new_application', '2025-05-10', '10:00:00', '2025-05-10', '10:00:00', 'confirmed', TRUE, '2025-05-02 15:46:00', '2025-05-02 15:47:00');

-- Sample barangay data (replace with your actual GeoJSON import)
INSERT INTO barangay_boundaries (name, geometry) VALUES
('San Roque', ST_GeomFromText('POLYGON((121.1400 14.0800, 121.1500 14.0800, 121.1500 14.0900, 121.1400 14.0900, 121.1400 14.0800))')),
('Santa Anastacia', ST_GeomFromText('POLYGON((121.1000 14.0500, 121.1100 14.0500, 121.1100 14.0600, 121.1000 14.0600, 121.1000 14.0500))'));

-- Sample PWD record linked to a barangay
INSERT INTO pwd_records (appointment_id, pwd_id_number, first_name, middle_name, last_name, date_of_birth, place_of_birth, gender, civil_status, address_line1, barangay, city_municipality, province, postal_code, phone_number, email_address, latitude, longitude, disability_type, disability_cause, disability_description, assistive_device, medical_condition, medication, attending_physician, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, emergency_contact_address, employment_status, occupation, employer_name, monthly_income, sss_number, philhealth_number, tin_number, status, created_by, barangay_id) VALUES
(1, 'PWD-00001', 'Pedro', 'M', 'Reyes', '1985-11-20', 'Sto. Tomas, Batangas', 'Male', 'Single', 'Purok 1', 'San Roque', 'Santo Tomas', 'Batangas', '4234', '09171234567', 'pedro@example.com', 14.0850, 121.1450, 'Physical Disability', 'Accident', 'Lower limb impairment', 'Crutches', 'None', 'None', 'Dr. Cruz', 'Maria Reyes', 'Sister', '09177654321', 'Purok 1, San Roque', 'Employed', 'Clerk', 'ABC Corp', 15000.00, '12-3456789-0', '123456789012', '123-456-789', 'draft', 1, (SELECT id FROM barangay_boundaries WHERE name = 'San Roque'));
