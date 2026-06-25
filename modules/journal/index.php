<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Diary Journal';
require __DIR__ . '/../../includes/header.php';
?>

<section class="section-heading">
    <h1>Diary Journal</h1>
    <p class="muted">Journal entries will be implemented in Phase 6.</p>
    <a class="button primary" href="<?= BASE_URL; ?>/modules/journal/create.php">Add Journal Entry</a>
</section>

<section class="panel empty-state">
    <h2>No journal entries yet</h2>
    <p class="muted">Once journal CRUD is implemented, entries can show mood, date, title, and a short preview here.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
