<?php
/**
 * Optional: run automatic backups from Windows Task Scheduler (or cron).
 *
 * Backups already happen automatically while people use the system. Use
 * this only if you also want them on days nobody signs in.
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\COMplaint\backup_cron.php
 *       → backs up only if one is due (follows the Settings interval)
 *   C:\xampp\php\php.exe C:\xampp\htdocs\COMplaint\backup_cron.php --force
 *       → backs up now
 *
 * Refuses to run from a web browser.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/config.php';

$name = DatabaseBackup::autoBackupIfDue(in_array('--force', $argv, true));
echo $name ? "Backup created: {$name}\n" : "No backup needed yet (or automatic backups are turned off).\n";
