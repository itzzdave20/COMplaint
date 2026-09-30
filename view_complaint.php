<?php
require_once 'config/config.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('dashboard.php');
}

$complaintObj = new Complaint();
$complaintId = intval($_GET['id']);
$complaint = $complaintObj->getComplaintById($complaintId);

if (!$complaint) {
    $_SESSION['message'] = 'Complaint not found';
    $_SESSION['message_type'] = 'danger';
    redirect('dashboard.php');
}

if (!$complaintObj->canAccessComplaint($complaint, $_SESSION['user_id'], $_SESSION['role'] ?? '')) {
    $_SESSION['message'] = 'You do not have permission to view this complaint';
    $_SESSION['message_type'] = 'danger';
    redirect('dashboard.php');
}

$canManage = $complaintObj->canManageComplaint($complaint, $_SESSION['user_id'], $_SESSION['role'] ?? '');
$canEscalate = $complaintObj->canEscalate($complaint, $_SESSION['user_id'], $_SESSION['role'] ?? '');

if (isset($_POST['update_status']) && $canManage) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        redirect('view_complaint.php?id=' . $complaintId);
    }
    $newStatus = $_POST['new_status'] ?? '';
    $result = $complaintObj->updateStatus($complaintId, $newStatus, $_SESSION['user_id']);

    if ($result['success']) {
        $_SESSION['message'] = 'Status updated successfully';
        $_SESSION['message_type'] = 'success';
        redirect('view_complaint.php?id=' . $complaintId);
    }
    $_SESSION['message'] = $result['message'] ?? 'Failed to update status';
    $_SESSION['message_type'] = 'danger';
}

if (isset($_POST['add_comment'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        redirect('view_complaint.php?id=' . $complaintId);
    }
    $comment = sanitizeInput($_POST['comment_text'] ?? '');
    $result = $complaintObj->addComment($complaintId, $_SESSION['user_id'], $comment);

    if ($result['success']) {
        $_SESSION['message'] = 'Comment added successfully';
        $_SESSION['message_type'] = 'success';
        redirect('view_complaint.php?id=' . $complaintId);
    }
    $_SESSION['message'] = $result['message'] ?? 'Failed to add comment';
    $_SESSION['message_type'] = 'danger';
}

if (isset($_POST['escalate']) && $canEscalate) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        redirect('view_complaint.php?id=' . $complaintId);
    }
    $reason = sanitizeInput($_POST['escalation_reason'] ?? '');
    $result = $complaintObj->escalateComplaint($complaintId, $_SESSION['user_id'], $reason, $_SESSION['role'] ?? '');

    if ($result['success']) {
        $_SESSION['message'] = 'Complaint escalated successfully';
        $_SESSION['message_type'] = 'success';
        redirect('view_complaint.php?id=' . $complaintId);
    }
    $_SESSION['message'] = $result['message'] ?? 'Failed to escalate complaint';
    $_SESSION['message_type'] = 'danger';
}

