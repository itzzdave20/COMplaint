<?php

class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($userId, $complaintId, $title, $message) {
        $title = trim((string)$title);
        $message = trim((string)$message);
        if ($title === '' || $message === '') {
            return false;
        }

        $sql = "INSERT INTO notifications (user_id, complaint_id, title, message)
                VALUES (:user_id, :complaint_id, :title, :message)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':complaint_id' => $complaintId,
            ':title' => $title,
            ':message' => $message,
        ]);
    }

    public function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getForUser($userId, $limit = 50) {
        $limit = max(1, min(100, (int)$limit));
        $sql = "SELECT n.*, c.complaint_title
                FROM notifications n
                LEFT JOIN complaints c ON n.complaint_id = c.complaint_id
                WHERE n.user_id = :user_id
                ORDER BY n.created_at DESC
                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function markRead($notificationId, $userId) {
        $sql = "UPDATE notifications SET is_read = 1
                WHERE notification_id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead($userId) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->rowCount();
    }
}
