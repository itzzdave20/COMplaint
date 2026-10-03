<?php
/**
 * Migration: Super Admin role, student aliases, and the audit log.
 *
 * Safe to run more than once — every step checks first and only ADDS
 * things, so existing users, complaints and login data are never changed
 * (except giving existing students an alias, which they did not have).
 *
 * Run:  php migrate_super_admin.php   (or via setup_system.php)
 */
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

/* ------------------------------------------------------------------
 * 1. Add 'super_admin' to the allowed values of users.role.
 *    MODIFY keeps every existing value, so current accounts are untouched.
 * ------------------------------------------------------------------ */
$roleColumn = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
if ($roleColumn && strpos($roleColumn['Type'], "'super_admin'") === false) {
    $db->exec("ALTER TABLE users MODIFY role
        ENUM('student','program_coordinator','department_chair','guidance_office','oswd','super_admin') NOT NULL");
    echo "Added super_admin role\n";
}

/* ------------------------------------------------------------------
 * 2. Student aliases. Personnel see "Student-7F3KQ2" instead of the real
 *    name; only the Super Admin can reveal who is behind an alias.
 * ------------------------------------------------------------------ */
if (!$db->query("SHOW COLUMNS FROM users LIKE 'alias'")->fetch()) {
    $db->exec("ALTER TABLE users ADD COLUMN alias VARCHAR(20) NULL UNIQUE AFTER full_name");
    echo "Added users.alias column\n";
}

// Give every existing student without an alias a new random one.
$missing = $db->query("SELECT user_id FROM users WHERE role = 'student' AND alias IS NULL")->fetchAll();
$setAlias = $db->prepare("UPDATE users SET alias = :alias WHERE user_id = :id");
$aliasTaken = $db->prepare("SELECT 1 FROM users WHERE alias = :alias");
foreach ($missing as $row) {
    do {
        // Same format as User::generateAlias() — no 0/O/1/I to avoid confusion.
        $alias = 'Student-';
        for ($i = 0; $i < 6; $i++) {
            $alias .= '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)];
        }
        $aliasTaken->execute([':alias' => $alias]);
    } while ($aliasTaken->fetch());
    $setAlias->execute([':alias' => $alias, ':id' => $row['user_id']]);
}
if ($missing) {
    echo 'Assigned aliases to ' . count($missing) . " existing student(s)\n";
}

/* ------------------------------------------------------------------
 * 3. Audit log. Records identity reveals, account changes, unlocks,
 *    password resets and exports.
 *
 *    No foreign keys on purpose: if a user is later deleted, MySQL would
 *    try to UPDATE the log rows (SET NULL), which the triggers below forbid.
 *    The actor's username is copied into the row instead.
 * ------------------------------------------------------------------ */
$db->exec("CREATE TABLE IF NOT EXISTS super_admin_audit_log (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ------------------------------------------------------------------
 * 4. Make the audit log append-only AT THE DATABASE LEVEL.
 *    These triggers reject every UPDATE and DELETE on the table, so even
 *    a bug in the PHP code (or the Super Admin) cannot alter history.
 * ------------------------------------------------------------------ */
$triggers = [
    'trg_audit_log_no_update' => 'BEFORE UPDATE',
    'trg_audit_log_no_delete' => 'BEFORE DELETE',
];
$exists = $db->prepare("SELECT 1 FROM information_schema.TRIGGERS
                        WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :name");
foreach ($triggers as $name => $timing) {
    $exists->execute([':name' => $name]);
    if (!$exists->fetch()) {
        $db->exec("CREATE TRIGGER {$name} {$timing} ON super_admin_audit_log FOR EACH ROW
                   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log is read-only.'");
        echo "Created trigger {$name}\n";
    }
}

echo "Super admin tables ready.\n";
