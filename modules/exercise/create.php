<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Add Exercise';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Add Exercise</h1>
    <p class="muted">Exercise create logic will be implemented in Phase 5.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
