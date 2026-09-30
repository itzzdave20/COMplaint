<?php
$flashToast = consumeFlashForToast();
?>
<div id="nemsu-toast-host" class="toast-container position-fixed top-0 end-0 p-3" aria-live="polite" aria-atomic="true"></div>
<?php if ($flashToast !== null): ?>
<div id="nemsu-flash-data" class="visually-hidden"
     data-message="<?php echo htmlspecialchars($flashToast['message'], ENT_QUOTES, 'UTF-8'); ?>"
     data-type="<?php echo htmlspecialchars($flashToast['type'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<?php endif; ?>
