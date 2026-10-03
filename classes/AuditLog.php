<?php
/**
 * AuditLog — append-only record of Super Admin actions, plus OSWD changes
 * to the enrolled-students list (which decides who may register).
 *
 * This class can only INSERT and SELECT. There is deliberately no update or
 * delete method, and the database triggers created in
 * migrate_super_admin.php reject UPDATE/DELETE on the table as a second
 * line of defence. Nobody, including the Super Admin, can edit history.
 */
class AuditLog {
    // Every action the log may contain, with the label shown on screen.
    const ACTIONS = [
        'identity_reveal'   => 'Identity reveal',
        'user_create'       => 'Account created',
        'user_update'       => 'Account edited',
        'user_deactivate'   => 'Account deactivated',
        'user_reactivate'   => 'Account reactivated',
        'password_reset'    => 'Password reset',
        'account_unlock'    => 'Account unlocked',
        'report_export'     => 'Report exported',
        'enrollment_upload' => 'Enrolled list uploaded',
        'enrollment_add'    => 'Enrolled student added',
        'enrollment_remove' => 'Enrolled student removed',
        'backup_create'     => 'Backup created',
        'backup_download'   => 'Backup downloaded',
        'backup_upload'     => 'Backup uploaded',
        'backup_delete'     => 'Backup deleted',
        'backup_restore'    => 'Database restored',
        'settings_update'   => 'Settings changed',
        'test_email'        => 'Test email sent',
        'super_admin_cli'   => 'Super Admin created (CLI)',
    ];

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Write one entry. The actor is taken from the current session unless
     * $actor is given (the CLI script passes its own).
     */
    public function record($action, $targetType = null, $targetId = null, $targetLabel = null,
                           $reason = null, array $details = [], ?array $actor = null) {
        $actor = $actor ?? [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? 'unknown',
        ];
        // Store a hash of the IP, like login_events does, not the raw address.
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        $stmt = $this->db->prepare(
            'INSERT INTO super_admin_audit_log
                (actor_id, actor_username, action, target_type, target_id, target_label, reason, details, ip_hash)
             VALUES (:actor_id, :actor_username, :action, :target_type, :target_id, :target_label, :reason, :details, :ip_hash)'
        );
        $stmt->execute([
            ':actor_id' => $actor['id'],
            ':actor_username' => $actor['username'],
            ':action' => $action,
            ':target_type' => $targetType,
            ':target_id' => $targetId,
            ':target_label' => $targetLabel,
            ':reason' => $reason,
            ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            ':ip_hash' => hash('sha256', $ip . LOGIN_HASH_SALT),
        ]);
    }

    /** Filtered, paginated read for the Audit log page. */
    public function search(array $filters, $page = 1, $perPage = 25) {
        [$where, $params] = $this->buildWhere($filters);

        $count = $this->db->prepare("SELECT COUNT(*) FROM super_admin_audit_log {$where}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $offset = (max(1, (int)$page) - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT * FROM super_admin_audit_log {$where}
             ORDER BY log_id DESC LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    private function buildWhere(array $filters) {
        $clauses = [];
        $params = [];
        if (!empty($filters['action']) && isset(self::ACTIONS[$filters['action']])) {
            $clauses[] = 'action = :action';
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['actor'])) {
            $clauses[] = 'actor_username LIKE :actor';
            $params[':actor'] = '%' . $filters['actor'] . '%';
        }
        if (!empty($filters['from'])) {
            $clauses[] = 'created_at >= :from';
            $params[':from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $clauses[] = 'created_at <= :to';
            $params[':to'] = $filters['to'] . ' 23:59:59';
        }
        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    public static function label($action) {
        return self::ACTIONS[$action] ?? formatStatus($action);
    }
}
