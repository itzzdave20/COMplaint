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

$isStaff = hasRole(staffRoles());

// Handle status update
if (isset($_POST['update_status']) && $isStaff) {
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
    } else {
        $_SESSION['message'] = $result['message'] ?? 'Failed to update status';
        $_SESSION['message_type'] = 'danger';
    }
}

// Handle comment submission
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
    } else {
        $_SESSION['message'] = $result['message'] ?? 'Failed to add comment';
        $_SESSION['message_type'] = 'danger';
    }
}

// Handle escalation
if (isset($_POST['escalate']) && hasRole(['program_coordinator', 'oswd'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        redirect('view_complaint.php?id=' . $complaintId);
    }
    $reason = sanitizeInput($_POST['escalation_reason'] ?? '');
    $result = $complaintObj->escalateComplaint($complaintId, $_SESSION['user_id'], $reason);
    
    if ($result['success']) {
        $_SESSION['message'] = 'Complaint escalated successfully';
        $_SESSION['message_type'] = 'success';
        redirect('view_complaint.php?id=' . $complaintId);
    } else {
        $_SESSION['message'] = $result['message'] ?? 'Failed to escalate complaint';
        $_SESSION['message_type'] = 'danger';
    }
}

$comments = $complaintObj->getComments($complaintId);
$timeline = $complaintObj->getTimeline($complaintId);
$complaint = $complaintObj->getComplaintById($complaintId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Complaint - OSWD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Complaint Details #<?php echo $complaint['complaint_id']; ?></h1>
                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <?php echo showAlert($_SESSION['message'], $_SESSION['message_type'] ?? 'info'); ?>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header"><h5>Complaint Information</h5></div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>Title:</strong></div>
                                    <div class="col-md-9"><?php echo htmlspecialchars($complaint['complaint_title']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>Description:</strong></div>
                                    <div class="col-md-9"><?php echo nl2br(htmlspecialchars($complaint['complaint_description'])); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>Category:</strong></div>
                                    <div class="col-md-9"><?php echo htmlspecialchars($complaint['complaint_category']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>Predicted Category:</strong></div>
                                    <div class="col-md-9">
                                        <span class="badge bg-info"><?php echo htmlspecialchars($complaint['predicted_category'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>Respondent:</strong></div>
                                    <div class="col-md-9">
                                        <?php echo htmlspecialchars($complaint['respondent_name'] ?? 'N/A'); ?>
                                        <?php if ($complaint['respondent_type']): ?>
                                            (<?php echo htmlspecialchars(formatStatus($complaint['respondent_type'])); ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>Incident Date:</strong></div>
                                    <div class="col-md-9"><?php echo date('M d, Y', strtotime($complaint['incident_date'])); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>Location:</strong></div>
                                    <div class="col-md-9"><?php echo htmlspecialchars($complaint['incident_location'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>Submitted By:</strong></div>
                                    <div class="col-md-9"><?php echo htmlspecialchars($complaint['complainant_name']); ?></div>
                                </div>
                                <?php if ($complaint['supporting_documents']): ?>
                                    <div class="row mb-2">
                                        <div class="col-md-3"><strong>Documents:</strong></div>
                                        <div class="col-md-9">
                                            <a href="uploads/<?php echo htmlspecialchars($complaint['supporting_documents']); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-file-earmark"></i> View Document
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-header"><h5>Comments & Updates</h5></div>
                            <div class="card-body">
                                <?php if (empty($comments)): ?>
                                    <p class="text-muted">No comments yet</p>
                                <?php else: ?>
                                    <?php foreach ($comments as $comment): ?>
                                        <div class="border-bottom pb-2 mb-2">
                                            <strong><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars(formatStatus($comment['role'])); ?></span>
                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></small>
                                            <p class="mt-1"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <form method="POST" class="mt-3">
                                    <?php echo csrfField(); ?>
                                    <div class="mb-3">
                                        <label class="form-label">Add Comment</label>
                                        <textarea class="form-control" name="comment_text" rows="3" required></textarea>
                                    </div>
                                    <button type="submit" name="add_comment" class="btn btn-primary">Post Comment</button>
                                </form>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header"><h5>Timeline</h5></div>
                            <div class="card-body">
                                <?php if (empty($timeline)): ?>
                                    <p class="text-muted mb-0">No timeline events yet</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($timeline as $event): ?>
                                            <li class="list-group-item px-0">
                                                <strong><?php echo htmlspecialchars($event['full_name']); ?></strong>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars(formatStatus($event['action_type'])); ?></span>
                                                <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($event['created_at'])); ?></small>
                                                <p class="mb-0 mt-1"><?php echo htmlspecialchars($event['action_description']); ?></p>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header"><h5>Status</h5></div>
                            <div class="card-body">
                                <p>
                                    <strong>Current Status:</strong><br>
                                    <span class="badge bg-<?php echo statusBadgeClass($complaint['status']); ?> fs-6">
                                        <?php echo htmlspecialchars(formatStatus($complaint['status'])); ?>
                                    </span>
                                </p>
                                <p>
                                    <strong>Severity:</strong><br>
                                    <span class="badge bg-<?php echo severityBadgeClass($complaint['severity']); ?> fs-6">
                                        <?php echo htmlspecialchars(ucfirst((string)$complaint['severity'])); ?>
                                    </span>
                                </p>
                                <p><strong>Created:</strong><br><?php echo date('M d, Y H:i', strtotime($complaint['created_at'])); ?></p>
                                <p><strong>Last Updated:</strong><br><?php echo date('M d, Y H:i', strtotime($complaint['updated_at'])); ?></p>
                                
                                <?php if ($isStaff): ?>
                                    <hr>
                                    <form method="POST">
                                        <?php echo csrfField(); ?>
                                        <div class="mb-3">
                                            <label class="form-label">Update Status</label>
                                            <select class="form-control" name="new_status" required>
                                                <option value="pending" <?php echo $complaint['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="under_review" <?php echo $complaint['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                                <option value="investigating" <?php echo $complaint['status'] === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                                                <option value="resolved" <?php echo $complaint['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                <option value="rejected" <?php echo $complaint['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                <option value="escalated" <?php echo $complaint['status'] === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                                            </select>
                                        </div>
                                        <button type="submit" name="update_status" class="btn btn-success w-100">Update Status</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (hasRole(['program_coordinator', 'oswd']) && $complaint['status'] !== 'escalated'): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-warning"><h5>Escalate Complaint</h5></div>
                                <div class="card-body">
                                    <form method="POST">
                                        <?php echo csrfField(); ?>
                                        <div class="mb-3">
                                            <label class="form-label">Escalation Reason</label>
                                            <textarea class="form-control" name="escalation_reason" rows="3" required></textarea>
                                        </div>
                                        <button type="submit" name="escalate" class="btn btn-warning w-100">Escalate</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

