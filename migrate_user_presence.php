<?php
/**
 * Migration: sign-in activity ("Active" / "Inactive" in User management).
 *
 * users.last_activity_at = when the user last opened a page while signed in.
 *   - updated by config.php on page loads (at most once a minute)
 * users.is_signed_in   = 1 while signed in; logout.php sets it to 0 so the
 *   user shows "Inactive" immediately (last_activity_at is kept as "last seen").
 *
 * This is separate from users.status (active/inactive), which is the
 * account switch that allows or blocks signing in.
 *
 * Safe to run more than once.  Run: php migrate_user_presence.php
 */
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

if (!$db->query("SHOW COLUMNS FROM users LIKE 'last_activity_at'")->fetch()) {
    $db->exec("ALTER TABLE users ADD COLUMN last_activity_at DATETIME NULL AFTER status");
    echo "Added users.last_activity_at column\n";
}
if (!$db->query("SHOW COLUMNS FROM users LIKE 'is_signed_in'")->fetch()) {
    $db->exec("ALTER TABLE users ADD COLUMN is_signed_in TINYINT(1) NOT NULL DEFAULT 0 AFTER last_activity_at");
    echo "Added users.is_signed_in column\n";
}

echo "User presence ready.\n";
