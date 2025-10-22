-- Enhanced PWD Portal Database Schema with Authentication and Appointments

-- Drop existing tables if they exist
DROP TABLE IF EXISTS appointment_documents;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS admin_users;

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

-- Insert sample data for testing
INSERT INTO users (first_name, last_name, email, phone, password_hash, date_of_birth, address, disability_type, emergency_contact_name, emergency_contact_phone, is_verified) VALUES 
('Juan', 'Dela Cruz', 'juan@email.com', '+63 912 345 6789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1990-05-15', '123 Main St, Manila', 'Physical Disability', 'Maria Dela Cruz', '+63 912 345 6790', TRUE);

INSERT INTO appointments (user_id, reference_number, appointment_type, preferred_date, preferred_time, actual_date, actual_time, status, sms_verification_sent, sms_verified_at, confirmed_at) VALUES 
(1, 'PWD-2025-05-10-1042', 'new_application', '2025-05-10', '10:00:00', '2025-05-10', '10:00:00', 'confirmed', TRUE, '2025-05-02 15:46:00', '2025-05-02 15:47:00');
