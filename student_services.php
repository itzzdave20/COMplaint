<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$lockout = new AccountLockout();
$prefill = sanitizeInput($_SESSION['login_gate']['username'] ?? ($_POST['username'] ?? ''));

if (isset($_POST['submit_help'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $result = $lockout->submitStudentServiceRequest(
            $_POST['username'] ?? '',
            $_POST['message'] ?? ''
        );
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
    <?php $pageTitle = 'Student Services - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="auth-body">
<?php include 'includes/skip_link.php'; ?>
<div class="auth-split">
    <?php include 'includes/auth_brand_panel.php'; ?>
    <main class="auth-panel-form" id="main-content">
        <div class="auth-panel-form__inner">
            <div class="auth-form-card">
                <h2 class="h4 mb-2">Student Services</h2>
                <p class="text-muted small mb-4">
                    If your account is locked after failed sign-in attempts, send a request here.
                    OSWD will review it and email a credential reset link if approved.
                </p>

                <?php if ($authMessage !== null): ?>
                    <div class="alert alert-<?php echo htmlspecialchars(in_array($authMessageType, ['success', 'danger', 'warning', 'info'], true) ? $authMessageType : 'info', ENT_QUOTES, 'UTF-8'); ?> py-2" role="alert" aria-live="assertive">
                        <?php echo htmlspecialchars($authMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="student_services.php" data-no-loading>
                    <?php echo csrfField(); ?>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or email</label>
                        <input type="text" class="form-control" id="username" name="username" required
                               autocomplete="username" value="<?php echo htmlspecialchars($prefill, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="message" class="form-label">What happened?</label>
                        <textarea class="form-control" id="message" name="message" rows="4" maxlength="1000"
                                  placeholder="Example: I could not sign in and my account was locked."></textarea>
                    </div>
                    <button type="submit" name="submit_help" class="btn btn-primary w-100">Send request to OSWD</button>
                </form>
                <p class="text-center small mt-4 mb-0"><a href="login.php">Back to sign in</a></p>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
