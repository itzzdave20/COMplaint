<?php
require_once 'config/config.php';
if (isLoggedIn()) {
    redirect('dashboard.php');
}

/*
 * Registration has two steps:
 *   1. The student enters their Student ID. It must be on the enrolled list
 *      uploaded by OSWD (enrolled_students) and not already have an account.
 *   2. Only then is the account form shown. The verified ID is kept in the
 *      session — never taken from the form — so it cannot be swapped.
 * User::register() repeats the check when the account is created.
 */
$enrollment = new EnrollmentList();

// "Use a different Student ID" link: go back to step 1.
if (isset($_GET['change_id'])) {
    unset($_SESSION['registration_student_id']);
    redirect('register.php');
}

if (isset($_POST['verify_student_id']) && (!REGISTRATION_OPEN || MAINTENANCE_MODE)) {
    redirect('register.php');   // closed: the page explains why
}

if (isset($_POST['verify_student_id'])) {
    // Limit guesses: 10 checks per 15 minutes per browser session, so the
    // form cannot be used to discover which IDs are on the list.
    $window = $_SESSION['id_check_window'] ?? ['start' => time(), 'count' => 0];
    if (time() - $window['start'] > 900) {
        $window = ['start' => time(), 'count' => 0];
    }
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } elseif ($window['count'] >= 10) {
        $_SESSION['message'] = 'Too many Student ID checks. Please wait 15 minutes and try again, or contact OSWD.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $window['count']++;
        $check = $enrollment->verifyForRegistration($_POST['student_id'] ?? '');
        if ($check['ok']) {
            $_SESSION['registration_student_id'] = $check['record']['student_id'];
            $_SESSION['message'] = 'Student ID verified. Complete your account details below.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = $check['message'];
            $_SESSION['message_type'] = 'danger';
        }
    }
    $_SESSION['id_check_window'] = $window;
    redirect('register.php');
}

$verifiedId = $_SESSION['registration_student_id'] ?? null;

if (isset($_POST['register']) && !$verifiedId) {
    // Account form submitted without passing step 1.
    $_SESSION['message'] = 'Please verify your Student ID first.';
    $_SESSION['message_type'] = 'danger';
} elseif (isset($_POST['register'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } elseif (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        $_SESSION['message'] = 'Passwords do not match.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $data = [
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'full_name' => sanitizeInput($_POST['full_name'] ?? ''),
            'role' => 'student',
            'student_id' => $verifiedId,   // from step 1, not from the form
            'department' => sanitizeInput($_POST['department'] ?? ''),
            'program' => sanitizeInput($_POST['program'] ?? ''),
            'contact_number' => sanitizeInput($_POST['contact_number'] ?? ''),
        ];

        $user = new User();
        $result = $user->register($data);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            unset($_SESSION['registration_student_id'], $_SESSION['id_check_window']);
            redirect('login.php');
        }
    }
}

// Step 2 details: re-check the verified ID (OSWD may have removed it, or
// someone may have registered it meanwhile) and pre-fill from the list.
$enrolledRecord = null;
if ($verifiedId) {
    $recheck = $enrollment->verifyForRegistration($verifiedId);
    if ($recheck['ok']) {
        $enrolledRecord = $recheck['record'];
    } else {
        unset($_SESSION['registration_student_id']);
        $verifiedId = null;
        $_SESSION['message'] = $recheck['message'];
        $_SESSION['message_type'] = 'danger';
    }
}
/** Value to show in a step-2 field: what was typed, else the enrolled list's value. */
$fieldValue = function ($field) use ($enrolledRecord) {
    return htmlspecialchars((string)($_POST[$field] ?? ($enrolledRecord[$field] ?? '')), ENT_QUOTES, 'UTF-8');
};

