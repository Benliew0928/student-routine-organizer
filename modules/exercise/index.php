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

<section class="panel empty-state">
    <h2>No exercise records yet</h2>
    <p class="muted">Start with a workout session, then this page can show totals, recent activity, and progress over time.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
