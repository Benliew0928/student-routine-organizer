<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Delete Exercise';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Delete Exercise</h1>
    <p class="muted">Exercise delete logic will be implemented in Phase 5.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
