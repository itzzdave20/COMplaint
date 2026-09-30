<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_login_otp']);
    redirect('login.php');
}

$loginAuth = new LoginAuth();

if (isset($_POST['verify_otp'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $result = $loginAuth->verifyOtp($_POST['otp_code'] ?? '');
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = 'success';
            redirect('dashboard.php');
        }
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = 'danger';
    }
}

if (isset($_POST['resend_otp'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $result = $loginAuth->resendOtp();
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'info' : 'danger';
    }
    redirect('login.php');
}

if (isset($_POST['login'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $result = $loginAuth->attempt($username, $password);

        if (!empty($result['success'])) {
            redirect('dashboard.php');
        }

        if (!empty($result['otp_required'])) {
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = 'info';
            redirect('login.php');
        }

        $_SESSION['message'] = $result['message'] ?? 'Login failed.';
        $_SESSION['message_type'] = 'danger';
    }
}

$showOtpStep = $loginAuth->hasPendingOtp();
$maskedEmail = '';
if ($showOtpStep && !empty($_SESSION['pending_login_otp']['email'])) {
    $email = (string)$_SESSION['pending_login_otp']['email'];
    $parts = explode('@', $email, 2);
    if (count($parts) === 2 && strlen($parts[0]) > 1) {
        $maskedEmail = substr($parts[0], 0, 1) . '***@' . $parts[1];
    } else {
        $maskedEmail = $email;
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
    <?php $pageTitle = 'Login - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="auth-body">
<?php include 'includes/skip_link.php'; ?>
<div class="auth-split">
    <?php include 'includes/auth_brand_panel.php'; ?>
    <main class="auth-panel-form" id="main-content">
        <div class="auth-panel-form__inner">
            <div class="auth-form-card">
                <?php if ($showOtpStep): ?>
                    <h2 class="h4 mb-2">Verify your sign-in</h2>
                    <p class="text-muted small mb-2">Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>.</p>
                    <p class="text-muted small mb-4">Check your inbox and spam folder. Sender: <?php echo htmlspecialchars(SMTP_FROM_EMAIL); ?>.</p>
                <?php else: ?>
                    <h2 class="h4 mb-2">Sign in</h2>
                    <p class="text-muted small mb-4">Use your student or staff account for NEMSU Cantilan.</p>
                <?php endif; ?>

                <?php if ($authMessage !== null): ?>
                    <div class="alert alert-<?php echo htmlspecialchars(in_array($authMessageType, ['success', 'danger', 'warning', 'info'], true) ? $authMessageType : 'info', ENT_QUOTES, 'UTF-8'); ?> py-2" role="alert" aria-live="assertive">
                        <?php echo htmlspecialchars($authMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($showOtpStep): ?>
                    <form method="POST" action="login.php" id="otpForm" data-no-loading>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="otp_code" id="otp_code" value="">
                        <div class="mb-3">
                            <label class="form-label">Verification code</label>
                            <div class="otp-input-group" role="group" aria-label="Six digit verification code">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" class="form-control otp-digit" inputmode="numeric" maxlength="1"
                                           autocomplete="one-time-code" aria-label="Digit <?php echo $i + 1; ?>"<?php echo $i === 0 ? ' autofocus' : ''; ?>>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <button type="submit" name="verify_otp" class="btn btn-primary w-100">Verify and continue</button>
                    </form>
                    <form method="POST" action="login.php" class="mt-3 text-center" data-no-loading>
                        <?php echo csrfField(); ?>
                        <button type="submit" name="resend_otp" class="btn btn-link btn-sm" id="resendOtpBtn" data-cooldown="60">Resend code</button>
                        <div class="small text-muted" id="resendOtpTimer" aria-live="polite"></div>
                    </form>
                    <p class="text-center mt-3 mb-0"><a href="login.php?cancel_otp=1" class="small">Cancel and sign in again</a></p>
                <?php else: ?>
                    <form method="POST" action="login.php" data-no-loading>
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required autofocus
                                       autocomplete="username">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required
                                       autocomplete="current-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="password"
                                        aria-label="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100">Sign in</button>
                    </form>
                    <p class="text-center text-muted small mt-4 mb-0">No account? <a href="register.php">Register as a student</a></p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
