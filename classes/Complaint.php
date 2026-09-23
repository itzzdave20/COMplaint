<?php
/**
 * Complaint Class - Part 1: Core Functions
 */
class Complaint {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function submitComplaint($data) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO complaints (complainant_id, respondent_name, respondent_type, 
                    complaint_title, complaint_description, complaint_category, predicted_category,
                    incident_date, incident_location, severity, supporting_documents) 
                    VALUES (:complainant_id, :respondent_name, :respondent_type, :complaint_title, 
                    :complaint_description, :complaint_category, :predicted_category, :incident_date, 
                    :incident_location, :severity, :supporting_documents)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':complainant_id' => $data['complainant_id'],
                ':respondent_name' => emptyToNull($data['respondent_name'] ?? null),
                ':respondent_type' => emptyToNull($data['respondent_type'] ?? null),
                ':complaint_title' => $data['complaint_title'],
                ':complaint_description' => $data['complaint_description'],
                ':complaint_category' => emptyToNull($data['complaint_category'] ?? null),
                ':predicted_category' => emptyToNull($data['predicted_category'] ?? null),
                ':incident_date' => $data['incident_date'],
                ':incident_location' => emptyToNull($data['incident_location'] ?? null),
                ':severity' => $data['severity'] ?? 'medium',
                ':supporting_documents' => emptyToNull($data['supporting_documents'] ?? null)
            ]);
            
            $complaintId = $this->db->lastInsertId();
            $this->addTimeline($complaintId, $data['complainant_id'], 'created', 'Complaint submitted');
            $this->autoAssignToOSWD($complaintId);
            
            $this->db->commit();
            return ['success' => true, 'complaint_id' => $complaintId];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to submit complaint. Please try again.'];
        }
    }
    
    public function getComplaintById($complaintId) {
        $sql = "SELECT c.*, u.full_name as complainant_name, u.email, u.student_id
                FROM complaints c
                INNER JOIN users u ON c.complainant_id = u.user_id
                WHERE c.complaint_id = :complaint_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':complaint_id' => $complaintId]);
        return $stmt->fetch();
    }

    public function canAccessComplaint($complaint, $userId, $role) {
        if (!$complaint) {
            return false;
        }
        if (in_array($role, staffRoles(), true)) {
            return true;
        }
        return (int)$complaint['complainant_id'] === (int)$userId;
    }
    
    public function getComplaintsByUser($userId) {
        $sql = "SELECT c.* FROM complaints c WHERE c.complainant_id = :user_id 
                ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    public function getAssignedComplaints($userId) {
        $sql = "SELECT c.*, u.full_name as complainant_name
                FROM complaints c
                INNER JOIN users u ON c.complainant_id = u.user_id
                WHERE c.assigned_to = :user_id ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    public function getAllComplaints($filters = []) {
        $sql = "SELECT c.*, u.full_name as complainant_name FROM complaints c
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
    
    public function updateStatus($complaintId, $newStatus, $userId) {
        if (!in_array($newStatus, allowedComplaintStatuses(), true)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }
        try {
            $sql = "UPDATE complaints SET status = :status WHERE complaint_id = :complaint_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':status' => $newStatus, ':complaint_id' => $complaintId]);
            $this->addTimeline($complaintId, $userId, 'status_changed', "Status changed to $newStatus");
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
        return ['success' => true];
    }
    
    public function getComments($complaintId) {
        $sql = "SELECT c.*, u.full_name, u.role FROM complaint_comments c
                INNER JOIN users u ON c.user_id = u.user_id
                WHERE c.complaint_id = :complaint_id ORDER BY c.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':complaint_id' => $complaintId]);
        return $stmt->fetchAll();
    }
    
    public function escalateComplaint($complaintId, $escalatedBy, $reason) {
        $reason = trim((string)$reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'Escalation reason is required'];
        }
        $sql = "INSERT INTO complaint_escalations (complaint_id, escalated_by, escalation_reason) 
                VALUES (:complaint_id, :escalated_by, :reason)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':complaint_id' => $complaintId,
            ':escalated_by' => $escalatedBy,
            ':reason' => $reason
        ]);
        
        $this->updateStatus($complaintId, 'escalated', $escalatedBy);
        return ['success' => true];
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
    
    private function autoAssignToOSWD($complaintId) {
        $sql = "SELECT user_id FROM users WHERE role = 'oswd' AND status = 'active' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $oswd = $stmt->fetch();
        
        if ($oswd) {
            $sql = "UPDATE complaints SET assigned_to = :oswd_id WHERE complaint_id = :complaint_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':oswd_id' => $oswd['user_id'], ':complaint_id' => $complaintId]);
        }
    }
    
    public function getTimeline($complaintId) {
        $sql = "SELECT t.*, u.full_name FROM complaint_timeline t
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

