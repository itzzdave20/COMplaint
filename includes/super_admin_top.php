<?php
/**
 * Opening layout for every Super Admin page: the same head, navbar,
 * sidebar and page header as the rest of the system.
 *
 * Set before including:  $pageTitle, $pageHeading, $pageSubtitle,
 * and optionally $pageActions (HTML for buttons on the right).
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = $pageTitle . ' - ' . SITE_NAME; include __DIR__ . '/head.php'; ?>
</head>
<body class="app-body">
<?php include __DIR__ . '/skip_link.php'; ?>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/flash.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 app-main" id="main-content">
            <div class="d-flex justify-content-between flex-wrap align-items-start pt-3 nemsu-page-header gap-2">
                <div>
                    <h1 class="h2"><?php echo htmlspecialchars($pageHeading); ?></h1>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($pageSubtitle); ?></p>
                </div>
                <?php if (!empty($pageActions)): ?>
                    <div class="d-flex flex-wrap gap-2"><?php echo $pageActions; ?></div>
                <?php endif; ?>
            </div>
