<?php
/**
 * One-time / repeat setup: run all DB migrations and train login RF model.
 * CLI: php setup_system.php
 * Browser (localhost only): http://localhost/Complaint/setup_system.php
 */
if (PHP_SAPI !== 'cli') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $local = $host === 'localhost'
        || str_starts_with($host, 'localhost:')
        || $host === '127.0.0.1'
        || str_starts_with($host, '127.0.0.1:');
    if (!$local) {
        http_response_code(403);
        exit('Setup is only allowed from localhost or via CLI.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$root = __DIR__;
$migrations = [
    'migrate_workflow.php',
    'migrate_notifications.php',
    'migrate_login_risk.php',
];

echo "=== OSWD Complaint System Setup ===\n\n";

foreach ($migrations as $file) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        echo "[SKIP] Missing {$file}\n";
        continue;
    }
    echo "--- {$file} ---\n";
    include $path;
    echo "\n";
}

echo "=== Setup complete ===\n";
echo "Next: log in at " . (PHP_SAPI === 'cli' ? 'http://localhost/Complaint/login.php' : 'login.php') . "\n";
echo "OSWD: review Login Security after test logins.\n";
