<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/money_helpers.php';

requireLogin();
$userId = (int) $_SESSION['user_id'];
$connection = getDatabaseConnection();

function moneyGoalRedirect(?int $goalId = null): void
{
    $location = BASE_URL . '/modules/money/index.php?goal_open=1';
    if ($goalId !== null && $goalId > 0) {
        $location .= '&goal_id=' . $goalId;
    }
    header('Location: ' . $location);
    exit;
}

function moneyGoalDateFromRequest(string $value): ?string
{
    $date = cleanInput($value);
    return $date !== '' && moneyIsValidDate($date) ? $date : null;
}

function moneyGoalWeeksUntilTarget(?string $targetDate): ?int
{
    if (!$targetDate) {
        return null;
    }

    try {
        $daysUntilTarget = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($targetDate))->format('%r%a');
    } catch (Exception $exception) {
        return null;
    }

    return $daysUntilTarget < 0 ? null : max(1, (int) ceil($daysUntilTarget / 7));
}

function moneyGoalAutomaticWeeklyAmount(float $target, float $saved, ?string $targetDate): ?float
{
    $weeksUntilTarget = moneyGoalWeeksUntilTarget($targetDate);
    if ($weeksUntilTarget === null) {
        return null;
    }

    return round(max(0, $target - $saved) / $weeksUntilTarget, 2);
}

function moneyGoalRefreshWeeklyAmount(mysqli $connection, int $goalId, int $userId): void
{
    $goal = moneySavingsGoalById($connection, $goalId, $userId);
    if (!$goal || !(int) $goal['auto_save_enabled']) {
        return;
    }

    $savedAmount = moneySavingsGoalRecordedAmount($connection, $goalId, $userId);
    $weeklyAmount = moneyGoalAutomaticWeeklyAmount((float) $goal['target_amount'], $savedAmount, $goal['target_date']);
    if ($weeklyAmount === null) {
        return;
    }

    $stmt = $connection->prepare('UPDATE money_savings_goals SET weekly_amount = ? WHERE goal_id = ? AND user_id = ?');
    $stmt->bind_param('dii', $weeklyAmount, $goalId, $userId);
    $stmt->execute();
}

