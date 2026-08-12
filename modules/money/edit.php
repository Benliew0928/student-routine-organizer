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
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $transaction = moneyLoadForUser($connection, (int) $transactionId, $userId);

    if (!$transaction) {
        setFlash('error', 'Transaction record was not found.');
        header('Location: ' . BASE_URL . '/modules/money/index.php');
        exit;
    }

    $data = [
        'amount' => number_format((float) $transaction['amount'], 2, '.', ''),
        'category' => $transaction['category'],
        'description' => $transaction['description'] ?? '',
        'transaction_type' => $transaction['transaction_type'],
        'transaction_date' => $transaction['transaction_date'],
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = moneyDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, moneyValidateData($data));

        if (!$errors) {
            $stmt = $connection->prepare('UPDATE money_transactions SET amount = ?, category = ?, description = ?, transaction_type = ?, transaction_date = ? WHERE transaction_id = ? AND user_id = ?');
            $formattedAmount = (float) $data['amount'];
            $stmt->bind_param(
                'dssssii',
                $formattedAmount,
                $data['category'],
                $data['description'],
                $data['transaction_type'],
                $data['transaction_date'],
                $transactionId,
                $userId
            );
            $stmt->execute();

            setFlash('success', 'Transaction record updated successfully.');
            header('Location: ' . BASE_URL . '/modules/money/index.php');
            exit;
        }
    }
} catch (Throwable $exception) {
    logApplicationException($exception, 'money edit');
    $pageError = 'Transaction edit is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Edit Transaction';
require __DIR__ . '/../../includes/header.php';
renderMoneyStyles();
?>

<section class="panel narrow">
    <h1>Edit Transaction</h1>
    <p class="muted">Update transaction details for your financial record.</p>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= escapeOutput($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($transaction): ?>
        <form method="post" action="<?= BASE_URL; ?>/modules/money/edit.php?id=<?= (int) $transactionId; ?>">
            <?= csrfInput(); ?>

            <label for="transaction_type">Transaction Type</label>
            <select id="transaction_type" name="transaction_type" required>
                <?php foreach (moneyTransactionTypeOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $data['transaction_type'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="amount">Amount (RM)</label>
            <input id="amount" name="amount" type="number" step="0.01" min="0.01" max="99999999.99" value="<?= escapeOutput($data['amount']); ?>" placeholder="0.00" required>

            <label for="category">Category</label>
            <select id="category" name="category" required>
                <?php foreach (moneyCategoryOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" data-types="<?= escapeOutput(implode(' ', moneyCategoryTypes($value))); ?>" <?= $data['category'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="transaction_date">Transaction Date</label>
            <input id="transaction_date" name="transaction_date" type="date" value="<?= escapeOutput($data['transaction_date']); ?>" required>

            <label for="description">Description (Optional)</label>
            <input id="description" name="description" type="text" maxlength="255" value="<?= escapeOutput($data['description']); ?>" placeholder="Short notes or details">

            <div class="button-row">
                <button class="button primary" type="submit">Save Changes</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/money/index.php">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php renderMoneyCategorySelectorScript(); ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
