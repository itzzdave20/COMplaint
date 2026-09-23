<?php
require_once 'config/config.php';
requireLogin();

if (!hasRole('oswd')) {
    redirect('dashboard.php');
}

$complaint = new Complaint();
$all = $complaint->getAllComplaints();
$stats = $complaint->getStatistics();
$total = count($all);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - OSWD</title>
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
                    <h1 class="h2">Reports</h1>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3"><div class="card text-white bg-primary"><div class="card-body"><h6>Total</h6><h3><?php echo $total; ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card text-white bg-warning"><div class="card-body"><h6>Pending</h6><h3><?php echo $stats['pending'] ?? 0; ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card text-white bg-info"><div class="card-body"><h6>Investigating</h6><h3><?php echo ($stats['investigating'] ?? 0) + ($stats['under_review'] ?? 0); ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card text-white bg-success"><div class="card-body"><h6>Resolved</h6><h3><?php echo $stats['resolved'] ?? 0; ?></h3></div></div></div>
                </div>
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Status Breakdown</h5></div>
                    <div class="card-body">
                        <?php if (empty($stats)): ?>
                            <p class="text-muted mb-0">No complaint data available yet.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($stats as $status => $count): ?>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                                        <strong><?php echo $count; ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
