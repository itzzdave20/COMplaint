<?php
/**
 * ML Classifier Service
 * Interface between PHP and Python ML model
 */

class MLClassifier {
    private $pythonPath;
    private $scriptPath;
    
    public function __construct() {
        $this->scriptPath = __DIR__ . '/../ml_model/classifier.py';
        $this->pythonPath = $this->resolvePython();
    }
    
    /**
     * Classify complaint using Random Forest, with keyword fallback
     */
    public function classifyComplaint($complaintText) {
        $complaintText = trim(preg_replace('/\s+/', ' ', (string)$complaintText));
        $pythonResult = $this->classifyWithPython($complaintText);

        if ($pythonResult && !empty($pythonResult['category'])) {
            return [
                'success' => true,
                'category' => $pythonResult['category'],
                'confidence' => $pythonResult['confidence'] ?? 0,
                'probabilities' => $pythonResult['all_probabilities'] ?? []
            ];
        }

        $fallback = $this->keywordClassify($complaintText);
        return [
            'success' => true,
            'category' => $fallback['category'],
            'confidence' => $fallback['confidence'],
            'probabilities' => []
        ];
    }
    
    /**
     * Train the model with new data
     */
    public function trainModel() {
        if (!$this->pythonPath || !is_file($this->scriptPath) || !function_exists('shell_exec')) {
            return ['success' => false, 'message' => 'Python ML service is not available'];
        }

        $command = $this->buildCommand(['train']);
        $output = @shell_exec($command);
        $result = json_decode((string)$output, true);

        return $result ?? ['success' => false, 'message' => 'Training failed'];
    }

    private function classifyWithPython($complaintText) {
        if ($complaintText === '' || !$this->pythonPath || !is_file($this->scriptPath) || !function_exists('shell_exec')) {
            return null;
        }

        $command = $this->buildCommand(['predict', $complaintText]);
        $output = @shell_exec($command);
        if (!$output) {
            return null;
        }

        $result = json_decode($output, true);
        if (!is_array($result) || empty($result['category'])) {
            return null;
        }

        return $result;
    }

    private function buildCommand(array $args) {
        $parts = [escapeshellarg($this->pythonPath), escapeshellarg($this->scriptPath)];
        foreach ($args as $arg) {
            $parts[] = escapeshellarg($arg);
        }
        return implode(' ', $parts) . ' 2>&1';
    }

    private function resolvePython() {
        $candidates = [];

        if (defined('PYTHON_PATH') && PYTHON_PATH) {
            $candidates[] = PYTHON_PATH;
        }

        $candidates[] = 'python';
        $candidates[] = 'py';
        $candidates[] = 'python3';
        $candidates[] = 'C:\\Program Files\\Python314\\python.exe';
        $candidates[] = 'C:\\Users\\HP\\AppData\\Local\\Programs\\Python\\Python313\\python.exe';

        foreach ($candidates as $candidate) {
            if ($this->pythonWorks($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function pythonWorks($binary) {
        if (!function_exists('shell_exec')) {
            return false;
        }
        $output = @shell_exec(escapeshellarg($binary) . ' --version 2>&1');
        return is_string($output) && stripos($output, 'python') !== false;
    }

    private function keywordClassify($text) {
        $text = strtolower($text);
        $map = [
            'Academic Integrity Violation' => ['grade', 'grading', 'favoritism', 'unfair', 'exam', 'cheat', 'plagiar', 'bias'],
            'Unprofessional Behavior' => ['harass', 'discriminat', 'abuse', 'intimidat', 'bully', 'verbal', 'threat'],
            'Institutional Rules Violation' => ['policy', 'code of conduct', 'rule', 'regulation', 'violation', 'handbook'],
            'Teaching Standards Failure' => ['teaching', 'communication', 'neglect', 'feedback', 'unprepared', 'quality', 'absent']
        ];

        $scores = [];
        foreach ($map as $category => $keywords) {
            $scores[$category] = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $scores[$category]++;
                }
            }
        }

        arsort($scores);
        $best = array_key_first($scores);
        if ($scores[$best] === 0) {
            return ['category' => 'Unprofessional Behavior', 'confidence' => 0.5];
        }

        return [
            'category' => $best,
            'confidence' => min(0.95, 0.55 + ($scores[$best] * 0.1))
        ];
    }
}
?>
