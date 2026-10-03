<?php
/**
 * Super Admin — User management.
 * Create, edit, deactivate/reactivate and reset passwords for every role,
 * and assign personnel to a department/program. Every change is written
 * to the audit log by the SuperAdmin class.
 *
 * Views:  super_admin_users.php            → list
 *         super_admin_users.php?new=1      → create form
 *         super_admin_users.php?edit=ID    → edit form
 */
require_once 'config/config.php';
requireRole('super_admin');

$superAdmin = new SuperAdmin();
$formErrors = [];
$tempPassword = null;   // shown once after a password reset, never stored

/** Read the account form fields from POST. */
function userFormInput() {
    $fields = ['username', 'email', 'full_name', 'role', 'student_id', 'department', 'program', 'contact_number'];
    $data = [];
    foreach ($fields as $field) {
        $data[$field] = trim((string)($_POST[$field] ?? ''));
    }
    $data['password'] = (string)($_POST['password'] ?? '');
    $data['password_confirm'] = (string)($_POST['password_confirm'] ?? '');
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        redirect('super_admin_users.php');
    }
    $targetId = (int)($_POST['user_id'] ?? 0);

    if (isset($_POST['create_user'])) {
        $result = $superAdmin->createUser(userFormInput());
        if ($result['success']) {
            $_SESSION['message'] = 'Account created.';
            $_SESSION['message_type'] = 'success';
            redirect('super_admin_users.php');
        }
        $formErrors = $result['errors'];          // re-show the form with errors
        $_GET['new'] = 1;
    } elseif (isset($_POST['update_user'])) {
        $result = $superAdmin->updateUser($targetId, userFormInput());
        if ($result['success']) {
            $_SESSION['message'] = 'Account updated.';
            $_SESSION['message_type'] = 'success';
            redirect('super_admin_users.php');
        }
        $formErrors = $result['errors'];
        $_GET['edit'] = $targetId;
    } elseif (isset($_POST['set_status'])) {
        $result = $superAdmin->setStatus($targetId, $_POST['set_status'] === 'active' ? 'active' : 'inactive');
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        redirect('super_admin_users.php?' . http_build_query(array_intersect_key($_GET, array_flip(['role', 'status', 'department', 'q']))));
    } elseif (isset($_POST['reset_password'])) {
        // No redirect: the temporary password is shown in THIS response only.
        $result = $superAdmin->resetPassword($targetId);
        if ($result['success']) {
            $tempPassword = $result;
        } else {
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = 'danger';
        }
    }
}

// ---- Which view? ------------------------------------------------
$editing = isset($_GET['edit']) ? $superAdmin->getUser((int)$_GET['edit']) : null;
$creating = !$editing && isset($_GET['new']);
$filters = [
    'role' => in_array($_GET['role'] ?? '', allRoles(), true) ? $_GET['role'] : '',
    'status' => in_array($_GET['status'] ?? '', ['active', 'inactive', 'deactivated'], true) ? $_GET['status'] : '',
    'department' => in_array($_GET['department'] ?? '', departments(), true) ? $_GET['department'] : '',
    'q' => trim((string)($_GET['q'] ?? '')),
];
// Pre-select role/department when coming from the Overview's "Add" links.
$prefill = [
    'role' => in_array($_GET['role'] ?? '', allRoles(), true) ? $_GET['role'] : 'student',
    'department' => $filters['department'],
];

if ($editing || $creating) {
    // Re-fill the form with what was typed if saving failed, else the stored values.
    $form = $formErrors ? userFormInput() : ($editing ?: $prefill);
    $programs = $superAdmin->knownValues('program');
} else {
    $users = $superAdmin->users($filters);
}

$pageTitle = 'User management';
$pageHeading = $editing ? 'Edit account' : ($creating ? 'Create account' : 'User management');
$pageSubtitle = $editing || $creating
    ? 'Personnel are assigned to a department and program here.'
    : 'Create, edit, deactivate and reset passwords for every role.';
$pageActions = $editing || $creating
    ? '<a href="super_admin_users.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to list</a>'
    : '<a href="super_admin_users.php?new=1" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Create account</a>';
