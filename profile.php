<?php
require_once 'config/config.php';
requireLogin();

$userObj = new User();

if (isset($_POST['update_profile'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = 'Please provide a valid name and email address.';
            $_SESSION['message_type'] = 'danger';
        } else {
            $data = [
                'full_name' => $fullName,
                'email' => $email,
                'contact_number' => sanitizeInput($_POST['contact_number'] ?? ''),
                'department' => sanitizeInput($_POST['department'] ?? ''),
                'program' => sanitizeInput($_POST['program'] ?? ''),
            ];
            $result = $userObj->updateProfile($_SESSION['user_id'], $data);
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            if ($result['success']) {
                $_SESSION['full_name'] = $data['full_name'];
                $_SESSION['email'] = $data['email'];
            }
        }
    }
}

$user = $userObj->getUserById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Profile - ' . SITE_NAME; include 'includes/head.php'; ?>
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
                <h1 class="h2">My profile</h1>
                <p class="text-muted mb-0">Keep your contact details current — OTP codes are sent to your email.</p>
            </div>

            <div class="card nemsu-panel">
                <div class="card-header"><i class="bi bi-person-gear me-2" aria-hidden="true"></i>Account details</div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required autocomplete="name">
                            </div>
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required autocomplete="email">
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" value="<?php echo htmlspecialchars(roleLabel($user['role'])); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_number" class="form-label">Contact number</label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" autocomplete="tel">
                            </div>
                            <div class="col-md-6">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label for="program" class="form-label">Program</label>
                                <input type="text" class="form-control" id="program" name="program" value="<?php echo htmlspecialchars($user['program'] ?? ''); ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary mt-4">Save changes</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
