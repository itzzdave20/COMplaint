<?php
require_once 'config/config.php';
requireLogin();

if (!hasRole(staffRoles())) {
    redirect('dashboard.php');
}

$complaint = new Complaint();
$complaints = $complaint->getAssignedComplaints($_SESSION['user_id']);
$roleMeta = dashboardMetaForRole($_SESSION['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Assigned Complaints - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="app-body">
<?php include 'includes/skip_link.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/flash.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 app-main" id="main-content">
            <div class="pt-3 nemsu-page-header mb-4">
                <h1 class="h2">Assigned to me</h1>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($roleMeta['subtitle']); ?></p>
            </div>

            <div class="card nemsu-panel">
                <div class="card-body p-0">
                    <?php if (empty($complaints)): ?>
                        <?php echo renderEmptyState('inbox', 'Queue is clear', 'No complaints are assigned to you right now.', 'Go to dashboard', 'dashboard.php'); ?>
                    <?php else: ?>
                        <div class="table-responsive nemsu-table-wrap">
                            <table class="table table-hover align-middle mb-0 nemsu-table nemsu-table--stack">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Complainant</th>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Severity</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($complaints as $comp): ?>
                                        <?php $critical = in_array($comp['severity'] ?? '', ['high', 'critical'], true); ?>
                                        <tr class="<?php echo $critical ? 'table-danger' : ''; ?>">
                                            <td>#<?php echo (int)$comp['complaint_id']; ?></td>
                                            <td><?php echo htmlspecialchars($comp['complainant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($comp['complaint_title']); ?></td>
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
