-- ============================================
-- iconcern Database Schema
-- Intelligent College-Based Student Concern Classification and Routing System
-- ============================================

CREATE DATABASE IF NOT EXISTS iconcern_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iconcern_db;

-- ============================================
-- Table 1: colleges
-- ============================================
CREATE TABLE IF NOT EXISTS colleges (
    college_id INT AUTO_INCREMENT PRIMARY KEY,
    college_code VARCHAR(10) UNIQUE NOT NULL,
    college_name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 2: departments
-- ============================================
CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 3: users
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('student', 'staff', 'admin') DEFAULT 'student',
    college_id INT NULL,
    department_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (college_id) REFERENCES colleges(college_id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    INDEX idx_role (role),
    INDEX idx_college (college_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 3b: pre_registered_students (Directory-based login)
-- Only Student IDs in this table can activate accounts.
-- ============================================
CREATE TABLE IF NOT EXISTS pre_registered_students (
    student_id VARCHAR(10) PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    course_year_level VARCHAR(160) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 4: concerns
-- ============================================
CREATE TABLE IF NOT EXISTS concerns (
    concern_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    college_id INT NULL,
    concern_text TEXT NOT NULL,
    attachment VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'In Progress', 'Resolved') DEFAULT 'Pending',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (college_id) REFERENCES colleges(college_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 5: classifications
-- ============================================
CREATE TABLE IF NOT EXISTS classifications (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    concern_id INT NOT NULL UNIQUE,
    predicted_category VARCHAR(50) NOT NULL,
    confidence_score DECIMAL(5,2) NOT NULL,
    classified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (concern_id) REFERENCES concerns(concern_id) ON DELETE CASCADE,
    INDEX idx_category (predicted_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 6: routing
-- ============================================
CREATE TABLE IF NOT EXISTS routing (
    routing_id INT AUTO_INCREMENT PRIMARY KEY,
    concern_id INT NOT NULL,
    college_id INT NULL,
    department_id INT NULL,
    routed_by INT NOT NULL,
    routed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (concern_id) REFERENCES concerns(concern_id) ON DELETE CASCADE,
    FOREIGN KEY (college_id) REFERENCES colleges(college_id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    FOREIGN KEY (routed_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_concern (concern_id),
    INDEX idx_college (college_id),
    INDEX idx_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 7: status_history
-- ============================================
CREATE TABLE IF NOT EXISTS status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    concern_id INT NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NOT NULL,
    updated_by INT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    FOREIGN KEY (concern_id) REFERENCES concerns(concern_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_concern (concern_id),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 8: notifications
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
    notif_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 9: training_data (Optional for ML retraining)
-- ============================================
CREATE TABLE IF NOT EXISTS training_data (
    data_id INT AUTO_INCREMENT PRIMARY KEY,
    concern_text TEXT NOT NULL,
    label VARCHAR(50) NOT NULL,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert Sample Data
-- ============================================

-- Insert All NWSSU Colleges
INSERT INTO colleges (college_code, college_name) VALUES
('CCIS', 'College of Computing and Information Sciences'),
('CCJS', 'College of Criminal Justice Studies'),
('COED', 'College of Education'),
('CON', 'College of Nursing'),
('CAT', 'College of Arts and Technology'),
('CEA', 'College of Engineering and Architecture'),
('COM', 'College of Management');

-- Insert All Departments (including colleges as departments)
INSERT INTO departments (department_name, description) VALUES
('Guidance Office', 'Student counseling and guidance services'),
('Cashiers Office', 'Tuition and financial payment processing'),
('Registrar Office', 'Academic records and enrollment'),
('Library Office', 'Library services and resources'),
('Student Affairs and Services (OSAS)', 'Student welfare and services'),
('College of Computing and Information Sciences (CCIS)', 'CCIS department'),
('College of Criminal Justice Studies (CCJS)', 'CCJS department'),
('College of Education (COED)', 'COED department'),
('College of Nursing (CON)', 'CON department'),
('College of Arts and Technology (CAT)', 'CAT department'),
('College of Engineering and Architecture (CEA)', 'CEA department'),
('College of Management (COM)', 'COM department'),
('IT Support', 'Technical support and IT services'),
('Maintenance Office', 'Facilities and infrastructure maintenance');

-- Insert Default Admin User (password: admin123)
-- Password hash for 'admin123' using PHP password_hash()
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@nwssu.edu.ph', 'admin');

-- Insert Sample Staff Users
INSERT INTO users (username, password, full_name, email, role, department_id) VALUES
('staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff Member', 'staff1@nwssu.edu.ph', 'staff', 1),
('dean1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'College Dean', 'dean1@nwssu.edu.ph', 'staff', NULL);

-- Insert Sample Student Users
INSERT INTO users (username, password, full_name, email, role, college_id) VALUES
('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'student1@nwssu.edu.ph', 'student', 1),
('student2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'student2@nwssu.edu.ph', 'student', 2);

-- Pre-Registered Student Directory (Student ID based)
INSERT INTO pre_registered_students (student_id, full_name, course_year_level) VALUES
('22-00482', 'Jerico B. Jadaone', 'College of Computing and Information Science (CCIS)'),
('22-22021', 'Czea Dawn J. Lunas', 'College of Education (COED)'),
('22-22001', 'Aira M. Lungsod', 'College of Nursing (CON)'),
('22-22113', 'Rosemarie M. Magallem', 'College Of Engineering and Architecture (CEA)'),
('22-22003', 'Frances Ivan Batican', 'College of Criminal Justice Studies (CCJS)');

