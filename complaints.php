<?php
require_once 'config/config.php';
requireLogin();
requireRole('oswd');

$complaint = new Complaint();
$filters = [];
if (!empty($_GET['status']) && in_array($_GET['status'], allowedComplaintStatuses(), true)) {
    $filters['status'] = $_GET['status'];
}
$complaints = $complaint->getAllComplaints($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'All Complaints - ' . SITE_NAME; include 'includes/head.php'; ?>
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
                <h1 class="h2">All complaints</h1>
                <p class="text-muted mb-0">Complete OSWD case registry</p>
            </div>

            <form method="GET" class="row g-2 mb-3 align-items-end">
                <div class="col-md-4">
                    <label for="status" class="form-label small">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <?php foreach (allowedComplaintStatuses() as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($_GET['status'] ?? '') === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars(formatStatus($st)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                </div>
            </form>

            <div class="card nemsu-panel">
                <div class="card-body p-0">
                    <?php if (empty($complaints)): ?>
                        <?php echo renderEmptyState('folder2-open', 'No complaints found', 'Try changing the status filter or check back later.'); ?>
                    <?php else: ?>
                        <div class="table-responsive nemsu-table-wrap">
                            <table class="table table-hover align-middle mb-0 nemsu-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Complainant</th>
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
                                            <td><?php echo htmlspecialchars($comp['complainant_name']); ?></td>
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
