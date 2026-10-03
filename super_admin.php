<?php
/**
 * Super Admin — Overview.
 * Totals, complaints by category, open complaints at each escalation
 * level, and users per role.
 */
require_once 'config/config.php';
requireRole('super_admin');   // role check, not just "logged in"

$overview = (new SuperAdmin())->overview();
$levelIcons = [
    'program_coordinator' => 'person-workspace',
    'department_chair' => 'diagram-3',
    'guidance_office' => 'heart-pulse',
    'oswd' => 'building',
];

$pageTitle = 'Super Admin';
$pageHeading = 'Super Admin Overview';
$pageSubtitle = 'System-wide figures across all roles and escalation levels.';
include 'includes/super_admin_top.php';
?>

<div class="row g-3 mb-4 nemsu-stat-grid">
    <div class="col-6 col-md-3">
        <div class="card nemsu-stat-card nemsu-stat-card--blue h-100">
            <div class="card-body">
                <i class="bi bi-inbox stat-icon" aria-hidden="true"></i>
                <h2 class="h6 card-title">Total complaints</h2>
                <p class="display-6 mb-0"><?php echo $overview['total']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card nemsu-stat-card nemsu-stat-card--gold h-100">
            <div class="card-body">
                <i class="bi bi-hourglass-split stat-icon" aria-hidden="true"></i>
                <h2 class="h6 card-title">Still open</h2>
                <p class="display-6 mb-0"><?php echo $overview['open']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="super_admin_auth.php?risk=suspicious" class="text-decoration-none">
            <div class="card nemsu-stat-card nemsu-stat-card--sky h-100">
                <div class="card-body">
                    <i class="bi bi-shield-exclamation stat-icon" aria-hidden="true"></i>
                    <h2 class="h6 card-title">Suspicious logins (7 days)</h2>
                    <p class="display-6 mb-0"><?php echo $overview['suspicious_7d']; ?></p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="super_admin_auth.php#locked" class="text-decoration-none">
            <div class="card nemsu-stat-card nemsu-stat-card--success h-100">
                <div class="card-body">
                    <i class="bi bi-lock stat-icon" aria-hidden="true"></i>
                    <h2 class="h6 card-title">Locked accounts</h2>
                    <p class="display-6 mb-0"><?php echo $overview['locked']; ?></p>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Open complaints at each escalation level -->
    <div class="col-lg-6">
        <div class="card nemsu-panel h-100">
            <div class="card-header"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>Open complaints per escalation level</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($overview['pending_by_level'] as $level => $count): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-<?php echo $levelIcons[$level]; ?> me-2 text-muted" aria-hidden="true"></i>
                            <?php echo htmlspecialchars(workflowLevelLabel($level)); ?>
                        </span>
                        <a class="badge bg-primary rounded-pill text-decoration-none"
                           href="super_admin_complaints.php?level=<?php echo urlencode($level); ?>"><?php echo $count; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-footer bg-white small text-muted">
                Person-related complaints move Coordinator → Chairperson → Guidance → OSWD.
                Service-related complaints go straight to OSWD.
            </div>
        </div>
    </div>

    <!-- Users per role -->
    <div class="col-lg-6">
        <div class="card nemsu-panel h-100">
            <div class="card-header"><i class="bi bi-people me-2" aria-hidden="true"></i>Users per role</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($overview['users_by_role'] as $role => $counts): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="super_admin_users.php?role=<?php echo urlencode($role); ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars(roleLabel($role)); ?>
                        </a>
                        <span>
                            <span class="badge bg-primary rounded-pill"><?php echo $counts['total']; ?></span>
                            <?php if ($counts['total'] !== $counts['active']): ?>
                                <small class="text-muted ms-1"><?php echo $counts['total'] - $counts['active']; ?> deactivated</small>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Personnel per department: shows which departments still need accounts -->
<div class="card nemsu-panel mb-4">
    <div class="card-header"><i class="bi bi-building me-2" aria-hidden="true"></i>Personnel per department</div>
    <div class="card-body p-0">
        <div class="table-responsive nemsu-table-wrap">
            <table class="table align-middle mb-0 nemsu-table nemsu-table--stack">
                <thead>
                    <tr>
                        <th>Department</th>
                        <?php foreach (departmentRoles() as $role): ?><th><?php echo htmlspecialchars(roleLabel($role)); ?></th><?php endforeach; ?>
                        <th>Students</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overview['personnel_by_dept'] as $dept => $counts): ?>
                        <tr>
                            <td>
                                <a href="super_admin_users.php?department=<?php echo urlencode($dept); ?>" class="fw-semibold text-decoration-none"><?php echo $dept; ?></a>
                            </td>
                            <?php foreach (departmentRoles() as $role): ?>
                                <td>
                                    <?php if ($counts[$role] > 0): ?>
                                        <span class="badge bg-success"><?php echo $counts[$role]; ?> active</span>
                                    <?php else: ?>
                                        <!-- No account yet: link straight to a pre-filled create form -->
                                        <a href="super_admin_users.php?new=1&amp;role=<?php echo $role; ?>&amp;department=<?php echo urlencode($dept); ?>"
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-person-plus" aria-hidden="true"></i> Add
                                        </a>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td><?php echo $counts['student']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Complaints by category -->
<div class="card nemsu-panel mb-4">
    <div class="card-header"><i class="bi bi-tags me-2" aria-hidden="true"></i>Complaints by category</div>
    <div class="card-body p-0">
        <?php if (!$overview['by_category']): ?>
            <?php echo renderEmptyState('inbox', 'No complaints yet', 'Category totals appear once students submit complaints.'); ?>
        <?php else: ?>
            <div class="table-responsive nemsu-table-wrap">
                <table class="table align-middle mb-0 nemsu-table nemsu-table--stack">
                    <thead>
                        <tr><th>Category</th><th>Type</th><th class="text-end">Complaints</th><th>Share</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overview['by_category'] as $row): ?>
                            <?php $share = $overview['total'] ? round($row['total'] / $overview['total'] * 100) : 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['label']); ?></td>
                                <td><?php echo $row['complaint_type'] === 'services' ? 'Service-related' : 'Person-related'; ?></td>
                                <td class="text-end"><?php echo (int)$row['total']; ?></td>
                                <td style="min-width: 140px">
                                    <div class="progress" role="progressbar" aria-label="<?php echo $share; ?> percent"
                                         aria-valuenow="<?php echo $share; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" style="width: <?php echo $share; ?>%"><?php echo $share; ?>%</div>
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

<?php include 'includes/super_admin_bottom.php'; ?>
