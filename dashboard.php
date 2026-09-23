<?php
require_once 'config/config.php';
requireLogin();

$complaint = new Complaint();

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

if ($userRole === 'student') {
    $complaints = $complaint->getComplaintsByUser($userId);
} elseif ($userRole === 'oswd') {
    $complaints = $complaint->getAllComplaints();
} else {
    $complaints = $complaint->getAssignedComplaints($userId);
}

$totalComplaints = count($complaints);
$pendingCount = count(array_filter($complaints, fn($c) => $c['status'] === 'pending'));
$inProgressCount = count(array_filter($complaints, fn($c) => in_array($c['status'], ['under_review', 'investigating'])));
$resolvedCount = count(array_filter($complaints, fn($c) => $c['status'] === 'resolved'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - OSWD Complaint System</title>
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
                    <h1 class="h2">Dashboard</h1>
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Complaints</h5>
                                <h2><?php echo $totalComplaints; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Pending</h5>
                                <h2><?php echo $pendingCount; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">In Progress</h5>
                                <h2><?php echo $inProgressCount; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Resolved</h5>
                                <h2><?php echo $resolvedCount; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>


                
                <!-- Complaints Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Complaints</h5>
                        <?php if ($userRole === 'student'): ?>
                            <a href="submit_complaint.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle"></i> Submit New Complaint
                            </a>
                        <?php endif; ?>
                    </div>
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
                                        <tr>
                                            <td colspan="7" class="text-center">No complaints found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($complaints as $comp): ?>
                                            <tr>
                                                <td>#<?php echo $comp['complaint_id']; ?></td>
                                                <td><?php echo htmlspecialchars($comp['complaint_title']); ?></td>
                                                <td><?php echo htmlspecialchars($comp['predicted_category'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $comp['status'] === 'resolved' ? 'success' : 
                                                            (in_array($comp['status'], ['under_review', 'investigating']) ? 'info' : 
                                                            ($comp['status'] === 'escalated' ? 'danger' : 'warning')); 
                                                    ?>">
                                                        <?php echo ucfirst($comp['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $comp['severity'] === 'high' ? 'danger' : 
                                                            ($comp['severity'] === 'medium' ? 'warning' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($comp['severity']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($comp['created_at'])); ?></td>
                                                <td>
                                                    <a href="view_complaint.php?id=<?php echo $comp['complaint_id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </td>
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