$authMessage = $_SESSION['message'] ?? null;
$authMessageType = $_SESSION['message_type'] ?? 'info';
if ($authMessage !== null) {
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Register - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="auth-body">
<?php include 'includes/skip_link.php'; ?>
<div class="auth-split">
    <?php include 'includes/auth_brand_panel.php'; ?>
    <main class="auth-panel-form" id="main-content">
        <div class="auth-panel-form__inner auth-panel-form__inner--wide">
            <div class="auth-form-card">
                <h2 class="h4 mb-2">Student registration</h2>
                <p class="text-muted small mb-3">
                    <?php echo $verifiedId
                        ? 'Step 2 of 2 — create your account. Use a valid email address: sign-in verification codes are sent there.'
                        : 'Step 1 of 2 — verify that you are an enrolled student.'; ?>
                </p>
                <div class="progress mb-4" style="height: 4px" role="progressbar" aria-label="Registration progress"
                     aria-valuenow="<?php echo $verifiedId ? 100 : 50; ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: <?php echo $verifiedId ? 100 : 50; ?>%"></div>
                </div>

                <?php if ($authMessage !== null): ?>
                    <div class="alert alert-<?php echo htmlspecialchars(in_array($authMessageType, ['success', 'danger', 'warning', 'info'], true) ? $authMessageType : 'info', ENT_QUOTES, 'UTF-8'); ?> py-2" role="alert" aria-live="assertive">
                        <?php echo htmlspecialchars($authMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!REGISTRATION_OPEN || MAINTENANCE_MODE): ?>
                <!-- Closed by the Super Admin (Settings) -->
                <div class="alert alert-warning" role="status">
                    <i class="bi bi-lock me-1" aria-hidden="true"></i>
                    <?php echo MAINTENANCE_MODE
                        ? htmlspecialchars(MAINTENANCE_MESSAGE, ENT_QUOTES, 'UTF-8')
                        : 'Student registration is currently closed. Please check again later or contact OSWD.'; ?>
                </div>
                <?php elseif (!$verifiedId): ?>
                <!-- ===== Step 1: verify the Student ID against OSWD's enrolled list ===== -->
                <form method="POST" action="register.php" data-no-loading>
                    <?php echo csrfField(); ?>
                    <label for="student_id" class="form-label">Student ID *</label>
                    <input type="text" class="form-control form-control-lg" id="student_id" name="student_id" required
                           autocomplete="off" placeholder="e.g. 2023-01721" maxlength="50" autofocus>
                    <div class="form-text">Your ID must be on the list of enrolled students provided by OSWD.</div>
                    <button type="submit" name="verify_student_id" value="1" class="btn btn-primary w-100 mt-4">
                        Verify Student ID <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                    </button>
                </form>
                <?php else: ?>
                <!-- ===== Step 2: account details (only after the ID is verified) ===== -->
                <form method="POST" action="register.php" data-no-loading>
                    <?php echo csrfField(); ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="student_id" class="form-label">Student ID</label>
                            <div class="input-group">
                                <span class="input-group-text text-success"><i class="bi bi-patch-check-fill" aria-hidden="true"></i></span>
                                <input type="text" class="form-control" id="student_id" value="<?php echo htmlspecialchars($verifiedId); ?>" readonly>
                                <a href="register.php?change_id=1" class="btn btn-outline-secondary">Change</a>
                            </div>
                            <div class="form-text">Verified against the enrolled students list.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required autocomplete="name" value="<?php echo $fieldValue('full_name'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" minlength="3" maxlength="50" required autocomplete="username" value="<?php echo $fieldValue('username'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required autocomplete="email" value="<?php echo $fieldValue('email'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department *</label>
                            <?php if (!empty($enrolledRecord['department'])): ?>
                                <!-- Department comes from OSWD's list and cannot be changed here -->
                                <input type="text" class="form-control" id="department" value="<?php echo htmlspecialchars($enrolledRecord['department']); ?>" readonly>
                                <input type="hidden" name="department" value="<?php echo htmlspecialchars($enrolledRecord['department']); ?>">
                            <?php else: ?>
                                <!-- Fixed list: complaints are routed to this department's personnel -->
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Choose your department</option>
                                    <?php foreach (departments() as $dept): ?>
                                        <option value="<?php echo $dept; ?>" <?php echo ($_POST['department'] ?? '') === $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="program" class="form-label">Program</label>
                            <input type="text" class="form-control" id="program" name="program" value="<?php echo $fieldValue('program'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="contact_number" class="form-label">Contact number</label>
                            <input type="tel" class="form-control" id="contact_number" name="contact_number" autocomplete="tel" value="<?php echo $fieldValue('contact_number'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                            <div class="form-text">At least 6 characters</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="confirm_password" aria-label="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="register" class="btn btn-primary w-100 mt-4">Create account</button>
                </form>
                <?php endif; ?>
                <p class="text-center text-muted small mt-4 mb-0">Already registered? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
