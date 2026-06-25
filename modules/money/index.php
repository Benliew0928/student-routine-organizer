<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Money Tracker';
require __DIR__ . '/../../includes/header.php';
?>

<section class="section-heading">
    <h1>Money Tracker</h1>
    <p class="muted">Transactions and balance summaries will be implemented in Phase 7.</p>
    <a class="button primary" href="<?= BASE_URL; ?>/modules/money/create.php">Add Transaction</a>
</section>

<section class="panel empty-state">
    <h2>No transactions yet</h2>
    <p class="muted">Add income and expense records later to see balance, category, and date summaries in this space.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
