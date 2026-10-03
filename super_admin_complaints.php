<?php
/**
 * Super Admin — Complaint overview (VIEW ONLY).
 * Lists every complaint in the system. There are no status, comment or
 * escalation controls: the Super Admin monitors complaints but does not
 * handle them.
 */
require_once 'config/config.php';
requireRole('super_admin');

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => in_array($_GET['status'] ?? '', allowedComplaintStatuses(), true) ? $_GET['status'] : '',
    'department' => in_array($_GET['department'] ?? '', departments(), true) ? $_GET['department'] : '',
    'level' => in_array($_GET['level'] ?? '', workflowLevels(), true) ? $_GET['level'] : '',
    'category' => in_array($_GET['category'] ?? '', allowedComplaintCategories(), true) ? $_GET['category'] : '',
];
$complaints = (new SuperAdmin())->complaints($filters);

$pageTitle = 'Complaints';
$pageHeading = 'Complaint overview';
$pageSubtitle = 'All complaints and where they are in the routing. View only.';
include 'includes/super_admin_top.php';
?>

<form method="GET" class="card nemsu-panel mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-2">
            <label for="q" class="form-label small">Search</label>
            <input type="search" class="form-control form-control-sm" id="q" name="q" placeholder="Title, alias or ID" value="<?php echo htmlspecialchars($filters['q']); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="status" class="form-label small">Status</label>
            <select class="form-select form-select-sm" id="status" name="status">
                <option value="">All</option>
                <?php foreach (allowedComplaintStatuses() as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo formatStatus($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="department" class="form-label small">Department</label>
            <select class="form-select form-select-sm" id="department" name="department">
                <option value="">All</option>
                <?php foreach (departments() as $dept): ?>
                    <option value="<?php echo $dept; ?>" <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="level" class="form-label small">Current level</label>
            <select class="form-select form-select-sm" id="level" name="level">
                <option value="">All</option>
                <?php foreach (workflowLevels() as $level): ?>
                    <option value="<?php echo $level; ?>" <?php echo $filters['level'] === $level ? 'selected' : ''; ?>><?php echo htmlspecialchars(workflowLevelLabel($level)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label for="category" class="form-label small">Category</label>
            <select class="form-select form-select-sm" id="category" name="category">
                <option value="">All</option>
                <?php foreach (allowedComplaintCategories() as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filters['category'] === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-fill" type="submit">Filter</button>
            <a class="btn btn-outline-secondary btn-sm" href="super_admin_complaints.php">Reset</a>
        </div>
    </div>
</form>

<div class="card nemsu-panel">
    <div class="card-header"><i class="bi bi-folder2-open me-2" aria-hidden="true"></i>Complaints (<?php echo count($complaints); ?>)</div>
    <div class="card-body p-0">
        <?php if (!$complaints): ?>
            <?php echo renderEmptyState('folder2-open', 'No complaints found', 'Try different filters.'); ?>
        <?php else: ?>
            <div class="table-responsive nemsu-table-wrap">
                <table class="table table-hover align-middle mb-0 nemsu-table nemsu-table--stack">
                    <thead>
                        <tr><th>ID</th><th>Title</th><th>Complainant</th><th>Department</th><th>Category</th><th>Current level</th><th>Status</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $c): ?>
                            <tr>
                                <td>#<?php echo (int)$c['complaint_id']; ?></td>
                                <td><?php echo htmlspecialchars($c['complaint_title']); ?></td>
                                <td><?php echo htmlspecialchars($c['complainant_alias']); ?></td>
                                <td><?php echo htmlspecialchars($c['department'] ?: '—'); ?></td>
                                <td><small><?php echo htmlspecialchars($c['complaint_category'] ?: '—'); ?></small></td>
                                <td>
                                    <?php echo htmlspecialchars(workflowLevelLabel($c['current_level'])); ?>
                                    <?php if ($c['assigned_name']): ?><div class="small text-muted"><?php echo htmlspecialchars($c['assigned_name']); ?></div><?php endif; ?>
                                </td>
                                <td><?php echo statusBadgeHtml($c['status']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($c['created_at'])); ?></td>
                                <td><a href="super_admin_complaint.php?id=<?php echo (int)$c['complaint_id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/super_admin_bottom.php'; ?>
