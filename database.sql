-- ===================================================================
-- OSWD Complaint System - Complete Database Schema
-- Version: 1.0
-- Date: 2026-09-22
-- Description: Complete database schema for OSWD Complaint Management System
-- ===================================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS oswd_complaint_system;
USE oswd_complaint_system;

-- ===================================================================
-- TABLE: users
-- Description: Stores all system users (students, coordinators, chairs, guidance, oswd)
-- ===================================================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    alias VARCHAR(20) NULL UNIQUE,  -- students only: shown to personnel instead of the real name
    role ENUM('student', 'program_coordinator', 'department_chair', 'guidance_office', 'oswd', 'super_admin') NOT NULL,
    student_id VARCHAR(50),
    department VARCHAR(100),
    program VARCHAR(100),
    contact_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',  -- account switch: inactive = cannot sign in
    last_activity_at DATETIME NULL,  -- last page opened while signed in ("last seen")
    is_signed_in TINYINT(1) NOT NULL DEFAULT 0  -- 0 after logout; drives Active/Inactive in User management
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: complaints
-- Description: Main complaints table storing all complaint records
-- ===================================================================
CREATE TABLE IF NOT EXISTS complaints (
    complaint_id INT AUTO_INCREMENT PRIMARY KEY,
    complainant_id INT NOT NULL,
    respondent_name VARCHAR(200),
    respondent_type ENUM('faculty', 'staff', 'student', 'other'),
    complaint_title VARCHAR(255) NOT NULL,
    complaint_description TEXT NOT NULL,
    complaint_category VARCHAR(100),
    predicted_category VARCHAR(100),
    complaint_type ENUM('behavioral','services') NOT NULL DEFAULT 'behavioral',
    department VARCHAR(20) NULL,  -- complainant's department when submitted; used for routing
    incident_date DATE NOT NULL,
    incident_location VARCHAR(255),
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('pending', 'under_review', 'investigating', 'resolved', 'rejected', 'escalated') DEFAULT 'pending',
    supporting_documents TEXT,
    assigned_to INT,
    current_level ENUM('program_coordinator','department_chair','guidance_office','oswd') NOT NULL DEFAULT 'program_coordinator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (complainant_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: complaint_categories
-- Description: Predefined complaint categories for classification
-- ===================================================================
CREATE TABLE IF NOT EXISTS complaint_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) UNIQUE NOT NULL,
    category_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: complaint_timeline
