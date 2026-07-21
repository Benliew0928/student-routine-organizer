<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/money_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$filters = moneyFiltersFromRequest($_GET);
$currentQuery = moneyReturnQuery($filters);
$records = [];
$summary = [
    'total_count' => 0,
    'total_income' => 0.00,
    'total_expense' => 0.00,
    'balance' => 0.00,
    'income_count' => 0,
    'expense_count' => 0,
    'total_flow' => 0.00,
    'income_pct' => 0,
    'expense_pct' => 0,
];
$pageError = null;

try {
    $connection = getDatabaseConnection();

    $summary = moneyGetSummary($connection, $userId);

    $filterQuery = moneyFilterQuery($filters, $userId);
    $sql = 'SELECT transaction_id, user_id, amount, category, description, transaction_type, transaction_date, created_at, updated_at FROM money_transactions WHERE ' . $filterQuery['where'] . ' ORDER BY ' . moneyOrderBy($filters['sort']);
    $stmt = $connection->prepare($sql);
    $params = $filterQuery['params'];
    moneyBindParams($stmt, $filterQuery['types'], $params);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="money-tracker-export.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Transaction Date', 'Type', 'Category', 'Description', 'Amount (RM)']);

        foreach ($records as $record) {
            fputcsv($output, [
                $record['transaction_date'],
                ucfirst($record['transaction_type']),
                $record['category'],
                $record['description'] ?? '',
                number_format((float) $record['amount'], 2, '.', ''),
            ]);
        }

        fclose($output);
        exit;
    }
} catch (Throwable $exception) {
    $pageError = 'Money transactions are unavailable right now. Please check the database setup.';
}

$activeFilterLabels = [];
if ($filters['search'] !== '') {
    $activeFilterLabels[] = 'Search: ' . $filters['search'];
}
if ($filters['transaction_type'] !== '') {
    $activeFilterLabels[] = 'Type: ' . (moneyTransactionTypeOptions()[$filters['transaction_type']] ?? $filters['transaction_type']);
}
if ($filters['category'] !== '') {
    $activeFilterLabels[] = 'Category: ' . $filters['category'];
}
if ($filters['date_from'] !== '') {
    $activeFilterLabels[] = 'From: ' . $filters['date_from'];
}
if ($filters['date_to'] !== '') {
    $activeFilterLabels[] = 'To: ' . $filters['date_to'];
}
if ($filters['sort'] !== 'newest') {
    $activeFilterLabels[] = 'Sort: ' . (moneySortOptions()[$filters['sort']] ?? $filters['sort']);
}

$circumference = 351.86; // 2 * PI * 56
$incomeDashOffset = round($circumference * (1 - ($summary['income_pct'] / 100)), 2);
$expenseDashOffset = round($circumference * (1 - ($summary['expense_pct'] / 100)), 2);

$pageTitle = 'Money Tracker';
require __DIR__ . '/../../includes/header.php';
renderMoneyStyles();
?>

<section class="money-theme-hero">
    <div class="exercise-hero-copy">
        <p class="eyebrow">Financial Tracker</p>
        <h1>Money Tracker</h1>
        <p class="hero-copy">Manage your income, track daily expenses, and maintain a clear budget balance.</p>
    </div>
</section>

<!-- Top Summary Section -->
<section class="money-summary-grid" aria-label="Money summary metrics">
    <article class="money-dash-card money-dash-card-income">
        <span class="summary-label">Total Income</span>
        <strong>RM <?= number_format($summary['total_income'], 2); ?></strong>
        <p><?= $summary['income_count']; ?> income record<?= $summary['income_count'] === 1 ? '' : 's'; ?></p>
    </article>
    <article class="money-dash-card money-dash-card-expense">
        <span class="summary-label">Total Expense</span>
        <strong>RM <?= number_format($summary['total_expense'], 2); ?></strong>
        <p><?= $summary['expense_count']; ?> expense record<?= $summary['expense_count'] === 1 ? '' : 's'; ?></p>
    </article>
    <article class="money-dash-card money-dash-card-balance">
        <span class="summary-label">Current Balance</span>
        <strong>RM <?= number_format($summary['balance'], 2); ?></strong>
        <p><?= $summary['total_count']; ?> total transaction<?= $summary['total_count'] === 1 ? '' : 's'; ?></p>
    </article>
</section>

<!-- Two Large Circular Progress Charts Section -->
<section class="money-twin-rings-grid" aria-label="Flow percentage ring charts">
    <article class="money-ring-card">
        <h3>Income Ratio</h3>
        <div class="money-ring-svg-wrapper">
            <svg viewBox="0 0 140 140">
                <circle cx="70" cy="70" r="56" fill="transparent" stroke="#e0f2e9" stroke-width="12" />
                <circle cx="70" cy="70" r="56" fill="transparent" stroke="url(#greenGrad)" stroke-width="12" 
                        stroke-dasharray="351.86" stroke-dashoffset="<?= $incomeDashOffset; ?>" stroke-linecap="round" />
                <defs>
                    <linearGradient id="greenGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#2ec4b6" />
                        <stop offset="100%" stop-color="#1f7a4d" />
                    </linearGradient>
                </defs>
            </svg>
            <div class="money-ring-center">
                <span class="pct pct-income"><?= $summary['income_pct']; ?>%</span>
                <span class="label">of Total</span>
            </div>
        </div>
        <div class="money-ring-amount">RM <?= number_format($summary['total_income'], 2); ?></div>
        <div class="money-ring-subtext">Total Cash Inflow</div>
    </article>

    <article class="money-ring-card">
        <h3>Expense Ratio</h3>
        <div class="money-ring-svg-wrapper">
            <svg viewBox="0 0 140 140">
                <circle cx="70" cy="70" r="56" fill="transparent" stroke="#ffe5cc" stroke-width="12" />
                <circle cx="70" cy="70" r="56" fill="transparent" stroke="url(#orangeGrad)" stroke-width="12" 
                        stroke-dasharray="351.86" stroke-dashoffset="<?= $expenseDashOffset; ?>" stroke-linecap="round" />
                <defs>
                    <linearGradient id="orangeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#d9822b" />
                        <stop offset="100%" stop-color="#e65100" />
                    </linearGradient>
                </defs>
            </svg>
            <div class="money-ring-center">
                <span class="pct pct-expense"><?= $summary['expense_pct']; ?>%</span>
                <span class="label">of Total</span>
            </div>
        </div>
        <div class="money-ring-amount">RM <?= number_format($summary['total_expense'], 2); ?></div>
        <div class="money-ring-subtext">Total Cash Outflow</div>
    </article>
