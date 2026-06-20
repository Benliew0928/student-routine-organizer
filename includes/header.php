<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/validation.php';

$pageTitle = $pageTitle ?? APP_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeOutput($pageTitle); ?> | <?= APP_NAME; ?></title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>
    <main class="page-shell">
        <?php displayFlash(); ?>

