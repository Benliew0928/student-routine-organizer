<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Exercise Tracker';
require __DIR__ . '/../../includes/header.php';
?>

<section class="section-heading">
    <h1>Exercise Tracker</h1>
    <p class="muted">Workout list and summaries will be implemented in Phase 5.</p>
    <a class="button primary" href="<?= BASE_URL; ?>/modules/exercise/create.php">Add Exercise</a>
</section>

<section class="panel">
    <p class="muted">No exercise records are connected yet.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
