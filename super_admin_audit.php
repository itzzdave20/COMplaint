<?php
/**
 * Super Admin — Audit log (READ ONLY).
 * Identity reveals, account changes, unlocks, password resets and exports.
 *
 * This page has no edit or delete buttons, AuditLog has no update/delete
 * methods, and database triggers reject UPDATE/DELETE on the table — so
 * entries cannot be changed even by the Super Admin.
 */
require_once 'config/config.php';
requireRole('super_admin');

$filters = [
    'action' => array_key_exists($_GET['action'] ?? '', AuditLog::ACTIONS) ? $_GET['action'] : '',
    'actor' => trim((string)($_GET['actor'] ?? '')),
    'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '',
    'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '',
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$log = (new AuditLog())->search($filters, $page, $perPage);
$totalPages = max(1, (int)ceil($log['total'] / $perPage));

/** Turn the JSON "details" column into short readable text. */
function auditDetails($json) {
    $data = $json ? json_decode($json, true) : null;
    if (!is_array($data)) {
        return '';
    }
    $parts = [];
    foreach ($data as $key => $value) {
        if (is_array($value) && array_key_exists('from', $value)) {
            $parts[] = formatStatus($key) . ': ' . ($value['from'] ?: '—') . ' → ' . ($value['to'] ?: '—');
        } elseif (is_array($value)) {
            $parts[] = formatStatus($key) . ': ' . (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
        } elseif ($value !== null && $value !== '') {
            $parts[] = formatStatus($key) . ': ' . $value;
        }
    }
    return implode(' · ', $parts);
}

$pageTitle = 'Audit log';
$pageHeading = 'Audit log';
$pageSubtitle = 'Permanent, read-only record of Super Admin actions. Entries cannot be edited or deleted.';
include 'includes/super_admin_top.php';
?>

<form method="GET" class="card nemsu-panel mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label for="action" class="form-label small">Action</label>
            <select class="form-select form-select-sm" id="action" name="action">
                <option value="">All actions</option>
                <?php foreach (AuditLog::ACTIONS as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filters['action'] === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="actor" class="form-label small">Done by (username)</label>
            <input type="text" class="form-control form-control-sm" id="actor" name="actor" value="<?php echo htmlspecialchars($filters['actor']); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="from" class="form-label small">From</label>
            <input type="date" class="form-control form-control-sm" id="from" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label small">To</label>
            <input type="date" class="form-control form-control-sm" id="to" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-fill" type="submit">Filter</button>
            <a class="btn btn-outline-secondary btn-sm" href="super_admin_audit.php">Reset</a>
        </div>
    </div>
</form>

<div class="card nemsu-panel">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-journal-check me-2" aria-hidden="true"></i>Entries (<?php echo $log['total']; ?>)</span>
        <small class="text-muted"><i class="bi bi-lock-fill" aria-hidden="true"></i> Read only</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive nemsu-table-wrap">
            <table class="table align-middle mb-0 nemsu-table nemsu-table--stack">
                <thead>
                    <tr><th>When</th><th>Done by</th><th>Action</th><th>Target</th><th>Reason</th><th>Details</th></tr>
                </thead>
                <tbody>
                    <?php if (!$log['rows']): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No entries match these filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($log['rows'] as $row): ?>
                        <tr class="<?php echo $row['action'] === 'identity_reveal' ? 'table-warning' : ''; ?>">
                            <td><small><?php echo date('M j, Y g:i:s A', strtotime($row['created_at'])); ?></small></td>
                            <td><?php echo htmlspecialchars($row['actor_username']); ?></td>
                            <td><span class="badge bg-<?php echo $row['action'] === 'identity_reveal' ? 'warning text-dark' : 'primary'; ?>"><?php echo htmlspecialchars(AuditLog::label($row['action'])); ?></span></td>
                            <td><?php echo htmlspecialchars($row['target_label'] ?? '—'); ?></td>
                            <td class="small"><?php echo htmlspecialchars($row['reason'] ?? '—'); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars(auditDetails($row['details'])) ?: '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white">
            <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2" aria-label="Audit log pagination">
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
