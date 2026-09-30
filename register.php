<?php
require_once 'config/config.php';
if (isLoggedIn()) {
    redirect('dashboard.php');
}

if (isset($_POST['register'])) {
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
            'student_id' => sanitizeInput($_POST['student_id'] ?? ''),
            'department' => sanitizeInput($_POST['department'] ?? ''),
            'program' => sanitizeInput($_POST['program'] ?? ''),
            'contact_number' => sanitizeInput($_POST['contact_number'] ?? ''),
        ];

        $user = new User();
        $result = $user->register($data);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            redirect('login.php');
        }
    }
}

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
                <p class="text-muted small mb-4">Use a valid email address — verification codes for sign-in are sent there.</p>

                <?php if ($authMessage !== null): ?>
                    <div class="alert alert-<?php echo htmlspecialchars(in_array($authMessageType, ['success', 'danger', 'warning', 'info'], true) ? $authMessageType : 'info', ENT_QUOTES, 'UTF-8'); ?> py-2" role="alert" aria-live="assertive">
                        <?php echo htmlspecialchars($authMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php" data-no-loading>
                    <?php echo csrfField(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required autocomplete="name">
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" minlength="3" maxlength="50" required autocomplete="username">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
                        </div>
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department">
                        </div>
                        <div class="col-md-6">
                            <label for="program" class="form-label">Program</label>
                            <input type="text" class="form-control" id="program" name="program">
                        </div>
                        <div class="col-12">
                            <label for="contact_number" class="form-label">Contact number</label>
                            <input type="tel" class="form-control" id="contact_number" name="contact_number" autocomplete="tel">
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
                <p class="text-center text-muted small mt-4 mb-0">Already registered? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
