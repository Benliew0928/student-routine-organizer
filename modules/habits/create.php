<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/habit_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$data = habitDefaultFormData();
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = habitDataFromRequest($_POST);
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }
        $errors = array_merge($errors, habitValidateData($data));
        if (!$errors) {
            $days = implode(',', $data['scheduled_days']);
            $time = $data['preferred_time'] !== '' ? $data['preferred_time'] . ':00' : null;
            $duration = $data['duration_minutes'] !== '' ? (int) $data['duration_minutes'] : null;
            $motivation = $data['motivation'] !== '' ? $data['motivation'] : null;
            $stmt = $connection->prepare('INSERT INTO habits (user_id, habit_name, realm, target_frequency, scheduled_days, preferred_time, duration_minutes, motivation, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isssssiss', $userId, $data['habit_name'], $data['realm'], $data['target_frequency'], $days, $time, $duration, $motivation, $data['priority']);
            $stmt->execute();
            setFlash('success', 'Quest blueprint created. It will appear on its next scheduled day.');
            header('Location: ' . BASE_URL . '/modules/habits/index.php');
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Quest creation is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Plant a Quest';
require __DIR__ . '/../../includes/header.php';
?>

<section class="blueprint-page" aria-labelledby="blueprintTitle">
    <header class="blueprint-hero">
        <a class="sanctuary-back-link" href="<?= BASE_URL; ?>/modules/habits/index.php">← Back to sanctuary</a>
        <p class="sanctuary-kicker">Plant a quest blueprint</p>
        <h1 id="blueprintTitle">Make the next small promise feel possible.</h1>
        <p>Blueprints are reusable routines. Your sanctuary will create a dated quest whenever its schedule arrives.</p>
    </header>

    <?php if ($pageError): ?><div class="alert alert-error"><?= escapeOutput($pageError); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $error): ?><p><?= escapeOutput($error); ?></p><?php endforeach; ?></div><?php endif; ?>

    <form method="post" action="<?= BASE_URL; ?>/modules/habits/create.php" class="blueprint-form" data-quest-form>
        <?= csrfInput(); ?>
        <section class="blueprint-step"><div class="step-number">01</div><div class="step-copy"><p class="sanctuary-kicker">Name the rhythm</p><h2>What do you want to make easier?</h2><p>A clear, modest action works best.</p></div><div class="step-fields"><label for="habit_name">Quest name</label><input id="habit_name" name="habit_name" maxlength="100" value="<?= escapeOutput($data['habit_name']); ?>" placeholder="e.g. Review PHP lecture notes" required><div class="template-row" aria-label="Quick-start quest templates"><button type="button" class="template-chip" data-quest-template="Review lecture notes|focus|weekdays|20|Feel prepared before class.">Lecture review</button><button type="button" class="template-chip" data-quest-template="Fill water bottle|energy|daily|5|Keep my energy steady through lectures.">Water reset</button><button type="button" class="template-chip" data-quest-template="Plan tomorrow|life_admin|weekdays|10|Begin tomorrow with less stress.">Plan tomorrow</button></div></div></section>

        <section class="blueprint-step"><div class="step-number">02</div><div class="step-copy"><p class="sanctuary-kicker">Choose a realm</p><h2>Where will this care land?</h2><p>One realm helps your weekly story stay readable.</p></div><fieldset class="realm-choice-grid"><legend class="sr-only">Sanctuary realm</legend><?php foreach (habitRealmOptions() as $value => $meta): ?><label class="realm-choice realm-<?= escapeOutput($value); ?> <?= $data['realm'] === $value ? 'is-checked' : ''; ?>"><input type="radio" name="realm" value="<?= escapeOutput($value); ?>" <?= $data['realm'] === $value ? 'checked' : ''; ?>><span class="realm-choice-symbol"><?= escapeOutput($meta['symbol']); ?></span><strong><?= escapeOutput($meta['label']); ?></strong><small><?= escapeOutput($meta['description']); ?></small></label><?php endforeach; ?></fieldset></section>

        <section class="blueprint-step"><div class="step-number">03</div><div class="step-copy"><p class="sanctuary-kicker">Set the invitation</p><h2>When should it appear?</h2><p>Schedules make a quest visible; they never need to be perfect.</p></div><div class="step-fields schedule-fields"><label for="target_frequency">Rhythm</label><select id="target_frequency" name="target_frequency" data-frequency-select><?php foreach (habitFrequencyOptions() as $value => $label): ?><option value="<?= escapeOutput($value); ?>" <?= $data['target_frequency'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option><?php endforeach; ?></select><fieldset class="day-picker" data-schedule-days><legend>Scheduled days</legend><p>For once-a-week and custom rhythms, choose the days that feel right.</p><div><?php foreach (habitDayOptions() as $day => $label): ?><label><input type="checkbox" name="scheduled_days[]" value="<?= escapeOutput($day); ?>" <?= in_array($day, $data['scheduled_days'], true) ? 'checked' : ''; ?>><span><?= escapeOutput($label); ?></span></label><?php endforeach; ?></div></fieldset><div class="two-field-row"><div><label for="preferred_time">Preferred time <span>optional</span></label><input id="preferred_time" name="preferred_time" type="time" value="<?= escapeOutput($data['preferred_time']); ?>"></div><div><label for="duration_minutes">Gentle duration <span>optional</span></label><div class="input-suffix"><input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" value="<?= escapeOutput($data['duration_minutes']); ?>" placeholder="20"><span>min</span></div></div></div></div></section>

        <section class="blueprint-step blueprint-final-step"><div class="step-number">04</div><div class="step-copy"><p class="sanctuary-kicker">Give it meaning</p><h2>Why does it matter to you?</h2><p>This appears as a quiet reminder when the quest is waiting.</p></div><div class="step-fields"><label for="motivation">A note for your future self <span>optional</span></label><textarea id="motivation" name="motivation" maxlength="180" placeholder="e.g. Feel prepared before class."><?= escapeOutput($data['motivation']); ?></textarea><label for="priority">Importance</label><select id="priority" name="priority"><?php foreach (habitPriorityOptions() as $value => $label): ?><option value="<?= escapeOutput($value); ?>" <?= $data['priority'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option><?php endforeach; ?></select><div class="blueprint-actions"><button class="sanctuary-button sanctuary-button-primary" type="submit">Plant this quest <span aria-hidden="true">✦</span></button><a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/index.php">Cancel</a></div></div></section>
    </form>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
