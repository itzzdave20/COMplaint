<?php
$studentUnreadNotifications = 0;
if (isLoggedIn() && ($_SESSION['role'] ?? '') === 'student') {
    try {
        $studentUnreadNotifications = (new Notification())->getUnreadCount((int)$_SESSION['user_id']);
    } catch (Throwable $e) {
        $studentUnreadNotifications = 0;
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark nemsu-navbar fixed-top">
    <div class="container-fluid">
        <button class="btn btn-link text-white d-md-none nemsu-sidebar-toggle me-1" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas"
                aria-label="Open navigation menu">
            <i class="bi bi-list fs-4" aria-hidden="true"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <?php echo nemsuLogoImg('nemsu-logo nemsu-logo--nav'); ?>
            <span class="d-none d-sm-inline">OSWD Complaints</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle account menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-2">
                    <button type="button" class="btn btn-sm btn-outline-light nemsu-theme-toggle" id="themeToggle"
                            aria-label="Toggle dark mode">
                        <i class="bi bi-moon-stars" aria-hidden="true"></i>
                    </button>
                </li>
                <?php if (($_SESSION['role'] ?? '') === 'student'): ?>
                    <li class="nav-item me-2">
                        <a class="nav-link position-relative nemsu-nav-bell" href="notifications.php" title="Notifications"
                           aria-label="Notifications<?php echo $studentUnreadNotifications > 0 ? ', ' . $studentUnreadNotifications . ' unread' : ''; ?>">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <?php if ($studentUnreadNotifications > 0): ?>
                                <span class="nemsu-notification-badge"><?php echo $studentUnreadNotifications > 99 ? '99+' : $studentUnreadNotifications; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear me-2" aria-hidden="true"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
