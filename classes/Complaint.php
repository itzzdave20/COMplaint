<?php
/**
 * Complaint Class - Part 1: Core Functions
 */
class Complaint {
    /** Name shown for a user: students by alias, staff by their real name. */
    const DISPLAY_NAME_SQL = "CASE WHEN u.role = 'student' THEN COALESCE(u.alias, 'Anonymous student') ELSE u.full_name END";

    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function submitComplaint($data) {
        try {
            $this->db->beginTransaction();

            $category = $data['complaint_category'] ?? null;
            $complaintType = complaintTypeFromCategory($category);
            $currentLevel = initialWorkflowLevel($complaintType);
            // Snapshot of the student's department; routing uses this value.
            $department = $this->complainantDepartment($data['complainant_id']);

            $sql = "INSERT INTO complaints (complainant_id, respondent_name, respondent_type,
                    complaint_title, complaint_description, complaint_category, predicted_category,
                    complaint_type, department, incident_date, incident_location, severity, supporting_documents,
                    current_level)
                    VALUES (:complainant_id, :respondent_name, :respondent_type, :complaint_title,
                    :complaint_description, :complaint_category, :predicted_category, :complaint_type,
                    :department, :incident_date, :incident_location, :severity, :supporting_documents, :current_level)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':complainant_id' => $data['complainant_id'],
                ':respondent_name' => emptyToNull($data['respondent_name'] ?? null),
                ':respondent_type' => emptyToNull($data['respondent_type'] ?? null),
                ':complaint_title' => $data['complaint_title'],
                ':complaint_description' => $data['complaint_description'],
                ':complaint_category' => emptyToNull($category),
                ':predicted_category' => emptyToNull($data['predicted_category'] ?? null),
                ':complaint_type' => $complaintType,
                ':department' => $department,
                ':incident_date' => $data['incident_date'],
                ':incident_location' => emptyToNull($data['incident_location'] ?? null),
                ':severity' => $data['severity'] ?? 'medium',
                ':supporting_documents' => emptyToNull($data['supporting_documents'] ?? null),
                ':current_level' => $currentLevel
            ]);

            $complaintId = $this->db->lastInsertId();
            $this->addTimeline($complaintId, $data['complainant_id'], 'created', 'Complaint submitted');
            // May land on a higher level if the department has nobody at this one.
            $route = $this->assignToRole($complaintId, $currentLevel, $data['complainant_id'], true, $department);

            $this->db->commit();

            $levelLabel = workflowLevelLabel($route['level']);
            $this->notifyComplainant(
                (int)$complaintId,
                'Complaint submitted',
                "Your complaint #{$complaintId} was received and routed to {$levelLabel}.",
                null
            );

            return ['success' => true, 'complaint_id' => $complaintId];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to submit complaint. Please try again.'];
        }
    }
    
    public function getComplaintById($complaintId) {
        // Anonymity: the complainant is shown by alias, never by real name,
        // email or student ID. Only the Super Admin can reveal the identity
        // (SuperAdmin::revealIdentity), and every reveal is audit-logged.
        $sql = "SELECT c.*, COALESCE(u.alias, 'Anonymous student') as complainant_name,
                       a.full_name as assigned_name, a.role as assigned_role
                FROM complaints c
                INNER JOIN users u ON c.complainant_id = u.user_id
                LEFT JOIN users a ON c.assigned_to = a.user_id
                WHERE c.complaint_id = :complaint_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':complaint_id' => $complaintId]);
        return $stmt->fetch();
    }

    public function canAccessComplaint($complaint, $userId, $role) {
        if (!$complaint) {
            return false;
        }
        if ($role === 'oswd') {
            return true;
        }
        if (in_array($role, staffRoles(), true)) {
            return (int)($complaint['assigned_to'] ?? 0) === (int)$userId;
        }
        return (int)$complaint['complainant_id'] === (int)$userId;
    }

    public function canManageComplaint($complaint, $userId, $role) {
        if (!$complaint) {
            return false;
        }
        if (in_array($role, staffRoles(), true)) {
            if (($complaint['current_level'] ?? '') !== $role) {
                return false;
            }
            if ($role === 'oswd') {
                return true;
            }
            return (int)($complaint['assigned_to'] ?? 0) === (int)$userId;
        }
        return false;
    }

    public function canEscalate($complaint, $userId, $role) {
        if (!$this->canManageComplaint($complaint, $userId, $role)) {
            return false;
        }
        if (($complaint['complaint_type'] ?? 'behavioral') === 'services') {
            return false;
        }
        if (in_array($complaint['status'], ['resolved', 'rejected'], true)) {
            return false;
        }
        return nextWorkflowLevel($complaint['current_level'] ?? '') !== null;
    }
    
    public function getComplaintsByUser($userId) {
        $sql = "SELECT c.*, a.full_name as assigned_name
                FROM complaints c
                LEFT JOIN users a ON c.assigned_to = a.user_id
                WHERE c.complainant_id = :user_id
                ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAssignedComplaints($userId) {
        $sql = "SELECT c.*, COALESCE(u.alias, 'Anonymous student') as complainant_name, a.full_name as assigned_name
                FROM complaints c
                INNER JOIN users u ON c.complainant_id = u.user_id
                LEFT JOIN users a ON c.assigned_to = a.user_id
                WHERE c.assigned_to = :user_id
                ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getComplaintsForDashboard($userId, $role) {
        if ($role === 'student') {
            return $this->getComplaintsByUser($userId);
        }
        if ($role === 'oswd') {
            return $this->getAllComplaints();
        }
        return $this->getAssignedComplaints($userId);
    }
    
    public function getAllComplaints($filters = []) {
        $sql = "SELECT c.*, COALESCE(u.alias, 'Anonymous student') as complainant_name FROM complaints c
                INNER JOIN users u ON c.complainant_id = u.user_id WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function updateStatus($complaintId, $newStatus, $userId, $notifyStudent = true) {
        if (!in_array($newStatus, allowedComplaintStatuses(), true)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }

        $complaint = $this->getComplaintById($complaintId);
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        $allowed = selectableStatusesForUpdate($complaint['status'] ?? 'pending');
        if (!in_array($newStatus, $allowed, true)) {
            return ['success' => false, 'message' => 'That status change is not allowed from the current state.'];
        }

        if (($complaint['status'] ?? '') === $newStatus) {
            return ['success' => true];
        }

        try {
            $sql = "UPDATE complaints SET status = :status WHERE complaint_id = :complaint_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':status' => $newStatus, ':complaint_id' => $complaintId]);
            $this->addTimeline($complaintId, $userId, 'status_changed', 'Status updated to ' . formatStatus($newStatus));

            if ($notifyStudent) {
                $this->notifyComplainant(
                    (int)$complaintId,
                    'Status updated',
                    'Complaint #' . $complaintId . ' is now ' . formatStatus($newStatus) . '.',
                    (int)$userId
                );
            }

            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to update status. Please try again.'];
        }
    }
    
    public function addComment($complaintId, $userId, $comment) {
        $comment = trim((string)$comment);
        if ($comment === '') {
            return ['success' => false, 'message' => 'Comment cannot be empty'];
        }
        $sql = "INSERT INTO complaint_comments (complaint_id, user_id, comment_text) 
                VALUES (:complaint_id, :user_id, :comment_text)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':complaint_id' => $complaintId,
            ':user_id' => $userId,
            ':comment_text' => $comment
        ]);

        $complaint = $this->getComplaintById($complaintId);
        if ($complaint && (int)$complaint['complainant_id'] !== (int)$userId) {
            $preview = strlen($comment) > 120 ? substr($comment, 0, 117) . '...' : $comment;
            $this->notifyComplainant(
                (int)$complaintId,
                'New comment on your complaint',
                'Complaint #' . $complaintId . ': ' . $preview,
                (int)$userId
            );
        }

        return ['success' => true];
    }
    
    public function getComments($complaintId) {
        // Students appear by alias in comments too, so staff cannot learn the name here.
        $sql = "SELECT c.*, " . self::DISPLAY_NAME_SQL . " AS full_name, u.role FROM complaint_comments c
                INNER JOIN users u ON c.user_id = u.user_id
                WHERE c.complaint_id = :complaint_id ORDER BY c.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':complaint_id' => $complaintId]);
        return $stmt->fetchAll();
    }
    
    public function escalateComplaint($complaintId, $escalatedBy, $reason, $role) {
        $reason = trim((string)$reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'Escalation reason is required'];
        }

        $complaint = $this->getComplaintById($complaintId);
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }
        if (!$this->canEscalate($complaint, $escalatedBy, $role)) {
            return ['success' => false, 'message' => 'You cannot escalate this complaint'];
        }

        $nextLevel = nextWorkflowLevel($complaint['current_level'] ?? '');
        if ($nextLevel === null) {
            return ['success' => false, 'message' => 'No further escalation level is available'];
        }

        try {
            if (!$this->resolveRoute($nextLevel, $complaint['department'] ?? null)) {
                return ['success' => false, 'message' => 'No active staff account is available at the next level'];
            }

            $this->db->beginTransaction();

            // Forward within the complaint's department (see resolveRoute).
            $route = $this->assignToRole($complaintId, $nextLevel, $escalatedBy, false, $complaint['department'] ?? null);

            $sql = "INSERT INTO complaint_escalations (complaint_id, escalated_by, escalated_to, escalation_reason)
                    VALUES (:complaint_id, :escalated_by, :escalated_to, :reason)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':complaint_id' => $complaintId,
                ':escalated_by' => $escalatedBy,
                ':escalated_to' => $route['user']['user_id'],
                ':reason' => $reason
            ]);

            $this->updateStatus($complaintId, 'under_review', $escalatedBy, false);
            $label = roleLabel($route['level']);
            $this->addTimeline(
                $complaintId,
                $escalatedBy,
                'escalated',
                "Escalated to {$label}" . $this->skippedNote($route, $complaint['department'] ?? null) . ": {$reason}"
            );

            $this->db->commit();

            $this->notifyComplainant(
                (int)$complaintId,
                'Complaint forwarded',
                "Your complaint #{$complaintId} was escalated to {$label} for further review.",
                (int)$escalatedBy
            );

            return ['success' => true];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to escalate complaint. Please try again.'];
        }
    }

    /* ------------------------------------------------------------------
     * DEPARTMENT-BASED ROUTING
     *
     * Program Coordinator and Program Chairperson belong to ONE department,
     * so a complaint goes to the one in the complainant's department.
     * Guidance and OSWD serve the whole campus.
     *
     * Order of preference at a department level:
     *   1. active staff of that role in the complaint's department
     *   2. active staff of that role with NO department set (campus-wide
     *      backup — keeps older accounts working until they are assigned)
     *   3. nobody → skip up to the next level (e.g. Coordinator → Chairperson)
     * The chain always ends at OSWD, so a complaint is never left unassigned.
     * ------------------------------------------------------------------ */

    /** The complainant's department, if it is one of the official departments. */
    private function complainantDepartment($userId) {
        $stmt = $this->db->prepare('SELECT department FROM users WHERE user_id = :id');
        $stmt->execute([':id' => (int)$userId]);
        $department = strtoupper(trim((string)$stmt->fetchColumn()));   // "dcs" → "DCS"
        return in_array($department, departments(), true) ? $department : null;
    }

    /** One active staff member for a level (and department, where it applies). */
    private function findActiveUserForWorkflowLevel($level, $department = null) {
        if (!in_array($level, departmentRoles(), true)) {
            // Campus-wide level: first active account of that role.
            $stmt = $this->db->prepare(
                "SELECT user_id, full_name FROM users WHERE role = :role AND status = 'active' ORDER BY user_id ASC LIMIT 1"
            );
            $stmt->execute([':role' => $level]);
            return $stmt->fetch() ?: null;
        }

        // Department level: same department first, then campus-wide backup.
        $stmt = $this->db->prepare(
            "SELECT user_id, full_name FROM users
             WHERE role = :role AND status = 'active'
               AND (department = :department OR department IS NULL OR department = '')
             ORDER BY (department = :department2) DESC, user_id ASC LIMIT 1"
        );
        $stmt->execute([':role' => $level, ':department' => (string)$department, ':department2' => (string)$department]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Starting at $level, find who should receive the complaint, moving up
     * the chain past any level with nobody available.
     * Returns ['level' => ..., 'user' => [...], 'skipped' => [levels]] or null.
     */
    private function resolveRoute($level, $department) {
        $skipped = [];
        while ($level !== null) {
            $user = $this->findActiveUserForWorkflowLevel($level, $department);
            if ($user) {
                return ['level' => $level, 'user' => $user, 'skipped' => $skipped];
            }
            $skipped[] = $level;
            $level = nextWorkflowLevel($level);
        }
        return null;
    }

    /** Text such as " (no Program Coordinator for DCS)" for the history. */
    private function skippedNote(array $route, $department) {
        if (!$route['skipped']) {
            return '';
        }
        $labels = implode(' or ', array_map('roleLabel', $route['skipped']));
        return ' (no ' . $labels . ' for ' . ($department ?: 'this department') . ')';
    }

    /** Assign the complaint to the resolved person and level. Returns the route. */
    private function assignToRole($complaintId, $level, $actionBy, $isInitial, $department = null) {
        $route = $this->resolveRoute($level, $department);
        if (!$route) {
            throw new PDOException('No assignee for workflow level: ' . $level);
        }

        $sql = "UPDATE complaints SET assigned_to = :assigned_to, current_level = :current_level WHERE complaint_id = :complaint_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':assigned_to' => $route['user']['user_id'],
            ':current_level' => $route['level'],
            ':complaint_id' => $complaintId
        ]);

        if ($isInitial) {
            $label = roleLabel($route['level']);
            $where = $department && in_array($route['level'], departmentRoles(), true) ? " — {$department}" : '';
            $this->addTimeline(
                $complaintId,
                $actionBy,
                'assigned',
                "Routed to {$label} ({$route['user']['full_name']}){$where}" . $this->skippedNote($route, $department)
            );
        }
        return $route;
    }
    
    private function notifyComplainant($complaintId, $title, $message, $actorUserId = null) {
        if (!class_exists('Notification')) {
            return;
        }

        $complaint = $this->getComplaintById($complaintId);
        if (!$complaint) {
            return;
        }

        $complainantId = (int)$complaint['complainant_id'];
        if ($actorUserId !== null && $complainantId === (int)$actorUserId) {
            return;
        }

        try {
            $notification = new Notification();
            $notification->create($complainantId, $complaintId, $title, $message);
        } catch (PDOException $e) {
            // Notifications must not block complaint workflow.
        }
    }

    private function addTimeline($complaintId, $userId, $actionType, $description) {
        $sql = "INSERT INTO complaint_timeline (complaint_id, action_by, action_type, action_description) 
                VALUES (:complaint_id, :action_by, :action_type, :description)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':complaint_id' => $complaintId,
            ':action_by' => $userId,
            ':action_type' => $actionType,
            ':description' => $description
        ]);
    }
    
    
    public function getTimeline($complaintId) {
        // Same rule for the routing history: student actions show the alias.
        $sql = "SELECT t.*, " . self::DISPLAY_NAME_SQL . " AS full_name FROM complaint_timeline t
                INNER JOIN users u ON t.action_by = u.user_id
                WHERE t.complaint_id = :complaint_id ORDER BY t.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':complaint_id' => $complaintId]);
        return $stmt->fetchAll();
    }
    
    public function getStatistics() {
        $sql = "SELECT status, COUNT(*) as count FROM complaints GROUP BY status";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();
        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['status']] = (int)$row['count'];
        }
        return $stats;
    }
}
?>

