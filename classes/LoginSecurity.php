<?php

class LoginSecurity {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getSummary($days = 7) {
        $days = max(1, min(90, (int)$days));
        $summary = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'high_risk' => 0,
            'medium_risk' => 0,
            'otp_required' => 0,
            'blocked' => 0,
        ];

        try {
            $sql = "SELECT
                        COUNT(*) AS total,
                        SUM(success = 1) AS success_count,
                        SUM(success = 0) AS failed_count,
                        SUM(risk_label = 'high') AS high_risk,
                        SUM(risk_label = 'medium') AS medium_risk,
                        SUM(failure_reason = 'otp_required') AS otp_required,
                        SUM(failure_reason = 'blocked_risk') AS blocked
                    FROM login_events
                    WHERE created_at >= (NOW() - INTERVAL :days DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            $row = $stmt->fetch();
            if ($row) {
                $summary['total'] = (int)$row['total'];
                $summary['success'] = (int)$row['success_count'];
                $summary['failed'] = (int)$row['failed_count'];
                $summary['high_risk'] = (int)$row['high_risk'];
                $summary['medium_risk'] = (int)$row['medium_risk'];
                $summary['otp_required'] = (int)$row['otp_required'];
                $summary['blocked'] = (int)$row['blocked'];
            }
        } catch (PDOException $e) {
            // Table may not exist yet.
        }

        return $summary;
    }

    public function getRecentEvents($limit = 100) {
        $limit = max(1, min(500, (int)$limit));
        try {
            $sql = "SELECT e.*, u.full_name
                    FROM login_events e
                    LEFT JOIN users u ON e.user_id = u.user_id
                    ORDER BY e.event_id DESC
                    LIMIT {$limit}";
            return $this->db->query($sql)->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getRecentEventsPaginated($page = 1, $perPage = 10) {
        $page = max(1, (int)$page);
        $perPage = max(1, min(100, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        try {
            $total = (int)$this->db->query('SELECT COUNT(*) FROM login_events')->fetchColumn();

            $sql = "SELECT e.*, u.full_name
                    FROM login_events e
                    LEFT JOIN users u ON e.user_id = u.user_id
                    ORDER BY e.event_id DESC
                    LIMIT {$perPage} OFFSET {$offset}";
            $rows = $this->db->query($sql)->fetchAll();

            return [
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => max(1, (int)ceil($total / $perPage)),
            ];
        } catch (PDOException $e) {
            return [
                'rows' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
            ];
        }
    }

    public function retrainModel() {
        $script = ML_MODEL_PATH . 'login_risk.py';
        if (!is_file($script) || !function_exists('shell_exec')) {
            return ['success' => false, 'message' => 'Python training is not available on this server.'];
        }

        $bins = [PYTHON_PATH, 'python', 'py', 'python3'];
        foreach ($bins as $bin) {
            $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($script) . ' train 2>&1';
            $out = @shell_exec($cmd);
            if ($out && str_contains($out, '"success"')) {
                $data = json_decode($out, true);
                return [
                    'success' => true,
                    'message' => 'Login risk model retrained successfully.',
                    'accuracy' => $data['accuracy'] ?? null,
                ];
            }
        }

        return ['success' => false, 'message' => 'Training failed. Install Python + scikit-learn and run ml_model/login_risk.py train'];
    }

    public function modelExists() {
        return is_file(ML_MODEL_PATH . 'models/login_risk.pkl');
    }
}
