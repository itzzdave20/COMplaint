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

// Site settings. Values marked Settings::get() can be changed by the Super
// Admin (Settings page); the rest are fixed here.
define('SITE_NAME', Settings::get('site_name'));
define('REGISTRATION_OPEN', Settings::get('registration_open'));
define('MAINTENANCE_MODE', Settings::get('maintenance_mode'));
define('MAINTENANCE_MESSAGE', Settings::get('maintenance_message'));
// Use the host the visitor actually reached (e.g. 192.168.1.59 from a phone on
// the same Wi-Fi) so redirects work on every device. Only localhost and
// private LAN addresses are trusted; anything else falls back to localhost so
// a forged Host header can't poison links in emails.
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$trustedHost = preg_match(
    '/^(localhost|127\.0\.0\.1|10(\.\d{1,3}){3}|192\.168(\.\d{1,3}){2}|172\.(1[6-9]|2\d|3[01])(\.\d{1,3}){2})(:\d{1,5})?$/',
    $requestHost
);
define('SITE_URL', 'http://' . ($trustedHost ? $requestHost : 'localhost') . '/Complaint');
define('SITE_LOGO', 'assets/images/nemsu-logo.png');
define('ADMIN_EMAIL', 'oswd@nemsu.edu');

$smtpConfig = __DIR__ . '/smtp.php';
if (is_file($smtpConfig)) {
    require_once $smtpConfig;
} else {
    define('SMTP_ENABLED', false);
    define('SMTP_HOST', '');
    define('SMTP_PORT', 587);
    define('SMTP_ENCRYPTION', 'tls');
    define('SMTP_USERNAME', '');
    define('SMTP_PASSWORD', '');
    define('SMTP_FROM_EMAIL', ADMIN_EMAIL);
    define('SMTP_FROM_NAME', SITE_NAME);
}

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Python ML service settings
define('PYTHON_PATH', 'python');
define('ML_MODEL_PATH', __DIR__ . '/../ml_model/');

// Login Random Forest risk scoring
define('LOGIN_RF_ENABLED', Settings::get('login_rf_enabled'));
define('LOGIN_RF_SHADOW_MODE', false);
define('LOGIN_HASH_SALT', 'nemsu-oswd-login-v1');
define('LOGIN_OTP_TTL', Settings::get('login_otp_minutes') * 60);
define('LOGIN_COOLDOWN_AFTER_FAILS', Settings::get('login_cooldown_after_fails'));
define('LOGIN_COOLDOWN_SECONDS', Settings::get('login_cooldown_seconds'));
define('LOGIN_BLOCK_AFTER_FAILS', Settings::get('login_block_after_fails'));
define('LOGIN_BLOCK_SECONDS', Settings::get('login_block_hours') * 3600);
define('LOGIN_RESET_TTL', 3600);

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
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'none'");
}


/*
 * Sign-in activity. A user counts as "Active" in User management while they
 * have opened a page within USER_ACTIVE_WINDOW seconds and not logged out.
 * Closing the browser without logging out shows "Inactive" once that time
 * has passed. Logging out clears it at once (see logout.php).
 */
define('USER_ACTIVE_WINDOW', Settings::get('user_active_minutes') * 60);

if (isset($_SESSION['user_id']) && PHP_SAPI !== 'cli') {
    // Write at most once a minute per session, not on every page load.
    if (time() - (int)($_SESSION['activity_saved_at'] ?? 0) >= 60) {
        try {
            Database::getInstance()->getConnection()
                ->prepare('UPDATE users SET last_activity_at = NOW(), is_signed_in = 1 WHERE user_id = :id')
                ->execute([':id' => (int)$_SESSION['user_id']]);
            $_SESSION['activity_saved_at'] = time();
        } catch (PDOException $e) {
            // Column missing (migration not run yet): never block the page.
        }
    }
}

/*
 * Automatic backups (Super Admin → Settings → Automatic backups). After the
 * page has been built, check whether a backup is due; usually this is just
 * a file-timestamp check. A failure here must never break the page.
 */
if (PHP_SAPI !== 'cli') {
    register_shutdown_function(function () {
        try {
            DatabaseBackup::autoBackupIfDue();
        } catch (Throwable $e) {
            error_log('Automatic backup failed: ' . $e->getMessage());
        }
    });
}

/*
 * Maintenance mode (Super Admin → Settings): everyone except the Super Admin
 * is signed out on their next page load. LoginAuth also refuses new
 * sign-ins for them while it is on.
 */
