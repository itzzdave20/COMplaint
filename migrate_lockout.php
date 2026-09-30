<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

$db->exec("CREATE TABLE IF NOT EXISTS login_lockouts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS lockout_reviews (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS credential_resets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Login lockout tables ready.\n";
