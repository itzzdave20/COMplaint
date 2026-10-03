<?php
/**
 * Super Admin — Authentication monitoring.
 * Login attempts recorded by the Random Forest login check (login_events),
 * with filters. Suspicious attempts are highlighted. Locked accounts can be
 * unlocked here; each unlock is written to the audit log.
 */
require_once 'config/config.php';
requireRole('super_admin');

$superAdmin = new SuperAdmin();

// ---- Unlock (POST + CSRF token) ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $result = $superAdmin->unlock((int)($_POST['lockout_id'] ?? 0));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    redirect('super_admin_auth.php#locked');
}

// ---- Filters ---------------------------------------------------
$filters = [
    'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '',
    'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '',
    'result' => in_array($_GET['result'] ?? '', ['success', 'failed'], true) ? $_GET['result'] : '',
    'risk' => in_array($_GET['risk'] ?? '', ['low', 'medium', 'high', 'suspicious'], true) ? $_GET['risk'] : '',
    'username' => trim((string)($_GET['username'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$events = $superAdmin->loginEvents($filters, $page, $perPage);
$totalPages = max(1, (int)ceil($events['total'] / $perPage));
$locked = $superAdmin->lockedAccounts();

$pageTitle = 'Authentication monitoring';
$pageHeading = 'Authentication monitoring';
$pageSubtitle = 'Login attempts scored by the Random Forest model. Suspicious attempts are highlighted.';
include 'includes/super_admin_top.php';
?>

<!-- Locked accounts -->
<div class="card nemsu-panel mb-4" id="locked">
    <div class="card-header"><i class="bi bi-lock me-2" aria-hidden="true"></i>Locked accounts (<?php echo count($locked); ?>)</div>
    <div class="card-body p-0">
        <?php if (!$locked): ?>
            <?php echo renderEmptyState('unlock', 'No locked accounts', 'Accounts are locked after repeated failed sign-ins.'); ?>
        <?php else: ?>
            <div class="table-responsive nemsu-table-wrap">
                <table class="table align-middle mb-0 nemsu-table nemsu-table--stack">
                    <thead>
                        <tr><th>Account</th><th>Role</th><th>Lock type</th><th>Until</th><th>Failed attempts</th><th class="nemsu-stack-full"></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locked as $row): ?>
                            <?php $blocked = !empty($row['blocked_until']) && strtotime($row['blocked_until']) > time(); ?>
                            <tr>
                                <td>
                                    <div><?php echo htmlspecialchars($row['username'] ?? $row['username_key']); ?></div>
                                    <?php if (!empty($row['full_name'])): ?><div class="small text-muted"><?php echo htmlspecialchars($row['full_name']); ?></div><?php endif; ?>
                                </td>
                                <td><?php echo $row['role'] ? htmlspecialchars(roleLabel($row['role'])) : '<span class="text-muted">Unknown username</span>'; ?></td>
                                <td><span class="badge bg-<?php echo $blocked ? 'danger' : 'warning'; ?>"><?php echo $blocked ? 'Blocked (1 day)' : 'Cooldown'; ?></span></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($blocked ? $row['blocked_until'] : $row['cooldown_until'])); ?></td>
                                <td><?php echo (int)$row['consecutive_fails']; ?></td>
                                <td class="text-end">
                                    <form method="POST" action="super_admin_auth.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="lockout_id" value="<?php echo (int)$row['lockout_id']; ?>">
                                        <button type="submit" name="unlock" value="1" class="btn btn-sm btn-success"
                                                data-confirm-message="Unlock this account now? This is recorded in the audit log.">
                                            <i class="bi bi-unlock me-1" aria-hidden="true"></i>Unlock
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="card nemsu-panel mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label for="from" class="form-label small">From</label>
            <input type="date" class="form-control form-control-sm" id="from" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label small">To</label>
            <input type="date" class="form-control form-control-sm" id="to" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="result" class="form-label small">Result</label>
            <select class="form-select form-select-sm" id="result" name="result">
                <option value="">All</option>
                <option value="success" <?php echo $filters['result'] === 'success' ? 'selected' : ''; ?>>Success</option>
                <option value="failed" <?php echo $filters['result'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="risk" class="form-label small">Risk</label>
            <select class="form-select form-select-sm" id="risk" name="risk">
                <option value="">All</option>
                <option value="suspicious" <?php echo $filters['risk'] === 'suspicious' ? 'selected' : ''; ?>>Suspicious only</option>
                <?php foreach (['high', 'medium', 'low'] as $risk): ?>
                    <option value="<?php echo $risk; ?>" <?php echo $filters['risk'] === $risk ? 'selected' : ''; ?>><?php echo ucfirst($risk); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label for="username" class="form-label small">Username</label>
            <input type="text" class="form-control form-control-sm" id="username" name="username" value="<?php echo htmlspecialchars($filters['username']); ?>">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-fill" type="submit">Filter</button>
            <a class="btn btn-outline-secondary btn-sm" href="super_admin_auth.php">Reset</a>
        </div>
    </div>
</form>

<!-- Login attempts -->
<div class="card nemsu-panel">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-list-check me-2" aria-hidden="true"></i>Login attempts (<?php echo $events['total']; ?>)</span>
        <small class="text-muted"><span class="badge bg-danger-subtle text-danger-emphasis">Red rows</span> are suspicious</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive nemsu-table-wrap">
            <table class="table table-hover align-middle mb-0 nemsu-table nemsu-table--stack">
                <thead>
                    <tr><th>When</th><th>Username tried</th><th>Account</th><th>Result</th><th>Reason</th><th>Risk</th><th>Score</th><th>Model</th></tr>
                </thead>
                <tbody>
                    <?php if (!$events['rows']): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No login attempts match these filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($events['rows'] as $ev): ?>
                        <?php $suspicious = SuperAdmin::isSuspicious($ev); ?>
                        <tr class="<?php echo $suspicious ? 'table-danger' : ''; ?>">
                            <td><?php echo date('M j, Y g:i A', strtotime($ev['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($ev['username_attempted']); ?></td>
                            <td><?php echo $ev['full_name'] ? htmlspecialchars($ev['full_name']) . '<div class="small text-muted">' . htmlspecialchars(roleLabel($ev['role'])) . '</div>' : '<span class="text-muted">No such account</span>'; ?></td>
                            <td><span class="badge bg-<?php echo $ev['success'] ? 'success' : 'secondary'; ?>"><?php echo $ev['success'] ? 'Success' : 'Failed'; ?></span></td>
                            <td>
                                <?php if ($suspicious): ?><i class="bi bi-exclamation-triangle-fill text-danger me-1" aria-label="Suspicious"></i><?php endif; ?>
                                <?php echo htmlspecialchars(SuperAdmin::failureLabel($ev['failure_reason'])); ?>
                            </td>
                            <td>
                                <?php if ($ev['risk_label']): ?>
                                    <span class="badge bg-<?php echo SuperAdmin::riskBadgeClass($ev['risk_label']); ?>"><?php echo ucfirst($ev['risk_label']); ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?php echo $ev['risk_score'] !== null ? htmlspecialchars((string)$ev['risk_score']) : '—'; ?></td>
                            <td><small><?php echo htmlspecialchars($ev['model_version'] ?? '—'); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white">
            <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2" aria-label="Login attempts pagination">
                <span class="small text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filters, ['page' => $page - 1]))); ?>">Previous</a>
                    </li>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filters, ['page' => $page + 1]))); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/super_admin_bottom.php'; ?>