if (MAINTENANCE_MODE && isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'super_admin' && PHP_SAPI !== 'cli') {
    $_SESSION = ['message' => MAINTENANCE_MESSAGE, 'message_type' => 'warning'];
    session_regenerate_id(true);
    redirect('login.php');
}

/**
 * Status shown in User management:
 *   'deactivated' — account switched off by the Super Admin (cannot sign in)
 *   'active'      — signed in and used the system in the last 10 minutes
 *   'inactive'    — logged out, or idle longer than that
 * $user['recently_active'] is computed in SQL (USER_RECENTLY_ACTIVE_SQL) so
 * the comparison uses the database clock, whatever PHP's time zone is.
 */
define('USER_RECENTLY_ACTIVE_SQL', '(is_signed_in = 1 AND last_activity_at IS NOT NULL AND last_activity_at >= NOW() - INTERVAL ' . USER_ACTIVE_WINDOW . ' SECOND)');

function userPresence(array $user) {
    if (($user['status'] ?? '') !== 'active') {
        return 'deactivated';
    }
    return !empty($user['recently_active']) ? 'active' : 'inactive';
}

// Helper functions
function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit();
}

function nemsuLogoImg($class = 'nemsu-logo') {
    $path = SITE_LOGO;
    $full = __DIR__ . '/../' . $path;
    if (!is_file($full)) {
        return '';
    }
    $class = trim((string)$class);
    $alt = 'North Eastern Mindanao State University';
    return '<img src="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" decoding="async">';
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

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        $_SESSION['message'] = 'You do not have permission to access that page.';
        $_SESSION['message_type'] = 'danger';
        redirect('dashboard.php');
    }
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

function behavioralCategories() {
    return [
        'Academic Integrity Violation',
        'Unprofessional Behavior',
        'Institutional Rules Violation',
        'Teaching Standards Failure'
    ];
}

function serviceCategories() {
    return [
        'Campus Services',
        'Campus Facilities'
    ];
}

function allowedComplaintCategories() {
    return array_merge(behavioralCategories(), serviceCategories());
}

function complaintCategoryCards() {
    return [
        [
            'name' => 'Academic Integrity Violation',
            'type' => 'behavioral',
            'icon' => 'bi-mortarboard',
            'example' => 'Cheating, plagiarism, or exam misconduct',
            'route' => 'Starts with your Program Coordinator',
        ],
        [
            'name' => 'Unprofessional Behavior',
            'type' => 'behavioral',
            'icon' => 'bi-person-exclamation',
            'example' => 'Harassment, disrespect, or inappropriate conduct',
            'route' => 'Starts with your Program Coordinator',
        ],
        [
            'name' => 'Institutional Rules Violation',
            'type' => 'behavioral',
            'icon' => 'bi-journal-x',
            'example' => 'Breaking campus policies or code of conduct',
            'route' => 'Starts with your Program Coordinator',
        ],
        [
            'name' => 'Teaching Standards Failure',
            'type' => 'behavioral',
            'icon' => 'bi-easel',
            'example' => 'Unprepared classes, unfair grading, poor communication',
            'route' => 'Starts with your Program Coordinator',
        ],
        [
            'name' => 'Campus Services',
            'type' => 'services',
            'icon' => 'bi-building-gear',
            'example' => 'Enrollment, ID, records, or student services',
            'route' => 'Goes directly to OSWD',
        ],
        [
            'name' => 'Campus Facilities',
            'type' => 'services',
            'icon' => 'bi-tools',
            'example' => 'Classrooms, restrooms, equipment, or maintenance',
            'route' => 'Goes directly to OSWD',
        ],
    ];
}

