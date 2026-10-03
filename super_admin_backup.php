<?php
/**
 * Super Admin — Database backup and restore.
 *
 * Create a full backup, download it, upload one, delete old ones, and
 * restore. Restoring needs the Super Admin's password, takes an automatic
 * safety backup first, and never overwrites the audit log
 * (see DatabaseBackup for the rules). Every action is audit-logged.
 */
require_once 'config/config.php';
requireRole('super_admin');

$backup = new DatabaseBackup();
$restoreResult = null;

/** Re-check the signed-in Super Admin's password before a destructive action. */
function confirmPassword($password) {
    $stmt = Database::getInstance()->getConnection()->prepare('SELECT password FROM users WHERE user_id = :id');
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();
    return $hash && password_verify((string)$password, $hash);
}

// ---- Download (GET, role-checked above) ---------------------------
if (isset($_GET['download'])) {
    $path = $backup->path($_GET['download']);
    if (!$path) {
        $_SESSION['message'] = 'Backup not found.';
        $_SESSION['message_type'] = 'danger';
        redirect('super_admin_backup.php');
    }
    (new AuditLog())->record('backup_download', 'backup', null, basename($path));
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        redirect('super_admin_backup.php');
    }

    if (isset($_POST['create_backup'])) {
        $name = $backup->create('manual');
        (new AuditLog())->record('backup_create', 'backup', null, $name);
        $_SESSION['message'] = 'Backup created: ' . $name;
        $_SESSION['message_type'] = 'success';
        redirect('super_admin_backup.php');
    }

    if (isset($_POST['upload_backup'])) {
        $result = $backup->storeUpload($_FILES['backup_file'] ?? []);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        redirect('super_admin_backup.php');
    }

    if (isset($_POST['delete_backup'])) {
        $result = $backup->delete($_POST['name'] ?? '');
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        redirect('super_admin_backup.php');
    }

    if (isset($_POST['restore_backup'])) {
        if (!confirmPassword($_POST['password'] ?? '')) {
            $_SESSION['message'] = 'Password incorrect. Nothing was restored.';
            $_SESSION['message_type'] = 'danger';
            redirect('super_admin_backup.php?restore=' . urlencode($_POST['name'] ?? ''));
        }
        // No redirect: show the result (and the safety backup name) right here.
        $restoreResult = $backup->restore($_POST['name'] ?? '');
    }
}

$backups = $backup->all();
$restoreTarget = isset($_GET['restore']) ? $backup->path($_GET['restore']) : null;

// Automatic backup schedule, for the status card.
$autoEnabled = Settings::get('auto_backup_enabled');
$autoInterval = Settings::get('auto_backup_interval_hours');
$lastAuto = DatabaseBackup::lastAutoBackupTime();
$nextAuto = $lastAuto ? $lastAuto + $autoInterval * 3600 : time();

$pageTitle = 'Backup & restore';
$pageHeading = 'Database backup & restore';
$pageSubtitle = 'Full backups of every table, created automatically and on demand. Restoring never overwrites the audit log.';
$pageActions = ($backups
        ? '<a href="super_admin_backup.php?download=' . urlencode($backups[0]['name']) . '" class="btn btn-outline-primary btn-sm">'
          . '<i class="bi bi-download me-1" aria-hidden="true"></i>Download latest backup</a>'
        : '')
    . '<form method="POST" class="d-inline">' . csrfField()
    . '<button type="submit" name="create_backup" value="1" class="btn btn-primary btn-sm">'
    . '<i class="bi bi-database-add me-1" aria-hidden="true"></i>Create backup now</button></form>';
include 'includes/super_admin_top.php';
?>

<?php if ($restoreResult): ?>
    <?php if ($restoreResult['success']): ?>
        <div class="alert alert-success" role="status">
            <h2 class="h6"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Database restored</h2>
            <p class="mb-0 small"><?php echo (int)$restoreResult['tables']; ?> table(s) restored. The audit log was kept as it is.
                To undo, restore the safety backup <strong><?php echo htmlspecialchars($restoreResult['safety']); ?></strong>.</p>
        </div>
    <?php else: ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($restoreResult['message']); ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($restoreTarget): ?>
    <!-- ===== Restore confirmation ===== -->
    <form method="POST" action="super_admin_backup.php" class="card nemsu-panel mb-4 border-danger">
        <?php echo csrfField(); ?>
        <input type="hidden" name="name" value="<?php echo htmlspecialchars(basename($restoreTarget)); ?>">
        <div class="card-header"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>Restore <?php echo htmlspecialchars(basename($restoreTarget)); ?></div>
        <div class="card-body">
            <p>This replaces the current data in every table with the data in this backup. Before it starts:</p>
            <ul class="small">
                <li>a <strong>safety backup</strong> of the current database is created automatically, so you can undo;</li>
                <li>the <strong>audit log is not changed</strong> — it keeps every entry, including this restore;</li>
                <li>accounts, complaints and settings go back to how they were when the backup was made.</li>
            </ul>
            <label for="password" class="form-label">Enter your password to confirm</label>
            <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" style="max-width: 320px">
        </div>
        <div class="card-footer bg-white d-flex gap-2 justify-content-end">
            <a href="super_admin_backup.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" name="restore_backup" value="1" class="btn btn-danger"
                    data-confirm-message="Restore this backup now? Current data will be replaced (a safety backup is taken first).">
                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restore
            </button>
        </div>
    </form>
