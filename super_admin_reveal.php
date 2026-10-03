<?php
/**
 * Super Admin — Identity reveal.
 *
 * Personnel only ever see a student's alias (e.g. Student-7F3KQ2). This page
 * shows the real identity behind an alias, but ONLY after the Super Admin
 * types a reason. SuperAdmin::revealIdentity() writes the audit log entry
 * first and only then returns the identity, so a reveal can never happen
 * without a record.
 *
 * The identity is shown in this one response and is not saved in the
 * session — refreshing or coming back requires a new, logged reveal.
 */
require_once 'config/config.php';
requireRole('super_admin');

$superAdmin = new SuperAdmin();
$alias = trim((string)($_POST['alias'] ?? $_GET['alias'] ?? ''));
$complaintId = (int)($_POST['complaint_id'] ?? $_GET['complaint'] ?? 0);
$reason = '';
$identity = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $result = $superAdmin->revealIdentity($alias, $reason, $complaintId ?: null);
        if ($result['success']) {
            $identity = $result['identity'];
        } else {
            $error = $result['message'];
        }
    }
}

// Before revealing, confirm the alias exists (shows nothing identifying).
$match = $alias !== '' && !$identity ? $superAdmin->findAlias($alias) : null;

$pageTitle = 'Identity reveal';
$pageHeading = 'Identity reveal';
$pageSubtitle = 'See the real student behind an alias. A reason is required and every reveal is permanently logged.';
include 'includes/super_admin_top.php';
?>

<div class="row g-4">
    <div class="col-lg-6">
        <?php if ($identity): ?>
            <!-- ===== Result: shown once ===== -->
            <div class="card nemsu-panel border-warning">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-badge me-2" aria-hidden="true"></i><?php echo htmlspecialchars($identity['alias']); ?></span>
                    <span class="badge bg-warning text-dark">Logged</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Full name</dt><dd class="col-sm-8"><?php echo htmlspecialchars($identity['full_name']); ?></dd>
                        <dt class="col-sm-4">Username</dt><dd class="col-sm-8"><?php echo htmlspecialchars($identity['username']); ?></dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?php echo htmlspecialchars($identity['email']); ?></dd>
                        <dt class="col-sm-4">Student ID</dt><dd class="col-sm-8"><?php echo htmlspecialchars($identity['student_id'] ?: '—'); ?></dd>
                        <dt class="col-sm-4">Department</dt><dd class="col-sm-8"><?php echo htmlspecialchars($identity['department'] ?: '—'); ?></dd>
                        <dt class="col-sm-4">Program</dt><dd class="col-sm-8"><?php echo htmlspecialchars($identity['program'] ?: '—'); ?></dd>
                        <dt class="col-sm-4">Contact</dt><dd class="col-sm-8"><?php echo htmlspecialchars($identity['contact_number'] ?: '—'); ?></dd>
                        <dt class="col-sm-4">Account</dt><dd class="col-sm-8"><?php echo ucfirst((string)$identity['status']); ?></dd>
                    </dl>
                </div>
                <div class="card-footer bg-white small text-muted">
                    Reason recorded: “<?php echo htmlspecialchars($reason); ?>”. This page will not show the identity again without a new reveal.
                </div>
            </div>
            <a href="super_admin_reveal.php" class="btn btn-outline-secondary btn-sm mt-3">Done</a>

        <?php else: ?>
            <!-- ===== Reveal form ===== -->
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="super_admin_reveal.php" class="card nemsu-panel">
                <?php echo csrfField(); ?>
                <input type="hidden" name="complaint_id" value="<?php echo $complaintId ?: ''; ?>">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="alias" class="form-label">Student alias <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="alias" name="alias" required placeholder="Student-XXXXXX"
                               value="<?php echo htmlspecialchars($alias); ?>">
                        <?php if ($alias !== '' && !$error): ?>
                            <div class="form-text">
                                <?php if ($match): ?>
                                    <i class="bi bi-check-circle text-success" aria-hidden="true"></i>
                                    Alias found · <?php echo (int)$match['complaint_count']; ?> complaint(s) on file.
                                <?php else: ?>
                                    <i class="bi bi-x-circle text-danger" aria-hidden="true"></i> No student has this alias.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($complaintId): ?>
                            <div class="form-text">Linked to complaint #<?php echo $complaintId; ?>.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for revealing <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required minlength="15"
                                  placeholder="e.g. OSWD requested the complainant's contact details to schedule a hearing for complaint #12."><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                        <div class="form-text">At least 15 characters. Stored permanently in the audit log.</div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end">
                    <button type="submit" class="btn btn-warning"
                            data-confirm-message="Reveal this student's identity? Your name, the time and the reason will be permanently logged.">
                        <i class="bi bi-eye me-1" aria-hidden="true"></i>Reveal identity
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-lg-6">
        <div class="card nemsu-panel">
            <div class="card-header"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>How anonymity works</div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Every student gets a random alias when their account is created.</li>
                    <li>Coordinators, chairpersons, guidance and OSWD see only the alias on complaints, comments and history.</li>
                    <li>Only the Super Admin can reveal an identity, and only with a written reason.</li>
                    <li>Each reveal records who, when, which alias, the reason and the related complaint in the
                        <a href="super_admin_audit.php?action=identity_reveal">audit log</a>, which cannot be edited or deleted.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/super_admin_bottom.php'; ?>
