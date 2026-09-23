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
    role ENUM('student', 'program_coordinator', 'department_chair', 'guidance_office', 'oswd') NOT NULL,
    student_id VARCHAR(50),
    department VARCHAR(100),
    program VARCHAR(100),
    contact_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
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
    incident_date DATE NOT NULL,
    incident_location VARCHAR(255),
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('pending', 'under_review', 'investigating', 'resolved', 'rejected', 'escalated') DEFAULT 'pending',
    supporting_documents TEXT,
    assigned_to INT,
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
-- DEFAULT DATA: Complaint Categories
-- ===================================================================
INSERT IGNORE INTO complaint_categories (category_name, category_description) VALUES
('Academic Integrity Violation', 'Grading disputes, favoritism, failure to meet academic standards'),
('Unprofessional Behavior', 'Discriminatory practices, harassment, intimidation, abuse of power'),
('Institutional Rules Violation', 'Breach of code of conduct, academic policies'),
('Teaching Standards Failure', 'Neglecting responsibilities, inadequate communication');

-- ===================================================================
-- DEFAULT DATA: Default Admin User
-- Username: admin
-- Password: admin123 (Please change after first login)
-- ===================================================================
INSERT IGNORE INTO users (username, email, password, full_name, role, status) VALUES
('admin', 'oswd@nemsu.edu', '$2y$10$Q49BTnExRLAm2rrvq4Gm4.w.A9c9ULH0kUATFoIFGrE7n5JeDKZjy', 'OSWD Administrator', 'oswd', 'active');

-- ===================================================================
-- INDEXES: For Performance Optimization
-- ===================================================================
CREATE INDEX idx_complaints_status ON complaints(status);
CREATE INDEX idx_complaints_category ON complaints(predicted_category);
CREATE INDEX idx_complaints_created ON complaints(created_at);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- ===================================================================
-- END OF DATABASE SCHEMA
-- ===================================================================
