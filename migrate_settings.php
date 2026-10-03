<?php
/**
 * Migration: system settings edited by the Super Admin.
 *
 * One row per setting. A missing row means "use the default" defined in
 * classes/Settings.php, so this table can start empty.
 *
 * Safe to run more than once.  Run: php migrate_settings.php
 */
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

$db->exec("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "System settings table ready.\n";