-- Description: Tracks all actions and status changes for complaints
-- ===================================================================
CREATE TABLE IF NOT EXISTS complaint_timeline (
    timeline_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    action_by INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: complaint_comments
-- Description: Stores comments and notes on complaints
-- ===================================================================
CREATE TABLE IF NOT EXISTS complaint_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: complaint_escalations
-- Description: Tracks complaint escalations to higher authorities
-- ===================================================================
CREATE TABLE IF NOT EXISTS complaint_escalations (
    escalation_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    escalated_by INT NOT NULL,
    escalated_to INT,
    escalation_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (escalated_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (escalated_to) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: login_events / login_otps (Random Forest login risk)
-- ===================================================================
CREATE TABLE IF NOT EXISTS login_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username_attempted VARCHAR(150) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    failure_reason VARCHAR(50) NULL,
    ip_hash CHAR(64) NOT NULL,
    user_agent_hash CHAR(64) NOT NULL,
    risk_score DECIMAL(5,4) NULL,
    risk_label ENUM('low','medium','high') NULL,
    model_version VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_otps (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: notifications
-- Description: In-app alerts for students about complaint updates
-- ===================================================================
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    complaint_id INT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: login_lockouts
-- Description: Tracks consecutive failed logins, cooldowns and blocks
-- ===================================================================
CREATE TABLE IF NOT EXISTS login_lockouts (
    lockout_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username_key VARCHAR(150) NOT NULL,
    consecutive_fails INT NOT NULL DEFAULT 0,
    cooldown_until DATETIME NULL,
    blocked_until DATETIME NULL,
    last_failed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_login_lockouts_key (username_key),
    INDEX idx_login_lockouts_user (user_id),
    INDEX idx_login_lockouts_blocked (blocked_until),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: lockout_reviews
-- Description: Unlock requests for blocked accounts, reviewed by staff
-- ===================================================================
CREATE TABLE IF NOT EXISTS lockout_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    lockout_id INT NULL,
    user_id INT NULL,
    username_attempted VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    full_name VARCHAR(200) NULL,
    message TEXT NULL,
    source ENUM('auto_block','student_service') NOT NULL DEFAULT 'auto_block',
    status ENUM('pending','accepted','rejected','completed') NOT NULL DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    review_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lockout_reviews_status (status, created_at),
    INDEX idx_lockout_reviews_user (user_id, status),
    FOREIGN KEY (lockout_id) REFERENCES login_lockouts(lockout_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: credential_resets
-- Description: One-time tokens for resetting credentials after review
-- ===================================================================
CREATE TABLE IF NOT EXISTS credential_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_credential_resets_token (token_hash),
    INDEX idx_credential_resets_review (review_id),
    FOREIGN KEY (review_id) REFERENCES lockout_reviews(review_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: super_admin_audit_log
-- Description: Append-only record of identity reveals, account changes,
-- unlocks, password resets and exports. No foreign keys on purpose (a
-- user delete would otherwise try to UPDATE rows the triggers forbid).
-- ===================================================================
CREATE TABLE IF NOT EXISTS super_admin_audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    actor_username VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    target_type VARCHAR(30) NULL,
    target_id INT NULL,
    target_label VARCHAR(200) NULL,
    reason TEXT NULL,
    details TEXT NULL,
    ip_hash CHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_action (action, created_at),
    INDEX idx_audit_actor (actor_id, created_at),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The audit log is read-only at the database level: reject UPDATE and DELETE.
DROP TRIGGER IF EXISTS trg_audit_log_no_update;
CREATE TRIGGER trg_audit_log_no_update BEFORE UPDATE ON super_admin_audit_log FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log is read-only.';
DROP TRIGGER IF EXISTS trg_audit_log_no_delete;
CREATE TRIGGER trg_audit_log_no_delete BEFORE DELETE ON super_admin_audit_log FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log is read-only.';

-- ===================================================================
-- TABLE: enrolled_students
-- Description: Official list of enrolled Student IDs uploaded by OSWD.
-- A student can only register if their Student ID is in this table.
-- ===================================================================
CREATE TABLE IF NOT EXISTS enrolled_students (
    enrolled_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(200) NULL,
    department VARCHAR(20) NULL,
    program VARCHAR(100) NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_enrolled_student_id (student_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TABLE: system_settings
-- Description: Settings changed by the Super Admin. A missing row means
-- the default in classes/Settings.php is used.
-- ===================================================================
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- DEFAULT DATA: Complaint Categories
-- ===================================================================
INSERT IGNORE INTO complaint_categories (category_name, category_description) VALUES
('Academic Integrity Violation', 'Grading disputes, favoritism, failure to meet academic standards'),
('Unprofessional Behavior', 'Discriminatory practices, harassment, intimidation, abuse of power'),
('Institutional Rules Violation', 'Breach of code of conduct, academic policies'),
('Teaching Standards Failure', 'Neglecting responsibilities, inadequate communication'),
('Campus Services', 'Issues with campus services such as registrar, cashier, clinic, library, internet, and student support offices'),
('Campus Facilities', 'Issues with campus facilities such as classrooms, restrooms, buildings, equipment, lighting, and infrastructure');

-- ===================================================================
-- DEFAULT DATA: Staff Accounts (change passwords after first login)
-- admin / admin123 | coordinator / coordinator123 | chairperson / chair123
-- counselor / counselor123 | oswdadmin / oswdadmin123
-- ===================================================================
INSERT IGNORE INTO users (username, email, password, full_name, role, status) VALUES
('admin', 'oswd@nemsu.edu', '$2y$10$Q49BTnExRLAm2rrvq4Gm4.w.A9c9ULH0kUATFoIFGrE7n5JeDKZjy', 'OSWD Administrator', 'oswd', 'active'),
('coordinator', 'coordinator@nemsu.edu', '$2y$10$PPiQfoG62n8uoMZn9EXER.lGccNMxuBuaICIjchkIR5P5Y1mogqWu', 'Program Coordinator', 'program_coordinator', 'active'),
('chairperson', 'chairperson@nemsu.edu', '$2y$10$gzEwzSOrzUK/hGI18GRLketjF7ST2F3nOw/xTxvtiP4UWzCRswHam', 'Program Chairperson', 'department_chair', 'active'),
('counselor', 'counselor@nemsu.edu', '$2y$10$/O7z2edOJUkcq81Rnvw52OY3eRZH5aUBAxWt0scgTAarYpxMU5/XS', 'Guidance Counselor', 'guidance_office', 'active'),
('oswdadmin', 'oswdadmin@nemsu.edu', '$2y$10$fuIeFJswJEByZd4kfVMjbuVTMG3qvGget65/9f3eMUqOkV8XZzLLG', 'OSWD Administrator', 'oswd', 'active');

-- ===================================================================
-- INDEXES: For Performance Optimization
-- ===================================================================
CREATE INDEX idx_complaints_status ON complaints(status);
CREATE INDEX idx_complaints_category ON complaints(predicted_category);
CREATE INDEX idx_complaints_created ON complaints(created_at);
CREATE INDEX idx_complaints_department ON complaints(department);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_notifications_created ON notifications(created_at);

-- ===================================================================
-- END OF DATABASE SCHEMA
-- ===================================================================