function moneyGoalPlanData(array $goal): array
{
    $target = (float) $goal['target_amount'];
    $saved = (float) $goal['saved_amount'];
    $remaining = max(0, $target - $saved);
    $weeksUntilTarget = moneyGoalWeeksUntilTarget($goal['target_date']);
    $automaticWeeklyAmount = (int) $goal['auto_save_enabled'] ? moneyGoalAutomaticWeeklyAmount($target, $saved, $goal['target_date']) : null;
    $weeklyAmount = $automaticWeeklyAmount ?? (float) $goal['weekly_amount'];
    $weeksToGoal = (int) $goal['auto_save_enabled'] ? $weeksUntilTarget : ($weeklyAmount > 0 ? (int) ceil($remaining / $weeklyAmount) : null);
    $recommendedWeekly = $automaticWeeklyAmount;

    return [
        'target' => $target,
        'saved' => $saved,
        'progress' => moneySavingsGoalProgress($goal),
        'remaining' => $remaining,
        'weekly_amount' => $weeklyAmount,
        'weeks_to_goal' => $weeksToGoal,
        'weeks_until_target' => $weeksUntilTarget,
        'recommended_weekly' => $recommendedWeekly,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session token expired. Please try again.');
        moneyGoalRedirect();
    }

    $action = cleanInput((string) ($_POST['goal_action'] ?? ''));
    $redirectGoalId = 0;

    if ($action === 'create') {
        $name = cleanInput((string) ($_POST['goal_name'] ?? ''));
        $target = (float) ($_POST['target_amount'] ?? 0);
        $date = moneyGoalDateFromRequest((string) ($_POST['target_date'] ?? ''));
        $planEnabled = isset($_POST['weekly_plan_enabled']) ? 1 : 0;
        $weeklyAmount = $planEnabled ? moneyGoalAutomaticWeeklyAmount($target, 0, $date) : 0.00;
        $reminders = isset($_POST['reminders_enabled']) ? 1 : 0;

        if ($name !== '' && mb_strlen($name) <= 120 && $target > 0 && $target <= 99999999.99 && (!$planEnabled || $weeklyAmount !== null)) {
            $stmt = $connection->prepare('INSERT INTO money_savings_goals (user_id, goal_name, target_amount, target_date, weekly_amount, auto_save_enabled, reminders_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isdsdii', $userId, $name, $target, $date, $weeklyAmount, $planEnabled, $reminders);
            $stmt->execute();
            $redirectGoalId = (int) $connection->insert_id;
        }
    } elseif ($action === 'duplicate') {
        $sourceGoalId = (int) ($_POST['goal_id'] ?? 0);
        $sourceGoal = moneySavingsGoalById($connection, $sourceGoalId, $userId);
        if ($sourceGoal) {
            $name = mb_substr($sourceGoal['goal_name'] . ' (new plan)', 0, 120);
            $target = (float) $sourceGoal['target_amount'];
            $date = null;
            $planEnabled = 0;
            $weeklyAmount = 0.00;
            $reminders = (int) $sourceGoal['reminders_enabled'];
            $stmt = $connection->prepare('INSERT INTO money_savings_goals (user_id, goal_name, target_amount, target_date, weekly_amount, auto_save_enabled, reminders_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isdsdii', $userId, $name, $target, $date, $weeklyAmount, $planEnabled, $reminders);
            $stmt->execute();
            $redirectGoalId = (int) $connection->insert_id;
        }
    } elseif ($action === 'update') {
        $goalId = (int) ($_POST['goal_id'] ?? 0);
        $goal = moneySavingsGoalById($connection, $goalId, $userId);
        $name = cleanInput((string) ($_POST['goal_name'] ?? ''));
        $target = (float) ($_POST['target_amount'] ?? 0);
        $date = moneyGoalDateFromRequest((string) ($_POST['target_date'] ?? ''));
        $planEnabled = isset($_POST['weekly_plan_enabled']) ? 1 : 0;
        $savedAmount = $goal ? moneySavingsGoalRecordedAmount($connection, $goalId, $userId) : 0.00;
        $weeklyAmount = $planEnabled ? moneyGoalAutomaticWeeklyAmount($target, $savedAmount, $date) : 0.00;
        $reminders = isset($_POST['reminders_enabled']) ? 1 : 0;

        if ($goal && $goal['status'] === 'active' && $name !== '' && mb_strlen($name) <= 120 && $target > 0 && $target <= 99999999.99 && (!$planEnabled || $weeklyAmount !== null)) {
            $stmt = $connection->prepare('UPDATE money_savings_goals SET goal_name = ?, target_amount = ?, target_date = ?, weekly_amount = ?, auto_save_enabled = ?, reminders_enabled = ? WHERE goal_id = ? AND user_id = ?');
            $stmt->bind_param('sdsdiiii', $name, $target, $date, $weeklyAmount, $planEnabled, $reminders, $goalId, $userId);
            $stmt->execute();
            $redirectGoalId = $goalId;
        }
    } elseif ($action === 'status') {
        $goalId = (int) ($_POST['goal_id'] ?? 0);
        $status = cleanInput((string) ($_POST['status'] ?? ''));
        $goal = moneySavingsGoalById($connection, $goalId, $userId);

        $canChangeStatus = $goal && (
            ($goal['status'] === 'active' && in_array($status, ['paused', 'completed', 'archived'], true)) ||
            ($goal['status'] === 'paused' && in_array($status, ['active', 'archived'], true))
        );
        if ($canChangeStatus) {
            $completedAt = $status === 'completed' ? 'NOW()' : 'NULL';
            $stmt = $connection->prepare("UPDATE money_savings_goals SET status = ?, completed_at = {$completedAt} WHERE goal_id = ? AND user_id = ?");
            $stmt->bind_param('sii', $status, $goalId, $userId);
            $stmt->execute();
            $redirectGoalId = $status === 'archived' ? 0 : $goalId;
        }
    } elseif ($action === 'contribute') {
        $goalId = (int) ($_POST['goal_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $note = cleanInput((string) ($_POST['note'] ?? ''));
        $date = moneyGoalDateFromRequest((string) ($_POST['contribution_date'] ?? ''));
        $goal = moneySavingsGoalById($connection, $goalId, $userId);

        if ($goal && $goal['status'] === 'active' && $amount > 0 && $amount <= 99999999.99 && mb_strlen($note) <= 255 && $date !== null) {
            $stmt = $connection->prepare('INSERT INTO money_savings_contributions (goal_id, user_id, amount, note, contribution_date) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iidss', $goalId, $userId, $amount, $note, $date);
            $stmt->execute();
            moneyGoalRefreshWeeklyAmount($connection, $goalId, $userId);
            $redirectGoalId = $goalId;
        }
    } elseif ($action === 'update_contribution') {
        $contributionId = (int) ($_POST['contribution_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $note = cleanInput((string) ($_POST['note'] ?? ''));
        $date = moneyGoalDateFromRequest((string) ($_POST['contribution_date'] ?? ''));
        $contribution = moneySavingsContributionById($connection, $contributionId, $userId);
        $goal = $contribution ? moneySavingsGoalById($connection, (int) $contribution['goal_id'], $userId) : null;

        if ($contribution && $goal && $goal['status'] === 'active' && $amount > 0 && $amount <= 99999999.99 && mb_strlen($note) <= 255 && $date !== null) {
            $stmt = $connection->prepare('UPDATE money_savings_contributions SET amount = ?, note = ?, contribution_date = ? WHERE contribution_id = ? AND user_id = ?');
            $stmt->bind_param('dssii', $amount, $note, $date, $contributionId, $userId);
            $stmt->execute();
            moneyGoalRefreshWeeklyAmount($connection, (int) $goal['goal_id'], $userId);
            $redirectGoalId = (int) $goal['goal_id'];
        }
    } elseif ($action === 'delete_contribution') {
        $contributionId = (int) ($_POST['contribution_id'] ?? 0);
        $contribution = moneySavingsContributionById($connection, $contributionId, $userId);
        $goal = $contribution ? moneySavingsGoalById($connection, (int) $contribution['goal_id'], $userId) : null;

        if ($contribution && $goal && $goal['status'] === 'active') {
            $stmt = $connection->prepare('DELETE FROM money_savings_contributions WHERE contribution_id = ? AND user_id = ?');
            $stmt->bind_param('ii', $contributionId, $userId);
            $stmt->execute();
            moneyGoalRefreshWeeklyAmount($connection, (int) $goal['goal_id'], $userId);
            $redirectGoalId = (int) $goal['goal_id'];
        }
    }

    moneyGoalRedirect($redirectGoalId);
}

$allGoals = moneySavingsGoalsForUser($connection, $userId);
$currentGoals = array_values(array_filter($allGoals, static fn (array $goal): bool => in_array($goal['status'], ['active', 'paused'], true)));
$pastGoals = array_values(array_filter($allGoals, static fn (array $goal): bool => $goal['status'] === 'completed'));
$selectedGoalId = (int) ($_GET['goal_id'] ?? 0);
$goal = null;
foreach (array_merge($currentGoals, $pastGoals) as $candidateGoal) {
    if ((int) $candidateGoal['goal_id'] === $selectedGoalId) {
        $goal = $candidateGoal;
        break;
    }
}
$goal ??= $currentGoals[0] ?? $pastGoals[0] ?? null;
$contributions = $goal ? moneySavingsContributions($connection, (int) $goal['goal_id'], $userId) : [];
$goalData = $goal ? moneyGoalPlanData($goal) : null;
$isActive = $goal && $goal['status'] === 'active';
$selectedIsPast = $goal && $goal['status'] === 'completed';
?>
<template id="money-goal-template">
<section class="money-goal-detail" aria-label="Savings plans">
  <div class="money-goal-detail-heading money-goal-plans-heading"><div><p class="summary-label">Savings planner</p><h2>Savings plans</h2><small>Every amount is a manual record for one selected plan. It never changes your Money Tracker balance.</small></div><span><?= count($currentGoals); ?> current &middot; <?= count($pastGoals); ?> past</span></div>

  <section class="money-goal-overview">
    <div class="money-goal-tabs" role="tablist" aria-label="Savings plan groups"><button type="button" class="<?= $selectedIsPast ? '' : 'is-selected'; ?>" data-money-goal-tab="current" role="tab" aria-selected="<?= $selectedIsPast ? 'false' : 'true'; ?>">Current plans <b><?= count($currentGoals); ?></b></button><button type="button" class="<?= $selectedIsPast ? 'is-selected' : ''; ?>" data-money-goal-tab="past" role="tab" aria-selected="<?= $selectedIsPast ? 'true' : 'false'; ?>">Past plans <b><?= count($pastGoals); ?></b></button></div>
    <div class="money-goal-plan-panel" data-money-goal-panel="current" <?= $selectedIsPast ? 'hidden' : ''; ?>>
      <?php if ($currentGoals): ?><div class="money-goal-plan-grid"><?php foreach ($currentGoals as $plan): ?><?php $planProgress = moneySavingsGoalProgress($plan); ?><article class="money-goal-plan-card is-selectable <?= $goal && (int) $goal['goal_id'] === (int) $plan['goal_id'] ? 'is-current' : ''; ?>" role="button" tabindex="0" data-money-goal-select="<?= (int) $plan['goal_id']; ?>" aria-label="Open current plan for <?= escapeOutput($plan['goal_name']); ?>"><div><span class="money-goal-status-chip is-<?= escapeOutput($plan['status']); ?>"><?= escapeOutput(ucfirst($plan['status'])); ?></span><h3><?= escapeOutput($plan['goal_name']); ?></h3><p>RM <?= number_format((float) $plan['saved_amount'], 2); ?> of RM <?= number_format((float) $plan['target_amount'], 2); ?></p></div><strong><?= number_format($planProgress, 1); ?>%</strong><i><b style="width: <?= $planProgress; ?>%"></b></i><footer><span><?= $plan['target_date'] ? 'Target ' . date('d M Y', strtotime($plan['target_date'])) : 'No target date'; ?></span><i class="bi bi-arrow-right" aria-hidden="true"></i></footer></article><?php endforeach; ?></div><?php else: ?><p class="money-goal-empty">No current plans yet. Create one below when you are ready.</p><?php endif; ?>
    </div>
    <div class="money-goal-plan-panel" data-money-goal-panel="past" <?= $selectedIsPast ? '' : 'hidden'; ?>>
      <?php if ($pastGoals): ?><div class="money-goal-plan-grid"><?php foreach ($pastGoals as $plan): ?><?php $planProgress = moneySavingsGoalProgress($plan); ?><article class="money-goal-plan-card is-past is-selectable <?= $goal && (int) $goal['goal_id'] === (int) $plan['goal_id'] ? 'is-current' : ''; ?>" role="button" tabindex="0" data-money-goal-select="<?= (int) $plan['goal_id']; ?>" aria-label="Open completed record for <?= escapeOutput($plan['goal_name']); ?>"><div><span class="money-goal-status-chip is-<?= escapeOutput($plan['status']); ?>"><?= escapeOutput(ucfirst($plan['status'])); ?></span><h3><?= escapeOutput($plan['goal_name']); ?></h3><p>RM <?= number_format((float) $plan['saved_amount'], 2); ?> of RM <?= number_format((float) $plan['target_amount'], 2); ?></p></div><strong><?= number_format($planProgress, 1); ?>%</strong><i><b style="width: <?= $planProgress; ?>%"></b></i><footer><span><?= $plan['completed_at'] ? 'Completed ' . date('d M Y', strtotime($plan['completed_at'])) : 'Completed record'; ?></span><i class="bi bi-arrow-right" aria-hidden="true"></i></footer></article><?php endforeach; ?></div><?php else: ?><p class="money-goal-empty">Completed plans will appear here.</p><?php endif; ?>
    </div>
  </section>

  <?php if ($goal && $goalData): ?>
    <section class="money-goal-selected-detail">
      <div class="money-goal-selected-heading"><div><p>Selected plan</p><h3><?= escapeOutput($goal['goal_name']); ?></h3></div><span class="money-goal-status-chip is-<?= escapeOutput($goal['status']); ?>"><?= escapeOutput(ucfirst($goal['status'])); ?></span></div>
      <section class="money-goal-hero"><div><p><?= $goal['status'] === 'completed' ? 'Completed progress' : ($goal['status'] === 'paused' ? 'Paused progress' : ($goal['status'] === 'archived' ? 'Archived progress' : 'Current progress')); ?></p><strong><?= number_format($goalData['progress'], 1); ?>%</strong><b>RM <?= number_format($goalData['saved'], 2); ?> recorded</b><small>RM <?= number_format($goalData['remaining'], 2); ?> remaining<?= $goal['target_date'] ? ' &middot; target ' . date('d M Y', strtotime($goal['target_date'])) : ''; ?></small></div><span class="money-goal-badge"><i class="bi bi-<?= $goal['status'] === 'completed' ? 'check2-circle' : 'bookmark-star'; ?>" aria-hidden="true"></i></span><i><b style="width: <?= $goalData['progress']; ?>%"></b></i></section>
      <section class="money-goal-milestones"><h3>Milestones</h3><div><?php foreach ([25 => 'First step', 50 => 'Halfway', 75 => 'Almost there', 100 => 'Goal reached'] as $milestone => $label): ?><span class="<?= $goalData['progress'] >= $milestone ? 'is-reached' : ''; ?>"><b><?= $milestone; ?>%</b><?= $label; ?></span><?php endforeach; ?></div></section>

      <?php if ($isActive && $goal['reminders_enabled'] && $goal['target_date'] && $goalData['recommended_weekly'] !== null): ?><p class="money-goal-reminder"><i class="bi bi-lightbulb" aria-hidden="true"></i><span>To reach this plan, record about RM <?= number_format($goalData['recommended_weekly'], 2); ?> per week for the next <?= $goalData['weeks_until_target']; ?> week<?= $goalData['weeks_until_target'] === 1 ? '' : 's'; ?>.</span></p><?php endif; ?>

      <?php if ($isActive): ?>
        <div class="money-goal-detail-grid">
          <section><h3>Record savings</h3><form method="post" action="<?= BASE_URL; ?>/modules/money/goals.php"><?= csrfInput(); ?><input type="hidden" name="goal_action" value="contribute"><input type="hidden" name="goal_id" value="<?= (int) $goal['goal_id']; ?>"><label>Amount<input name="amount" type="number" min="0.01" step="0.01" required placeholder="RM 0.00"></label><label>Note <span>(optional)</span><input name="note" maxlength="255" placeholder="e.g. Saved from freelance work"></label><label>Date<input name="contribution_date" type="date" value="<?= date('Y-m-d'); ?>" required></label><button type="submit">Record savings</button></form></section>
          <section><h3>Plan settings</h3><form method="post" action="<?= BASE_URL; ?>/modules/money/goals.php" data-money-weekly-calculator data-money-saved-amount="<?= number_format($goalData['saved'], 2, '.', ''); ?>"><?= csrfInput(); ?><input type="hidden" name="goal_action" value="update"><input type="hidden" name="goal_id" value="<?= (int) $goal['goal_id']; ?>"><label>Plan name<input name="goal_name" maxlength="120" value="<?= escapeOutput($goal['goal_name']); ?>" required></label><label>Target amount<input name="target_amount" data-money-weekly-target type="number" min="0.01" step="0.01" value="<?= number_format($goalData['target'], 2, '.', ''); ?>" required></label><label>Target date<input name="target_date" data-money-weekly-date type="date" value="<?= escapeOutput((string) $goal['target_date']); ?>"></label><label class="money-goal-weekly-amount" data-money-weekly-amount-field>Weekly amount <span>(auto)</span><input name="weekly_amount" data-money-weekly-amount type="number" min="0" step="0.01" value="<?= number_format($goalData['weekly_amount'], 2, '.', ''); ?>" <?= $goal['auto_save_enabled'] && $goalData['recommended_weekly'] !== null ? 'readonly' : 'disabled'; ?>><small data-money-weekly-help><?= $goal['auto_save_enabled'] ? 'Calculated from your target date.' : 'Enable the plan to calculate a weekly amount.'; ?></small></label><div class="money-goal-plan-options"><label class="money-goal-check money-goal-weekly-toggle"><input name="weekly_plan_enabled" type="checkbox" data-money-weekly-plan <?= $goal['auto_save_enabled'] ? 'checked' : ''; ?>> Weekly saving plan</label><label class="money-goal-check"><input name="reminders_enabled" type="checkbox" <?= $goal['reminders_enabled'] ? 'checked' : ''; ?>> Show plan reminders</label></div><button type="submit">Save plan</button></form><?php if ($goalData['weeks_until_target'] !== null && $goal['auto_save_enabled'] && $goalData['recommended_weekly'] !== null): ?><p class="money-goal-pace">Auto plan: RM <?= number_format($goalData['weekly_amount'], 2); ?> per week for the next <?= $goalData['weeks_until_target']; ?> week<?= $goalData['weeks_until_target'] === 1 ? '' : 's'; ?>. Records remain manual.</p><?php endif; ?></section>
        </div>
        <section class="money-goal-status"><h3>Plan status</h3><form method="post" action="<?= BASE_URL; ?>/modules/money/goals.php"><?= csrfInput(); ?><input type="hidden" name="goal_action" value="status"><input type="hidden" name="goal_id" value="<?= (int) $goal['goal_id']; ?>"><button name="status" value="paused" type="submit">Pause plan</button><button name="status" value="completed" type="submit">Mark completed</button><button name="status" value="archived" type="submit">Archive plan</button></form></section>
      <?php elseif ($goal['status'] === 'paused'): ?>
        <section class="money-goal-status"><h3>Plan status</h3><p>This plan is paused. Its records stay unchanged until you resume it.</p><form method="post" action="<?= BASE_URL; ?>/modules/money/goals.php"><?= csrfInput(); ?><input type="hidden" name="goal_action" value="status"><input type="hidden" name="goal_id" value="<?= (int) $goal['goal_id']; ?>"><button name="status" value="active" type="submit">Resume plan</button><button name="status" value="archived" type="submit">Archive plan</button></form></section>
      <?php else: ?>
        <section class="money-goal-status"><h3>Plan history</h3><p>This <?= escapeOutput($goal['status']); ?> plan is read-only, so its record stays accurate. You can start a similar plan without copying its savings entries.</p><form method="post" action="<?= BASE_URL; ?>/modules/money/goals.php"><?= csrfInput(); ?><input type="hidden" name="goal_action" value="duplicate"><input type="hidden" name="goal_id" value="<?= (int) $goal['goal_id']; ?>"><button type="submit">Start similar plan</button></form></section>
      <?php endif; ?>

      <section class="money-goal-activity-section"><h3><?= $goal['status'] === 'completed' || $goal['status'] === 'archived' ? 'Full savings history' : 'Savings activity'; ?></h3><?php if ($contributions): ?><div class="money-goal-activity"><?php foreach ($contributions as $item): ?><details><summary><span><i class="bi bi-plus-lg" aria-hidden="true"></i></span><p><b><?= escapeOutput($item['note'] ?: 'Savings added'); ?></b><small><?= date('d M Y', strtotime($item['contribution_date'])); ?></small></p><strong>+ RM <?= number_format((float) $item['amount'], 2); ?></strong></summary><?php if ($isActive): ?><div class="money-goal-record-edit"><form method="post" action="<?= BASE_URL; ?>/modules/money/goals.php"><?= csrfInput(); ?><input type="hidden" name="goal_action" value="update_contribution"><input type="hidden" name="contribution_id" value="<?= (int) $item['contribution_id']; ?>"><label>Amount<input name="amount" type="number" min="0.01" step="0.01" value="<?= number_format((float) $item['amount'], 2, '.', ''); ?>" required></label><label>Note<input name="note" maxlength="255" value="<?= escapeOutput((string) $item['note']); ?>"></label><label>Date<input name="contribution_date" type="date" value="<?= escapeOutput($item['contribution_date']); ?>" required></label><button type="submit">Update record</button></form><form method="post" action="<?= BASE_URL; ?>/modules/money/goals.php"><?= csrfInput(); ?><input type="hidden" name="goal_action" value="delete_contribution"><input type="hidden" name="contribution_id" value="<?= (int) $item['contribution_id']; ?>"><button class="money-goal-delete" type="submit">Delete record</button></form></div><?php endif; ?></details><?php endforeach; ?></div><?php else: ?><p class="money-goal-empty">No savings recorded for this plan yet.</p><?php endif; ?></section>
    </section>
  <?php endif; ?>

  <section class="money-goal-create" data-money-goal-current-only <?= $selectedIsPast ? 'hidden' : ''; ?>><h3>New savings plan</h3><p>Create another plan without changing your existing plans or records.</p><form method="post" action="<?= BASE_URL; ?>/modules/money/goals.php" data-money-weekly-calculator data-money-saved-amount="0"><?= csrfInput(); ?><input type="hidden" name="goal_action" value="create"><label>Plan name<input name="goal_name" maxlength="120" required placeholder="e.g. Emergency fund"></label><label>Target amount<input name="target_amount" data-money-weekly-target type="number" min="0.01" step="0.01" required placeholder="RM 3,000.00"></label><label>Target date <span>(optional)</span><input name="target_date" data-money-weekly-date type="date"></label><label class="money-goal-weekly-amount" data-money-weekly-amount-field>Weekly amount <span>(auto)</span><input name="weekly_amount" data-money-weekly-amount type="number" min="0" step="0.01" value="0.00" disabled><small data-money-weekly-help>Enable the plan to calculate a weekly amount.</small></label><div class="money-goal-plan-options"><label class="money-goal-check money-goal-weekly-toggle"><input name="weekly_plan_enabled" data-money-weekly-plan type="checkbox"> Weekly saving plan</label><label class="money-goal-check"><input name="reminders_enabled" type="checkbox" checked> Show plan reminders</label></div><button type="submit">Create plan</button></form></section>
</section>
</template>
