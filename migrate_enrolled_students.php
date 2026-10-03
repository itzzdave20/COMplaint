<?php
/**
 * Migration: list of enrolled students uploaded by OSWD.
 *
 * Registration checks this table: a new student can only create an account
 * if their Student ID is in it (see EnrollmentList and register.php).
 *
 * Safe to run more than once.  Run: php migrate_enrolled_students.php
 */
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

$db->exec("CREATE TABLE IF NOT EXISTS enrolled_students (
    enrolled_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,          -- stored normalised (trimmed, upper-case)
    full_name VARCHAR(200) NULL,              -- optional, from the uploaded file
    department VARCHAR(20) NULL,              -- optional; one of departments()
    program VARCHAR(100) NULL,                -- optional
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_enrolled_student_id (student_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Enrolled students table ready.\n";
