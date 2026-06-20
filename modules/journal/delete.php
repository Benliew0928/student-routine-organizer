<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Delete Journal Entry';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Delete Journal Entry</h1>
    <p class="muted">Journal delete logic will be implemented in Phase 6.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
