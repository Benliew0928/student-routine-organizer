<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Delete Habit';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Delete Habit</h1>
    <p class="muted">Habit delete logic will be implemented in Phase 8.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
