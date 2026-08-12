<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/money_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$monthlyExpenseTotal = 0.00;
$previousMonthlyExpenseTotal = 0.00;
$expenseTrend = [];
$expenseBreakdown = [];
$recentExpenses = [];
$pageError = null;
$requestedPeriod = (string) ($_GET['period'] ?? date('Y-m'));
$selectedMonth = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedPeriod)
    ? new DateTimeImmutable($requestedPeriod . '-01')
    : new DateTimeImmutable('first day of this month');

try {
    $connection = getDatabaseConnection();
    $monthlyExpenseTotal = moneyGetExpenseTotalForMonth($connection, $userId, $selectedMonth);
    $previousMonthlyExpenseTotal = moneyGetExpenseTotalForMonth($connection, $userId, $selectedMonth->modify('-1 month'));
    $monthFilters = ['period_year' => $selectedMonth->format('Y'), 'period_month' => $selectedMonth->format('n')];
    $expenseTrend = moneyGetExpenseTrend($connection, $userId, $monthFilters);
    $expenseBreakdown = moneyGetCategoryBreakdown($connection, $userId, 'expense', $monthFilters);
    $monthStart = $selectedMonth->format('Y-m-01');
    $nextMonthStart = $selectedMonth->modify('+1 month')->format('Y-m-d');
    $stmt = $connection->prepare("SELECT amount, category, description, transaction_date FROM money_transactions WHERE user_id = ? AND transaction_type = 'expense' AND transaction_date >= ? AND transaction_date < ? ORDER BY transaction_date DESC, transaction_id DESC LIMIT 3");
    $stmt->bind_param('iss', $userId, $monthStart, $nextMonthStart);
    $stmt->execute();
    $recentExpenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $exception) {
    logApplicationException($exception, 'money spending');
    $pageError = 'Spending insights are unavailable right now. Please check the database setup.';
}

$monthChange = $previousMonthlyExpenseTotal > 0 ? (($monthlyExpenseTotal - $previousMonthlyExpenseTotal) / $previousMonthlyExpenseTotal) * 100 : null;
$trendTotals = array_map(static fn (array $item): float => (float) $item['total'], $expenseTrend);
$trendMax = max(1, ...($trendTotals ?: [1]));
$trendStep = $trendMax <= 60 ? 20 : ($trendMax <= 150 ? 50 : 100);
$trendAxisMax = max($trendStep, (int) ceil($trendMax / $trendStep) * $trendStep);
$daysInMonth = (int) $selectedMonth->format('t');
$trendPoints = [];
foreach ($expenseTrend as $index => $item) {
    $day = (int) date('j', strtotime($item['transaction_date']));
    $x = 12 + (($day - 1) / max(1, $daysInMonth - 1)) * 276;
    $y = 106 - (((float) $item['total'] / $trendAxisMax) * 82);
    $trendPoints[] = ['x' => round($x, 1), 'y' => round($y, 1), 'total' => (float) $item['total'], 'date' => $item['transaction_date']];
}
$trendPolyline = implode(' ', array_map(static fn (array $point): string => $point['x'] . ',' . $point['y'], $trendPoints));
$trendArea = $trendPoints ? 'M ' . implode(' L ', array_map(static fn (array $point): string => $point['x'] . ' ' . $point['y'], $trendPoints)) : '';
$peakIndex = $trendTotals ? array_keys($trendTotals, max($trendTotals), true)[0] : null;
$trendPeak = $peakIndex !== null ? $trendPoints[$peakIndex] : null;
$trendDateTicks = [1, min(8, $daysInMonth), min(15, $daysInMonth), min(22, $daysInMonth), $daysInMonth];
$simpleTrend = $expenseTrend;
if (count($simpleTrend) > 5) {
    $simpleTrend = [];
    $lastTrendItem = count($expenseTrend) - 1;
    foreach (range(0, 4) as $step) {
        $simpleTrend[] = $expenseTrend[(int) round(($step / 4) * $lastTrendItem)];
    }
}
$simpleTotals = array_map(static fn (array $item): float => (float) $item['total'], $simpleTrend);
$simpleMax = max(1, ...($simpleTotals ?: [1]));
$simplePoints = [];
foreach ($simpleTotals as $index => $total) {
    $simplePoints[] = ['x' => round(62 + (($index / max(1, count($simpleTotals) - 1)) * 876), 1), 'y' => round(120 - (($total / $simpleMax) * 82), 1)];
}
$simplePeakIndex = $simpleTotals ? array_keys($simpleTotals, max($simpleTotals), true)[0] : null;
$spendingCategories = array_slice($expenseBreakdown, 0, 4);
$spendingCategoryIcons = ['Food' => 'bi-fork-knife', 'Transport' => 'bi-bus-front', 'Shopping' => 'bi-bag', 'Bills' => 'bi-receipt', 'Education' => 'bi-mortarboard', 'Entertainment' => 'bi-film', 'Healthcare' => 'bi-heart-pulse', 'Others' => 'bi-grid-3x3-gap'];
$embed = ($_GET['embed'] ?? '') === '1';

