<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/habit_helpers.php';

requireLogin();
$userId = (int) $_SESSION['user_id'];
$pageError = null;
try {
    $connection = getDatabaseConnection();
    $weekStart = habitJourneyStartFromRequest($_GET);
    $weekEnd = $weekStart->modify('+6 days');
    habitEnsureLogsForRange($connection, $userId, $weekStart, $weekEnd);
    $rows = habitJourneyRows($connection, $userId, $weekStart, $weekEnd);
    $streaks = habitStreaks($connection, $userId);
    $days = iterator_to_array(new DatePeriod($weekStart, new DateInterval('P1D'), $weekEnd->modify('+1 day')));
} catch (Throwable $exception) { $pageError = 'Your Momentum Trail is unavailable right now. Please check the database setup.'; $rows = []; $streaks = []; $days = []; $weekStart = new DateTimeImmutable('monday this week'); $weekEnd = $weekStart->modify('+6 days'); }
$pageTitle = 'Momentum Trail'; require __DIR__ . '/../../includes/header.php';
?>
<section class="journey-page" aria-labelledby="journeyTitle"><header class="journey-header"><div><a class="sanctuary-back-link" href="<?= BASE_URL; ?>/modules/habits/index.php">← Back to sanctuary</a><p class="sanctuary-kicker">Momentum Trail</p><h1 id="journeyTitle">Evidence of your rhythm.</h1><p>Every scheduled quest leaves a small marker. Consistency is a pattern, never a perfect line.</p></div><a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/manage.php">Manage blueprints</a></header>
<?php if ($pageError): ?><div class="alert alert-error"><?= escapeOutput($pageError); ?></div><?php else: ?>
<section class="journey-controls" aria-label="Momentum Trail week navigation"><a class="sanctuary-icon-button" href="<?= BASE_URL; ?>/modules/habits/journey.php?week=<?= escapeOutput($weekStart->modify('-7 days')->format('Y-m-d')); ?>" aria-label="Previous week">←</a><div><strong><?= escapeOutput($weekStart->format('j M')); ?> – <?= escapeOutput($weekEnd->format('j M Y')); ?></strong><span><?= $weekStart->format('Y-m-d') === (new DateTimeImmutable('monday this week'))->format('Y-m-d') ? 'This week' : 'A week in your story'; ?></span></div><a class="sanctuary-icon-button" href="<?= BASE_URL; ?>/modules/habits/journey.php?week=<?= escapeOutput($weekStart->modify('+7 days')->format('Y-m-d')); ?>" aria-label="Next week">→</a></section>
<div class="journey-legend" aria-label="Trail legend"><span class="trail-completed">✓ Completed</span><span class="trail-scheduled">· Ready</span><span class="trail-skipped">– Skipped</span><span class="trail-missed">× Missed</span><span class="trail-empty">○ Not scheduled</span></div>
<?php if (!$rows): ?><section class="sanctuary-empty compact-empty"><p>Your trail begins when you plant a quest.</p><a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/create.php">Plant a quest blueprint</a></section><?php else: ?><div class="journey-table-wrap"><table class="journey-table"><thead><tr><th scope="col">Quest</th><?php foreach ($days as $day): ?><th scope="col" class="<?= $day->format('Y-m-d') === date('Y-m-d') ? 'is-today' : ''; ?>"><span><?= escapeOutput($day->format('D')); ?></span><?= escapeOutput($day->format('j')); ?></th><?php endforeach; ?><th scope="col">Rhythm</th></tr></thead><tbody><?php foreach ($rows as $habitId => $row): $habit = $row['habit']; $meta = habitRealmOptions()[$habit['realm']]; $streak = $streaks[(int) $habitId] ?? ['current' => 0, 'best' => 0]; ?><tr><th scope="row"><span class="journey-realm realm-<?= escapeOutput($habit['realm']); ?>"><?= escapeOutput($meta['symbol']); ?></span><span><strong><?= escapeOutput($habit['habit_name']); ?></strong><small><?= escapeOutput($meta['label']); ?></small></span></th><?php foreach ($days as $day): $date = $day->format('Y-m-d'); $log = $row['logs'][$date] ?? null; ?><td class="<?= $day->format('Y-m-d') === date('Y-m-d') ? 'is-today' : ''; ?>"><?php if ($log): ?><a class="trail-marker trail-<?= escapeOutput($log['completion_status']); ?>" href="<?= BASE_URL; ?>/modules/habits/log.php?id=<?= (int) $log['log_id']; ?>" aria-label="<?= escapeOutput($habit['habit_name']); ?> on <?= escapeOutput($day->format('j F')); ?>: <?= escapeOutput(habitLogStatusOptions()[$log['completion_status']]); ?>"><?php if ($log['completion_status'] === 'completed'): ?>✓<?php elseif ($log['completion_status'] === 'missed'): ?>×<?php elseif ($log['completion_status'] === 'skipped'): ?>–<?php else: ?>·<?php endif; ?></a><?php else: ?><span class="trail-marker trail-empty" aria-label="Not scheduled">○</span><?php endif; ?></td><?php endforeach; ?><td><span class="journey-streak"><strong><?= (int) $streak['current']; ?></strong> day<br>best <?= (int) $streak['best']; ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php endif; ?></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
