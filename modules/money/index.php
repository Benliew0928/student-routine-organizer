<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/money_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$filters = moneyFiltersFromRequest($_GET);
$analysisFilters = moneyFiltersFromRequest(array_merge($_GET, [
    'period_year' => $_GET['analysis_year'] ?? '',
    'period_month' => $_GET['analysis_month'] ?? '',
]));
$currentQuery = moneyReturnQuery($filters);
$records = [];
$incomeBreakdown = [];
$expenseBreakdown = [];
$expenseTrend = [];
$monthlyExpenseTotal = 0.00;
$previousMonthlyExpenseTotal = 0.00;
$analysisIncomeBreakdown = [];
$analysisExpenseBreakdown = [];
$availableYears = [(int) date('Y')];
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
$analysisSummary = $summary;
$pageError = null;

try {
    $connection = getDatabaseConnection();

    $summary = moneyGetSummary($connection, $userId, $filters);
    $incomeBreakdown = moneyGetCategoryBreakdown($connection, $userId, 'income', $filters);
    $expenseBreakdown = moneyGetCategoryBreakdown($connection, $userId, 'expense', $filters);
    $expenseTrend = moneyGetExpenseTrend($connection, $userId, $filters);
    $currentMonth = new DateTimeImmutable('first day of this month');
    $monthlyExpenseTotal = moneyGetExpenseTotalForMonth($connection, $userId, $currentMonth);
    $previousMonthlyExpenseTotal = moneyGetExpenseTotalForMonth($connection, $userId, $currentMonth->modify('-1 month'));

    $analysisSummary = moneyGetSummary($connection, $userId, $analysisFilters);
    $analysisIncomeBreakdown = moneyGetCategoryBreakdown($connection, $userId, 'income', $analysisFilters);
    $analysisExpenseBreakdown = moneyGetCategoryBreakdown($connection, $userId, 'expense', $analysisFilters);

    $yearStmt = $connection->prepare('SELECT DISTINCT YEAR(transaction_date) AS transaction_year FROM money_transactions WHERE user_id = ? ORDER BY transaction_year DESC');
    $yearStmt->bind_param('i', $userId);
    $yearStmt->execute();
    $availableYears = array_map(static fn (array $row): int => (int) $row['transaction_year'], $yearStmt->get_result()->fetch_all(MYSQLI_ASSOC));
    if (!$availableYears) {
        $availableYears = [(int) date('Y')];
    }

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

$monthChange = $previousMonthlyExpenseTotal > 0
    ? (($monthlyExpenseTotal - $previousMonthlyExpenseTotal) / $previousMonthlyExpenseTotal) * 100
    : null;

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

$showAllTransactions = ($_GET['show'] ?? '') === 'all';
$visibleRecords = $showAllTransactions ? $records : array_slice($records, 0, 8);

$pageTitle = 'Money Tracker';
require __DIR__ . '/../../includes/header.php';
renderMoneyStyles();
?>

<section class="money-theme-hero">
    <div class="money-hero-copy">
        <p class="eyebrow">Financial Tracker</p>
        <h1>Money Tracker</h1>
        <p class="hero-copy">Manage your income, track daily expenses, and maintain a clear budget balance.</p>
        <div class="money-hero-metrics" aria-label="Money overview">
            <span><strong>RM <?= number_format($summary['total_income'], 2); ?></strong> income</span>
            <span><strong>RM <?= number_format($summary['total_expense'], 2); ?></strong> expense</span>
        </div>
    </div>
    <div class="money-overview-card" aria-label="Money overview">
        <span class="summary-label">Available Balance</span>
        <strong>RM <?= number_format($summary['balance'], 2); ?></strong>
        <small><?= number_format($summary['total_count']); ?> total transaction<?= $summary['total_count'] === 1 ? '' : 's'; ?></small>
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

<?php if (false): // Category analysis is available on analysis.php. ?>
$chartCircumference = 439.82; // 2 * PI * 70
$chartOffset = 0.0;
$chartColors = ['#4baed8', '#cf76bc', '#f2a85a', '#52bf95', '#9b8ce1', '#e88282', '#8ecbd4'];
$categoryIcons = ['Food' => '🍜', 'Transport' => '🚌', 'Shopping' => '🛍', 'Bills' => '🧾', 'Education' => '📚', 'Entertainment' => '🎬', 'Healthcare' => '✚', 'Salary' => '💼', 'Allowance' => '◎', 'Freelance' => '⌘', 'Others' => '•'];
?>
<section class="money-analysis" aria-label="Income and expense category analysis">
    <div class="money-analysis-heading"><small>Category insights</small><h2>Money Analysis</h2></div>
    <div class="money-analysis-grid">
        <?php foreach (['income' => ['label' => 'Income', 'items' => $incomeBreakdown, 'total' => $summary['total_income']], 'expense' => ['label' => 'Expense', 'items' => $expenseBreakdown, 'total' => $summary['total_expense']]] as $type => $analysis): ?>
            <?php $chartOffset = 0.0; ?>
            <article class="money-analysis-card money-analysis-<?= $type; ?>">
                <h3><?= $analysis['label']; ?> by Category</h3>
                <?php if ($analysis['items']): ?>
                    <div class="money-donut-wrap">
                        <svg class="money-category-donut" viewBox="0 0 200 200" role="img" aria-label="<?= $analysis['label']; ?> breakdown by category">
                            <?php foreach ($analysis['items'] as $index => $item): ?>
                                <?php
                                $percentage = $analysis['total'] > 0 ? ((float) $item['total'] / $analysis['total']) * 100 : 0;
                                $segmentLength = max(0, ($chartCircumference * ($percentage / 100)) - 3);
                                $color = $chartColors[$index % count($chartColors)];
                                ?>
                                <circle class="money-donut-segment" data-analysis="<?= $type; ?>" data-category="<?= escapeOutput($item['category']); ?>" cx="100" cy="100" r="70" fill="none" stroke="<?= $color; ?>" stroke-width="30" stroke-linecap="butt" stroke-dasharray="<?= $segmentLength; ?> <?= $chartCircumference; ?>" stroke-dashoffset="<?= -$chartOffset; ?>" transform="rotate(-90 100 100)" />
                                <?php $chartOffset += $chartCircumference * ($percentage / 100); ?>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                    <p class="money-donut-summary"><span>Total <?= strtolower($analysis['label']); ?></span><strong>RM <?= number_format($analysis['total'], 2); ?></strong></p>
                    <details class="money-category-details">
                        <summary>Show category details</summary>
                        <div class="money-category-list" aria-label="<?= $analysis['label']; ?> category totals">
                            <?php foreach ($analysis['items'] as $index => $item): ?>
                                <?php $percentage = $analysis['total'] > 0 ? ((float) $item['total'] / $analysis['total']) * 100 : 0; ?>
                                <div class="money-category-row" data-analysis="<?= $type; ?>" data-category="<?= escapeOutput($item['category']); ?>" style="--category-color: <?= $chartColors[$index % count($chartColors)]; ?>;">
                                    <span class="money-category-icon"><?= $categoryIcons[$item['category']] ?? '•'; ?></span>
                                    <div class="money-category-progress"><div><strong><?= escapeOutput($item['category']); ?></strong><span>RM <?= number_format((float) $item['total'], 2); ?></span></div><i><b style="width: <?= round($percentage, 1); ?>%"></b></i></div>
                                    <em><?= round($percentage); ?>%</em>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php else: ?>
                    <p class="money-analysis-empty">No <?= strtolower($analysis['label']); ?> records yet.</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
$goalTarget = 5000.00;
$goalSaved = max(0.00, (float) $summary['balance']);
$goalProgress = min(100, (int) round(($goalSaved / $goalTarget) * 100));
$donutCircumference = 351.86;
$analysisChartCircumference = 439.82;
$miniCharts = [
    'income' => ['label' => 'Income', 'items' => $incomeBreakdown, 'total' => (float) $summary['total_income'], 'colors' => ['#0f766e', '#38a68f', '#8ad5c5', '#c1ede5']],
    'expense' => ['label' => 'Expense', 'items' => $expenseBreakdown, 'total' => (float) $summary['total_expense'], 'colors' => ['#e9540c', '#ff7a18', '#f6ae4d', '#ffd49d']],
];
$analysisCharts = [
    'income' => ['label' => 'Income', 'items' => $analysisIncomeBreakdown, 'total' => (float) $analysisSummary['total_income'], 'colors' => ['#0f766e', '#38a68f', '#8ad5c5', '#c1ede5']],
    'expense' => ['label' => 'Expense', 'items' => $analysisExpenseBreakdown, 'total' => (float) $analysisSummary['total_expense'], 'colors' => ['#e9540c', '#ff7a18', '#f6ae4d', '#ffd49d']],
];
$moneyCategoryIcons = [
    'Food' => 'bi-fork-knife', 'Transport' => 'bi-bus-front', 'Shopping' => 'bi-bag',
    'Bills' => 'bi-receipt', 'Education' => 'bi-mortarboard', 'Entertainment' => 'bi-film',
    'Healthcare' => 'bi-heart-pulse', 'Salary' => 'bi-briefcase', 'Allowance' => 'bi-wallet2',
    'Freelance' => 'bi-laptop', 'Others' => 'bi-grid-3x3-gap',
];
$moneyCategoryInitials = ['Food' => 'F', 'Transport' => 'T', 'Shopping' => 'S', 'Bills' => 'B', 'Education' => 'E', 'Entertainment' => 'E', 'Healthcare' => 'H', 'Salary' => 'S', 'Allowance' => 'A', 'Freelance' => 'F', 'Others' => 'O'];
$incomeTotal = (float) $analysisSummary['total_income'];
$expenseTotal = (float) $analysisSummary['total_expense'];
$incomeRetained = $incomeTotal > 0 ? (($incomeTotal - $expenseTotal) / $incomeTotal) * 100 : 0;
$expenseToIncome = $incomeTotal > 0 ? ($expenseTotal / $incomeTotal) * 100 : 0;
$topIncomeSource = $analysisIncomeBreakdown[0] ?? null;
$topIncomeShare = $topIncomeSource && $incomeTotal > 0 ? ((float) $topIncomeSource['total'] / $incomeTotal) * 100 : 0;
?>
<template id="money-analysis-template">
    <section class="money-cash-health" aria-label="Cash flow health">
        <div class="money-cash-health-heading"><small>Cash flow health</small><p>A quick view of how well this period's income supports your spending.</p></div>
        <div class="money-cash-health-grid">
            <article class="money-health-card money-health-income"><i class="bi bi-wallet2" aria-hidden="true"></i><div><span>Income retained</span><strong><?= number_format($incomeRetained, 0); ?>%</strong></div></article>
            <article class="money-health-card money-health-expense"><i class="bi bi-pie-chart" aria-hidden="true"></i><div><span>Expense to income</span><strong><?= number_format($expenseToIncome, 1); ?>%</strong></div></article>
            <article class="money-health-card money-health-income"><i class="bi bi-people" aria-hidden="true"></i><div><span>Active income sources</span><strong><?= number_format(count($analysisIncomeBreakdown)); ?></strong></div></article>
            <article class="money-health-card money-health-highlight"><i class="bi bi-star" aria-hidden="true"></i><div><span>Top income source</span><strong><?= $topIncomeSource ? escapeOutput($topIncomeSource['category']) . ' · ' . number_format($topIncomeShare, 0) . '%' : 'No income yet'; ?></strong></div></article>
        </div>
    </section>
    <section class="money-analysis" aria-label="Income and expense category analysis">
        <div class="money-analysis-heading">
            <div class="money-analysis-title"><small>Category insights</small><h2>Income &amp; Expense Breakdown</h2><p>Hover over a chart segment or category to focus on its contribution.</p></div>
            <form class="money-period-picker" method="get" action="<?= BASE_URL; ?>/modules/money/index.php" aria-label="Choose analysis period">
                <?php foreach ($filters as $filterName => $filterValue): ?>
                    <?php if ($filterValue !== '' && $filterValue !== 'newest'): ?><input type="hidden" name="<?= escapeOutput($filterName); ?>" value="<?= escapeOutput($filterValue); ?>"><?php endif; ?>
                <?php endforeach; ?>
                <input type="hidden" name="analysis_open" value="1">
                <span class="money-period-icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                <select name="analysis_month" aria-label="Month"><option value="">Month</option><?php foreach (range(1, 12) as $month): ?><option value="<?= $month; ?>" <?= $analysisFilters['period_month'] === (string) $month ? 'selected' : ''; ?>><?= date('M', mktime(0, 0, 0, $month, 1)); ?></option><?php endforeach; ?></select>
                <select name="analysis_year" aria-label="Year"><option value="">Year</option><?php foreach ($availableYears as $year): ?><option value="<?= $year; ?>" <?= $analysisFilters['period_year'] === (string) $year ? 'selected' : ''; ?>><?= $year; ?></option><?php endforeach; ?></select>
            </form>
        </div>
        <div class="money-analysis-grid">
            <?php foreach ($analysisCharts as $type => $chart): ?>
                <?php $chartOffset = 0.0; ?>
                <article class="money-analysis-card money-analysis-<?= $type; ?>">
                    <h3><?= $chart['label']; ?></h3>
                    <?php if ($chart['items']): ?>
                        <div class="money-analysis-ring">
                            <svg class="money-analysis-chart" viewBox="0 0 200 200" role="img" aria-label="<?= $chart['label']; ?> breakdown by category">
                                <?php foreach ($chart['items'] as $index => $item): ?>
                                    <?php $percentage = $chart['total'] > 0 ? ((float) $item['total'] / $chart['total']) * 100 : 0; $segmentLength = max(0, ($analysisChartCircumference * ($percentage / 100)) - 3); $color = $chart['colors'][$index % count($chart['colors'])]; ?>
                                    <circle class="money-donut-segment" data-analysis="<?= $type; ?>" data-category="<?= escapeOutput($item['category']); ?>" data-percentage="<?= round($percentage); ?>" data-amount="RM <?= number_format((float) $item['total'], 2); ?>" cx="100" cy="100" r="70" fill="none" stroke="<?= $color; ?>" stroke-width="30" stroke-dasharray="<?= $segmentLength; ?> <?= $analysisChartCircumference; ?>" stroke-dashoffset="<?= -$chartOffset; ?>" transform="rotate(-90 100 100)" />
                                    <?php $chartOffset += $analysisChartCircumference * ($percentage / 100); ?>
                                <?php endforeach; ?>
                                <text class="money-analysis-donut-label" x="100" y="93" text-anchor="middle">Total <?= $chart['label']; ?></text>
                                <text class="money-analysis-donut-value" x="100" y="116" text-anchor="middle" textLength="100" lengthAdjust="spacingAndGlyphs">RM <?= number_format($chart['total'], 2); ?></text>
                            </svg>
                        </div>
                        <div class="money-category-details"><div class="money-category-list">
                            <?php foreach ($chart['items'] as $index => $item): ?>
                                <?php $percentage = $chart['total'] > 0 ? ((float) $item['total'] / $chart['total']) * 100 : 0; ?>
                                <div class="money-category-row" data-analysis="<?= $type; ?>" data-category="<?= escapeOutput($item['category']); ?>" data-percentage="<?= round($percentage); ?>" data-amount="RM <?= number_format((float) $item['total'], 2); ?>" style="--category-color: <?= $chart['colors'][$index % count($chart['colors'])]; ?>;"><span class="money-category-icon"><?= escapeOutput($moneyCategoryInitials[$item['category']] ?? 'O'); ?></span><div class="money-category-progress"><div><strong><?= escapeOutput($item['category']); ?></strong><span>RM <?= number_format((float) $item['total'], 2); ?></span></div><i><b style="width: <?= round($percentage, 1); ?>%"></b></i></div><em><?= round($percentage); ?>%</em></div>
                            <?php endforeach; ?>
                        </div></div>
                    <?php else: ?>
                        <p class="money-analysis-empty">No <?= strtolower($chart['label']); ?> records yet.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</template>
<div class="money-analysis-stage" data-money-analysis-stage></div>
<template id="money-spending-template">
    <?php
    $spendingRecentExpenses = array_slice(array_values(array_filter($records, static fn (array $record): bool => $record['transaction_type'] === 'expense')), 0, 3);
    $spendingCategories = array_slice($expenseBreakdown, 0, 4);
    $spendingTrendTotals = array_map(static fn (array $item): float => (float) $item['total'], $expenseTrend);
    $spendingTrendMax = max(1, ...($spendingTrendTotals ?: [1]));
    $spendingTrendPoints = [];
    foreach ($spendingTrendTotals as $index => $total) {
        $x = count($spendingTrendTotals) > 1 ? 12 + ($index / (count($spendingTrendTotals) - 1)) * 276 : 150;
        $y = 106 - (($total / $spendingTrendMax) * 78);
        $spendingTrendPoints[] = round($x, 1) . ',' . round($y, 1);
    }
    ?>
    <section class="money-spending-detail" aria-label="Spending insights">
        <header class="money-spending-detail-heading"><div><p class="summary-label">Spending insights</p><h2>Your monthly spending</h2></div><span class="money-spending-period"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('F Y'); ?></span></header>
        <div class="money-spending-stat-grid">
            <article><i class="bi bi-wallet2" aria-hidden="true"></i><span>Total spent</span><strong>RM <?= number_format($monthlyExpenseTotal, 2); ?></strong></article>
            <article><i class="bi bi-arrow-down" aria-hidden="true"></i><span>vs last month</span><strong><?= $monthChange !== null ? number_format(abs($monthChange), 0) . '%' : '—'; ?></strong></article>
            <article><i class="bi bi-calendar-event" aria-hidden="true"></i><span>Highest spend day</span><strong><?= $expenseTrend ? date('j M', strtotime($expenseTrend[array_keys($spendingTrendTotals, max($spendingTrendTotals), true)[0]]['transaction_date'])) . ' · RM ' . number_format(max($spendingTrendTotals), 2) : 'No expenses yet'; ?></strong></article>
        </div>
        <section class="money-spending-chart-panel"><h3>Daily spending</h3><?php if ($spendingTrendPoints): ?><svg viewBox="0 0 300 130" role="img" aria-label="Detailed daily spending trend"><defs><linearGradient id="moneySpendingDetailFill" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#ff7a18" stop-opacity=".3"/><stop offset="1" stop-color="#ff7a18" stop-opacity="0"/></linearGradient></defs><path d="M <?= implode(' L ', $spendingTrendPoints); ?> L 288,112 L 12,112 Z" fill="url(#moneySpendingDetailFill)"/><polyline points="<?= implode(' ', $spendingTrendPoints); ?>" fill="none" stroke="#ef5a16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"/></svg><?php else: ?><p class="money-insight-empty">Add an expense to see your spending details.</p><?php endif; ?></section>
        <div class="money-spending-detail-grid">
            <section class="money-spending-list-panel"><h3>Spending by category</h3><?php foreach ($spendingCategories as $category): ?><?php $share = $monthlyExpenseTotal > 0 ? min(100, ((float) $category['total'] / $monthlyExpenseTotal) * 100) : 0; ?><div class="money-spending-category-row"><strong><?= escapeOutput($category['category']); ?></strong><i><b style="width: <?= round($share); ?>%"></b></i><span>RM <?= number_format((float) $category['total'], 2); ?></span></div><?php endforeach; ?></section>
            <section class="money-spending-list-panel"><h3>Recent expenses</h3><?php foreach ($spendingRecentExpenses as $expense): ?><div class="money-spending-recent-row"><span><strong><?= escapeOutput($expense['category']); ?><?= $expense['description'] ? ' · ' . escapeOutput($expense['description']) : ''; ?></strong><small><?= escapeOutput($expense['transaction_date']); ?></small></span><em>RM <?= number_format((float) $expense['amount'], 2); ?></em></div><?php endforeach; ?><a class="money-view-all" href="#transactions">See all transactions <i class="bi bi-arrow-right" aria-hidden="true"></i></a></section>
        </div>
    </section>
</template>
<div class="money-spending-stage" data-money-spending-stage></div>
<section class="money-workspace" aria-label="Money tracker workspace">
<div class="money-transaction-column">
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

    <section class="exercise-board-header" id="transactions">
        <div>
            <h2>Recent transactions</h2>
        </div>
        <div class="money-table-toolbar">
            <button class="button icon-button filter-button" type="button" data-filter-open aria-controls="moneyFilters" aria-expanded="false">
                <img src="<?= BASE_URL; ?>/assets/img/filter-icon.png" alt="" aria-hidden="true"> Filter<?php if ($activeFilterLabels): ?> <span class="filter-count"><?= count($activeFilterLabels); ?></span><?php endif; ?>
            </button>
            <a class="button" data-money-preserve-scroll href="<?= BASE_URL; ?>/modules/money/index.php<?= $currentQuery !== '' ? '?' . escapeOutput($currentQuery . '&export=csv') : '?export=csv'; ?>">Export CSV</a>
            <a class="button primary" data-money-preserve-scroll href="<?= BASE_URL; ?>/modules/money/create.php">Add Transaction <i class="bi bi-plus-lg" aria-hidden="true"></i></a>
        </div>
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
                        <th>Description</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th class="money-actions-heading"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visibleRecords as $record): ?>
                        <tr>
                            <td><?= escapeOutput($record['transaction_date']); ?></td>
                            <td>
                                <span><?= $record['description'] !== '' && $record['description'] !== null ? escapeOutput($record['description']) : '<span class="muted">No description</span>'; ?></span>
                            </td>
                            <td><span class="money-transaction-category-icon"><i class="bi <?= $moneyCategoryIcons[$record['category']] ?? 'bi-receipt' ?>" aria-hidden="true"></i></span><strong><?= escapeOutput($record['category']); ?></strong></td>
                            <td>
                                <span class="status-pill <?= $record['transaction_type'] === 'income' ? 'pill-income' : 'pill-expense'; ?>">
                                    <?= ucfirst(escapeOutput($record['transaction_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <strong class="money-amount <?= $record['transaction_type'] === 'income' ? 'is-income' : 'is-expense'; ?>">
                                    <?= $record['transaction_type'] === 'income' ? '+' : '-'; ?>RM <?= number_format((float) $record['amount'], 2); ?>
                                </strong>
                            </td>
                            <td>
                                <div class="table-actions money-table-actions">
                                    <a class="money-action-icon money-action-edit" data-money-return-scroll href="<?= BASE_URL; ?>/modules/money/edit.php?id=<?= (int) $record['transaction_id']; ?>" aria-label="Edit <?= escapeOutput($record['category']); ?> transaction" title="Edit transaction"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                    <a class="money-action-icon money-action-delete" data-money-return-scroll href="<?= BASE_URL; ?>/modules/money/delete.php?id=<?= (int) $record['transaction_id']; ?>" aria-label="Delete <?= escapeOutput($record['category']); ?> transaction" title="Delete transaction"><i class="bi bi-trash3" aria-hidden="true"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($records) > 8 && !$showAllTransactions): ?>
                <div class="money-table-footer"><a class="money-view-all" href="<?= BASE_URL; ?>/modules/money/index.php<?= $currentQuery !== '' ? '?' . escapeOutput($currentQuery . '&show=all') : '?show=all'; ?>#transactions">View more transactions <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
            <?php elseif ($showAllTransactions && count($records) > 8): ?>
                <div class="money-table-footer"><a class="money-view-all" href="<?= BASE_URL; ?>/modules/money/index.php<?= $currentQuery !== '' ? '?' . escapeOutput($currentQuery) : ''; ?>#transactions">Show recent transactions <i class="bi bi-arrow-up" aria-hidden="true"></i></a></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
</div>

<aside class="money-insights-column" aria-label="Money insights">
    <section class="money-insight-card money-trend-card money-spending-launch" data-money-spending-expand data-money-spending-url="<?= BASE_URL; ?>/modules/money/spending.php?embed=1" tabindex="0" role="button" aria-expanded="false" aria-label="Expand spending insights">
        <?php
        $trendTotals = array_map(static fn (array $item): float => (float) $item['total'], $expenseTrend);
        $trendMax = max(1, ...($trendTotals ?: [1]));
        $trendPoints = [];
        $trendCount = count($trendTotals);
        foreach ($trendTotals as $index => $total) {
            $x = $trendCount > 1 ? 8 + ($index / ($trendCount - 1)) * 284 : 150;
            $y = 104 - (($total / $trendMax) * 78);
            $trendPoints[] = round($x, 1) . ',' . round($y, 1);
        }
        $monthChange = $previousMonthlyExpenseTotal > 0 ? (($monthlyExpenseTotal - $previousMonthlyExpenseTotal) / $previousMonthlyExpenseTotal) * 100 : null;
        $topExpenseCategory = $expenseBreakdown[0] ?? null;
        ?>
        <div class="money-insight-heading"><div><p class="summary-label">This month</p><h2>Spending trend</h2></div><span class="money-insight-value">RM <?= number_format($monthlyExpenseTotal, 2); ?></span></div>
        <?php if ($monthChange !== null): ?><p class="money-trend-change <?= $monthChange <= 0 ? 'is-down' : 'is-up'; ?>"><i class="bi bi-arrow-<?= $monthChange <= 0 ? 'down' : 'up'; ?>" aria-hidden="true"></i> <?= number_format(abs($monthChange), 0); ?>% vs last month</p><?php endif; ?>
        <?php if ($trendPoints): ?>
            <svg class="money-trend-chart" viewBox="0 0 300 120" role="img" aria-label="Daily expense trend"><defs><linearGradient id="moneyTrendFill" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#ff7a18" stop-opacity=".32"/><stop offset="1" stop-color="#ff7a18" stop-opacity="0"/></linearGradient></defs><path d="M <?= implode(' L ', $trendPoints); ?> L 292,112 L 8,112 Z" fill="url(#moneyTrendFill)"/><polyline points="<?= implode(' ', $trendPoints); ?>" fill="none" stroke="#e9540c" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/></svg>
            <div class="money-trend-footer"><span><i aria-hidden="true"></i>Daily expenses</span><a class="money-view-all" href="#transactions">View details <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
            <?php if ($topExpenseCategory): ?><p class="money-trend-category"><span><i class="bi bi-fork-knife" aria-hidden="true"></i></span>Most spent: <?= escapeOutput($topExpenseCategory['category']); ?> &middot; RM <?= number_format((float) $topExpenseCategory['total'], 2); ?></p><?php endif; ?>
        <?php else: ?>
            <p class="money-insight-empty">Add an expense to see your spending rhythm.</p>
        <?php endif; ?>
    </section>

    <section class="money-insight-card money-mini-analysis money-analysis-launch" data-money-analysis-expand data-money-analysis-url="<?= BASE_URL; ?>/modules/money/analysis.php" tabindex="0" role="button" aria-expanded="false" aria-label="Expand cash flow overview">
        <div class="money-insight-heading money-flow-heading"><div><p class="summary-label">Financial snapshot</p><h2>Cash Flow Overview</h2></div><span class="money-view-all">View details <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span></div>
        <div class="money-mini-chart-grid" aria-label="Income and expense flow">
            <?php foreach ($miniCharts as $type => $chart): ?>
                <article class="money-mini-chart money-mini-chart-<?= $type; ?>">
                    <div class="money-flow-label"><span class="money-flow-icon"><i class="bi <?= $type === 'income' ? 'bi-wallet2' : 'bi-receipt' ?>" aria-hidden="true"></i></span><h3><?= $chart['label']; ?></h3></div>
                    <div class="money-flow-data">
                        <div><strong class="money-flow-amount">RM <?= number_format($chart['total'], 2); ?></strong><span class="money-flow-period">This month</span></div>
                        <?php if ($chart['items']): ?>
                            <?php $offset = 0.0; ?>
                            <svg viewBox="0 0 140 140" role="img" aria-label="<?= $chart['label']; ?> category breakdown">
                                <circle cx="70" cy="70" r="56" fill="none" stroke="#f4e9dc" stroke-width="18"/>
                                <?php foreach ($chart['items'] as $index => $item): ?>
                                    <?php $percentage = $chart['total'] > 0 ? ((float) $item['total'] / $chart['total']) * 100 : 0; $length = max(0, ($donutCircumference * ($percentage / 100)) - 3); ?>
                                    <circle cx="70" cy="70" r="56" fill="none" stroke="<?= $chart['colors'][$index % count($chart['colors'])]; ?>" stroke-width="18" stroke-linecap="butt" stroke-dasharray="<?= $length; ?> <?= $donutCircumference; ?>" stroke-dashoffset="<?= -$offset; ?>" transform="rotate(-90 70 70)"/>
                                    <?php $offset += $donutCircumference * ($percentage / 100); ?>
                                <?php endforeach; ?>
                            </svg>
                        <?php else: ?>
                            <svg viewBox="0 0 140 140" role="img" aria-label="No <?= strtolower($chart['label']); ?> records yet"><circle cx="70" cy="70" r="56" fill="none" stroke="#f4e9dc" stroke-width="18"/></svg>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="money-net-balance"><span class="money-balance-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 3v17M6 7h12M8 7 4.5 14h7L8 7Zm8 0-3.5 7h7L16 7ZM3 20h18"/></svg></span><span>Net balance</span><strong>RM <?= number_format((float) $summary['balance'], 2); ?></strong></div>
    </section>

    <section class="money-insight-card money-saving-card">
        <div class="money-insight-heading"><div><p class="summary-label">Your next milestone</p><h2>Savings goal</h2></div><i class="bi bi-piggy-bank saving-icon" aria-hidden="true"></i></div>
        <div class="saving-goal-main"><div><strong><?= $goalProgress; ?>%</strong><p>New laptop fund</p></div><span>RM <?= number_format($goalSaved, 2); ?><small> saved of RM <?= number_format($goalTarget, 2); ?></small></span></div>
        <div class="saving-progress" aria-label="<?= $goalProgress; ?> percent saved"><i style="width: <?= $goalProgress; ?>%"></i></div>
        <p class="saving-note">Keep building your balance — every transaction brings this goal closer.</p>
    </section>
</aside>
</section>

<script>
const saveMoneyScroll = () => window.sessionStorage.setItem('moneyTrackerScrollY', String(window.scrollY));
document.querySelectorAll('[data-money-return-scroll], [data-money-preserve-scroll], .money-view-all').forEach((link) => link.addEventListener('click', saveMoneyScroll));
document.querySelectorAll('.filter-form').forEach((form) => form.addEventListener('submit', saveMoneyScroll));

const savedMoneyScroll = window.sessionStorage.getItem('moneyTrackerScrollY');
if (savedMoneyScroll !== null) {
    window.sessionStorage.removeItem('moneyTrackerScrollY');
    window.requestAnimationFrame(() => window.scrollTo(0, Number(savedMoneyScroll)));
}

document.querySelectorAll('.money-donut-segment, .money-category-row').forEach((item) => {
    const category = item.dataset.category;
    const analysis = item.dataset.analysis;
    const relatedItems = document.querySelectorAll('[data-analysis="' + CSS.escape(analysis) + '"][data-category="' + CSS.escape(category) + '"]');

    item.addEventListener('mouseenter', () => relatedItems.forEach((related) => related.classList.add('is-active')));
    item.addEventListener('mouseleave', () => relatedItems.forEach((related) => related.classList.remove('is-active')));
});

const cashFlowCard = document.querySelector('[data-money-analysis-expand]');
const cashFlowStage = document.querySelector('[data-money-analysis-stage]');
const analysisTemplate = document.querySelector('#money-analysis-template');
const closeMoneyFilterDrawer = () => {
    const drawer = document.querySelector('[data-filter-drawer]');
    const backdrop = document.querySelector('[data-filter-backdrop]');
    drawer?.classList.remove('is-open');
    drawer?.setAttribute('aria-hidden', 'true');
    if (backdrop) backdrop.hidden = true;
    document.body.classList.remove('has-open-drawer');
};
const bindMoneyAnalysisInteractions = (root) => {
    root.querySelectorAll('.money-analysis-card').forEach((card) => {
        card.querySelectorAll('.money-donut-segment, .money-category-row').forEach((item) => {
            const setActive = (active) => {
                const selector = '[data-analysis="' + CSS.escape(item.dataset.analysis) + '"][data-category="' + CSS.escape(item.dataset.category) + '"]';
                card.querySelectorAll(selector).forEach((related) => related.classList.toggle('is-active', active));
            };
            item.addEventListener('mouseenter', () => setActive(true));
            item.addEventListener('mouseleave', () => setActive(false));
        });
    });

    const periodPicker = root.querySelector('.money-period-picker');
    if (periodPicker && !periodPicker.dataset.bound) {
        periodPicker.dataset.bound = 'true';
        periodPicker.addEventListener('change', async () => {
            const url = new URL(periodPicker.action, window.location.origin);
            new FormData(periodPicker).forEach((value, key) => url.searchParams.set(key, value.toString()));
            const selects = periodPicker.querySelectorAll('select');
            selects.forEach((select) => { select.disabled = true; });

            try {
                const response = await fetch(url, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('Unable to update analysis');
                const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
                const responseTemplate = responseDocument.querySelector('#money-analysis-template');
                if (!responseTemplate) throw new Error('Analysis template was not found');
                const replacement = document.createElement('div');
                replacement.innerHTML = responseTemplate.innerHTML;
                const newHealth = replacement.querySelector('.money-cash-health');
                const newAnalysis = replacement.querySelector('.money-analysis');
                const currentHealth = root.querySelector('.money-cash-health');
                const currentAnalysis = root.querySelector('.money-analysis');
                if (!newHealth || !newAnalysis || !currentHealth || !currentAnalysis) throw new Error('Analysis content was not found');
                currentHealth.replaceWith(newHealth);
                currentAnalysis.replaceWith(newAnalysis);
                url.searchParams.set('analysis_open', '1');
                window.history.replaceState({}, '', url);
                bindMoneyAnalysisInteractions(root);
            } catch (error) {
                selects.forEach((select) => { select.disabled = false; });
            }
        });
    }
};
if (cashFlowCard && cashFlowStage) {
    const originalCashFlowMarkup = cashFlowCard.innerHTML;
    const cashFlowPlaceholder = document.createComment('cash flow card position');
    const collapseCashFlowCard = () => {
        cashFlowCard.classList.remove('is-expanded');
        cashFlowCard.setAttribute('aria-expanded', 'false');
        cashFlowCard.innerHTML = originalCashFlowMarkup;
        cashFlowPlaceholder.replaceWith(cashFlowCard);
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('analysis_open');
        window.history.replaceState({}, '', currentUrl);
        cashFlowCard.focus();
    };
    window.addEventListener('money:collapse-analysis', () => {
        if (cashFlowCard.classList.contains('is-expanded')) collapseCashFlowCard();
    });

    cashFlowCard.addEventListener('click', async (event) => {
        event.preventDefault();
        if (event.target.closest('[data-money-analysis-collapse]')) {
            collapseCashFlowCard();
            return;
        }
        if (cashFlowCard.classList.contains('is-expanded')) {
            return;
        }

        window.dispatchEvent(new Event('money:collapse-spending'));
        closeMoneyFilterDrawer();
        cashFlowCard.before(cashFlowPlaceholder);
        cashFlowStage.append(cashFlowCard);
        cashFlowCard.classList.add('is-expanded');
        cashFlowCard.setAttribute('aria-expanded', 'true');
        cashFlowCard.innerHTML = '<button class="money-analysis-inline-close" type="button" data-money-analysis-collapse><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button><p class="money-analysis-inline-loading">Loading full analysis…</p>';

        if (!analysisTemplate) {
            cashFlowCard.innerHTML = '<button class="money-analysis-inline-close" type="button" data-money-analysis-collapse><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button><p class="money-analysis-inline-loading">Unable to load the analysis. Please try again.</p>';
            return;
        }

        cashFlowCard.innerHTML = '<button class="money-analysis-inline-close" type="button" data-money-analysis-collapse><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button>' + analysisTemplate.innerHTML;
        bindMoneyAnalysisInteractions(cashFlowCard);
    });
    cashFlowCard.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && !cashFlowCard.classList.contains('is-expanded')) {
            event.preventDefault();
            cashFlowCard.click();
        }
    });
    if (new URLSearchParams(window.location.search).get('analysis_open') === '1') {
        window.requestAnimationFrame(() => cashFlowCard.click());
    }
}

