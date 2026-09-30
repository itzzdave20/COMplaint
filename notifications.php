<?php
require_once 'config/config.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'student') {
    redirect('dashboard.php');
}

$notificationService = new Notification();
$userId = (int)$_SESSION['user_id'];

if (isset($_POST['mark_all_read']) && verifyCsrf()) {
    $notificationService->markAllRead($userId);
    $_SESSION['message'] = 'All notifications marked as read.';
    $_SESSION['message_type'] = 'success';
    redirect('notifications.php');
}

if (isset($_GET['read']) && ctype_digit((string)$_GET['read'])) {
    $notificationService->markRead((int)$_GET['read'], $userId);
    if (!empty($_GET['complaint'])) {
        redirect('view_complaint.php?id=' . (int)$_GET['complaint']);
    }
    redirect('notifications.php');
}

$notifications = $notificationService->getForUser($userId);
$unreadCount = $notificationService->getUnreadCount($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Notifications - ' . SITE_NAME; include 'includes/head.php'; ?>
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
                    <h1 class="h2">Notifications</h1>
                    <p class="text-muted mb-0"><?php echo $unreadCount > 0 ? $unreadCount . ' unread update(s)' : 'You are up to date'; ?></p>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm">Mark all read</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card nemsu-panel">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <?php echo renderEmptyState('bell', 'No notifications yet', 'Updates about your complaints will appear here.', 'View my complaints', 'my_complaints.php'); ?>
                    <?php else: ?>
                        <ul class="list-group list-group-flush nemsu-notification-list">
                            <?php foreach ($notifications as $item): ?>
                                <?php
                                $isUnread = !(bool)$item['is_read'];
                                $link = 'notifications.php?read=' . (int)$item['notification_id'];
                                if (!empty($item['complaint_id'])) {
                                    $link .= '&complaint=' . (int)$item['complaint_id'];
                                }
                                ?>
                                <li class="list-group-item nemsu-notification-item<?php echo $isUnread ? ' nemsu-notification-item--unread' : ''; ?>">
                                    <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" class="nemsu-notification-link text-decoration-none">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                <?php if ($isUnread): ?>
                                                    <span class="badge bg-warning text-dark ms-1"><i class="bi bi-circle-fill" aria-hidden="true"></i> New</span>
                                                <?php endif; ?>
                                                <p class="mb-1 mt-1 text-muted"><?php echo htmlspecialchars($item['message']); ?></p>
                                                <?php if (!empty($item['complaint_title'])): ?>
                                                    <small class="text-muted">Re: <?php echo htmlspecialchars($item['complaint_title']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted text-nowrap"><?php echo date('M j, g:i A', strtotime($item['created_at'])); ?></small>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