if (!$embed) {
    $pageTitle = 'Spending Insights';
    require __DIR__ . '/../../includes/header.php';
    renderMoneyStyles();
}
?>
<template id="money-spending-template">
    <section class="money-spending-detail" aria-label="Spending insights">
        <?php if ($pageError): ?>
            <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
        <?php else: ?>
            <header class="money-spending-detail-heading"><div><p class="summary-label">Spending insights</p><h2>Your <?= $selectedMonth->format('F'); ?> spending</h2></div><div class="money-spending-header-actions"><form class="money-spending-period" method="get" action="<?= BASE_URL; ?>/modules/money/spending.php"><i class="bi bi-calendar3" aria-hidden="true"></i><input type="month" name="period" value="<?= $selectedMonth->format('Y-m'); ?>" aria-label="Choose spending month"></form></div></header>
            <div class="money-spending-stat-grid">
                <article><i class="bi bi-wallet2" aria-hidden="true"></i><span>Total spent</span><strong>RM <?= number_format($monthlyExpenseTotal, 2); ?></strong></article>
                <article><i class="bi bi-arrow-<?= $monthChange !== null && $monthChange > 0 ? 'up' : 'down'; ?>" aria-hidden="true"></i><span>vs last month</span><strong><?= $monthChange !== null ? number_format(abs($monthChange), 0) . '%' : '—'; ?></strong></article>
                <article><i class="bi bi-calendar-event" aria-hidden="true"></i><span>Highest spend day</span><strong><?= $peakIndex !== null ? date('j M', strtotime($expenseTrend[$peakIndex]['transaction_date'])) . ' · RM ' . number_format($trendTotals[$peakIndex], 2) : 'No expenses yet'; ?></strong></article>
            </div>
            <section class="money-spending-chart-panel"><h3>Daily spending</h3><?php if ($simplePoints): ?><svg class="money-spending-simple-chart" viewBox="0 0 1000 160" role="img" aria-label="Daily spending trend"><defs><linearGradient id="moneySpendingSimpleFill" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#ff7a18" stop-opacity=".32"/><stop offset="1" stop-color="#ff7a18" stop-opacity="0"/></linearGradient></defs><?php foreach ($simplePoints as $point): ?><line x1="<?= $point['x']; ?>" y1="30" x2="<?= $point['x']; ?>" y2="120" class="money-spending-simple-guide"/><?php endforeach; ?><path d="M <?= implode(' L ', array_map(static fn (array $point): string => $point['x'] . ' ' . $point['y'], $simplePoints)); ?> L <?= $simplePoints[count($simplePoints) - 1]['x']; ?> 120 L <?= $simplePoints[0]['x']; ?> 120 Z" fill="url(#moneySpendingSimpleFill)"/><polyline points="<?= implode(' ', array_map(static fn (array $point): string => $point['x'] . ',' . $point['y'], $simplePoints)); ?>" fill="none" stroke="#ef5a16" stroke-linecap="round" stroke-linejoin="round" stroke-width="5"/><?php foreach ($simplePoints as $index => $point): ?><circle cx="<?= $point['x']; ?>" cy="<?= $point['y']; ?>" r="8" class="money-spending-simple-dot"/><text x="<?= $point['x']; ?>" y="148" text-anchor="middle" class="money-spending-chart-label"><?= $index === 0 ? date('j M', strtotime($simpleTrend[$index]['transaction_date'])) : date('j', strtotime($simpleTrend[$index]['transaction_date'])); ?></text><?php endforeach; ?><?php if ($simplePeakIndex !== null): ?><?php $peak = $simplePoints[$simplePeakIndex]; $tipY = max(5, $peak['y'] - 34); $tipX = max(24, min(824, $peak['x'] - 76)); ?><rect x="<?= $tipX; ?>" y="<?= $tipY; ?>" width="152" height="25" rx="8" class="money-spending-simple-tooltip"/><text x="<?= $tipX + 76; ?>" y="<?= $tipY + 16; ?>" text-anchor="middle" class="money-spending-simple-tooltip-text">RM <?= number_format($simpleTotals[$simplePeakIndex], 2); ?></text><?php endif; ?></svg><?php else: ?><p class="money-insight-empty">Add an expense to see your spending details.</p><?php endif; ?></section>
            <div class="money-spending-detail-grid">
                <section class="money-spending-list-panel"><h3>Spending by category</h3><?php foreach ($spendingCategories as $category): ?><?php $share = $monthlyExpenseTotal > 0 ? min(100, ((float) $category['total'] / $monthlyExpenseTotal) * 100) : 0; ?><div class="money-spending-category-row"><span class="money-spending-row-icon"><i class="bi <?= $spendingCategoryIcons[$category['category']] ?? 'bi-grid-3x3-gap'; ?>" aria-hidden="true"></i></span><strong><?= escapeOutput($category['category']); ?></strong><i><b style="width: <?= round($share); ?>%"></b></i><span>RM <?= number_format((float) $category['total'], 2); ?></span><em><?= round($share); ?>%</em></div><?php endforeach; ?></section>
                <section class="money-spending-list-panel"><h3>Recent expenses</h3><?php foreach ($recentExpenses as $expense): ?><div class="money-spending-recent-row"><span class="money-spending-row-icon"><i class="bi <?= $spendingCategoryIcons[$expense['category']] ?? 'bi-grid-3x3-gap'; ?>" aria-hidden="true"></i></span><span><strong><?= escapeOutput($expense['category']); ?><?= $expense['description'] ? ' · ' . escapeOutput($expense['description']) : ''; ?></strong><small><?= date('j M', strtotime($expense['transaction_date'])); ?></small></span><em>RM <?= number_format((float) $expense['amount'], 2); ?></em></div><?php endforeach; ?><a class="money-view-all" href="<?= BASE_URL; ?>/modules/money/index.php#transactions">See all transactions <i class="bi bi-arrow-right" aria-hidden="true"></i></a></section>
            </div>
        <?php endif; ?>
    </section>
</template>
<?php if (!$embed): ?>
    <main class="money-spending-page"><script>document.currentScript.parentElement.append(document.querySelector('#money-spending-template').content.cloneNode(true));</script></main>
    <?php require __DIR__ . '/../../includes/footer.php'; ?>
<?php endif; ?>
