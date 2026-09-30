<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$lockout = new AccountLockout();

if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_unlock_otp']);
    redirect('login.php');
}

if (isset($_POST['verify_otp'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $result = $lockout->verifyUnlockOtp($_POST['otp_code'] ?? '');
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            redirect('login.php');
        }
    }
}

if (isset($_POST['resend_otp'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $result = $lockout->resendUnlockOtp();
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'info' : 'danger';
    }
    redirect('reset_credentials.php');
}

if (isset($_POST['save_credentials'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } elseif (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        $_SESSION['message'] = 'Passwords do not match.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $result = $lockout->applyCredentialReset(
            $_POST['token'] ?? '',
            $_POST['username'] ?? '',
            $_POST['password'] ?? ''
        );
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = !empty($result['success']) ? 'info' : 'danger';
        if (!empty($result['otp_required'])) {
            redirect('reset_credentials.php');
        }
    }
}

$showOtpStep = $lockout->hasPendingUnlockOtp();
$token = trim((string)($_POST['token'] ?? ($_GET['token'] ?? '')));
$reset = $showOtpStep ? null : $lockout->getResetByToken($token);
$maskedEmail = '';
if ($showOtpStep && !empty($_SESSION['pending_unlock_otp']['email'])) {
    $email = (string)$_SESSION['pending_unlock_otp']['email'];
    $parts = explode('@', $email, 2);
    $maskedEmail = (count($parts) === 2 && strlen($parts[0]) > 1)
        ? substr($parts[0], 0, 1) . '***@' . $parts[1]
        : $email;
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
    <?php $pageTitle = 'Reset credentials - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="auth-body">
<?php include 'includes/skip_link.php'; ?>
<div class="auth-split">
    <?php include 'includes/auth_brand_panel.php'; ?>
    <main class="auth-panel-form" id="main-content">
        <div class="auth-panel-form__inner">
            <div class="auth-form-card">
                <?php if ($showOtpStep): ?>
                    <h2 class="h4 mb-2">Verify your new credentials</h2>
                    <p class="text-muted small mb-2">Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>.</p>
                    <p class="text-muted small mb-4">This finishes unlocking your account. Check inbox and spam. Sender: <?php echo htmlspecialchars(SMTP_FROM_EMAIL); ?>.</p>
                <?php elseif ($reset): ?>
                    <h2 class="h4 mb-2">Set new credentials</h2>
                    <p class="text-muted small mb-4">Choose a new username and password for <?php echo htmlspecialchars($reset['full_name'] ?? 'your account'); ?>.</p>
                <?php else: ?>
                    <h2 class="h4 mb-2">Reset link unavailable</h2>
                    <p class="text-muted small mb-4">This link is invalid or has expired. Ask Student Services to send a new request to OSWD.</p>
                <?php endif; ?>

                <?php if ($authMessage !== null): ?>
                    <div class="alert alert-<?php echo htmlspecialchars(in_array($authMessageType, ['success', 'danger', 'warning', 'info'], true) ? $authMessageType : 'info', ENT_QUOTES, 'UTF-8'); ?> py-2" role="alert" aria-live="assertive">
                        <?php echo htmlspecialchars($authMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($showOtpStep): ?>
                    <form method="POST" action="reset_credentials.php" id="otpForm" data-no-loading>
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
                        <button type="submit" name="verify_otp" class="btn btn-primary w-100">Verify and unlock</button>
                    </form>
                    <form method="POST" action="reset_credentials.php" class="mt-3 text-center" data-no-loading>
                        <?php echo csrfField(); ?>
                        <button type="submit" name="resend_otp" class="btn btn-link btn-sm" id="resendOtpBtn" data-cooldown="60">Resend code</button>
                        <div class="small text-muted" id="resendOtpTimer" aria-live="polite"></div>
                    </form>
                    <p class="text-center mt-3 mb-0"><a href="reset_credentials.php?cancel_otp=1" class="small">Cancel and return to sign in</a></p>
                <?php elseif ($reset): ?>
                    <form method="POST" action="reset_credentials.php" data-no-loading>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">New username</label>
                            <input type="text" class="form-control" id="username" name="username" required
                                   minlength="3" maxlength="50" autocomplete="username"
                                   value="<?php echo htmlspecialchars($reset['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required minlength="6"
                                       autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="password"
                                        aria-label="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6"
                                       autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="confirm_password"
                                        aria-label="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <button type="submit" name="save_credentials" class="btn btn-primary w-100">Save and send verification code</button>
                    </form>
                    <p class="text-center small mt-4 mb-0"><a href="login.php">Back to sign in</a></p>
                <?php else: ?>
                    <a href="student_services.php" class="btn btn-primary w-100">Contact Student Services</a>
                    <p class="text-center small mt-4 mb-0"><a href="login.php">Back to sign in</a></p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>