function complaintTypeFromCategory($category) {
    return in_array($category, serviceCategories(), true) ? 'services' : 'behavioral';
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

/**
 * Every role in the system, in display order. The Super Admin is NOT a
 * staff role: it never receives or handles complaints, so staffRoles()
 * above deliberately leaves it out.
 */
function allRoles() {
    return ['student', 'program_coordinator', 'department_chair', 'guidance_office', 'oswd', 'super_admin'];
}

/**
 * The campus departments. Accounts created by the Super Admin must use one
 * of these, so every department's personnel are spelled the same way.
 */
function departments() {
    return ['DIT', 'DCS', 'CCJE', 'DBM', 'DGTT'];
}

/**
 * Roles that belong to ONE department, so a department is required for
 * them. Guidance, OSWD and the Super Admin serve the whole campus.
 */
function departmentRoles() {
    return ['program_coordinator', 'department_chair'];
}

/**
 * Strong password rules for accounts made by the Super Admin and by
 * create_super_admin.php. Returns a list of problems (empty = password OK).
 * Modelled on Django's default validators: minimum length, character mix,
 * common-password list, and similarity to the username/email.
 */
function passwordPolicyErrors($password, $username = '', $email = '') {
    $password = (string)$password;
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = 'Use at least 12 characters.';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)) {
        $errors[] = 'Use both upper-case and lower-case letters.';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Include at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Include at least one symbol (for example ! @ # $).';
    }

    // Common passwords (and these with numbers stuck on the end) are rejected.
    $common = ['password', 'admin', 'administrator', 'superadmin', 'qwerty', 'letmein', 'welcome',
               'iloveyou', 'nemsu', 'oswd', 'abc123', '123456', 'passw0rd', 'changeme'];
    $core = strtolower(preg_replace('/[^a-z]/i', '', $password));
    if (in_array($core, $common, true)) {
        $errors[] = 'This password is too common.';
    }

    // The password must not contain the username or the email's name part.
    $lower = strtolower($password);
    $emailName = strtolower(strstr((string)$email, '@', true) ?: '');
    foreach ([strtolower((string)$username), $emailName] as $personal) {
        if (strlen($personal) >= 3 && strpos($lower, $personal) !== false) {
            $errors[] = 'The password is too similar to the username or email.';
            break;
        }
    }

    return $errors;
}

function workflowLevels() {
    return ['program_coordinator', 'department_chair', 'guidance_office', 'oswd'];
}

function initialWorkflowLevel($complaintType) {
    return $complaintType === 'services' ? 'oswd' : 'program_coordinator';
}

function nextWorkflowLevel($currentLevel) {
    return match ($currentLevel) {
        'program_coordinator' => 'department_chair',
        'department_chair' => 'guidance_office',
        'guidance_office' => 'oswd',
        default => null,
    };
}

function roleLabel($role) {
    return match ($role) {
        'student' => 'Student',
        'program_coordinator' => 'Program Coordinator',
        'department_chair' => 'Program Chairperson',
        'guidance_office' => 'Guidance Counselor',
        'oswd' => 'OSWD Admin',
        'super_admin' => 'Super Admin',
        default => formatStatus($role),
    };
}

function workflowLevelLabel($level) {
    return roleLabel($level);
}

function dashboardMetaForRole($role) {
    return match ($role) {
        'student' => [
            'title' => 'Student Dashboard',
            'subtitle' => 'Submit complaints and track your own submissions only.',
            'theme' => 'primary',
        ],
        'program_coordinator' => [
            'title' => 'Program Coordinator Dashboard',
            'subtitle' => 'Behavioral complaints start here. Escalate to the Program Chair if unresolved.',
            'theme' => 'info',
        ],
        'department_chair' => [
            'title' => 'Program Chairperson Dashboard',
            'subtitle' => 'Review escalated behavioral complaints. Escalate to Guidance if needed.',
            'theme' => 'secondary',
        ],
        'guidance_office' => [
            'title' => 'Guidance Counselor Dashboard',
            'subtitle' => 'Handle escalated behavioral cases. Escalate to OSWD when necessary.',
            'theme' => 'warning',
        ],
        'oswd' => [
            'title' => 'OSWD Admin Dashboard',
            'subtitle' => 'Campus services and facilities complaints come here directly. Final level for behavioral cases.',
            'theme' => 'dark',
        ],
        'super_admin' => [
            'title' => 'Super Admin',
            'subtitle' => 'System-wide oversight: monitoring, accounts, audit and reports. View only for complaints.',
            'theme' => 'dark',
        ],
        default => [
            'title' => 'Dashboard',
            'subtitle' => '',
            'theme' => 'primary',
        ],
    };
}

function formatStatus($status) {
    return ucfirst(str_replace('_', ' ', (string)$status));
}

function statusBadgeClass($status) {
    return statusMeta($status)['bootstrap'];
}

function severityBadgeClass($severity) {
    return severityMeta($severity)['bootstrap'];
}