const spendingCard = document.querySelector('[data-money-spending-expand]');
const spendingStage = document.querySelector('[data-money-spending-stage]');
if (spendingCard && spendingStage) {
    const originalSpendingMarkup = spendingCard.innerHTML;
    const spendingPlaceholder = document.createComment('spending trend card position');
    const collapseSpendingCard = () => {
        spendingCard.classList.remove('is-expanded');
        spendingCard.setAttribute('aria-expanded', 'false');
        spendingCard.innerHTML = originalSpendingMarkup;
        spendingPlaceholder.replaceWith(spendingCard);
        spendingCard.focus();
    };
    window.addEventListener('money:collapse-spending', () => {
        if (spendingCard.classList.contains('is-expanded')) collapseSpendingCard();
    });
    const bindSpendingPeriodPicker = () => {
        const periodInput = spendingCard.querySelector('.money-spending-period input');
        if (!periodInput || periodInput.dataset.bound) return;
        periodInput.dataset.bound = 'true';
        periodInput.addEventListener('change', async () => {
            const url = new URL(spendingCard.dataset.moneySpendingUrl, window.location.origin);
            url.searchParams.set('period', periodInput.value);
            const response = await fetch(url, { credentials: 'same-origin' });
            const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const responseTemplate = responseDocument.querySelector('#money-spending-template');
            if (!response.ok || !responseTemplate) return;
            spendingCard.innerHTML = '<button class="money-analysis-inline-close" type="button" data-money-spending-collapse><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button>' + responseTemplate.innerHTML;
            bindSpendingPeriodPicker();
        });
    };

    spendingCard.addEventListener('click', async (event) => {
        if (event.target.closest('[data-money-spending-collapse]')) {
            collapseSpendingCard();
            return;
        }
        if (spendingCard.classList.contains('is-expanded')) return;

        event.preventDefault();
        window.dispatchEvent(new Event('money:collapse-analysis'));
        closeMoneyFilterDrawer();
        spendingCard.before(spendingPlaceholder);
        spendingStage.append(spendingCard);
        spendingCard.classList.add('is-expanded');
        spendingCard.setAttribute('aria-expanded', 'true');
        spendingCard.innerHTML = '<button class="money-analysis-inline-close" type="button" data-money-spending-collapse><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button><p class="money-analysis-inline-loading">Loading spending insights…</p>';
        try {
            const response = await fetch(spendingCard.dataset.moneySpendingUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('Unable to load spending insights');
            const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const responseTemplate = responseDocument.querySelector('#money-spending-template');
            if (!responseTemplate) throw new Error('Spending template was not found');
            spendingCard.innerHTML = '<button class="money-analysis-inline-close" type="button" data-money-spending-collapse><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button>' + responseTemplate.innerHTML;
            bindSpendingPeriodPicker();
        } catch (error) {
            spendingCard.innerHTML = '<button class="money-analysis-inline-close" type="button" data-money-spending-collapse><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button><p class="money-analysis-inline-loading">Unable to load spending insights. Please try again.</p>';
        }
    });
    spendingCard.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && !spendingCard.classList.contains('is-expanded')) {
            event.preventDefault();
            spendingCard.click();
        }
    });
}
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
