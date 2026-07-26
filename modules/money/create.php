<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/money_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$data = moneyDefaultFormData();
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = moneyDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, moneyValidateData($data));

        if (!$errors) {
            $stmt = $connection->prepare('INSERT INTO money_transactions (user_id, amount, category, description, transaction_type, transaction_date) VALUES (?, ?, ?, ?, ?, ?)');
            $formattedAmount = (float) $data['amount'];
            $stmt->bind_param(
                'idssss',
                $userId,
                $formattedAmount,
                $data['category'],
                $data['description'],
                $data['transaction_type'],
                $data['transaction_date']
            );
            $stmt->execute();

            setFlash('success', 'Transaction record added successfully.');
            header('Location: ' . BASE_URL . '/modules/money/index.php');
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Transaction creation is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Add Transaction';
require __DIR__ . '/../../includes/header.php';
renderMoneyStyles();
?>

<section class="panel narrow">
    <h1>Add Transaction</h1>
    <p class="muted">Record an income or expense transaction with amount, category, and date.</p>

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

    <form method="post" action="<?= BASE_URL; ?>/modules/money/create.php">
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
            <button class="button primary" type="submit">Add Transaction</button>
            <a class="button" href="<?= BASE_URL; ?>/modules/money/index.php">Cancel</a>
        </div>
    </form>
</section>

<?php renderMoneyCategorySelectorScript(); ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
