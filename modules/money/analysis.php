<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/money_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$summary = ['total_income' => 0.00, 'total_expense' => 0.00, 'balance' => 0.00, 'total_count' => 0];
$incomeBreakdown = [];
$expenseBreakdown = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $summary = moneyGetSummary($connection, $userId);
    $incomeBreakdown = moneyGetCategoryBreakdown($connection, $userId, 'income');
    $expenseBreakdown = moneyGetCategoryBreakdown($connection, $userId, 'expense');
} catch (Throwable $exception) {
    $pageError = 'Money analysis is unavailable right now. Please check the database setup.';
}

$chartCircumference = 439.82;
$chartColors = [
    'income' => ['#176b4d', '#2f9e72', '#5cc799', '#38a7a0', '#8bd9c2', '#238268', '#9ee5d1'],
    'expense' => ['#c65d24', '#e8863a', '#f2ad58', '#d9774b', '#b84d3a', '#f4c77a', '#d99962'],
];
$categoryIcons = ['Food' => 'F', 'Transport' => 'T', 'Shopping' => 'S', 'Bills' => 'B', 'Education' => 'E', 'Entertainment' => 'E', 'Healthcare' => 'H', 'Salary' => 'S', 'Allowance' => 'A', 'Freelance' => 'F', 'Others' => 'O'];

$pageTitle = 'Money Analysis';
require __DIR__ . '/../../includes/header.php';
renderMoneyStyles();
?>

<section class="money-theme-hero">
    <div class="money-hero-copy">
        <p class="eyebrow">Financial Tracker</p>
        <h1>Money Analysis</h1>
        <p class="hero-copy">Explore where your money comes from and where it goes, grouped by category.</p>
        <div class="money-hero-metrics" aria-label="Money overview">
            <span><strong>RM <?= number_format($summary['total_income'], 2); ?></strong> income</span>
            <span><strong>RM <?= number_format($summary['total_expense'], 2); ?></strong> expense</span>
        </div>
    </div>
    <div class="money-overview-card"><span class="summary-label">Available Balance</span><strong>RM <?= number_format($summary['balance'], 2); ?></strong><small><?= number_format($summary['total_count']); ?> total transactions</small></div>
</section>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php else: ?>
    <section class="money-summary-grid" aria-label="Analysis summary metrics">
        <article class="money-dash-card money-dash-card-income">
            <span class="summary-label">Income share</span>
            <strong><?= $summary['income_pct']; ?>%</strong>
            <p>RM <?= number_format($summary['total_income'], 2); ?> received</p>
        </article>
        <article class="money-dash-card money-dash-card-expense">
            <span class="summary-label">Expense share</span>
            <strong><?= $summary['expense_pct']; ?>%</strong>
            <p>RM <?= number_format($summary['total_expense'], 2); ?> spent</p>
        </article>
        <article class="money-dash-card money-dash-card-balance">
            <span class="summary-label">Net position</span>
            <strong><?= $summary['balance'] >= 0 ? '+' : '-'; ?>RM <?= number_format(abs($summary['balance']), 2); ?></strong>
            <p><?= $summary['balance'] >= 0 ? 'You are currently in surplus' : 'Your expenses exceed income'; ?></p>
        </article>
    </section>

    <section class="money-analysis" aria-label="Income and expense category analysis">
        <div class="money-analysis-heading"><small>Category insights</small><h2>Income &amp; Expense Breakdown</h2><p>Hover over a chart segment or category to focus on its contribution.</p></div>
        <div class="money-analysis-grid">
            <?php foreach (['income' => ['label' => 'Income', 'items' => $incomeBreakdown, 'total' => $summary['total_income']], 'expense' => ['label' => 'Expense', 'items' => $expenseBreakdown, 'total' => $summary['total_expense']]] as $type => $analysis): ?>
                <?php $chartOffset = 0.0; $colors = $chartColors[$type]; ?>
                <article class="money-analysis-card money-analysis-<?= $type; ?>">
                    <h3><?= $analysis['label']; ?> by Category</h3>
                    <?php if ($analysis['items']): ?>
                        <div class="money-donut-wrap">
                            <svg class="money-category-donut" viewBox="0 0 200 200" role="img" aria-label="<?= $analysis['label']; ?> breakdown by category">
                            <?php foreach ($analysis['items'] as $index => $item): ?>
                                    <?php
                                    $percentage = $analysis['total'] > 0 ? ((float) $item['total'] / $analysis['total']) * 100 : 0;
                                    $segmentLength = max(0, ($chartCircumference * ($percentage / 100)) - 3);
                                    $color = $colors[$index % count($colors)];
                                    ?>
                                    <circle class="money-donut-segment" data-analysis="<?= $type; ?>" data-category="<?= escapeOutput($item['category']); ?>" data-percentage="<?= round($percentage); ?>" data-amount="RM <?= number_format((float) $item['total'], 2); ?>" cx="100" cy="100" r="70" fill="none" stroke="<?= $color; ?>" stroke-width="30" stroke-dasharray="<?= $segmentLength; ?> <?= $chartCircumference; ?>" stroke-dashoffset="<?= -$chartOffset; ?>" transform="rotate(-90 100 100)" />
                                    <?php $chartOffset += $chartCircumference * ($percentage / 100); ?>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                        <p class="money-donut-summary"><span>Total <?= strtolower($analysis['label']); ?></span><strong>RM <?= number_format($analysis['total'], 2); ?></strong></p>
                        <div class="money-category-details">
                            <div class="money-category-list">
                                <?php foreach ($analysis['items'] as $index => $item): ?>
                                    <?php $percentage = $analysis['total'] > 0 ? ((float) $item['total'] / $analysis['total']) * 100 : 0; ?>
                                    <div class="money-category-row" data-analysis="<?= $type; ?>" data-category="<?= escapeOutput($item['category']); ?>" style="--category-color: <?= $colors[$index % count($colors)]; ?>;">
                                        <span class="money-category-icon"><?= escapeOutput($categoryIcons[$item['category']] ?? 'Other'); ?></span>
                                        <div class="money-category-progress"><div><strong><?= escapeOutput($item['category']); ?></strong><span>RM <?= number_format((float) $item['total'], 2); ?></span></div><i><b style="width: <?= round($percentage, 1); ?>%"></b></i></div>
                                        <em><?= round($percentage); ?>%</em>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="money-analysis-empty">No <?= strtolower($analysis['label']); ?> records yet.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="exercise-action-bar" aria-label="Analysis actions">
    <div><p class="summary-label">Money Tracker</p><strong>Manage your transactions</strong></div>
    <div class="button-row compact-actions"><a class="button" href="<?= BASE_URL; ?>/modules/money/index.php">Back to Transactions</a><a class="button primary" href="<?= BASE_URL; ?>/modules/money/create.php">Add Transaction</a></div>
