<?php
$role = $_SESSION['role'] ?? '';
?>
<div class="col-auto px-0 nemsu-sidebar-col">
    <div class="offcanvas-md offcanvas-start sidebar nemsu-sidebar" tabindex="-1" id="sidebarOffcanvas"
         aria-labelledby="sidebarOffcanvasLabel">
        <div class="offcanvas-header d-md-none border-bottom">
            <h2 class="offcanvas-title h6" id="sidebarOffcanvasLabel">Navigation</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarOffcanvas"
                    aria-label="Close navigation"></button>
        </div>
        <div class="offcanvas-body nemsu-sidebar__body">
            <?php if ($role === 'super_admin'): ?>
                <?php
                // Super Admin menu. Every page it links to checks
                // requireRole('super_admin') itself; hiding links is not security.
                $superAdminLinks = [
                    'super_admin.php' => ['speedometer2', 'Overview'],
                    'super_admin_auth.php' => ['shield-lock', 'Authentication'],
                    'super_admin_users.php' => ['people', 'User management'],
                    'super_admin_reveal.php' => ['incognito', 'Identity reveal'],
                    'super_admin_complaints.php' => ['folder2-open', 'Complaints'],
                    'super_admin_audit.php' => ['journal-check', 'Audit log'],
                    'super_admin_reports.php' => ['file-earmark-arrow-down', 'Reports'],
                    'super_admin_backup.php' => ['database-down', 'Backup & restore'],
                    'super_admin_settings.php' => ['sliders', 'Settings'],
                ];
                $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
                ?>
                <p class="sidebar-label">Super Admin</p>
                <ul class="nav flex-column nemsu-sidebar__nav">
                    <?php foreach ($superAdminLinks as $file => [$icon, $label]): ?>
                        <?php
                        // The complaint detail page highlights "Complaints".
                        $active = $currentPage === $file
                            || ($file === 'super_admin_complaints.php' && $currentPage === 'super_admin_complaint.php');
                        ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $active ? ' active' : ''; ?>" href="<?php echo $file; ?>">
                                <i class="bi bi-<?php echo $icon; ?>" aria-hidden="true"></i>
                                <span class="nemsu-sidebar__text"><?php echo $label; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
            <p class="sidebar-label">Navigation</p>
            <ul class="nav flex-column nemsu-sidebar__nav">
                <li class="nav-item">
                    <a class="<?php echo navLinkClass('dashboard.php'); ?>" href="dashboard.php">
                        <i class="bi bi-house-door" aria-hidden="true"></i>
                        <span class="nemsu-sidebar__text">Dashboard</span>
                    </a>
                </li>

                <?php if ($role === 'student'): ?>
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('submit_complaint.php'); ?>" href="submit_complaint.php">
                            <i class="bi bi-plus-circle" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">Submit complaint</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('my_complaints.php'); ?>" href="my_complaints.php">
                            <i class="bi bi-journal-text" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">My complaints</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('notifications.php'); ?>" href="notifications.php">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">Notifications</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (in_array($role, staffRoles(), true) && $role !== 'oswd'): ?>
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('assigned_complaints.php'); ?>" href="assigned_complaints.php">
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">Assigned to me</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if ($role === 'oswd'): ?>
                <p class="sidebar-label nemsu-sidebar__section-label">Administration</p>
                <ul class="nav flex-column nemsu-sidebar__nav">
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('complaints.php'); ?>" href="complaints.php">
                            <i class="bi bi-folder2-open" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">All complaints</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('reports.php'); ?>" href="reports.php">
                            <i class="bi bi-bar-chart" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('login_security.php'); ?>" href="login_security.php">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">Login security</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('enrolled_students.php'); ?>" href="enrolled_students.php">
                            <i class="bi bi-person-vcard" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">Enrolled students</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="<?php echo navLinkClass('users.php'); ?>" href="users.php">
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <span class="nemsu-sidebar__text">Users</span>
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
            <?php endif; /* end: not super_admin */ ?>
        </div>
    </div>
</div>
