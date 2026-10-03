<?php
/**
 * Super Admin — Settings.
 * Edits the values defined in Settings::DEFINITIONS. The form is built from
 * those definitions, so adding a setting there adds it here automatically.
 * Changes are validated, saved, and audit-logged as old → new.
 */
require_once 'config/config.php';
requireRole('super_admin');

$formErrors = [];
$testEmailResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        redirect('super_admin_settings.php');
    }

    if (isset($_POST['save_settings'])) {
        $result = Settings::save($_POST, (int)$_SESSION['user_id']);
        if ($result['success']) {
            $_SESSION['message'] = $result['changed'] ? $result['changed'] . ' setting(s) saved.' : 'No changes to save.';
            $_SESSION['message_type'] = 'success';
            redirect('super_admin_settings.php');
        }
        $formErrors = $result['errors'];
    }

    if (isset($_POST['test_email'])) {
        // Send a test message to the signed-in Super Admin's own address.
        $to = (string)($_SESSION['email'] ?? '');
        $sent = filter_var($to, FILTER_VALIDATE_EMAIL) && Mailer::send(
            $to,
            $_SESSION['full_name'] ?? '',
            'Test email from ' . SITE_NAME,
            "This is a test email from the Super Admin Settings page.\n\nIf you can read this, outgoing email (SMTP) works."
        );
        (new AuditLog())->record('test_email', 'settings', null, $to, null, ['result' => $sent ? 'sent' : 'failed']);
        $testEmailResult = $sent
            ? ['success' => true, 'message' => 'Test email sent to ' . $to . '. Check the inbox (and spam folder).']
            : ['success' => false, 'message' => 'The test email could not be sent: ' . (Mailer::getLastError() ?: 'check config/smtp.php.')];
    }
}

/** Value to show in a field: what was just typed (if saving failed), else the saved value. */
function settingFieldValue($key, array $formErrors) {
    if ($formErrors && Settings::DEFINITIONS[$key]['type'] !== 'bool') {
        return (string)($_POST[$key] ?? '');
    }
    if ($formErrors) {
        return !empty($_POST[$key]);
    }
    return Settings::get($key);
}

$pageTitle = 'Settings';
$pageHeading = 'Settings';
$pageSubtitle = 'System-wide options. Every change is recorded in the audit log.';
include 'includes/super_admin_top.php';
?>

<?php if ($formErrors): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0"><?php foreach ($formErrors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if (MAINTENANCE_MODE): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2" role="status">
        <i class="bi bi-cone-striped" aria-hidden="true"></i>
        <span><strong>Maintenance mode is on.</strong> Only Super Admins can sign in right now.</span>
    </div>
<?php endif; ?>

<form method="POST" action="super_admin_settings.php">
    <?php echo csrfField(); ?>
    <div class="row g-4">
        <?php foreach (Settings::GROUPS as $group => [$groupLabel, $groupIcon]): ?>
            <div class="col-xl-6">
                <div class="card nemsu-panel h-100">
                    <div class="card-header"><i class="bi bi-<?php echo $groupIcon; ?> me-2" aria-hidden="true"></i><?php echo $groupLabel; ?></div>
                    <div class="card-body">
                        <?php foreach (Settings::DEFINITIONS as $key => $def): ?>
                            <?php if ($def['group'] !== $group) { continue; } ?>
                            <?php $value = settingFieldValue($key, $formErrors); ?>
                            <div class="mb-3">
                                <?php if ($def['type'] === 'bool'): ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="1" <?php echo $value ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="<?php echo $key; ?>"><?php echo htmlspecialchars($def['label']); ?></label>
                                    </div>
                                <?php else: ?>
                                    <label class="form-label fw-semibold" for="<?php echo $key; ?>"><?php echo htmlspecialchars($def['label']); ?></label>
                                    <?php if ($def['type'] === 'int'): ?>
                                        <div class="input-group" style="max-width: 300px">
                                            <input type="number" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                                   min="<?php echo $def['min']; ?>" max="<?php echo $def['max']; ?>" step="1" required
                                                   value="<?php echo htmlspecialchars((string)$value); ?>">
                                            <span class="input-group-text"><?php echo htmlspecialchars($def['unit']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                               maxlength="<?php echo $def['max']; ?>" required value="<?php echo htmlspecialchars((string)$value); ?>">
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="form-text"><?php echo htmlspecialchars($def['help']); ?>
                                    <?php if ($def['type'] === 'int'): ?>Allowed: <?php echo $def['min']; ?>–<?php echo $def['max']; ?>. Default: <?php echo $def['default']; ?>.<?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex justify-content-end mt-3 mb-4">
        <button type="submit" name="save_settings" value="1" class="btn btn-primary">
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>Save settings
        </button>
    </div>
</form>

<!-- Email (SMTP): status + test. Credentials stay in config/smtp.php, not the database. -->
<div class="card nemsu-panel mb-4">
    <div class="card-header"><i class="bi bi-envelope me-2" aria-hidden="true"></i>Email (SMTP)</div>
    <div class="card-body">
        <?php if ($testEmailResult): ?>
            <div class="alert alert-<?php echo $testEmailResult['success'] ? 'success' : 'danger'; ?> py-2" role="status"><?php echo htmlspecialchars($testEmailResult['message']); ?></div>
        <?php endif; ?>
        <dl class="row mb-3 small">
            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9">
                <?php if (SMTP_ENABLED && SMTP_HOST !== '' && SMTP_USERNAME !== ''): ?>
                    <span class="badge bg-success">Configured</span>
                <?php else: ?>
                    <span class="badge bg-danger">Not configured</span> Sign-in codes and reset emails cannot be sent.
                <?php endif; ?>
            </dd>
            <dt class="col-sm-3">Server</dt><dd class="col-sm-9"><?php echo htmlspecialchars(SMTP_HOST ?: '—'); ?>:<?php echo (int)SMTP_PORT; ?> (<?php echo htmlspecialchars(strtoupper((string)SMTP_ENCRYPTION)); ?>)</dd>
            <dt class="col-sm-3">Sends as</dt><dd class="col-sm-9"><?php echo htmlspecialchars(SMTP_FROM_EMAIL ?: '—'); ?></dd>
        </dl>
        <p class="small text-muted mb-3">For security the SMTP password is kept in <code>config/smtp.php</code> on the server and is never shown or stored in the database.</p>
        <form method="POST" action="super_admin_settings.php" class="d-inline">
            <?php echo csrfField(); ?>
            <button type="submit" name="test_email" value="1" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-send me-1" aria-hidden="true"></i>Send test email to <?php echo htmlspecialchars($_SESSION['email'] ?? 'me'); ?>
            </button>
        </form>
    </div>
</div>

<?php include 'includes/super_admin_bottom.php'; ?>
