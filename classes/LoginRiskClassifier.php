<?php

class LoginRiskClassifier {
    private $scriptPath;
    private $pythonPath;

    public function __construct() {
        $this->scriptPath = ML_MODEL_PATH . 'login_risk.py';
        $this->pythonPath = $this->resolvePython();
    }

    public function assess(array $features) {
        $pythonResult = $this->predictWithPython($features);
        if ($pythonResult !== null) {
            return $pythonResult;
        }

        return $this->ruleFallback($features);
    }

    private function predictWithPython(array $features) {
        if (!$this->pythonPath || !is_file($this->scriptPath) || !function_exists('shell_exec')) {
            return null;
        }

        $json = json_encode($features, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return null;
        }

        $command = escapeshellarg($this->pythonPath) . ' '
            . escapeshellarg($this->scriptPath) . ' predict '
            . escapeshellarg($json) . ' 2>&1';
        $output = @shell_exec($command);
        if (!$output) {
            return null;
        }

        $result = json_decode($output, true);
        if (!is_array($result) || empty($result['label'])) {
            return null;
        }

        return [
            'label' => (string)$result['label'],
            'score' => (float)($result['score'] ?? 0),
            'model_version' => (string)($result['model_version'] ?? 'unknown'),
        ];
    }

    private function ruleFallback(array $features) {
        $failIp = (int)($features['failed_attempts_ip_15m'] ?? 0);
        $failUser = (int)($features['failed_attempts_user_15m'] ?? 0);
        $newIp = (int)($features['is_new_ip_for_user'] ?? 0);
        $newUa = (int)($features['is_new_user_agent'] ?? 0);

        if ($failIp >= 10 || $failUser >= 6) {
            $label = 'high';
        } elseif ($failIp >= 4 || $failUser >= 3 || ($newIp && $newUa)) {
            $label = 'medium';
        } else {
            $label = 'low';
        }

        $scores = ['low' => 0.15, 'medium' => 0.55, 'high' => 0.9];
        return [
            'label' => $label,
            'score' => $scores[$label],
            'model_version' => 'rules',
        ];
    }

    private function resolvePython() {
        $candidates = [];
        if (defined('PYTHON_PATH') && PYTHON_PATH) {
            $candidates[] = PYTHON_PATH;
        }
        $candidates = array_merge($candidates, ['python', 'py', 'python3']);

        foreach ($candidates as $candidate) {
            if ($this->pythonWorks($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function pythonWorks($binary) {
        $output = @shell_exec(escapeshellarg($binary) . ' --version 2>&1');
        return is_string($output) && stripos($output, 'python') !== false;
    }
}