</section>

<script>
document.querySelectorAll('.money-donut-segment, .money-category-row').forEach((item) => {
    const relatedItems = document.querySelectorAll('[data-analysis="' + CSS.escape(item.dataset.analysis) + '"][data-category="' + CSS.escape(item.dataset.category) + '"]');
    const tooltip = item.classList.contains('money-donut-segment') ? item.closest('.money-donut-wrap').querySelector('.money-donut-tooltip') : null;
    const donutWrap = item.closest('.money-donut-wrap');
    const callout = item.classList.contains('money-donut-segment')
        ? item.closest('.money-donut-wrap').querySelector('.money-donut-callout[data-analysis="' + CSS.escape(item.dataset.analysis) + '"][data-category="' + CSS.escape(item.dataset.category) + '"]')
        : null;
    item.addEventListener('mouseenter', () => {
        relatedItems.forEach((related) => related.classList.add('is-active'));
        if (callout) {
            const chartBounds = item.closest('.money-category-donut').getBoundingClientRect();
            callout.classList.add(item.dataset.analysis === 'income' ? 'callout-near-right' : 'callout-near-left');
            callout.style.position = 'fixed';
            callout.style.right = 'auto';
            callout.style.top = (chartBounds.top + (chartBounds.height / 2)) + 'px';
            callout.style.left = item.dataset.analysis === 'income'
                ? (chartBounds.right + 8) + 'px'
                : (chartBounds.left - callout.offsetWidth - 8) + 'px';
            callout.style.transform = 'translateY(-50%)';
        }
        if (tooltip) {
            tooltip.textContent = item.dataset.category + ' · ' + item.dataset.percentage + '% · ' + item.dataset.amount;
            tooltip.style.color = item.getAttribute('stroke');
            const chartBounds = item.closest('.money-category-donut').getBoundingClientRect();
            tooltip.hidden = false;
            tooltip.style.left = item.dataset.analysis === 'expense'
                ? (chartBounds.left - tooltip.offsetWidth - 6) + 'px'
                : (chartBounds.right + 6) + 'px';
            tooltip.style.top = (chartBounds.top + (chartBounds.height / 2)) + 'px';
            donutWrap.classList.add('has-tooltip');
        }
    });
    item.addEventListener('mouseleave', () => {
        relatedItems.forEach((related) => related.classList.remove('is-active'));
        if (callout) {
            callout.classList.remove('callout-near-right', 'callout-near-left');
            callout.style.position = '';
            callout.style.right = '';
            callout.style.left = '';
            callout.style.top = '';
            callout.style.transform = '';
        }
        if (tooltip) {
            tooltip.hidden = true;
            donutWrap.classList.remove('has-tooltip');
        }
    });
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
