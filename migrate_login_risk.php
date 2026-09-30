<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

$db->exec("CREATE TABLE IF NOT EXISTS login_events (
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
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_login_events_user (user_id, created_at),
    INDEX idx_login_events_ip (ip_hash, created_at),
    INDEX idx_login_events_username (username_attempted, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS login_otps (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_login_otps_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Login risk tables ready.\n";

$trainScript = __DIR__ . '/ml_model/login_risk.py';
$python = defined('PYTHON_PATH') ? PYTHON_PATH : 'python';
if (is_file($trainScript)) {
    $candidates = ['py', 'python', 'python3'];
    foreach ($candidates as $bin) {
        if ($bin === 'C:\\xampp\\php\\..\\python\\python.exe') {
            continue;
        }
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($trainScript) . ' train 2>&1';
        $out = @shell_exec($cmd);
        if ($out && str_contains($out, '"success"')) {
            echo "Login risk model trained.\n";
            break;
        }
    }
}

echo "Run manually if needed: python ml_model/login_risk.py train\n";
