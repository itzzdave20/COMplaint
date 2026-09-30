<?php

class LoginAuth {
    private $db;
    private $userService;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->userService = new User();
    }

    public function attempt($username, $password) {
        $username = trim((string)$username);
        $context = $this->captureContext();
        $lockout = new AccountLockout();
        $gate = $lockout->inspect($username);

        if (empty($gate['allowed'])) {
            $reason = ($gate['type'] ?? '') === 'blocked' ? 'account_blocked' : 'login_cooldown';
            $this->logEvent(!empty($gate['user']['user_id']) ? (int)$gate['user']['user_id'] : null, $username, false, $reason, $context, null);
            return [
                'success' => false,
                'lockout' => $gate['type'],
                'retry_after' => $gate['retry_after'],
                'show_student_service' => !empty($gate['show_student_service']),
                'message' => $gate['message'],
            ];
        }

        $user = $this->findActiveUser($username);
        if (!$user || !password_verify($password, $user['password'])) {
            $fail = $lockout->recordFailure($username, $gate['user'] ?? null);
            $reason = ($fail['type'] ?? '') === 'blocked' ? 'account_blocked' : 'invalid_credentials';
            $this->logEvent(!empty($fail['user']['user_id']) ? (int)$fail['user']['user_id'] : null, $username, false, $reason, $context, null);
            return [
                'success' => false,
                'lockout' => ($fail['type'] ?? 'invalid') !== 'invalid' ? $fail['type'] : null,
                'retry_after' => $fail['retry_after'] ?? 0,
                'show_student_service' => !empty($fail['show_student_service']),
                'message' => $fail['message'] ?? 'Invalid credentials!',
            ];
        }

        $lockout->recordSuccess($username, $user);

        if ($this->isLoginRfExempt($user)) {
            $risk = ['label' => 'exempt', 'score' => 0.0, 'model_version' => 'exempt'];
            $this->logEvent((int)$user['user_id'], $username, true, null, $context, $risk);
            $this->establishSession($user);
            return ['success' => true, 'message' => 'Login successful!'];
        }

        $features = $this->buildFeatures($user, $context);
        $risk = $this->assessRisk($features);

        if (LOGIN_RF_SHADOW_MODE) {
            $this->logEvent((int)$user['user_id'], $username, true, null, $context, $risk);
            $this->establishSession($user);
            return ['success' => true, 'message' => 'Login successful!', 'risk' => $risk];
        }

        if ($risk['label'] === 'high') {
            $this->logEvent((int)$user['user_id'], $username, false, 'blocked_risk', $context, $risk);
            return [
                'success' => false,
                'message' => 'Login blocked for security reasons. Please try again later or contact OSWD.',
            ];
        }

        if ($risk['label'] === 'medium' && LOGIN_RF_ENABLED) {
            $otp = $this->createOtpChallenge($user);
            if (empty($otp['email_sent'])) {
                $this->logEvent((int)$user['user_id'], $username, false, 'otp_email_failed', $context, $risk);
                return [
                    'success' => false,
                    'message' => $otp['message'] ?? 'Could not send verification email. Please contact OSWD or try again later.',
                ];
            }
            $this->logEvent((int)$user['user_id'], $username, false, 'otp_required', $context, $risk);
            return [
                'success' => false,
                'otp_required' => true,
                'message' => $otp['message'],
            ];
        }

        $this->logEvent((int)$user['user_id'], $username, true, null, $context, $risk);
        $this->establishSession($user);
        return ['success' => true, 'message' => 'Login successful!'];
    }

    public function verifyOtp($code) {
        $pending = $_SESSION['pending_login_otp'] ?? null;
        if (!$pending || empty($pending['user_id'])) {
            return ['success' => false, 'message' => 'No verification pending. Please log in again.'];
        }

        $code = trim((string)$code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return ['success' => false, 'message' => 'Please enter the 6-digit verification code.'];
        }

        if (time() > (int)($pending['expires_at'] ?? 0)) {
            unset($_SESSION['pending_login_otp']);
            return ['success' => false, 'message' => 'Verification code expired. Please log in again.'];
        }

        $hash = hash('sha256', $code . LOGIN_HASH_SALT);
        $sql = "SELECT otp_id FROM login_otps
                WHERE user_id = :user_id AND otp_hash = :hash AND used_at IS NULL AND expires_at > NOW()
                ORDER BY otp_id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $pending['user_id'], ':hash' => $hash]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['success' => false, 'message' => 'Invalid verification code.'];
        }

        $update = $this->db->prepare("UPDATE login_otps SET used_at = NOW() WHERE otp_id = :id");
        $update->execute([':id' => $row['otp_id']]);

        $user = $this->userService->getUserById((int)$pending['user_id']);
        unset($_SESSION['pending_login_otp']);

        if (!$user) {
            return ['success' => false, 'message' => 'Account not found.'];
        }

        $context = $this->captureContext();
        $this->logEvent((int)$user['user_id'], $user['username'], true, 'otp_verified', $context, [
            'label' => 'low',
            'score' => 0.1,
            'model_version' => 'otp',
        ]);
        $this->establishSession($user);
        return ['success' => true, 'message' => 'Verification successful. Welcome back!'];
    }

    public function hasPendingOtp() {
        return !empty($_SESSION['pending_login_otp']['user_id']);
    }

    public function resendOtp() {
        $pending = $_SESSION['pending_login_otp'] ?? null;
        if (!$pending || empty($pending['user_id'])) {
            return ['success' => false, 'message' => 'No verification pending. Please log in again.'];
        }

        $now = time();
        $resend = $_SESSION['otp_resend'] ?? ['count' => 0, 'window_start' => $now];
        if ($now - (int)$resend['window_start'] > LOGIN_OTP_TTL) {
            $resend = ['count' => 0, 'window_start' => $now];
        }
        if ((int)$resend['count'] >= 3) {
            return [
                'success' => false,
                'message' => 'Too many resend attempts. Wait a few minutes, then log in again.',
            ];
        }

        $user = $this->userService->getUserById((int)$pending['user_id']);
        if (!$user) {
            unset($_SESSION['pending_login_otp']);
            return ['success' => false, 'message' => 'Account not found. Please log in again.'];
        }

        $otp = $this->createOtpChallenge($user, true);
        if (empty($otp['email_sent'])) {
            return [
                'success' => false,
                'message' => $otp['message'] ?? 'Could not resend verification email.',
            ];
        }

        $_SESSION['otp_resend'] = [
            'count' => (int)$resend['count'] + 1,
            'window_start' => (int)$resend['window_start'],
        ];

        return [
            'success' => true,
            'message' => 'A new verification code was sent. Check your inbox and spam folder.',
        ];
    }

    private function isLoginRfExempt(array $user) {
        static $exemptUsernames = ['admin', 'oswdadmin'];
        $name = strtolower(trim((string)($user['username'] ?? '')));
        return in_array($name, $exemptUsernames, true);
    }

    private function findActiveUser($username) {
        $sql = "SELECT * FROM users WHERE (username = :username OR email = :email) AND status = 'active' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $username, ':email' => $username]);
        return $stmt->fetch() ?: null;
    }

    private function establishSession(array $user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
    }

    private function createOtpChallenge(array $user, $isResend = false) {
        $email = trim((string)($user['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            unset($_SESSION['pending_login_otp']);
            return [
                'email_sent' => false,
                'message' => 'Your account has no valid email address. Update your profile or contact OSWD.',
            ];
        }

        $code = (string)random_int(100000, 999999);
        $hash = hash('sha256', $code . LOGIN_HASH_SALT);
        $expiresAt = date('Y-m-d H:i:s', time() + LOGIN_OTP_TTL);

        $invalidate = $this->db->prepare(
            'UPDATE login_otps SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL'
        );
        $invalidate->execute([':user_id' => $user['user_id']]);

        $sql = "INSERT INTO login_otps (user_id, otp_hash, expires_at) VALUES (:user_id, :hash, :expires_at)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $user['user_id'],
            ':hash' => $hash,
            ':expires_at' => $expiresAt,
        ]);

        $subject = 'Your OSWD login verification code';
        $minutes = (int)(LOGIN_OTP_TTL / 60);
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : ADMIN_EMAIL;
        $body = SITE_NAME . "\n"
            . "North Eastern Mindanao State University\n\n"
            . 'Hello ' . ($user['full_name'] ?? 'User') . ",\n\n"
            . "Use this code to finish signing in:\n\n"
            . "    {$code}\n\n"
            . "This code expires in {$minutes} minutes.\n\n"
            . "If you did not try to log in, contact OSWD.\n\n"
            . "This message was sent from {$fromEmail}.\n"
            . 'If you do not see it, check your Spam or Promotions folder.';

        $sent = Mailer::send(
            $email,
            $user['full_name'] ?? '',
            $subject,
            $body
        );

        if (!$sent) {
            $otpId = (int)$this->db->lastInsertId();
            if ($otpId > 0) {
                $del = $this->db->prepare('DELETE FROM login_otps WHERE otp_id = :id');
                $del->execute([':id' => $otpId]);
            }
            if (!$isResend) {
                unset($_SESSION['pending_login_otp']);
            }
            $detail = Mailer::getLastError();
            error_log('OTP email failed for user ' . ($user['user_id'] ?? '?') . ' to ' . $email . ': ' . $detail);
            return [
                'email_sent' => false,
                'message' => 'We could not send the verification email. Please try again or contact OSWD.',
            ];
        }

        $_SESSION['pending_login_otp'] = [
            'user_id' => (int)$user['user_id'],
            'email' => $email,
            'expires_at' => time() + LOGIN_OTP_TTL,
        ];

        return [
            'email_sent' => true,
            'message' => 'We sent a 6-digit code to ' . $this->maskEmail($email)
                . '. Check your inbox and spam folder, then enter the code below.',
        ];
    }

    private function maskEmail($email) {
        $email = (string)$email;
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || strlen($parts[0]) < 2) {
            return $email;
        }
        return substr($parts[0], 0, 1) . '***@' . $parts[1];
    }

    private function assessRisk(array $features) {
        if (!LOGIN_RF_ENABLED) {
            return ['label' => 'low', 'score' => 0.0, 'model_version' => 'disabled'];
        }

        $classifier = new LoginRiskClassifier();
        return $classifier->assess($features);
    }

    private function buildFeatures(array $user, array $context) {
        $userId = (int)$user['user_id'];
        $ipHash = $context['ip_hash'];
        $uaHash = $context['user_agent_hash'];

        return [
            'hour' => (int)date('G'),
            'day_of_week' => (int)date('w'),
            'failed_attempts_user_15m' => $this->countRecentFailures('username_attempted', $user['username']),
            'failed_attempts_ip_15m' => $this->countRecentFailures('ip_hash', $ipHash),
            'is_new_ip_for_user' => $this->isNewForUser($userId, 'ip_hash', $ipHash) ? 1 : 0,
            'is_new_user_agent' => $this->isNewForUser($userId, 'user_agent_hash', $uaHash) ? 1 : 0,
            'account_age_days' => max(0, (int)floor((time() - strtotime($user['created_at'])) / 86400)),
            'role_student' => ($user['role'] ?? '') === 'student' ? 1 : 0,
        ];
    }

    private function countRecentFailures($field, $value) {
        $allowed = ['username_attempted', 'ip_hash'];
        if (!in_array($field, $allowed, true)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM login_events
                WHERE {$field} = :value AND success = 0
                AND created_at >= (NOW() - INTERVAL 15 MINUTE)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':value' => $value]);
        return (int)$stmt->fetchColumn();
    }

    private function isNewForUser($userId, $field, $value) {
        $allowed = ['ip_hash', 'user_agent_hash'];
        if (!in_array($field, $allowed, true)) {
            return true;
        }

        $sql = "SELECT COUNT(*) FROM login_events
                WHERE user_id = :user_id AND success = 1 AND {$field} = :value";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':value' => $value]);
        return (int)$stmt->fetchColumn() === 0;
    }

    private function captureContext() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return [
            'ip_hash' => hash('sha256', $ip . LOGIN_HASH_SALT),
            'user_agent_hash' => hash('sha256', $ua . LOGIN_HASH_SALT),
        ];
    }

    private function logEvent($userId, $username, $success, $failureReason, array $context, $risk) {
        try {
            $sql = "INSERT INTO login_events (user_id, username_attempted, success, failure_reason,
                    ip_hash, user_agent_hash, risk_score, risk_label, model_version)
                    VALUES (:user_id, :username, :success, :failure_reason, :ip_hash, :ua_hash,
                    :risk_score, :risk_label, :model_version)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':username' => $username,
                ':success' => $success ? 1 : 0,
                ':failure_reason' => $failureReason,
                ':ip_hash' => $context['ip_hash'],
                ':ua_hash' => $context['user_agent_hash'],
                ':risk_score' => is_array($risk) ? ($risk['score'] ?? null) : null,
                ':risk_label' => is_array($risk) ? ($risk['label'] ?? null) : null,
                ':model_version' => is_array($risk) ? ($risk['model_version'] ?? null) : null,
            ]);
        } catch (PDOException $e) {
            // Logging must not block authentication.
        }
    }
}
