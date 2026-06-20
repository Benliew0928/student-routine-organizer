<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Delete Transaction';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Delete Transaction</h1>
    <p class="muted">Transaction delete logic will be implemented in Phase 7.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
