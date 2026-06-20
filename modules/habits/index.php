<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Habit Tracker';
require __DIR__ . '/../../includes/header.php';
?>

<section class="section-heading">
    <h1>Habit Tracker</h1>
    <p class="muted">Habits and completion summaries will be implemented in Phase 8.</p>
    <a class="button primary" href="<?= BASE_URL; ?>/modules/habits/create.php">Add Habit</a>
</section>

<section class="panel">
    <p class="muted">No habit records are connected yet.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
