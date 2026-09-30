<?php
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0a3d7a">
<title><?php echo htmlspecialchars((string)$pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/style.css">
<?php if (defined('SITE_LOGO') && is_file(__DIR__ . '/../' . SITE_LOGO)): ?>
<link rel="icon" href="<?php echo htmlspecialchars(SITE_LOGO, ENT_QUOTES, 'UTF-8'); ?>" type="image/png">
<?php endif; ?>