$comments = $complaintObj->getComments($complaintId);
$timeline = filterTimelineForDisplay($complaintObj->getTimeline($complaintId));
$complaint = $complaintObj->getComplaintById($complaintId);
$canManage = $complaintObj->canManageComplaint($complaint, $_SESSION['user_id'], $_SESSION['role'] ?? '');
$canEscalate = $complaintObj->canEscalate($complaint, $_SESSION['user_id'], $_SESSION['role'] ?? '');
$nextLevel = nextWorkflowLevel($complaint['current_level'] ?? '');
$isStudent = ($_SESSION['role'] ?? '') === 'student';
$currentUserId = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Complaint #' . $complaintId . ' - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="app-body">
<?php include 'includes/skip_link.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/flash.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 app-main" id="main-content">
            <div class="d-flex justify-content-between flex-wrap align-items-start pt-3 nemsu-page-header gap-2">
                <div>
                    <h1 class="h2 mb-1">Case #<?php echo (int)$complaint['complaint_id']; ?></h1>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($complaint['complaint_title']); ?></p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Dashboard</a>
            </div>

            <?php if ($isStudent): ?>
                <div class="card nemsu-progress-card mb-4">
                    <div class="card-header"><i class="bi bi-signpost-split me-2" aria-hidden="true"></i>Your complaint progress</div>
                    <div class="card-body">
                        <?php echo renderComplaintProgressSteps($complaint, false); ?>
                        <p class="small mb-1 mt-3"><?php echo statusPlainLanguage($complaint['status']); ?></p>
                        <p class="small text-muted mb-0"><i class="bi bi-clock me-1" aria-hidden="true"></i><?php echo htmlspecialchars(expectedResponseHint($complaint['status'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card nemsu-panel mb-4">
                        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <span>Summary</span>
                            <div class="d-flex gap-2">
                                <?php echo statusBadgeHtml($complaint['status']); ?>
                                <?php echo severityBadgeHtml($complaint['severity']); ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4">Category</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['complaint_category'] ?? '—'); ?></dd>
                                <dt class="col-sm-4">Type</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars(formatStatus($complaint['complaint_type'] ?? 'behavioral')); ?></dd>
                                <dt class="col-sm-4">Handler</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars(workflowLevelLabel($complaint['current_level'] ?? '')); ?><?php if (!empty($complaint['assigned_name'])): ?> — <?php echo htmlspecialchars($complaint['assigned_name']); ?><?php endif; ?></dd>
                                <dt class="col-sm-4">Description</dt>
                                <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($complaint['complaint_description'])); ?></dd>
                                <dt class="col-sm-4">Respondent</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['respondent_name'] ?? 'N/A'); ?><?php if ($complaint['respondent_type']): ?> (<?php echo htmlspecialchars(formatStatus($complaint['respondent_type'])); ?>)<?php endif; ?></dd>
                                <dt class="col-sm-4">Incident</dt>
                                <dd class="col-sm-8"><?php echo date('M j, Y', strtotime($complaint['incident_date'])); ?> · <?php echo htmlspecialchars($complaint['incident_location'] ?? 'No location given'); ?></dd>
                                <?php if (!$isStudent): ?>
                                    <dt class="col-sm-4">Submitted by</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['complainant_name']); ?></dd>
                                <?php endif; ?>
                                <?php if ($complaint['supporting_documents']): ?>
                                    <dt class="col-sm-4">Evidence</dt>
                                    <dd class="col-sm-8">
                                        <a href="uploads/<?php echo htmlspecialchars($complaint['supporting_documents']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-paperclip me-1" aria-hidden="true"></i>View document
                                        </a>
                                    </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <div class="card nemsu-panel mb-4">
                        <div class="card-header"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Timeline</div>
                        <div class="card-body">
                            <?php if (empty($timeline)): ?>
                                <p class="text-muted mb-0">No activity recorded yet.</p>
                            <?php else: ?>
                                <ul class="nemsu-timeline">
                                    <?php foreach ($timeline as $event): ?>
                                        <li class="nemsu-timeline__item">
                                            <div class="nemsu-timeline__marker" aria-hidden="true"><i class="bi bi-dot"></i></div>
                                            <div class="nemsu-timeline__body">
                                                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                                    <strong><?php echo htmlspecialchars($event['full_name']); ?></strong>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars(formatStatus($event['action_type'])); ?></span>
                                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?></small>
                                                </div>
                                                <p class="mb-0 small"><?php echo htmlspecialchars($event['action_description']); ?></p>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card nemsu-panel">
                        <div class="card-header"><i class="bi bi-chat-dots me-2" aria-hidden="true"></i>Comments</div>
                        <div class="card-body">
                            <div class="nemsu-chat-thread mb-3" aria-live="polite">
                                <?php if (empty($comments)): ?>
                                    <p class="text-muted small mb-0">No comments yet. Staff updates will appear here.</p>
                                <?php else: ?>
                                    <?php foreach ($comments as $comment): ?>
                                        <?php $mine = (int)$comment['user_id'] === $currentUserId; ?>
                                        <article class="nemsu-chat-bubble <?php echo $mine ? 'nemsu-chat-bubble--mine' : 'nemsu-chat-bubble--theirs'; ?>">
                                            <header class="nemsu-chat-bubble__meta">
                                                <strong><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(roleLabel($comment['role'])); ?></span>
                                                <time datetime="<?php echo htmlspecialchars($comment['created_at']); ?>"><?php echo date('M j, g:i A', strtotime($comment['created_at'])); ?></time>
                                            </header>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <label for="comment_text" class="form-label">Add a comment</label>
                                <textarea class="form-control mb-2" id="comment_text" name="comment_text" rows="3" required></textarea>
                                <button type="submit" name="add_comment" class="btn btn-primary btn-sm">Post comment</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="nemsu-sticky-panel">
                        <div class="card nemsu-panel mb-3">
                            <div class="card-header">Case status</div>
                            <div class="card-body">
                                <p class="mb-2"><?php echo statusBadgeHtml($complaint['status']); ?></p>
                                <p class="mb-2"><?php echo severityBadgeHtml($complaint['severity']); ?></p>
                                <p class="small text-muted mb-1">Created <?php echo date('M j, Y g:i A', strtotime($complaint['created_at'])); ?></p>
                                <p class="small text-muted mb-0">Updated <?php echo date('M j, Y g:i A', strtotime($complaint['updated_at'])); ?></p>

                                <?php if ($canManage): ?>
                                    <hr>
                                    <form method="POST">
                                        <?php echo csrfField(); ?>
                                        <label for="new_status" class="form-label">Update status</label>
                                        <select class="form-select mb-2" id="new_status" name="new_status" required>
                                            <?php foreach (selectableStatusesForUpdate($complaint['status']) as $statusOption): ?>
                                                <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $complaint['status'] === $statusOption ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(formatStatus($statusOption)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-success w-100">Save status</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($canEscalate && $nextLevel): ?>
                            <div class="card nemsu-panel border-warning">
                                <div class="card-header bg-warning-subtle">Escalate case</div>
                                <div class="card-body">
                                    <p class="small text-muted">Send to <?php echo htmlspecialchars(workflowLevelLabel($nextLevel)); ?> if this office cannot resolve the case.</p>
                                    <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#escalateModal">
                                        <i class="bi bi-arrow-up-circle me-1" aria-hidden="true"></i>Escalate
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php if ($canEscalate && $nextLevel): ?>
<div class="modal fade" id="escalateModal" tabindex="-1" aria-labelledby="escalateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="escalateModalLabel">Confirm escalation</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">This will reassign the case to <?php echo htmlspecialchars(workflowLevelLabel($nextLevel)); ?>.</p>
                    <label for="escalation_reason" class="form-label">Reason for escalation *</label>
                    <textarea class="form-control" id="escalation_reason" name="escalation_reason" rows="4" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="escalate" class="btn btn-warning">Escalate case</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/scripts.php'; ?>
</body>
</html>
