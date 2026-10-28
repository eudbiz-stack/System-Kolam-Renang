<?php
// includes/header.php
if (!isset($page_title)) {
    $page_title = "Kolam Renang Licia";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <?php if (isset($extra_css)): ?>
        <?= $extra_css ?>
    <?php endif; ?>
</head>

<body>
    <!-- Alert Display -->
    <?php
    $alert = getAlert();
    if ($alert):
    ?>
        <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show m-0" role="alert">
            <i class="fas fa-<?= $alert['type'] === 'success' ? 'check-circle' : ($alert['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
            <?= htmlspecialchars($alert['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>