<?php endif; ?>

<div class="row g-4">
    <!-- Backups list -->
    <div class="col-xl-8">
        <div class="card nemsu-panel">
            <div class="card-header"><i class="bi bi-database me-2" aria-hidden="true"></i>Backups (<?php echo count($backups); ?>)</div>
            <div class="card-body p-0">
                <?php if (!$backups): ?>
                    <?php echo renderEmptyState('database', 'No backups yet', 'Create the first backup with the button above.'); ?>
                <?php else: ?>
                    <div class="table-responsive nemsu-table-wrap">
                        <table class="table align-middle mb-0 nemsu-table nemsu-table--stack">
                            <thead>
                                <tr><th>Created</th><th>Type</th><th>By</th><th>Size</th><th class="text-end nemsu-stack-full">Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $b): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo htmlspecialchars($b['created'] ? date('M j, Y g:i A', strtotime($b['created'])) : date('M j, Y g:i A', $b['mtime'])); ?></div>
                                            <div class="small text-muted text-break"><?php echo htmlspecialchars($b['name']); ?></div>
                                        </td>
                                        <td>
                                            <?php $labels = ['auto' => ['success', 'Automatic'], 'manual' => ['primary', 'Manual'], 'pre-restore' => ['warning text-dark', 'Safety (before restore)'], 'uploaded' => ['info text-dark', 'Uploaded']]; ?>
                                            <?php [$cls, $text] = $labels[$b['label']] ?? ['secondary', $b['label'] ?: '—']; ?>
                                            <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars($text); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($b['created_by'] ?: '—'); ?></td>
                                        <td><?php echo number_format($b['size'] / 1024, 1); ?> KB</td>
                                        <td class="text-end">
                                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                                <a href="super_admin_backup.php?download=<?php echo urlencode($b['name']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-download" aria-hidden="true"></i> Download
                                                </a>
                                                <a href="super_admin_backup.php?restore=<?php echo urlencode($b['name']); ?>" class="btn btn-sm btn-outline-warning">
                                                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Restore
                                                </a>
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($b['name']); ?>">
                                                    <button type="submit" name="delete_backup" value="1" class="btn btn-sm btn-outline-danger"
                                                            data-confirm-message="Delete this backup file permanently?">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <!-- Automatic backup schedule -->
        <div class="card nemsu-panel">
            <div class="card-header"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Automatic backups</div>
            <div class="card-body small">
                <?php if ($autoEnabled): ?>
                    <p class="mb-2"><span class="badge bg-success">On</span>
                        Every <?php echo $autoInterval; ?> hour(s), keeping the newest <?php echo Settings::get('auto_backup_keep'); ?>.</p>
                    <dl class="row mb-0">
                        <dt class="col-5">Last</dt><dd class="col-7"><?php echo $lastAuto ? date('M j, Y g:i A', $lastAuto) : 'Not yet'; ?></dd>
                        <dt class="col-5">Next due</dt><dd class="col-7"><?php echo $nextAuto <= time() ? 'Now (on the next page load)' : date('M j, Y g:i A', $nextAuto); ?></dd>
                    </dl>
                <?php else: ?>
                    <p class="mb-0"><span class="badge bg-secondary">Off</span> Only manual backups are made.</p>
                <?php endif; ?>
                <a href="super_admin_settings.php" class="d-inline-block mt-2">Change schedule</a>
            </div>
        </div>

        <!-- Upload a backup file -->
        <form method="POST" enctype="multipart/form-data" class="card nemsu-panel">
            <?php echo csrfField(); ?>
            <div class="card-header"><i class="bi bi-upload me-2" aria-hidden="true"></i>Upload a backup</div>
            <div class="card-body">
                <input type="file" class="form-control" name="backup_file" accept=".sql" required aria-label="Backup file">
                <div class="form-text">A .sql file downloaded from this page (up to 50 MB). It is checked before it is saved.</div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end">
                <button type="submit" name="upload_backup" value="1" class="btn btn-outline-primary">Upload</button>
            </div>
        </form>

        <div class="card nemsu-panel">
            <div class="card-header"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Good practice</div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Create a backup before big changes (new semester list, many account edits).</li>
                    <li>Download backups and keep a copy off this computer.</li>
                    <li>Turn on <a href="super_admin_settings.php">maintenance mode</a> before restoring so nobody is working during it.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/super_admin_bottom.php'; ?>
