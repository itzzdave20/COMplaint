<?php
/**
 * General Configuration
 * OSWD Complaint System
 */

// Session cookie hardening must happen before the session starts
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site settings
define('SITE_NAME', 'OSWD Complaint System');
define('SITE_URL', 'http://localhost/Complaint');
define('ADMIN_EMAIL', 'oswd@nemsu.edu');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Python ML service settings
define('PYTHON_PATH', 'python');
define('ML_MODEL_PATH', __DIR__ . '/../ml_model/');

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('X-XSS-Protection: 1; mode=block');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src 'self' https://cdn.jsdelivr.net; font-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; frame-ancestors 'none'");
}

// Include database configuration
require_once __DIR__ . '/database.php';

// Autoload classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../classes/',
        __DIR__ . '/../controllers/',
        __DIR__ . '/../models/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Helper functions
function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['role'] ?? '';
    if (is_array($roles)) {
        return in_array($userRole, $roles, true);
    }
    return $userRole === $roles;
}

function sanitizeInput($data) {
    return trim((string)$data);
}

function emptyToNull($value) {
    $value = is_string($value) ? trim($value) : $value;
    return ($value === '' || $value === null) ? null : $value;
}

function showAlert($message, $type = 'info') {
    $safeType = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
    return "<div class='alert alert-{$safeType} alert-dismissible fade show' role='alert'>"
        . htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8')
        . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

function allowedComplaintStatuses() {
    return ['pending', 'under_review', 'investigating', 'resolved', 'rejected', 'escalated'];
}

function allowedComplaintCategories() {
    return [
        'Academic Integrity Violation',
        'Unprofessional Behavior',
        'Institutional Rules Violation',
        'Teaching Standards Failure'
    ];
}

function allowedRespondentTypes() {
    return ['faculty', 'staff', 'student', 'other'];
}

function allowedSeverities() {
    return ['low', 'medium', 'high', 'critical'];
}

function staffRoles() {
    return ['oswd', 'program_coordinator', 'department_chair', 'guidance_office'];
}

function formatStatus($status) {
    return ucfirst(str_replace('_', ' ', (string)$status));
}

function statusBadgeClass($status) {
    return match ($status) {
        'resolved' => 'success',
        'under_review', 'investigating' => 'info',
        'escalated' => 'danger',
        'rejected' => 'dark',
        default => 'warning',
    };
}

function severityBadgeClass($severity) {
    return match ($severity) {
        'critical', 'high' => 'danger',
        'medium' => 'warning',
        default => 'secondary',
    };
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
