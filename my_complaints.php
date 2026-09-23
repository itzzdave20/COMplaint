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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - OSWD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Complaints</h1>
                    <a href="submit_complaint.php" class="btn btn-primary btn-sm">Submit New Complaint</a>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Severity</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($complaints)): ?>
                                        <tr><td colspan="7" class="text-center">No complaints submitted yet</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($complaints as $comp): ?>
                                            <tr>
                                                <td>#<?php echo $comp['complaint_id']; ?></td>
                                                <td><?php echo htmlspecialchars($comp['complaint_title']); ?></td>
                                                <td><?php echo htmlspecialchars($comp['predicted_category'] ?? $comp['complaint_category'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo statusBadgeClass($comp['status']); ?>">
                                                        <?php echo htmlspecialchars(formatStatus($comp['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo severityBadgeClass($comp['severity']); ?>">
                                                        <?php echo htmlspecialchars(ucfirst((string)$comp['severity'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($comp['created_at'])); ?></td>
                                                <td><a href="view_complaint.php?id=<?php echo $comp['complaint_id']; ?>" class="btn btn-sm btn-primary">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