function statusMeta($status) {
    return match ((string)$status) {
        'resolved' => ['bootstrap' => 'success', 'icon' => 'bi-check-circle-fill', 'label' => 'Resolved'],
        'under_review' => ['bootstrap' => 'info', 'icon' => 'bi-eye-fill', 'label' => 'Under review'],
        'investigating' => ['bootstrap' => 'primary', 'icon' => 'bi-search', 'label' => 'Investigating'],
        'escalated' => ['bootstrap' => 'danger', 'icon' => 'bi-arrow-up-circle-fill', 'label' => 'Escalated'],
        'rejected' => ['bootstrap' => 'dark', 'icon' => 'bi-x-circle-fill', 'label' => 'Rejected'],
        default => ['bootstrap' => 'warning', 'icon' => 'bi-hourglass-split', 'label' => 'Pending'],
    };
}

function severityMeta($severity) {
    return match ((string)$severity) {
        'critical' => ['bootstrap' => 'danger', 'icon' => 'bi-exclamation-octagon-fill', 'label' => 'Critical'],
        'high' => ['bootstrap' => 'danger', 'icon' => 'bi-exclamation-triangle-fill', 'label' => 'High'],
        'medium' => ['bootstrap' => 'warning', 'icon' => 'bi-dash-circle-fill', 'label' => 'Medium'],
        default => ['bootstrap' => 'secondary', 'icon' => 'bi-circle', 'label' => 'Low'],
    };
}

