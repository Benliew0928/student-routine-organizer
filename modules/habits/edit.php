<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Edit Habit';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Edit Habit</h1>
    <p class="muted">Habit edit logic will be implemented in Phase 8.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
