<?php
require_once 'config/config.php';
requireLogin();
requireRole('oswd');

$lockout = new AccountLockout();
$allowedStatus = ['pending', 'accepted', 'rejected', 'completed', 'all'];
$status = (string)($_GET['status'] ?? 'pending');
if (!in_array($status, $allowedStatus, true)) {
    $status = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $note = sanitizeInput($_POST['review_note'] ?? '');
        if (isset($_POST['accept_review'])) {
            $result = $lockout->acceptReview($reviewId, (int)$_SESSION['user_id'], $note);
        } elseif (isset($_POST['reject_review'])) {
            $result = $lockout->rejectReview($reviewId, (int)$_SESSION['user_id'], $note);
        } else {
            $result = ['success' => false, 'message' => 'Unknown action.'];
        }
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    redirect('lockout_reviews.php?status=' . urlencode($status));
}

$reviews = $lockout->getReviews($status);
$pendingCount = $lockout->getPendingCount();

function lockoutStatusBadge($value) {
    return match ((string)$value) {
        'pending' => 'warning',
        'accepted' => 'info',
        'rejected' => 'secondary',
        'completed' => 'success',
        default => 'secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Lockout reviews - ' . SITE_NAME; include 'includes/head.php'; ?>
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
                <h1 class="h2">Account lockout reviews</h1>
                <p class="text-muted mb-0">Accept a request to email a credential reset link, or reject it with a note.</p>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($allowedStatus as $filter): ?>
                    <a href="lockout_reviews.php?status=<?php echo urlencode($filter); ?>"
                       class="btn btn-sm <?php echo $status === $filter ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <?php echo htmlspecialchars(ucfirst($filter)); ?>
                        <?php if ($filter === 'pending' && $pendingCount > 0): ?>
                            <span class="badge text-bg-light text-dark ms-1"><?php echo (int)$pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="card nemsu-panel">
                <div class="card-header"><i class="bi bi-shield-exclamation me-2" aria-hidden="true"></i>Lockout requests</div>
                <div class="card-body p-0">
                    <?php if (empty($reviews)): ?>
                        <?php echo renderEmptyState('shield-check', 'No requests found', 'There are no lockout reviews in this filter.'); ?>
                    <?php else: ?>
                        <div class="table-responsive nemsu-table-wrap">
                            <table class="table table-hover align-middle mb-0 nemsu-table nemsu-table--stack">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Account</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Requested</th>
                                        <th>Message</th>
                                        <th class="text-end nemsu-stack-full">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php foreach ($reviews as $review): ?>
                                        <tr>
                                            <td>
                                                <div><?php echo htmlspecialchars($review['full_name'] ?: 'Unknown student'); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($review['email'] ?: 'No email on file'); ?></div>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($review['account_username'] ?: $review['username_attempted']); ?></div>
                                                <?php if (!empty($review['blocked_until'])): ?>
                                                    <div class="small text-muted">Blocked until <?php echo date('M j, Y g:i A', strtotime($review['blocked_until'])); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(str_replace('_', ' ', (string)$review['source'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo lockoutStatusBadge($review['status']); ?> nemsu-badge">
                                                    <?php echo htmlspecialchars(ucfirst((string)$review['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?></td>
                                            <td class="small"><?php echo htmlspecialchars($review['message'] ?: '—'); ?></td>

                                            <td class="text-end">
                                                <?php if ($review['status'] === 'pending'): ?>
                                                    <form method="POST" action="lockout_reviews.php?status=<?php echo urlencode($status); ?>" class="d-flex flex-column gap-2 align-items-end">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="review_id" value="<?php echo (int)$review['review_id']; ?>">
                                                        <input type="text" class="form-control form-control-sm" name="review_note" maxlength="255" placeholder="Optional note">
                                                        <div class="btn-group">
                                                            <button type="submit" name="accept_review" value="1" class="btn btn-sm btn-success"
                                                                    data-confirm-message="Email a credential reset link to this student?">Accept</button>
                                                            <button type="submit" name="reject_review" value="1" class="btn btn-sm btn-outline-danger"
                                                                    data-confirm-message="Reject this unlock request?">Reject</button>
                                                        </div>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="small text-muted">
                                                        <?php echo htmlspecialchars($review['reviewer_name'] ?: 'OSWD'); ?>
                                                        <?php if (!empty($review['reviewed_at'])): ?>
                                                            · <?php echo date('M j, Y', strtotime($review['reviewed_at'])); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($review['review_note'])): ?>
                                                        <div class="small"><?php echo htmlspecialchars($review['review_note']); ?></div>
                                                    <?php endif; ?>
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

