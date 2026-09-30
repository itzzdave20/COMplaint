<?php
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

function columnExists(PDO $db, $table, $column) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return (bool)$stmt->fetch();
}

if (!columnExists($db, 'complaints', 'complaint_type')) {
    $db->exec("ALTER TABLE complaints ADD COLUMN complaint_type ENUM('behavioral','services') NOT NULL DEFAULT 'behavioral' AFTER predicted_category");
    echo "Added complaint_type column\n";
}

if (!columnExists($db, 'complaints', 'current_level')) {
    $db->exec("ALTER TABLE complaints ADD COLUMN current_level ENUM('program_coordinator','department_chair','guidance_office','oswd') NOT NULL DEFAULT 'program_coordinator' AFTER assigned_to");
    echo "Added current_level column\n";
}

$db->exec("INSERT IGNORE INTO complaint_categories (category_name, category_description) VALUES
    ('Campus Services', 'Issues with campus services such as registrar, cashier, clinic, library, internet, and student support offices'),
    ('Campus Facilities', 'Issues with campus facilities such as classrooms, restrooms, buildings, equipment, lighting, and infrastructure')");
echo "Ensured service/facility categories\n";

$accounts = [
    [
        'username' => 'coordinator',
        'email' => 'coordinator@nemsu.edu',
        'password' => '$2y$10$PPiQfoG62n8uoMZn9EXER.lGccNMxuBuaICIjchkIR5P5Y1mogqWu',
        'full_name' => 'Program Coordinator',
        'role' => 'program_coordinator'
    ],
    [
        'username' => 'chairperson',
        'email' => 'chairperson@nemsu.edu',
        'password' => '$2y$10$gzEwzSOrzUK/hGI18GRLketjF7ST2F3nOw/xTxvtiP4UWzCRswHam',
        'full_name' => 'Program Chairperson',
        'role' => 'department_chair'
    ],
    [
        'username' => 'counselor',
        'email' => 'counselor@nemsu.edu',
        'password' => '$2y$10$/O7z2edOJUkcq81Rnvw52OY3eRZH5aUBAxWt0scgTAarYpxMU5/XS',
        'full_name' => 'Guidance Counselor',
        'role' => 'guidance_office'
    ],
    [
        'username' => 'oswdadmin',
        'email' => 'oswdadmin@nemsu.edu',
        'password' => '$2y$10$fuIeFJswJEByZd4kfVMjbuVTMG3qvGget65/9f3eMUqOkV8XZzLLG',
        'full_name' => 'OSWD Administrator',
        'role' => 'oswd'
    ],
];

$insert = $db->prepare("INSERT IGNORE INTO users (username, email, password, full_name, role, status)
    VALUES (:username, :email, :password, :full_name, :role, 'active')");

foreach ($accounts as $account) {
    $insert->execute($account);
    echo "Ensured account: {$account['username']} ({$account['role']})\n";
}

$db->exec("UPDATE complaints
    SET complaint_type = CASE
            WHEN complaint_category IN ('Campus Services', 'Campus Facilities') THEN 'services'
            ELSE 'behavioral'
        END,
        current_level = CASE
            WHEN complaint_category IN ('Campus Services', 'Campus Facilities') THEN 'oswd'
            WHEN assigned_to IS NOT NULL THEN 'oswd'
            ELSE current_level
        END");

echo "Default passwords: coordinator/coordinator123, chairperson/chair123, counselor/counselor123, oswdadmin/oswdadmin123\n";
echo "Migration complete.\n";
