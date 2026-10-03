<?php
/**
 * SuperAdmin — all data logic behind the Super Admin dashboard pages.
 *
 * The pages (super_admin*.php) only check the role, read the form, call a
 * method here, and render the result. Every method that changes something
 * also writes an entry to the audit log (AuditLog::record).
 *
 * The Super Admin oversees the system but does NOT handle complaints:
 * there are no methods here that change a complaint's status, routing or
 * comments.
 */
class SuperAdmin {
    // Login attempts the dashboard highlights as suspicious: the Random
    // Forest scored them medium/high risk, or the lockout rules stopped them.
    const SUSPICIOUS_REASONS = ['blocked_risk', 'account_blocked', 'login_cooldown', 'otp_required', 'otp_email_failed'];

    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->audit = new AuditLog();
    }

    /* ==============================================================
     * 1. OVERVIEW
     * ============================================================== */

    public function overview() {
        $total = (int)$this->db->query('SELECT COUNT(*) FROM complaints')->fetchColumn();

        $byCategory = $this->db->query(
            "SELECT COALESCE(complaint_category, 'Uncategorized') AS label, complaint_type, COUNT(*) AS total
             FROM complaints GROUP BY label, complaint_type ORDER BY total DESC"
        )->fetchAll();

        // "Pending" here means still open (not resolved/rejected), grouped by
        // the escalation level currently holding the complaint.
        $openRows = $this->db->query(
            "SELECT current_level, COUNT(*) AS total FROM complaints
             WHERE status NOT IN ('resolved', 'rejected') GROUP BY current_level"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $pendingByLevel = [];
        foreach (workflowLevels() as $level) {
            $pendingByLevel[$level] = (int)($openRows[$level] ?? 0);
        }

        $roleRows = $this->db->query(
            "SELECT role, COUNT(*) AS total, SUM(status = 'active') AS active FROM users GROUP BY role"
        )->fetchAll(PDO::FETCH_UNIQUE);
        $usersByRole = [];
        foreach (allRoles() as $role) {
            $usersByRole[$role] = [
                'total' => (int)($roleRows[$role]['total'] ?? 0),
                'active' => (int)($roleRows[$role]['active'] ?? 0),
            ];
        }

        // Active personnel per department, so the Super Admin can see which
        // departments still need a Program Coordinator or Chairperson account.
        $deptRows = $this->db->query(
            "SELECT department, role, COUNT(*) AS total FROM users
             WHERE status = 'active' AND department IS NOT NULL
             GROUP BY department, role"
        )->fetchAll();
        $personnelByDept = [];
        foreach (departments() as $dept) {
            $personnelByDept[$dept] = array_fill_keys(array_merge(departmentRoles(), ['student']), 0);
        }
        foreach ($deptRows as $row) {
            if (isset($personnelByDept[$row['department']][$row['role']])) {
                $personnelByDept[$row['department']][$row['role']] = (int)$row['total'];
            }
        }

        return [
            'total' => $total,
            'open' => array_sum($pendingByLevel),
            'personnel_by_dept' => $personnelByDept,
            'by_category' => $byCategory,
            'pending_by_level' => $pendingByLevel,
            'users_by_role' => $usersByRole,
            'suspicious_7d' => (int)$this->db->query(
                "SELECT COUNT(*) FROM login_events
                 WHERE created_at >= NOW() - INTERVAL 7 DAY AND " . self::suspiciousSql()
            )->fetchColumn(),
            'locked' => count($this->lockedAccounts()),
        ];
    }

    /* ==============================================================
     * 2. AUTHENTICATION MONITORING
     * ============================================================== */

    /** SQL condition that marks a login_events row as suspicious. */
    public static function suspiciousSql($alias = '') {
        $p = $alias ? $alias . '.' : '';
        $reasons = "'" . implode("','", self::SUSPICIOUS_REASONS) . "'";
        return "({$p}risk_label IN ('medium','high') OR {$p}failure_reason IN ({$reasons}))";
    }

    public static function isSuspicious(array $event) {
        return in_array($event['risk_label'] ?? '', ['medium', 'high'], true)
            || in_array($event['failure_reason'] ?? '', self::SUSPICIOUS_REASONS, true);
    }

    /** Login attempts from the Random Forest log, filtered and paginated. */
    public function loginEvents(array $f, $page = 1, $perPage = 25) {
        $where = [];
        $params = [];
        if (!empty($f['from'])) {
            $where[] = 'e.created_at >= :from';
            $params[':from'] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to'])) {
            $where[] = 'e.created_at <= :to';
            $params[':to'] = $f['to'] . ' 23:59:59';
        }
        if (($f['result'] ?? '') === 'success') {
            $where[] = 'e.success = 1';
        } elseif (($f['result'] ?? '') === 'failed') {
            $where[] = 'e.success = 0';
        }
        if (in_array($f['risk'] ?? '', ['low', 'medium', 'high'], true)) {
            $where[] = 'e.risk_label = :risk';
            $params[':risk'] = $f['risk'];
        } elseif (($f['risk'] ?? '') === 'suspicious') {
            $where[] = self::suspiciousSql('e');
        }
        if (!empty($f['username'])) {
            $where[] = 'e.username_attempted LIKE :username';
            $params[':username'] = '%' . $f['username'] . '%';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = $this->db->prepare("SELECT COUNT(*) FROM login_events e {$whereSql}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $offset = (max(1, (int)$page) - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT e.*, u.full_name, u.role FROM login_events e
             LEFT JOIN users u ON e.user_id = u.user_id
             {$whereSql} ORDER BY e.event_id DESC LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /** Accounts currently blocked (1 day) or in a short cooldown. */
    public function lockedAccounts() {
        return $this->db->query(
            "SELECT l.*, u.full_name, u.role, u.username
             FROM login_lockouts l LEFT JOIN users u ON l.user_id = u.user_id
             WHERE l.blocked_until > NOW() OR l.cooldown_until > NOW()
             ORDER BY COALESCE(l.blocked_until, l.cooldown_until) DESC"
        )->fetchAll();
    }

    /** Clear a lockout so the user can sign in again immediately. */
    public function unlock($lockoutId) {
        $stmt = $this->db->prepare('SELECT * FROM login_lockouts WHERE lockout_id = :id');
        $stmt->execute([':id' => (int)$lockoutId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['success' => false, 'message' => 'Lockout record not found.'];
        }

        // Same reset the existing AccountLockout::clearForUser() performs.
        $this->db->prepare(
            'UPDATE login_lockouts SET consecutive_fails = 0, cooldown_until = NULL,
             blocked_until = NULL, last_failed_at = NULL WHERE lockout_id = :id'
        )->execute([':id' => (int)$lockoutId]);

        $this->audit->record('account_unlock', 'user', $row['user_id'], $row['username_key'], null, [
            'was_blocked_until' => $row['blocked_until'],
            'was_cooldown_until' => $row['cooldown_until'],
            'consecutive_fails' => (int)$row['consecutive_fails'],
        ]);
        return ['success' => true, 'message' => 'Account "' . $row['username_key'] . '" unlocked.'];
    }

    /* ==============================================================
     * 3. USER MANAGEMENT
     * ============================================================== */

    public function users(array $f) {
        $where = [];
        $params = [];
        if (in_array($f['role'] ?? '', allRoles(), true)) {
            $where[] = 'role = :role';
            $params[':role'] = $f['role'];
        }
        // Status filter matches what the list shows (see userPresence()):
        // active = signed in recently, inactive = logged out/idle, deactivated = account off.
        if (($f['status'] ?? '') === 'deactivated') {
            $where[] = "status = 'inactive'";
        } elseif (($f['status'] ?? '') === 'active') {
            $where[] = "status = 'active' AND " . USER_RECENTLY_ACTIVE_SQL;
        } elseif (($f['status'] ?? '') === 'inactive') {
            $where[] = "status = 'active' AND NOT " . USER_RECENTLY_ACTIVE_SQL;
        }
        if (in_array($f['department'] ?? '', departments(), true)) {
            $where[] = 'department = :department';
            $params[':department'] = $f['department'];
        }
        if (!empty($f['q'])) {
            $where[] = '(username LIKE :q1 OR full_name LIKE :q2 OR email LIKE :q3 OR alias LIKE :q4)';
            foreach ([':q1', ':q2', ':q3', ':q4'] as $key) {
                $params[$key] = '%' . $f['q'] . '%';
            }
        }
        $stmt = $this->db->prepare(
            'SELECT user_id, username, email, full_name, alias, role, student_id, department, program,
                    contact_number, status, last_activity_at, ' . USER_RECENTLY_ACTIVE_SQL . ' AS recently_active, created_at
             FROM users ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY role, full_name'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getUser($userId) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE user_id = :id');
        $stmt->execute([':id' => (int)$userId]);
        return $stmt->fetch() ?: null;
    }

    /** Distinct departments/programs already in use, for the form suggestions. */
    public function knownValues($column) {
        $column = $column === 'program' ? 'program' : 'department';
        return $this->db->query(
            "SELECT DISTINCT {$column} FROM users WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column}"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Validate the create/edit form. Returns a list of error messages. */
    private function validateUser(array $d, $existingId = null) {
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $d['username'])) {
            $errors[] = 'Username must be 3-50 letters, numbers, dots, underscores or hyphens.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($d['full_name'] === '') {
            $errors[] = 'Full name is required.';
        }
        if (!in_array($d['role'], allRoles(), true)) {
            $errors[] = 'Choose a valid role.';
        }
        // Department must come from the fixed list, and is required for
        // roles that belong to a single department (coordinator, chairperson).
        if ($d['department'] !== '' && !in_array($d['department'], departments(), true)) {
            $errors[] = 'Choose a department from the list.';
        }
        if ($d['department'] === '' && in_array($d['role'], departmentRoles(), true)) {
            $errors[] = 'A ' . roleLabel($d['role']) . ' must be assigned to a department.';
        }
        // Username and email must stay unique.
        $dup = $this->db->prepare(
            'SELECT username, email FROM users WHERE (username = :u OR email = :e) AND user_id <> :id'
        );
        $dup->execute([':u' => $d['username'], ':e' => $d['email'], ':id' => (int)$existingId]);
        foreach ($dup->fetchAll() as $other) {
            $errors[] = strcasecmp($other['username'], $d['username']) === 0
                ? 'That username is already taken.'
                : 'That email is already used by another account.';
        }
        return array_values(array_unique($errors));
    }

    public function createUser(array $d) {
        $errors = $this->validateUser($d);
        $errors = array_merge($errors, passwordPolicyErrors($d['password'], $d['username'], $d['email']));
        if ($d['password'] !== $d['password_confirm']) {
            $errors[] = 'The two passwords do not match.';
        }
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        // Students get an alias; staff roles do not need one.
        $alias = $d['role'] === 'student' ? (new User())->generateAlias() : null;
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password, full_name, alias, role, student_id, department, program, contact_number, status)
             VALUES (:username, :email, :password, :full_name, :alias, :role, :student_id, :department, :program, :contact, \'active\')'
        );
        $stmt->execute([
            ':username' => $d['username'],
            ':email' => $d['email'],
            ':password' => password_hash($d['password'], PASSWORD_BCRYPT),
            ':full_name' => $d['full_name'],
            ':alias' => $alias,
            ':role' => $d['role'],
            ':student_id' => emptyToNull($d['student_id']),
            ':department' => emptyToNull($d['department']),
            ':program' => emptyToNull($d['program']),
            ':contact' => emptyToNull($d['contact_number']),
        ]);
        $id = (int)$this->db->lastInsertId();

        $this->audit->record('user_create', 'user', $id, $d['username'], null, [
            'role' => $d['role'],
            'department' => $d['department'],
            'program' => $d['program'],
        ]);
        return ['success' => true, 'user_id' => $id];
    }

    public function updateUser($userId, array $d) {
        $before = $this->getUser($userId);
        if (!$before) {
            return ['success' => false, 'errors' => ['User not found.']];
        }
        $errors = $this->validateUser($d, $userId);

        // Guard rails: a Super Admin cannot remove their own Super Admin
        // role, and a workflow level must never be left with nobody active.
        if ((int)$userId === (int)$_SESSION['user_id'] && $d['role'] !== 'super_admin') {
            $errors[] = 'You cannot change your own role.';
        }
        if ($before['role'] !== $d['role'] && $before['status'] === 'active') {
            $lastError = $this->lastActiveGuard($before);
            if ($lastError) {
                $errors[] = $lastError;
            }
        }
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        // Becoming a student → needs an alias for anonymity.
        $alias = $before['alias'];
        if ($d['role'] === 'student' && !$alias) {
            $alias = (new User())->generateAlias();
        }

        $this->db->prepare(
            'UPDATE users SET username = :username, email = :email, full_name = :full_name, alias = :alias,
                role = :role, student_id = :student_id, department = :department, program = :program,
                contact_number = :contact WHERE user_id = :id'
        )->execute([
            ':username' => $d['username'],
            ':email' => $d['email'],
            ':full_name' => $d['full_name'],
            ':alias' => $alias,
            ':role' => $d['role'],
            ':student_id' => emptyToNull($d['student_id']),
            ':department' => emptyToNull($d['department']),
            ':program' => emptyToNull($d['program']),
            ':contact' => emptyToNull($d['contact_number']),
            ':id' => (int)$userId,
        ]);

        // Log only the fields that actually changed, as "old → new".
        $changes = [];
        foreach (['username', 'email', 'full_name', 'role', 'student_id', 'department', 'program', 'contact_number'] as $field) {
            $old = (string)($before[$field] ?? '');
            $new = (string)($d[$field] ?? '');
            if ($old !== $new) {
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
        }
        if ($changes) {
            $this->audit->record('user_update', 'user', (int)$userId, $d['username'], null, $changes);
        }
        return ['success' => true];
    }

    /** Activate or deactivate. Inactive users cannot sign in (LoginAuth only accepts 'active'). */
    public function setStatus($userId, $status) {
        $user = $this->getUser($userId);
        if (!$user || !in_array($status, ['active', 'inactive'], true)) {
            return ['success' => false, 'message' => 'User not found.'];
        }
        if ($status === 'inactive') {
            if ((int)$userId === (int)$_SESSION['user_id']) {
                return ['success' => false, 'message' => 'You cannot deactivate your own account.'];
            }
            $lastError = $this->lastActiveGuard($user);
            if ($lastError) {
                return ['success' => false, 'message' => $lastError];
            }
        }

        $this->db->prepare('UPDATE users SET status = :status WHERE user_id = :id')
            ->execute([':status' => $status, ':id' => (int)$userId]);
        $this->audit->record($status === 'active' ? 'user_reactivate' : 'user_deactivate',
            'user', (int)$userId, $user['username'], null, ['role' => $user['role']]);

        return ['success' => true, 'message' => 'Account "' . $user['username'] . '" is now ' . $status . '.'];
    }

    /**
     * Guidance and OSWD are campus-wide: if the last active one were removed,
     * complaints reaching that level could not be assigned, so it is blocked.
     * (Coordinators/Chairpersons are not protected: when a department has
     * none, routing moves the complaint up to the next level instead.)
     * The last Super Admin is protected the same way.
     */
    private function lastActiveGuard(array $user) {
        $protected = ['guidance_office', 'oswd', 'super_admin'];
        if (!in_array($user['role'], $protected, true) || $user['status'] !== 'active') {
            return null;
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = :role AND status = 'active'");
        $stmt->execute([':role' => $user['role']]);
        if ((int)$stmt->fetchColumn() <= 1) {
            return 'This is the only active ' . roleLabel($user['role'])
                . ' account. Create or activate another one first, or complaints cannot be routed.';
        }
        return null;
    }

    /**
     * Set a new random temporary password and return it ONCE for the
     * Super Admin to pass on. It is never stored in plain text or logged.
     */
    public function resetPassword($userId) {
        $user = $this->getUser($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        // 14 characters guaranteed to satisfy passwordPolicyErrors().
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '!@#$%*?'];
        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        $all = implode('', $sets);
        while (count($chars) < 14) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);
        $temporary = implode('', $chars);

        $this->db->prepare('UPDATE users SET password = :p WHERE user_id = :id')
            ->execute([':p' => password_hash($temporary, PASSWORD_BCRYPT), ':id' => (int)$userId]);
        // A forgotten password often comes with a lockout, so clear that too.
        (new AccountLockout())->clearForUser((int)$userId, strtolower($user['username']));

        $this->audit->record('password_reset', 'user', (int)$userId, $user['username'], null, ['role' => $user['role']]);
        return ['success' => true, 'password' => $temporary, 'username' => $user['username']];
    }

    /* ==============================================================
     * 4. IDENTITY REVEAL
     * ============================================================== */

    /** Find a student by alias. Returns only non-identifying fields. */
    public function findAlias($alias) {
        $stmt = $this->db->prepare(
            "SELECT user_id, alias, created_at,
                    (SELECT COUNT(*) FROM complaints c WHERE c.complainant_id = users.user_id) AS complaint_count
             FROM users WHERE role = 'student' AND alias = :alias"
        );
        $stmt->execute([':alias' => trim((string)$alias)]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Return the real identity behind an alias. A written reason is
     * REQUIRED and the reveal is logged BEFORE the identity is returned,
     * so there is no way to see an identity without leaving a record.
     */
    public function revealIdentity($alias, $reason, $complaintId = null) {
        $reason = trim((string)$reason);
        if (mb_strlen($reason) < 15) {
            return ['success' => false, 'message' => 'Give a specific reason (at least 15 characters).'];
        }
        $match = $this->findAlias($alias);
        if (!$match) {
            return ['success' => false, 'message' => 'No student has that alias.'];
        }

        $this->audit->record('identity_reveal', 'user', (int)$match['user_id'], $match['alias'], $reason,
            $complaintId ? ['complaint_id' => (int)$complaintId] : []);

        $stmt = $this->db->prepare(
            'SELECT user_id, alias, full_name, username, email, student_id, department, program, contact_number, status
             FROM users WHERE user_id = :id'
        );
        $stmt->execute([':id' => (int)$match['user_id']]);
        return ['success' => true, 'identity' => $stmt->fetch()];
    }

    /* ==============================================================
     * 5. COMPLAINT OVERVIEW (view only)
     * ============================================================== */

    public function complaints(array $f) {
        [$whereSql, $params] = $this->complaintWhere($f);
        $stmt = $this->db->prepare(
            "SELECT c.complaint_id, c.complaint_title, c.complaint_category, c.complaint_type, c.department, c.current_level,
                    c.status, c.severity, c.created_at, c.updated_at,
                    COALESCE(u.alias, 'Anonymous student') AS complainant_alias, a.full_name AS assigned_name
             FROM complaints c
             INNER JOIN users u ON c.complainant_id = u.user_id
             LEFT JOIN users a ON c.assigned_to = a.user_id
             {$whereSql} ORDER BY c.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Shared filters for the complaint list and the report exports. */
    private function complaintWhere(array $f) {
        $where = [];
        $params = [];
        if (in_array($f['status'] ?? '', allowedComplaintStatuses(), true)) {
            $where[] = 'c.status = :status';
            $params[':status'] = $f['status'];
        }
        if (in_array($f['department'] ?? '', departments(), true)) {
            $where[] = 'c.department = :department';
            $params[':department'] = $f['department'];
        }
        if (in_array($f['level'] ?? '', workflowLevels(), true)) {
            $where[] = 'c.current_level = :level';
            $params[':level'] = $f['level'];
        }
        if (in_array($f['category'] ?? '', allowedComplaintCategories(), true)) {
            $where[] = 'c.complaint_category = :category';
            $params[':category'] = $f['category'];
        }
        if (!empty($f['from'])) {
            $where[] = 'c.created_at >= :from';
            $params[':from'] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to'])) {
            $where[] = 'c.created_at <= :to';
            $params[':to'] = $f['to'] . ' 23:59:59';
        }
        if (!empty($f['q'])) {
            $where[] = '(c.complaint_title LIKE :q1 OR u.alias LIKE :q2 OR c.complaint_id = :qid)';
            $params[':q1'] = '%' . $f['q'] . '%';
            $params[':q2'] = '%' . $f['q'] . '%';
            $params[':qid'] = (int)$f['q'];
        }
        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /** One complaint with its full routing history. Student shown by alias. */
    public function complaintDetail($complaintId) {
        $complaintObj = new Complaint();
        $complaint = $complaintObj->getComplaintById((int)$complaintId);  // already alias-only
        if (!$complaint) {
            return null;
        }
        $esc = $this->db->prepare(
            'SELECT e.*, b.full_name AS by_name, b.role AS by_role, t.full_name AS to_name, t.role AS to_role
             FROM complaint_escalations e
             LEFT JOIN users b ON e.escalated_by = b.user_id
             LEFT JOIN users t ON e.escalated_to = t.user_id
             WHERE e.complaint_id = :id ORDER BY e.created_at ASC'
        );
        $esc->execute([':id' => (int)$complaintId]);

        return [
            'complaint' => $complaint,
            'timeline' => $complaintObj->getTimeline((int)$complaintId),   // unfiltered: full history
            'escalations' => $esc->fetchAll(),
            'comments' => $complaintObj->getComments((int)$complaintId),
        ];
    }

    /* ==============================================================
     * 7. REPORTS
     * ============================================================== */

    /** Everything the PDF/Excel exports contain, for the given filters. */
    public function reportData(array $f) {
        $rows = $this->complaints($f);
        $summary = ['by_status' => [], 'by_department' => [], 'by_category' => [], 'by_level' => [], 'by_severity' => []];
        foreach ($rows as $r) {
            $summary['by_status'][formatStatus($r['status'])] = ($summary['by_status'][formatStatus($r['status'])] ?? 0) + 1;
            $cat = $r['complaint_category'] ?: 'Uncategorized';
            $summary['by_category'][$cat] = ($summary['by_category'][$cat] ?? 0) + 1;
            $lvl = workflowLevelLabel($r['current_level']);
            $summary['by_level'][$lvl] = ($summary['by_level'][$lvl] ?? 0) + 1;
            $sev = ucfirst((string)$r['severity']);
            $summary['by_severity'][$sev] = ($summary['by_severity'][$sev] ?? 0) + 1;
            $dept = $r['department'] ?: 'No department';
            $summary['by_department'][$dept] = ($summary['by_department'][$dept] ?? 0) + 1;
        }
        foreach ($summary as &$group) {
            arsort($group);
        }
        unset($group);
        return ['rows' => $rows, 'summary' => $summary, 'total' => count($rows)];
    }

    public function logExport($format, array $filters, $count) {
        $this->audit->record('report_export', 'report', null, strtoupper($format), null,
            ['filters' => array_filter($filters), 'rows' => $count]);
    }

    /* ==============================================================
     * Display helpers shared by the pages
     * ============================================================== */

    public static function failureLabel($reason) {
        return match ((string)$reason) {
            'invalid_credentials' => 'Invalid credentials',
            'blocked_risk' => 'Blocked (high risk)',
            'account_blocked' => 'Account locked',
            'login_cooldown' => 'Cooldown',
            'otp_required' => 'OTP required',
            'otp_verified' => 'OTP verified',
            'otp_email_failed' => 'OTP email failed',
            default => $reason ? formatStatus($reason) : '—',
        };
    }

    public static function riskBadgeClass($label) {
        return match ((string)$label) {
            'high' => 'danger',
            'medium' => 'warning',
            'low' => 'success',
            default => 'secondary',
        };
    }
}
