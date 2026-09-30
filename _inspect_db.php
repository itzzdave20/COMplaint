<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();

echo "COMPLAINT COLUMNS:\n";
foreach ($db->query('SHOW COLUMNS FROM complaints') as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . "\n";
}

echo "\nUSER COLUMNS:\n";
foreach ($db->query('SHOW COLUMNS FROM users') as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . "\n";
}

echo "\nUSERS:\n";
foreach ($db->query('SELECT user_id, username, role, status FROM users') as $u) {
    echo $u['user_id'] . ' | ' . $u['username'] . ' | ' . $u['role'] . ' | ' . $u['status'] . "\n";
}

echo "\nCATEGORIES:\n";
foreach ($db->query('SELECT category_name FROM complaint_categories') as $c) {
    echo $c['category_name'] . "\n";
}
