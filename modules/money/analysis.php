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
    logApplicationException($exception, 'money analysis');
    $pageError = 'Money analysis is unavailable right now. Please check the database setup.';
}

$chartCircumference = 439.82;
$chartColors = [
    'income' => ['#176b4d', '#2f9e72', '#5cc799', '#38a7a0', '#8bd9c2', '#238268', '#9ee5d1'],
    'expense' => ['#c65d24', '#e8863a', '#f2ad58', '#d9774b', '#b84d3a', '#f4c77a', '#d99962'],
];
$categoryIcons = ['Food' => 'F', 'Transport' => 'T', 'Shopping' => 'S', 'Bills' => 'B', 'Education' => 'E', 'Entertainment' => 'E', 'Healthcare' => 'H', 'Salary' => 'S', 'Allowance' => 'A', 'Freelance' => 'F', 'Others' => 'O'];
$incomeTotal = (float) $summary['total_income'];
$expenseTotal = (float) $summary['total_expense'];
$incomeRetained = $incomeTotal > 0 ? (($incomeTotal - $expenseTotal) / $incomeTotal) * 100 : 0;
$expenseToIncome = $incomeTotal > 0 ? ($expenseTotal / $incomeTotal) * 100 : 0;
$topIncomeSource = $incomeBreakdown[0] ?? null;
$topIncomeShare = $topIncomeSource && $incomeTotal > 0
    ? ((float) $topIncomeSource['total'] / $incomeTotal) * 100
    : 0;

$pageTitle = 'Money Analysis';
require __DIR__ . '/../../includes/header.php';
renderMoneyStyles();
?>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php else: ?>
    <section class="money-cash-health" aria-label="Cash flow health">
        <div class="money-cash-health-heading">
            <small>Cash flow health</small>
            <p>A quick view of how well this period's income supports your spending.</p>
        </div>
        <div class="money-cash-health-grid">
            <article class="money-health-card money-health-income">
                <i class="bi bi-wallet2" aria-hidden="true"></i>
                <div><span>Income retained</span><strong><?= number_format($incomeRetained, 0); ?>%</strong></div>
            </article>
            <article class="money-health-card money-health-expense">
                <i class="bi bi-pie-chart" aria-hidden="true"></i>
                <div><span>Expense to income</span><strong><?= number_format($expenseToIncome, 1); ?>%</strong></div>
            </article>
            <article class="money-health-card money-health-income">
                <i class="bi bi-people" aria-hidden="true"></i>
                <div><span>Active income sources</span><strong><?= number_format(count($incomeBreakdown)); ?></strong></div>
            </article>
            <article class="money-health-card money-health-highlight">
                <i class="bi bi-star" aria-hidden="true"></i>
                <div><span>Top income source</span><strong><?= $topIncomeSource ? escapeOutput($topIncomeSource['category']) . ' · ' . number_format($topIncomeShare, 0) . '%' : 'No income yet'; ?></strong></div>
            </article>
        </div>
    </section>

    <section class="money-analysis" aria-label="Income and expense category analysis">
        <div class="money-analysis-heading"><small>Category insights</small><h2>Income &amp; Expense Breakdown</h2><p>Hover over a chart segment or category to focus on its contribution.</p></div>
        <div class="money-analysis-grid">
            <?php foreach (['income' => ['label' => 'Income', 'items' => $incomeBreakdown, 'total' => $summary['total_income']], 'expense' => ['label' => 'Expense', 'items' => $expenseBreakdown, 'total' => $summary['total_expense']]] as $type => $analysis): ?>
                <?php $chartOffset = 0.0; $colors = $chartColors[$type]; ?>
                <article class="money-analysis-card money-analysis-<?= $type; ?>">
                    <h3><?= $analysis['label']; ?></h3>
                    <?php if ($analysis['items']): ?>
                        <div class="money-analysis-ring">
                                <svg class="money-analysis-chart" viewBox="0 0 200 200" role="img" aria-label="<?= $analysis['label']; ?> breakdown by category">
                            <?php foreach ($analysis['items'] as $index => $item): ?>
                                        <?php
                                        $percentage = $analysis['total'] > 0 ? ((float) $item['total'] / $analysis['total']) * 100 : 0;
                                        $segmentLength = max(0, ($chartCircumference * ($percentage / 100)) - 3);
                                        $color = $colors[$index % count($colors)];
                                        ?>
                                        <circle class="money-donut-segment" data-analysis="<?= $type; ?>" data-category="<?= escapeOutput($item['category']); ?>" data-percentage="<?= round($percentage); ?>" data-amount="RM <?= number_format((float) $item['total'], 2); ?>" cx="100" cy="100" r="70" fill="none" stroke="<?= $color; ?>" stroke-width="30" stroke-dasharray="<?= $segmentLength; ?> <?= $chartCircumference; ?>" stroke-dashoffset="<?= -$chartOffset; ?>" transform="rotate(-90 100 100)" />
                                    <?php $chartOffset += $chartCircumference * ($percentage / 100); ?>
                            <?php endforeach; ?>
                                    <text class="money-analysis-donut-label" x="100" y="93" text-anchor="middle">Total <?= $analysis['label']; ?></text>
                                    <text class="money-analysis-donut-value" x="100" y="116" text-anchor="middle" textLength="100" lengthAdjust="spacingAndGlyphs">RM <?= number_format($analysis['total'], 2); ?></text>
                            </svg>
                        </div>
                        <div class="money-category-details">
                            <div class="money-category-list">
                                <?php foreach ($analysis['items'] as $index => $item): ?>
                                    <?php $percentage = $analysis['total'] > 0 ? ((float) $item['total'] / $analysis['total']) * 100 : 0; ?>
                                    <div class="money-category-row" data-analysis="<?= $type; ?>" data-category="<?= escapeOutput($item['category']); ?>" data-percentage="<?= round($percentage); ?>" data-amount="RM <?= number_format((float) $item['total'], 2); ?>" style="--category-color: <?= $colors[$index % count($colors)]; ?>;">
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
    const tooltip = item.classList.contains('money-donut-segment') ? item.closest('.money-analysis-ring').querySelector('.money-donut-tooltip') : null;
    const donutWrap = item.closest('.money-analysis-ring');
    const callout = item.classList.contains('money-donut-segment')
        ? item.closest('.money-analysis-ring').querySelector('.money-donut-callout[data-analysis="' + CSS.escape(item.dataset.analysis) + '"][data-category="' + CSS.escape(item.dataset.category) + '"]')
        : null;
    item.addEventListener('mouseenter', () => {
        relatedItems.forEach((related) => related.classList.add('is-active'));
        if (callout) {
            const chartBounds = item.closest('.money-analysis-chart').getBoundingClientRect();
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
            const chartBounds = item.closest('.money-analysis-chart').getBoundingClientRect();
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
