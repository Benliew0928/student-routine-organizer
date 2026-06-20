<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Add Habit';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Add Habit</h1>
    <p class="muted">Habit create logic will be implemented in Phase 8.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
