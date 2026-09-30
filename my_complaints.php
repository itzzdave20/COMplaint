<?php
require_once 'config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    redirect('dashboard.php');
}

$complaint = new Complaint();
$complaints = $complaint->getComplaintsByUser($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'My Complaints - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="app-body">
<?php include 'includes/skip_link.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/flash.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 app-main" id="main-content">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 nemsu-page-header gap-2">
                <div>
                    <h1 class="h2">My complaints</h1>
                    <p class="text-muted mb-0">All cases you have submitted</p>
                </div>
                <a href="submit_complaint.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>File a complaint</a>
            </div>

            <div class="card nemsu-panel">
                <div class="card-body p-0">
                    <?php if (empty($complaints)): ?>
                        <?php echo renderEmptyState('journal-x', 'No complaints yet', 'When you file a complaint, it will appear here with status updates.', 'File a complaint', 'submit_complaint.php'); ?>
                    <?php else: ?>
                        <div class="d-md-none p-3 vstack gap-3">
                            <?php foreach ($complaints as $comp): ?>
                                <article class="nemsu-complaint-card">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted small">#<?php echo (int)$comp['complaint_id']; ?></span>
                                        <span class="text-muted small"><?php echo date('M j, Y', strtotime($comp['created_at'])); ?></span>
                                    </div>
                                    <h2 class="h6"><?php echo htmlspecialchars($comp['complaint_title']); ?></h2>
                                    <div class="d-flex flex-wrap gap-2 my-2"><?php echo statusBadgeHtml($comp['status']); ?><?php echo severityBadgeHtml($comp['severity']); ?></div>
                                    <a href="view_complaint.php?id=<?php echo (int)$comp['complaint_id']; ?>" class="btn btn-sm btn-outline-primary w-100">View</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="table-responsive d-none d-md-block nemsu-table-wrap">
                            <table class="table table-hover align-middle mb-0 nemsu-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Severity</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($complaints as $comp): ?>
                                        <tr>
                                            <td>#<?php echo (int)$comp['complaint_id']; ?></td>
                                            <td><?php echo htmlspecialchars($comp['complaint_title']); ?></td>
                                            <td><small><?php echo htmlspecialchars($comp['predicted_category'] ?? $comp['complaint_category'] ?? '—'); ?></small></td>
                                            <td><?php echo statusBadgeHtml($comp['status']); ?></td>
                                            <td><?php echo severityBadgeHtml($comp['severity']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($comp['created_at'])); ?></td>
                                            <td><a href="view_complaint.php?id=<?php echo (int)$comp['complaint_id']; ?>" class="btn btn-sm btn-primary">Open</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