include 'includes/super_admin_top.php';
?>

<?php if ($tempPassword): ?>
    <div class="alert alert-warning" role="alert">
        <h2 class="h6"><i class="bi bi-key me-1" aria-hidden="true"></i>Temporary password for <?php echo htmlspecialchars($tempPassword['username']); ?></h2>
        <p class="mb-2">Give this to the user privately. It is shown <strong>only once</strong> and is not stored anywhere in readable form.
            Any lockout on the account was also cleared.</p>
        <code class="fs-5 user-select-all"><?php echo htmlspecialchars($tempPassword['password']); ?></code>
    </div>
<?php endif; ?>

<?php if ($editing || $creating): ?>
    <!-- ================= CREATE / EDIT FORM ================= -->
    <?php if ($formErrors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0"><?php foreach ($formErrors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="super_admin_users.php" class="card nemsu-panel" novalidate>
        <?php echo csrfField(); ?>
        <?php if ($editing): ?><input type="hidden" name="user_id" value="<?php echo (int)$editing['user_id']; ?>"><?php endif; ?>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label for="full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($form['full_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                <select class="form-select" id="role" name="role" required>
                    <?php foreach (allRoles() as $role): ?>
                        <option value="<?php echo $role; ?>" <?php echo ($form['role'] ?? '') === $role ? 'selected' : ''; ?>><?php echo htmlspecialchars(roleLabel($role)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="username" name="username" required autocomplete="off" value="<?php echo htmlspecialchars($form['username'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($form['email'] ?? ''); ?>">
                <div class="form-text">Must be real: the login risk check may email a verification code.</div>
            </div>

            <!-- Personnel assignment: free text with suggestions from existing values -->
            <div class="col-md-6">
                <label for="department" class="form-label">Department</label>
                <select class="form-select" id="department" name="department">
                    <option value="">— None —</option>
                    <?php foreach (departments() as $dept): ?>
                        <option value="<?php echo $dept; ?>" <?php echo ($form['department'] ?? '') === $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Required for Program Coordinators and Program Chairpersons.</div>
            </div>
            <div class="col-md-6">
                <label for="program" class="form-label">Program</label>
                <input type="text" class="form-control" id="program" name="program" list="programList" value="<?php echo htmlspecialchars($form['program'] ?? ''); ?>">
                <datalist id="programList"><?php foreach ($programs as $value): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="col-md-6">
                <label for="student_id" class="form-label">Student ID <small class="text-muted">(students only)</small></label>
                <input type="text" class="form-control" id="student_id" name="student_id" value="<?php echo htmlspecialchars($form['student_id'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label for="contact_number" class="form-label">Contact number</label>
                <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($form['contact_number'] ?? ''); ?>">
            </div>

            <?php if ($creating): ?>
                <div class="col-md-6">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                    <div class="form-text">At least 12 characters with upper and lower case, a number and a symbol.</div>
                </div>
                <div class="col-md-6">
                    <label for="password_confirm" class="form-label">Confirm password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                </div>
            <?php elseif (!empty($editing['alias'])): ?>
                <div class="col-12">
                    <span class="small text-muted">Alias shown to personnel: <strong><?php echo htmlspecialchars($editing['alias']); ?></strong></span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="super_admin_users.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" name="<?php echo $editing ? 'update_user' : 'create_user'; ?>" value="1" class="btn btn-primary">
                <?php echo $editing ? 'Save changes' : 'Create account'; ?>
            </button>
        </div>
    </form>

<?php else: ?>
    <!-- ================= LIST ================= -->
    <form method="GET" class="card nemsu-panel mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label for="q" class="form-label small">Search</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q" placeholder="Name, username, email or alias" value="<?php echo htmlspecialchars($filters['q']); ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="fdept" class="form-label small">Department</label>
                <select class="form-select form-select-sm" id="fdept" name="department">
                    <option value="">All</option>
                    <?php foreach (departments() as $dept): ?>
                        <option value="<?php echo $dept; ?>" <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="frole" class="form-label small">Role</label>
                <select class="form-select form-select-sm" id="frole" name="role">
                    <option value="">All roles</option>
                    <?php foreach (allRoles() as $role): ?>
                        <option value="<?php echo $role; ?>" <?php echo $filters['role'] === $role ? 'selected' : ''; ?>><?php echo htmlspecialchars(roleLabel($role)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="fstatus" class="form-label small">Status</label>
                <select class="form-select form-select-sm" id="fstatus" name="status">
                    <option value="">All</option>
                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="deactivated" <?php echo $filters['status'] === 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-fill" type="submit">Filter</button>
                <a class="btn btn-outline-secondary btn-sm" href="super_admin_users.php">Reset</a>
            </div>
        </div>
    </form>

    <div class="card nemsu-panel">
        <div class="card-header"><i class="bi bi-people me-2" aria-hidden="true"></i>Accounts (<?php echo count($users); ?>)</div>
        <div class="card-body p-0">
            <?php if (!$users): ?>
                <?php echo renderEmptyState('people', 'No accounts found', 'Try different filters.'); ?>
            <?php else: ?>
                <div class="table-responsive nemsu-table-wrap">
                    <table class="table table-hover align-middle mb-0 nemsu-table nemsu-table--stack">
                        <thead>
                            <tr><th>Name</th><th>Username</th><th>Role</th><th>Department / Program</th><th>Status</th><th class="text-end nemsu-stack-full">Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <?php $isSelf = (int)$u['user_id'] === (int)$_SESSION['user_id']; ?>
                                <?php $presence = userPresence($u); ?>
                                <tr class="<?php echo $presence === 'deactivated' ? 'text-muted' : ''; ?>">
                                    <td>
                                        <div><?php echo htmlspecialchars($u['full_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                        <?php if ($u['alias']): ?><div class="small text-muted">Alias: <?php echo htmlspecialchars($u['alias']); ?></div><?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><span class="badge bg-primary nemsu-badge"><?php echo htmlspecialchars(roleLabel($u['role'])); ?></span></td>
                                    <td><small><?php echo htmlspecialchars(trim(($u['department'] ?? '') . ' / ' . ($u['program'] ?? ''), ' /') ?: '—'); ?></small></td>
                                    <td>
                                        <!-- Active = signed in now; Inactive = logged out or idle 10+ min; Deactivated = account off -->
                                        <span class="badge bg-<?php echo ['active' => 'success', 'inactive' => 'secondary', 'deactivated' => 'danger'][$presence]; ?>">
                                            <?php if ($presence === 'active'): ?><i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle" aria-hidden="true"></i><?php endif; ?>
                                            <?php echo ucfirst($presence); ?>
                                        </span>
                                        <?php if ($presence !== 'active' && !empty($u['last_activity_at'])): ?>
                                            <div class="small text-muted">Last seen <?php echo date('M j, g:i A', strtotime($u['last_activity_at'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                                            <a href="super_admin_users.php?edit=<?php echo (int)$u['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                                            </a>
                                            <?php if (!$isSelf): ?>
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                                                    <button type="submit" name="reset_password" value="1" class="btn btn-sm btn-outline-warning"
                                                            data-confirm-message="Reset the password for <?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>? A new temporary password will be shown once.">
                                                        <i class="bi bi-key" aria-hidden="true"></i> Reset password
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                                                    <?php if ($u['status'] === 'active'): ?>
                                                        <button type="submit" name="set_status" value="inactive" class="btn btn-sm btn-outline-danger"
                                                                data-confirm-message="Deactivate <?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>? They will not be able to sign in.">
                                                            <i class="bi bi-person-x" aria-hidden="true"></i> Deactivate
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="set_status" value="active" class="btn btn-sm btn-outline-success">
                                                            <i class="bi bi-person-check" aria-hidden="true"></i> Reactivate
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            <?php else: ?>
                                                <span class="small text-muted align-self-center">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'GET'): ?>
        <!-- Reload the list every minute so Active/Inactive stays current (handled in app.js). -->
        <div data-auto-refresh="60" hidden></div>
    <?php endif; ?>
<?php endif; ?>

<?php include 'includes/super_admin_bottom.php'; ?>
