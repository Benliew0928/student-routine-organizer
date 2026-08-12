<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/habit_helpers.php';

requireLogin();
$userId = (int) $_SESSION['user_id'];
$habitId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$habitId) { setFlash('error', 'Invalid quest blueprint.'); header('Location: ' . BASE_URL . '/modules/habits/manage.php'); exit; }
$errors = [];
$pageError = null;
$data = habitDefaultFormData();

try {
    $connection = getDatabaseConnection();
    $habit = habitLoadForUser($connection, (int) $habitId, $userId);
    if (!$habit) { setFlash('error', 'Quest blueprint not found.'); header('Location: ' . BASE_URL . '/modules/habits/manage.php'); exit; }
    $data = ['habit_name' => $habit['habit_name'], 'realm' => $habit['realm'], 'target_frequency' => $habit['target_frequency'], 'scheduled_days' => array_filter(explode(',', $habit['scheduled_days'])), 'preferred_time' => $habit['preferred_time'] ? substr($habit['preferred_time'], 0, 5) : '', 'duration_minutes' => $habit['duration_minutes'] ?? '', 'motivation' => $habit['motivation'] ?? '', 'priority' => $habit['priority']];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = habitDataFromRequest($_POST);
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { $errors[] = 'Your session token expired. Please try again.'; }
        $errors = array_merge($errors, habitValidateData($data));
        if (!$errors) {
            $days = implode(',', $data['scheduled_days']); $time = $data['preferred_time'] !== '' ? $data['preferred_time'] . ':00' : null; $duration = $data['duration_minutes'] !== '' ? (int) $data['duration_minutes'] : null; $motivation = $data['motivation'] !== '' ? $data['motivation'] : null;
            $stmt = $connection->prepare('UPDATE habits SET habit_name = ?, realm = ?, target_frequency = ?, scheduled_days = ?, preferred_time = ?, duration_minutes = ?, motivation = ?, priority = ? WHERE habit_id = ? AND user_id = ?');
            $stmt->bind_param('sssssissii', $data['habit_name'], $data['realm'], $data['target_frequency'], $days, $time, $duration, $motivation, $data['priority'], $habitId, $userId);
            $stmt->execute();
            setFlash('success', 'Quest blueprint updated.'); header('Location: ' . BASE_URL . '/modules/habits/manage.php'); exit;
        }
    }
} catch (Throwable $exception) {
    logApplicationException($exception, 'habit edit');
    $pageError = 'Quest editing is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Edit Quest'; require __DIR__ . '/../../includes/header.php';
?>
<section class="blueprint-page" aria-labelledby="blueprintTitle"><header class="blueprint-hero"><a class="sanctuary-back-link" href="<?= BASE_URL; ?>/modules/habits/manage.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to blueprints</a><p class="sanctuary-kicker">Refine a quest blueprint</p><h1 id="blueprintTitle">Keep the promise realistic.</h1><p>Changes affect future scheduled quests. Existing trail entries remain part of your story.</p></header>
<?php if ($pageError): ?><div class="alert alert-error"><?= escapeOutput($pageError); ?></div><?php endif; ?><?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $error): ?><p><?= escapeOutput($error); ?></p><?php endforeach; ?></div><?php endif; ?>
<form method="post" action="<?= BASE_URL; ?>/modules/habits/edit.php?id=<?= (int) $habitId; ?>" class="blueprint-form compact-blueprint-form" data-quest-form><?= csrfInput(); ?>
<section class="blueprint-step"><div class="step-number">01</div><div class="step-copy"><p class="sanctuary-kicker">The promise</p><h2>Name and realm</h2></div><div class="step-fields"><label for="habit_name">Quest name</label><input id="habit_name" name="habit_name" maxlength="100" value="<?= escapeOutput($data['habit_name']); ?>" required><fieldset class="realm-choice-grid"><legend class="sr-only">Sanctuary realm</legend><?php foreach (habitRealmOptions() as $value => $meta): ?><label class="realm-choice realm-<?= escapeOutput($value); ?> <?= $data['realm'] === $value ? 'is-checked' : ''; ?>"><input type="radio" name="realm" value="<?= escapeOutput($value); ?>" <?= $data['realm'] === $value ? 'checked' : ''; ?>><span class="realm-choice-symbol"><i class="bi <?= escapeOutput($meta['icon']); ?>" aria-hidden="true"></i></span><strong><?= escapeOutput($meta['label']); ?></strong></label><?php endforeach; ?></fieldset></div></section>
<section class="blueprint-step"><div class="step-number">02</div><div class="step-copy"><p class="sanctuary-kicker">The rhythm</p><h2>Schedule and scale</h2></div><div class="step-fields schedule-fields"><label for="target_frequency">Rhythm</label><select id="target_frequency" name="target_frequency" data-frequency-select><?php foreach (habitFrequencyOptions() as $value => $label): ?><option value="<?= escapeOutput($value); ?>" <?= $data['target_frequency'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option><?php endforeach; ?></select><fieldset class="day-picker" data-schedule-days><legend>Scheduled days</legend><div><?php foreach (habitDayOptions() as $day => $label): ?><label><input type="checkbox" name="scheduled_days[]" value="<?= escapeOutput($day); ?>" <?= in_array($day, $data['scheduled_days'], true) ? 'checked' : ''; ?>><span><?= escapeOutput($label); ?></span></label><?php endforeach; ?></div></fieldset><div class="two-field-row"><div><label for="preferred_time_hour">Preferred time <span>optional</span></label><div class="sanctuary-time-picker" data-time-picker><input id="preferred_time" name="preferred_time" type="hidden" value="<?= escapeOutput($data['preferred_time']); ?>" data-time-value><span class="control-emblem" aria-hidden="true"><i class="bi bi-clock"></i></span><div class="time-number-group" role="group" aria-label="Preferred time"><div class="time-slot"><span>Hour</span><input id="preferred_time_hour" type="text" inputmode="numeric" autocomplete="off" maxlength="2" placeholder="hh" aria-label="Hour" data-time-hour></div><span class="time-divider" aria-hidden="true">:</span><div class="time-slot"><span>Minute</span><input type="text" inputmode="numeric" autocomplete="off" maxlength="2" placeholder="mm" aria-label="Minute" data-time-minute></div></div><div class="time-period-toggle" role="group" aria-label="Time period"><button type="button" data-time-period="AM" aria-pressed="true">AM</button><button type="button" data-time-period="PM" aria-pressed="false">PM</button></div><button class="time-clear-button" type="button" data-time-clear aria-label="Clear preferred time" title="Clear preferred time"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><small class="field-hint">A quiet 12-hour time, only if it helps.</small></div><div><label for="duration_minutes">Gentle duration <span>optional</span></label><div class="control-with-icon input-suffix"><span class="control-emblem" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span><input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" value="<?= escapeOutput((string) $data['duration_minutes']); ?>"><span class="input-unit">min</span></div><small class="field-hint">Keep it small enough to repeat.</small></div></div></div></section>
<section class="blueprint-step blueprint-final-step"><div class="step-number">03</div><div class="step-copy"><p class="sanctuary-kicker">The reminder</p><h2>Protect the reason.</h2></div><div class="step-fields"><label for="motivation">A note for your future self <span>optional</span></label><textarea id="motivation" name="motivation" maxlength="180"><?= escapeOutput($data['motivation']); ?></textarea><label for="priority">Importance</label><select id="priority" name="priority"><?php foreach (habitPriorityOptions() as $value => $label): ?><option value="<?= escapeOutput($value); ?>" <?= $data['priority'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option><?php endforeach; ?></select><div class="blueprint-actions"><button class="sanctuary-button sanctuary-button-primary" type="submit">Save blueprint</button><a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/manage.php">Cancel</a></div></div></section></form></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
