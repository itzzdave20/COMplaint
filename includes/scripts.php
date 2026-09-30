<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="assets/js/toast.js" defer></script>
<script src="assets/js/theme.js" defer></script>
<script src="assets/js/auth.js" defer></script>
<script src="assets/js/app.js" defer></script>
<?php
if (!empty($pageScripts) && is_array($pageScripts)) {
    foreach ($pageScripts as $scriptPath) {
        $scriptPath = (string)$scriptPath;
        if (preg_match('#^(https://cdn\\.jsdelivr\\.net/|assets/)#i', $scriptPath)) {
            echo '<script src="' . htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }
    }
}
?>
