<?php
/**
 * Copy this file to smtp.php and set your mail server credentials.
 * smtp.php is listed in .gitignore — do not commit passwords.
 */

define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls'); // tls | ssl | none
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'OSWD Complaint System');
