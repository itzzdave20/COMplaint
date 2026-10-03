<?php
/**
 * Super Admin — One complaint and its full routing history (VIEW ONLY).
 * Shows details, every timeline event, every escalation with its reason,
 * and the comments. Deliberately has no forms that change the complaint.
 */
require_once 'config/config.php';
requireRole('super_admin');

$detail = (new SuperAdmin())->complaintDetail((int)($_GET['id'] ?? 0));
if (!$detail) {
    $_SESSION['message'] = 'Complaint not found.';
    $_SESSION['message_type'] = 'danger';
    redirect('super_admin_complaints.php');
}
$c = $detail['complaint'];

$pageTitle = 'Complaint #' . (int)$c['complaint_id'];
$pageHeading = 'Complaint #' . (int)$c['complaint_id'];
$pageSubtitle = $c['complaint_title'];
$pageActions = '<a href="super_admin_complaints.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>All complaints</a>'
    . '<a href="super_admin_reveal.php?alias=' . urlencode($c['complainant_name']) . '&complaint=' . (int)$c['complaint_id']
    . '" class="btn btn-outline-warning btn-sm"><i class="bi bi-incognito me-1" aria-hidden="true"></i>Reveal complainant</a>';
include 'includes/super_admin_top.php';
?>

<div class="alert alert-info d-flex align-items-center gap-2 py-2" role="status">
    <i class="bi bi-eye" aria-hidden="true"></i>
    <span>View only. Complaints are handled by the assigned personnel, not the Super Admin.</span>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card nemsu-panel mb-4">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span>Details</span>
                <span class="d-flex gap-2"><?php echo statusBadgeHtml($c['status']); ?><?php echo severityBadgeHtml($c['severity']); ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Complainant</dt><dd class="col-sm-8"><?php echo htmlspecialchars($c['complainant_name']); ?> <small class="text-muted">(alias)</small></dd>
                    <dt class="col-sm-4">Department</dt><dd class="col-sm-8"><?php echo htmlspecialchars($c['department'] ?: 'Not recorded'); ?></dd>
                    <dt class="col-sm-4">Category</dt><dd class="col-sm-8"><?php echo htmlspecialchars($c['complaint_category'] ?? '—'); ?></dd>
                    <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo ($c['complaint_type'] ?? '') === 'services' ? 'Service-related (direct to OSWD)' : 'Person-related (escalation chain)'; ?></dd>
                    <dt class="col-sm-4">Current level</dt><dd class="col-sm-8"><?php echo htmlspecialchars(workflowLevelLabel($c['current_level'])); ?><?php if (!empty($c['assigned_name'])): ?> — <?php echo htmlspecialchars($c['assigned_name']); ?><?php endif; ?></dd>
                    <dt class="col-sm-4">Respondent</dt><dd class="col-sm-8"><?php echo htmlspecialchars($c['respondent_name'] ?? 'N/A'); ?><?php if ($c['respondent_type']): ?> (<?php echo htmlspecialchars(formatStatus($c['respondent_type'])); ?>)<?php endif; ?></dd>
                    <dt class="col-sm-4">Incident</dt><dd class="col-sm-8"><?php echo date('M j, Y', strtotime($c['incident_date'])); ?> · <?php echo htmlspecialchars($c['incident_location'] ?? 'No location given'); ?></dd>
                    <dt class="col-sm-4">Submitted</dt><dd class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($c['created_at'])); ?></dd>
                    <dt class="col-sm-4">Description</dt><dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($c['complaint_description'])); ?></dd>
                </dl>
            </div>
        </div>

        <!-- Escalations: who forwarded it, to whom, and why -->
        <div class="card nemsu-panel mb-4">
            <div class="card-header"><i class="bi bi-arrow-up-right-circle me-2" aria-hidden="true"></i>Escalations (<?php echo count($detail['escalations']); ?>)</div>
            <div class="card-body p-0">
                <?php if (!$detail['escalations']): ?>
                    <p class="text-muted small p-3 mb-0">Not escalated. <?php echo ($c['complaint_type'] ?? '') === 'services' ? 'Service complaints go directly to OSWD.' : ''; ?></p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($detail['escalations'] as $e): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <strong>
                                        <?php echo htmlspecialchars(roleLabel($e['by_role'] ?? '')); ?>
                                        <i class="bi bi-arrow-right mx-1" aria-hidden="true"></i>
                                        <?php echo htmlspecialchars(roleLabel($e['to_role'] ?? '')); ?>
                                    </strong>
                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($e['created_at'])); ?></small>
                                </div>
                                <div class="small text-muted"><?php echo htmlspecialchars(($e['by_name'] ?? '?') . ' → ' . ($e['to_name'] ?? '?')); ?></div>
                                <div class="small mt-1">Reason: <?php echo htmlspecialchars($e['escalation_reason'] ?? '—'); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments (read only) -->
        <div class="card nemsu-panel mb-4">
            <div class="card-header"><i class="bi bi-chat-left-text me-2" aria-hidden="true"></i>Comments (<?php echo count($detail['comments']); ?>)</div>
            <div class="card-body">
                <?php if (!$detail['comments']): ?>
                    <p class="text-muted small mb-0">No comments.</p>
                <?php endif; ?>
                <?php foreach ($detail['comments'] as $comment): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong class="small"><?php echo htmlspecialchars($comment['full_name']); ?> <span class="text-muted fw-normal">· <?php echo htmlspecialchars(roleLabel($comment['role'])); ?></span></strong>
                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?></small>
                        </div>
                        <div class="small"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Full routing history (every timeline event, unfiltered) -->
    <div class="col-lg-5">
        <div class="card nemsu-panel">
            <div class="card-header"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Routing history</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($detail['timeline'] as $event): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars(formatStatus($event['action_type'])); ?></span>
                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?></small>
                        </div>
                        <div class="small mt-1"><?php echo htmlspecialchars($event['action_description']); ?></div>
                        <div class="small text-muted">by <?php echo htmlspecialchars($event['full_name']); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/super_admin_bottom.php'; ?>
