<?php
require_once 'config/config.php';
requireLogin();

if (!hasRole('oswd')) {
    $_SESSION['message'] = 'You do not have permission to manage users.';
    $_SESSION['message_type'] = 'danger';
    redirect('dashboard.php');
}

$userObj = new User();
$currentUserId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $result = $userObj->deleteUser($targetId, $currentUserId);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    redirect('users.php');
}

$users = $userObj->getAllUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Users - ' . SITE_NAME; include 'includes/head.php'; ?>
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
                <h1 class="h2">Users</h1>
                <p class="text-muted mb-0">Registered accounts and roles. Deleting an account is permanent.</p>
            </div>

            <div class="card nemsu-panel">
                <div class="card-header"><i class="bi bi-people me-2" aria-hidden="true"></i>All registered users</div>
                <div class="card-body p-0">
                    <?php if (empty($users)): ?>
                        <?php echo renderEmptyState('people', 'No users found', 'There are no accounts in the system yet.'); ?>
                    <?php else: ?>
                        <div class="table-responsive nemsu-table-wrap">
                            <table class="table table-hover align-middle mb-0 nemsu-table nemsu-table--stack">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th class="text-end nemsu-stack-full">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <?php
                                        $isSelf = (int)$u['user_id'] === $currentUserId;
                                        $confirmMsg = 'Permanently delete '
                                            . ($u['full_name'] ?? 'this user')
                                            . ' (@' . ($u['username'] ?? '') . ')? '
                                            . 'This cannot be undone. Their complaints and related data may be removed.';
                                        ?>
                                        <tr>
                                            <td><?php echo (int)$u['user_id']; ?></td>
                                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td><span class="badge bg-primary nemsu-badge"><i class="bi bi-person-badge" aria-hidden="true"></i><span><?php echo htmlspecialchars(roleLabel($u['role'])); ?></span></span></td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'secondary'; ?> nemsu-badge">
                                                    <i class="bi bi-<?php echo $u['status'] === 'active' ? 'check-circle' : 'pause-circle'; ?>" aria-hidden="true"></i>
                                                    <span><?php echo htmlspecialchars(ucfirst((string)$u['status'])); ?></span>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                            <td class="text-end">
                                                <?php if ($isSelf): ?>
                                                    <span class="text-muted small">You</span>
                                                <?php else: ?>
                                                    <form method="POST" action="users.php" class="d-inline"
                                                          data-no-loading="true"
                                                          data-confirm-message="<?php echo htmlspecialchars($confirmMsg, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                                                        <button type="submit" name="delete_user" value="1"
                                                                class="btn btn-sm btn-outline-danger"
                                                                aria-label="Delete account <?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                                            <span class="d-md-none d-lg-inline">Delete</span>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
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
