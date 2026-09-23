<?php
/**
 * ML Classifier Service
 * Interface between PHP and Python ML model
 */

class MLClassifier {
    private $pythonPath;
    private $scriptPath;
    
    public function __construct() {
        $this->pythonPath = 'python'; // or full path to python.exe
        $this->scriptPath = __DIR__ . '/../ml_model/classifier.py';
    }
    
    /**
     * Classify complaint using Random Forest
     */
    public function classifyComplaint($complaintText) {
        try {
            $command = sprintf(
                '%s "%s" predict "%s"',
                $this->pythonPath,
                $this->scriptPath,
                addslashes($complaintText)
            );
            
            $output = shell_exec($command);
            $result = json_decode($output, true);
            
            if ($result && isset($result['category'])) {
                return [
                    'success' => true,
                    'category' => $result['category'],
                    'confidence' => $result['confidence'] ?? 0,
                    'probabilities' => $result['all_probabilities'] ?? []
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Classification failed',
                'category' => 'Unprofessional Behavior', // default
                'confidence' => 0.5
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'category' => 'Unprofessional Behavior', // default
                'confidence' => 0
            ];
        }
    }
    
    /**
     * Train the model with new data
     */
    public function trainModel() {
        try {
            $command = sprintf(
                '%s "%s" train',
                $this->pythonPath,
                $this->scriptPath
            );
            
            $output = shell_exec($command);
            $result = json_decode($output, true);
            
            return $result ?? ['success' => false];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>
