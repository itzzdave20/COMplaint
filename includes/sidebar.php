<?php
$role = $_SESSION['role'] ?? '';
?>
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            
            <?php if ($role === 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="submit_complaint.php">
                        <i class="fas fa-plus-circle"></i> Submit Complaint
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_complaints.php">
                        <i class="fas fa-list"></i> My Complaints
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if (in_array($role, ['oswd', 'program_coordinator', 'department_chair', 'guidance_office'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="complaints.php">
                        <i class="fas fa-folder-open"></i> All Complaints
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="assigned_complaints.php">
                        <i class="fas fa-tasks"></i> Assigned to Me
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if ($role === 'oswd'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

