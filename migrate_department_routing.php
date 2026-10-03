<?php
/**
 * Migration: department-based complaint routing.
 *
 * Adds complaints.department — the complainant's department at the moment
 * the complaint was submitted. Routing uses this snapshot, so a student who
 * later edits their profile does not move complaints already in progress.
 *
 * Safe to run more than once. Existing complaints are only back-filled
 * (never re-routed): they stay with whoever they are assigned to now.
 *
 * Run:  php migrate_department_routing.php   (or via setup_system.php)
 */
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance()->getConnection();

if (!$db->query("SHOW COLUMNS FROM complaints LIKE 'department'")->fetch()) {
    $db->exec("ALTER TABLE complaints ADD COLUMN department VARCHAR(20) NULL AFTER complaint_type,
               ADD INDEX idx_complaints_department (department)");
    echo "Added complaints.department column\n";
}

// Normalise spelling first: older accounts typed the department by hand
// (e.g. "dcs"). Rewrite case variants to the official form ("DCS").
$normalise = $db->prepare("UPDATE users SET department = :official
                           WHERE LOWER(TRIM(department)) = LOWER(:match) AND department <> BINARY :official2");
$fixedUsers = 0;
foreach (departments() as $dept) {
    $normalise->execute([':official' => $dept, ':match' => $dept, ':official2' => $dept]);
    $fixedUsers += $normalise->rowCount();
}
$fixedComplaints = 0;
$normaliseComplaints = $db->prepare("UPDATE complaints SET department = :official
                                     WHERE LOWER(department) = LOWER(:match) AND department <> BINARY :official2");
foreach (departments() as $dept) {
    $normaliseComplaints->execute([':official' => $dept, ':match' => $dept, ':official2' => $dept]);
    $fixedComplaints += $normaliseComplaints->rowCount();
}
if ($fixedUsers || $fixedComplaints) {
    echo "Normalised department spelling on {$fixedUsers} account(s) and {$fixedComplaints} complaint(s)\n";
}

// Back-fill from the complainant's account, but only with valid departments.
$valid = "'" . implode("','", departments()) . "'";
$filled = $db->exec("UPDATE complaints c JOIN users u ON u.user_id = c.complainant_id
                     SET c.department = u.department
                     WHERE c.department IS NULL AND u.department IN ({$valid})");
if ($filled) {
    echo "Recorded the department of {$filled} existing complaint(s)\n";
}

echo "Department routing ready.\n";