function statusBadgeHtml($status) {
    $m = statusMeta($status);
    return '<span class="badge nemsu-badge bg-' . htmlspecialchars($m['bootstrap'], ENT_QUOTES, 'UTF-8') . '">'
        . '<i class="bi ' . htmlspecialchars($m['icon'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> '
        . '<span>' . htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8') . '</span></span>';
}

function severityBadgeHtml($severity) {
    $m = severityMeta($severity);
    return '<span class="badge nemsu-badge bg-' . htmlspecialchars($m['bootstrap'], ENT_QUOTES, 'UTF-8') . '">'
        . '<i class="bi ' . htmlspecialchars($m['icon'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> '
        . '<span>' . htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8') . '</span></span>';
}

function statusPlainLanguage($status) {
    return match ((string)$status) {
        'pending' => 'Your complaint was received and is waiting for staff review.',
        'under_review' => 'A staff member is reviewing your complaint.',
        'investigating' => 'Your case is being investigated in more detail.',
        'escalated' => 'Your complaint was escalated to the next office in the chain.',
        'resolved' => 'This complaint has been marked resolved.',
        'rejected' => 'This complaint was closed without further action.',
        default => 'Status updates will appear here as your case moves forward.',
    };
}

function expectedResponseHint($status) {
    return match ((string)$status) {
        'pending' => 'Typical first response: 3–5 school days',
        'under_review', 'investigating' => 'Updates usually within 5–7 school days',
        'escalated' => 'The next office will review within 7–10 school days',
        'resolved', 'rejected' => 'No further action expected unless you have new information',
        default => 'Response times vary by case complexity',
    };
}

function renderEmptyState($icon, $title, $message, $actionLabel = '', $actionHref = '') {
    $icon = preg_replace('/[^a-z0-9-]/i', '', (string)$icon);
    ob_start();
    echo '<div class="nemsu-empty-state text-center py-5 px-3">';
    echo '<div class="nemsu-empty-state__icon" aria-hidden="true"><i class="bi bi-' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i></div>';
    echo '<h3 class="h5">' . htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') . '</h3>';
    echo '<p class="text-muted mb-3">' . htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($actionLabel !== '' && $actionHref !== '') {
        echo '<a class="btn btn-primary" href="' . htmlspecialchars((string)$actionHref, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars((string)$actionLabel, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    echo '</div>';
    return (string)ob_get_clean();
}

function consumeFlashForToast() {
    if (empty($_SESSION['message'])) {
        return null;
    }
    $payload = [
        'message' => (string)$_SESSION['message'],
        'type' => in_array($_SESSION['message_type'] ?? 'info', ['info', 'success', 'warning', 'danger'], true)
            ? (string)$_SESSION['message_type']
            : 'info',
    ];
    unset($_SESSION['message'], $_SESSION['message_type']);
    return $payload;
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

function navLinkClass($filename) {
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return $current === $filename ? 'nav-link active' : 'nav-link';
}

function statusProgressionOrder() {
    return ['pending', 'under_review', 'investigating', 'resolved'];
}

function selectableStatusesForUpdate($currentStatus) {
    $currentStatus = (string)$currentStatus;
    if (in_array($currentStatus, ['resolved', 'rejected'], true)) {
        return [$currentStatus];
    }

    $order = statusProgressionOrder();
    $index = array_search($currentStatus, $order, true);
    if ($index === false) {
        $index = array_search('under_review', $order, true);
        if ($index === false) {
            $index = 0;
        }
    }

    $options = array_slice($order, (int)$index);
    if ($currentStatus === 'escalated' && !in_array('under_review', $options, true)) {
        array_unshift($options, 'under_review');
        $options = array_values(array_unique($options));
    }

    if (!in_array('rejected', $options, true)) {
        $options[] = 'rejected';
    }

    return $options;
}

/**
 * @return list<array{id: string, label: string, state: string}>
 */
function complaintProgressSteps(array $complaint) {
    $type = $complaint['complaint_type'] ?? complaintTypeFromCategory($complaint['complaint_category'] ?? '');
    $status = $complaint['status'] ?? 'pending';
    $level = $complaint['current_level'] ?? initialWorkflowLevel($type);

    if ($type === 'services') {
        $steps = [
            ['id' => 'submitted', 'label' => 'Submitted'],
            ['id' => 'oswd', 'label' => 'OSWD Admin'],
            ['id' => 'outcome', 'label' => 'Final Decision'],
        ];
        $levelIndex = 1;
    } else {
        $steps = [
            ['id' => 'submitted', 'label' => 'Submitted'],
            ['id' => 'program_coordinator', 'label' => 'Program Coordinator'],
            ['id' => 'department_chair', 'label' => 'Program Chair'],
            ['id' => 'guidance_office', 'label' => 'Guidance Counselor'],
            ['id' => 'oswd', 'label' => 'OSWD Admin'],
            ['id' => 'outcome', 'label' => 'Final Decision'],
        ];
        $levelMap = [
            'program_coordinator' => 1,
            'department_chair' => 2,
            'guidance_office' => 3,
            'oswd' => 4,
        ];
        $levelIndex = $levelMap[$level] ?? 1;
    }

    if ($status === 'resolved') {
        $activeIndex = count($steps) - 1;
        $steps[$activeIndex]['label'] = 'Resolved';
    } elseif ($status === 'rejected') {
        $activeIndex = count($steps) - 1;
        $steps[$activeIndex]['label'] = 'Rejected';
    } else {
        $activeIndex = $levelIndex;
    }

    foreach ($steps as $i => &$step) {
        if ($i < $activeIndex) {
            $step['state'] = 'done';
        } elseif ($i === $activeIndex) {
            $step['state'] = 'current';
        } else {
            $step['state'] = 'upcoming';
        }
    }
    unset($step);

    return $steps;
}

function renderComplaintProgressSteps(array $complaint, $compact = false) {
    $steps = complaintProgressSteps($complaint);
    $compactClass = $compact ? ' nemsu-progress--compact' : '';

    ob_start();
    echo '<div class="nemsu-progress' . $compactClass . '" role="list" aria-label="Complaint progress">';
    $total = count($steps);
    foreach ($steps as $i => $step) {
        $state = htmlspecialchars($step['state'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8');
        $isLast = $i === $total - 1;
        echo '<div class="nemsu-progress-step nemsu-progress-step--' . $state . '" role="listitem">';
        echo '<div class="nemsu-progress-marker" aria-hidden="true">';
        if ($step['state'] === 'done') {
            echo '<i class="bi bi-check-lg"></i>';
        } else {
            echo (string)($i + 1);
        }
        echo '</div>';
        echo '<span class="nemsu-progress-label">' . $label . '</span>';
        if (!$isLast) {
            echo '<div class="nemsu-progress-line" aria-hidden="true"></div>';
        }
        echo '</div>';
    }
    echo '</div>';

    return (string)ob_get_clean();
}

/**
 * Hide superseded status timeline entries; keep the latest status update only.
 *
 * @param list<array<string, mixed>> $timeline
 * @return list<array<string, mixed>>
 */
function filterTimelineForDisplay(array $timeline) {
    $latestStatusIndex = null;
    foreach ($timeline as $i => $event) {
        if (($event['action_type'] ?? '') === 'status_changed') {
            $latestStatusIndex = $i;
        }
    }

    $filtered = [];
    foreach ($timeline as $i => $event) {
        if (($event['action_type'] ?? '') === 'status_changed' && $latestStatusIndex !== null && $i !== $latestStatusIndex) {
            continue;
        }
        $filtered[] = $event;
    }

    return $filtered;
}
?>