</section>

<section class="exercise-action-bar" aria-label="Money actions">
    <div>
        <p class="summary-label">Transactions Summary</p>
        <strong><?= number_format(count($records)); ?> record<?= count($records) === 1 ? '' : 's'; ?> listed</strong>
    </div>
    <div class="button-row compact-actions">
        <button class="button icon-button filter-button" type="button" data-filter-open aria-controls="moneyFilters" aria-expanded="false">
            <img src="<?= BASE_URL; ?>/assets/img/filter-icon.png" alt="" aria-hidden="true">
            Filter
            <?php if ($activeFilterLabels): ?>
                <span class="filter-count"><?= count($activeFilterLabels); ?></span>
            <?php endif; ?>
        </button>
        <a class="button" href="<?= BASE_URL; ?>/modules/money/index.php<?= $currentQuery !== '' ? '?' . escapeOutput($currentQuery . '&export=csv') : '?export=csv'; ?>">Export CSV</a>
        <a class="button primary" href="<?= BASE_URL; ?>/modules/money/create.php">Add Transaction</a>
    </div>
</section>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php else: ?>
    <div class="filter-backdrop" data-filter-backdrop hidden></div>
    <aside class="filter-drawer" id="moneyFilters" data-filter-drawer aria-hidden="true" aria-label="Money filters">
        <div class="filter-drawer-header">
            <div>
                <p class="summary-label">Filters</p>
                <h2>Filter Transactions</h2>
            </div>
            <button class="button small-button" type="button" data-filter-close>Close</button>
        </div>
        <form method="get" action="<?= BASE_URL; ?>/modules/money/index.php" class="filter-form">
            <label for="search">Search</label>
            <input id="search" name="search" type="search" value="<?= escapeOutput($filters['search']); ?>" placeholder="Category, description, or amount">

            <label for="transaction_type">Transaction Type</label>
            <select id="transaction_type" name="transaction_type">
                <option value="">All Types</option>
                <?php foreach (moneyTransactionTypeOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['transaction_type'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All Categories</option>
                <?php foreach (moneyCategoryOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['category'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="date_from">From Date</label>
            <input id="date_from" name="date_from" type="date" value="<?= escapeOutput($filters['date_from']); ?>">

            <label for="date_to">To Date</label>
            <input id="date_to" name="date_to" type="date" value="<?= escapeOutput($filters['date_to']); ?>">

            <label for="sort">Sort By</label>
            <select id="sort" name="sort">
                <?php foreach (moneySortOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['sort'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="filter-actions">
                <button class="button primary" type="submit">Apply Filters</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/money/index.php">Reset</a>
            </div>
        </form>
    </aside>

    <section class="exercise-board-header">
        <div>
            <p class="summary-label">Transaction Log</p>
            <h2><?= number_format(count($records)); ?> record<?= count($records) === 1 ? '' : 's'; ?> showing</h2>
        </div>
        <?php if ($activeFilterLabels): ?>
            <div class="active-filter-list" aria-label="Active filters">
                <?php foreach ($activeFilterLabels as $label): ?>
                    <span><?= escapeOutput($label); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$records): ?>
        <section class="panel empty-state">
            <h2>No transactions found</h2>
            <p class="muted">Add an income or expense record or adjust your filter criteria.</p>
            <div class="button-row">
                <button class="button" type="button" data-filter-open>Open Filters</button>
                <a class="button primary" href="<?= BASE_URL; ?>/modules/money/create.php">Add Transaction</a>
            </div>
        </section>
    <?php else: ?>
        <section class="panel table-panel" aria-label="Transaction table">
            <table class="data-table money-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= escapeOutput($record['transaction_date']); ?></td>
                            <td>
                                <span class="status-pill <?= $record['transaction_type'] === 'income' ? 'pill-income' : 'pill-expense'; ?>">
                                    <?= ucfirst(escapeOutput($record['transaction_type'])); ?>
                                </span>
                            </td>
                            <td><strong><?= escapeOutput($record['category']); ?></strong></td>
                            <td><?= $record['description'] !== '' && $record['description'] !== null ? escapeOutput($record['description']) : '<span class="muted">No description</span>'; ?></td>
                            <td>
                                <strong>
                                    <?= $record['transaction_type'] === 'income' ? '+' : '-'; ?>RM <?= number_format((float) $record['amount'], 2); ?>
                                </strong>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="button small-button" href="<?= BASE_URL; ?>/modules/money/edit.php?id=<?= (int) $record['transaction_id']; ?>">Edit</a>
                                    <a class="button small-button danger-button" href="<?= BASE_URL; ?>/modules/money/delete.php?id=<?= (int) $record['transaction_id']; ?>">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
