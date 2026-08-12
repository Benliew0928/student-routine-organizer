<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/money_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$transactionId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$transactionId) {
    setFlash('error', 'Invalid transaction record.');
    header('Location: ' . BASE_URL . '/modules/money/index.php');
    exit;
}

$transaction = null;
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $transaction = moneyLoadForUser($connection, (int) $transactionId, $userId);

    if (!$transaction) {
        setFlash('error', 'Transaction record was not found.');
        header('Location: ' . BASE_URL . '/modules/money/index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Your session token expired. Please try again.');
            header('Location: ' . BASE_URL . '/modules/money/delete.php?id=' . (int) $transactionId);
            exit;
        }

        $stmt = $connection->prepare('DELETE FROM money_transactions WHERE transaction_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $transactionId, $userId);
        $stmt->execute();

        setFlash('success', 'Transaction record deleted successfully.');
        header('Location: ' . BASE_URL . '/modules/money/index.php');
        exit;
    }
} catch (Throwable $exception) {
    logApplicationException($exception, 'money delete');
    $pageError = 'Transaction deletion is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Delete Transaction';
require __DIR__ . '/../../includes/header.php';
renderMoneyStyles();
?>

<section class="panel narrow">
    <h1>Delete Transaction</h1>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php elseif ($transaction): ?>
        <p class="muted">Confirm that you want to remove this transaction record. This action cannot be undone.</p>

        <dl class="detail-list">
            <div>
                <dt>Type</dt>
                <dd><?= ucfirst(escapeOutput($transaction['transaction_type'])); ?></dd>
            </div>
            <div>
                <dt>Amount</dt>
                <dd>RM <?= number_format((float) $transaction['amount'], 2); ?></dd>
            </div>
            <div>
                <dt>Category</dt>
                <dd><?= escapeOutput($transaction['category']); ?></dd>
            </div>
            <div>
                <dt>Date</dt>
                <dd><?= escapeOutput($transaction['transaction_date']); ?></dd>
            </div>
            <?php if (!empty($transaction['description'])): ?>
                <div>
                    <dt>Description</dt>
                    <dd><?= escapeOutput($transaction['description']); ?></dd>
                </div>
            <?php endif; ?>
        </dl>

        <form method="post" action="<?= BASE_URL; ?>/modules/money/delete.php?id=<?= (int) $transactionId; ?>">
            <?= csrfInput(); ?>
            <div class="button-row">
                <button class="button primary danger-primary" type="submit">Delete Transaction</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/money/index.php">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
