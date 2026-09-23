<?php
require_once 'config/config.php';
requireLogin();

if (!hasRole(['oswd', 'program_coordinator', 'department_chair', 'guidance_office'])) {
    redirect('dashboard.php');
}

$complaint = new Complaint();
$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
$complaints = $complaint->getAllComplaints($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Complaints - OSWD</title>
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
                    <h1 class="h2">All Complaints</h1>
                </div>
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="under_review" <?php echo ($_GET['status'] ?? '') === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="investigating" <?php echo ($_GET['status'] ?? '') === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                            <option value="resolved" <?php echo ($_GET['status'] ?? '') === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="rejected" <?php echo ($_GET['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="escalated" <?php echo ($_GET['status'] ?? '') === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary" type="submit">Filter</button>
                    </div>
                </form>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Complainant</th>
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
                                        <tr><td colspan="8" class="text-center">No complaints found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($complaints as $comp): ?>
                                            <tr>
                                                <td>#<?php echo $comp['complaint_id']; ?></td>
                                                <td><?php echo htmlspecialchars($comp['complainant_name']); ?></td>
                                                <td><?php echo htmlspecialchars($comp['complaint_title']); ?></td>
                                                <td><?php echo htmlspecialchars($comp['predicted_category'] ?? $comp['complaint_category'] ?? 'N/A'); ?></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $comp['status'])); ?></td>
                                                <td><?php echo ucfirst($comp['severity']); ?></td>
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
