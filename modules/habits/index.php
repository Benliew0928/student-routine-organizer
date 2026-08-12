<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/habit_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$realm = cleanInput((string) ($_GET['realm'] ?? ''));
if (!array_key_exists($realm, habitRealmOptions())) {
    $realm = '';
}
$pageError = null;

try {
    $connection = getDatabaseConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $logId = filter_var($_POST['log_id'] ?? null, FILTER_VALIDATE_INT);
        $status = cleanInput((string) ($_POST['completion_status'] ?? ''));
        $reflection = cleanInput((string) ($_POST['reflection_note'] ?? ''));
        $returnRealm = cleanInput((string) ($_POST['realm'] ?? ''));

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Your session token expired. Please try again.');
        } elseif (!$logId || !array_key_exists($status, habitLogStatusOptions())) {
            setFlash('error', 'That quest update was not valid.');
        } elseif (mb_strlen($reflection) > 255) {
            setFlash('error', 'Reflections must be 255 characters or fewer.');
        } elseif (!habitLoadLogForUser($connection, (int) $logId, $userId)) {
            setFlash('error', 'That quest could not be found.');
        } else {
            $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
            $stmt = $connection->prepare('UPDATE habit_logs SET completion_status = ?, completed_at = ?, reflection_note = ? WHERE log_id = ? AND user_id = ? AND deleted_at IS NULL');
            $stmt->bind_param('sssii', $status, $completedAt, $reflection, $logId, $userId);
            $stmt->execute();
            $messages = [
                'completed' => 'Quest completed. Your sanctuary responded.',
                'skipped' => 'Quest skipped with intention. Tomorrow is still yours.',
                'missed' => 'Quest marked missed. A fresh starting point is waiting.',
                'scheduled' => 'Quest returned to your deck.',
            ];
            setFlash('success', $messages[$status]);
        }
        header('Location: ' . BASE_URL . '/modules/habits/index.php' . (array_key_exists($returnRealm, habitRealmOptions()) ? '?realm=' . rawurlencode($returnRealm) : ''));
        exit;
    }

    $today = new DateTimeImmutable('today');
    [$weekStart, $weekEnd] = habitCurrentWeek();
    habitEnsureLogsForRange($connection, $userId, $weekStart, $weekEnd);
    $realmStats = habitRealmStats($connection, $userId, $weekStart, $weekEnd);
    $quests = habitTodayQuests($connection, $userId, $today->format('Y-m-d'), $realm);
    $allTodayQuests = habitTodayQuests($connection, $userId, $today->format('Y-m-d'));
    $streaks = habitStreaks($connection, $userId);
    $weeklyStory = habitWeeklyStory($realmStats);
} catch (Throwable $exception) {
    logApplicationException($exception, 'habit index');
    $pageError = 'Your sanctuary is unavailable right now. Please check the database setup.';
    $realmStats = [];
    $quests = [];
    $allTodayQuests = [];
    $streaks = [];
    $weeklyStory = '';
}

$todayCompleted = count(array_filter($allTodayQuests, static fn (array $quest): bool => $quest['completion_status'] === 'completed'));
$todayTotal = count($allTodayQuests);
$openQuests = array_values(array_filter($quests, static fn (array $quest): bool => $quest['completion_status'] !== 'completed'));
$completedQuests = array_values(array_filter($quests, static fn (array $quest): bool => $quest['completion_status'] === 'completed'));
$pageTitle = 'Routine Sanctuary';
require __DIR__ . '/../../includes/header.php';
?>

<section class="sanctuary-shell" aria-labelledby="sanctuaryTitle">
    <div class="sanctuary-aurora" aria-hidden="true"></div>
    <header class="sanctuary-hero">
        <div class="sanctuary-hero-copy">
            <p class="sanctuary-kicker">Routine Sanctuary · <?= escapeOutput((new DateTimeImmutable('today'))->format('l, j F')); ?></p>
            <h1 id="sanctuaryTitle">Good evening, <?= escapeOutput(currentUserName()); ?>.</h1>
            <p class="sanctuary-intro">One deliberate action is enough to keep your rhythm alive.</p>
            <div class="sanctuary-hero-actions">
                <a class="sanctuary-button sanctuary-button-primary" href="#todayQuests">See today’s quests <i class="bi bi-arrow-down" aria-hidden="true"></i></a>
                <a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/manage.php"><i class="bi bi-journal-bookmark" aria-hidden="true"></i> Manage blueprints</a>
            </div>
        </div>
        <div class="sanctuary-orbit" aria-label="<?= $todayCompleted; ?> of <?= $todayTotal; ?> quests completed today">
            <svg viewBox="0 0 180 180" role="img" aria-hidden="true">
                <circle class="orbit-track" cx="90" cy="90" r="71"></circle>
                <circle class="orbit-progress" cx="90" cy="90" r="71" style="--orbit-progress: <?= $todayTotal ? (int) round($todayCompleted / $todayTotal * 100) : 0; ?>"></circle>
            </svg>
            <div><strong><?= $todayCompleted; ?><span>/<?= $todayTotal; ?></span></strong><small>cared for today</small></div>
        </div>
    </header>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php else: ?>
        <nav class="realm-grid" aria-label="Filter quests by sanctuary realm">
            <?php foreach ($realmStats as $realmKey => $stat): ?>
                <a class="realm-card realm-<?= escapeOutput($realmKey); ?> realm-<?= escapeOutput($stat['state']); ?> <?= $realm === $realmKey ? 'is-selected' : ''; ?>" href="<?= BASE_URL; ?>/modules/habits/index.php?realm=<?= escapeOutput($realmKey); ?>#todayQuests">
                    <span class="realm-scene" aria-hidden="true"><i class="bi <?= escapeOutput($stat['icon']); ?>"></i><span class="realm-scene-ring"></span></span>
                    <span class="realm-card-top"><span><i class="bi <?= escapeOutput($stat['icon']); ?>" aria-hidden="true"></i> <?= escapeOutput($stat['label']); ?></span><strong><?= $stat['percentage']; ?>%</strong></span>
                    <span class="realm-meter"><i style="--realm-progress: <?= $stat['percentage']; ?>%"></i></span>
                    <span class="realm-card-bottom"><?= $stat['completed']; ?> of <?= $stat['planned']; ?> this week <em><?= escapeOutput(ucfirst($stat['state'])); ?></em></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="quest-section" id="todayQuests" aria-labelledby="todayQuestsTitle">
            <div class="sanctuary-section-heading">
                <div>
                    <p class="sanctuary-kicker">Today’s rhythm<?= $realm !== '' ? ' · ' . escapeOutput(habitRealmOptions()[$realm]['label']) : ''; ?></p>
                    <h2 id="todayQuestsTitle"><?= $openQuests ? 'Choose your next small promise.' : 'Your open deck is clear.'; ?></h2>
                </div>
                <div class="quest-heading-actions">
                    <?php if ($realm !== ''): ?><a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/index.php#todayQuests">Show all realms</a><?php endif; ?>
                    <a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/create.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> New quest</a>
                </div>
            </div>

            <?php if (!$allTodayQuests): ?>
                <article class="sanctuary-empty">
                    <span class="empty-orb" aria-hidden="true"><i class="bi bi-flower1"></i></span>
                    <div><p class="sanctuary-kicker">The conservatory is quiet</p><h2>Plant your first small promise.</h2><p>Start with a simple habit. It will become a daily quest, a visible trail, and part of your weekly story.</p></div>
                    <a class="sanctuary-button sanctuary-button-primary" href="<?= BASE_URL; ?>/modules/habits/create.php">Create a quest blueprint</a>
                </article>
            <?php elseif (!$quests): ?>
                <article class="sanctuary-empty compact-empty"><p>No quests from this realm are scheduled for today.</p><a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/index.php#todayQuests">Show today’s full deck</a></article>
            <?php else: ?>
                <div class="quest-deck">
                    <?php foreach ($openQuests as $quest): ?>
                        <?php $realmMeta = habitRealmOptions()[$quest['realm']]; $streak = $streaks[(int) $quest['habit_id']] ?? ['current' => 0, 'best' => 0]; ?>
                        <article class="quest-card quest-<?= escapeOutput($quest['realm']); ?> status-<?= escapeOutput($quest['completion_status']); ?>">
                            <div class="quest-card-glow" aria-hidden="true"></div>
                            <div class="quest-card-main">
                                <div class="quest-realm"><span><i class="bi <?= escapeOutput($realmMeta['icon']); ?>" aria-hidden="true"></i></span><?= escapeOutput($realmMeta['label']); ?></div>
                                <h3><?= escapeOutput($quest['habit_name']); ?></h3>
                                <p class="quest-motivation"><?= $quest['motivation'] !== '' && $quest['motivation'] !== null ? escapeOutput($quest['motivation']) : escapeOutput($realmMeta['description']); ?></p>
                                <div class="quest-meta"><span><i class="bi bi-clock" aria-hidden="true"></i><?= escapeOutput(habitFormatTime($quest['preferred_time'])); ?></span><?php if ($quest['duration_minutes']): ?><span><i class="bi bi-hourglass-split" aria-hidden="true"></i><?= (int) $quest['duration_minutes']; ?> min</span><?php endif; ?><span><i class="bi bi-fire" aria-hidden="true"></i><?= (int) $streak['current']; ?>-day rhythm</span></div>
                            </div>
                            <div class="quest-card-actions">
                                <?php if ($quest['completion_status'] === 'scheduled'): ?>
                                    <form method="post" action="<?= BASE_URL; ?>/modules/habits/index.php<?= $realm !== '' ? '?realm=' . escapeOutput($realm) : ''; ?>">
                                        <?= csrfInput(); ?><input type="hidden" name="log_id" value="<?= (int) $quest['log_id']; ?>"><input type="hidden" name="completion_status" value="completed"><input type="hidden" name="realm" value="<?= escapeOutput($realm); ?>">
                                        <button class="sanctuary-button sanctuary-button-complete" type="submit">Complete quest <i class="bi bi-check-lg" aria-hidden="true"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="quest-status-note"><?= escapeOutput(habitLogStatusOptions()[$quest['completion_status']]); ?></span>
                                <?php endif; ?>
                                <button class="sanctuary-icon-button" type="button" data-quest-adjust data-dialog-id="questDialog<?= (int) $quest['log_id']; ?>" aria-label="Adjust <?= escapeOutput($quest['habit_name']); ?>"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            </div>
                        </article>
                        <dialog class="quest-dialog" id="questDialog<?= (int) $quest['log_id']; ?>" aria-labelledby="questDialogTitle<?= (int) $quest['log_id']; ?>">
                            <form method="dialog" class="dialog-close-form"><button class="sanctuary-icon-button" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></form>
                            <div class="quest-dialog-copy"><p class="sanctuary-kicker"><?= escapeOutput($realmMeta['label']); ?></p><h2 id="questDialogTitle<?= (int) $quest['log_id']; ?>"><?= escapeOutput($quest['habit_name']); ?></h2><p>Adjust today’s quest with kindness. This will update your Momentum Trail.</p></div>
                            <form method="post" action="<?= BASE_URL; ?>/modules/habits/index.php<?= $realm !== '' ? '?realm=' . escapeOutput($realm) : ''; ?>" class="quest-adjust-form">
                                <?= csrfInput(); ?><input type="hidden" name="log_id" value="<?= (int) $quest['log_id']; ?>"><input type="hidden" name="realm" value="<?= escapeOutput($realm); ?>">
                                <label for="reflection<?= (int) $quest['log_id']; ?>">What shaped this moment? <span>optional</span></label>
                                <textarea id="reflection<?= (int) $quest['log_id']; ?>" name="reflection_note" maxlength="255" placeholder="A small note for your future self."><?= escapeOutput($quest['reflection_note'] ?? ''); ?></textarea>
                                <div class="dialog-actions"><button class="sanctuary-button sanctuary-button-primary" name="completion_status" value="completed" type="submit">Mark complete</button><button class="sanctuary-button sanctuary-button-quiet" name="completion_status" value="skipped" type="submit">Skip today</button><button class="sanctuary-link-danger" name="completion_status" value="missed" type="submit">Mark missed</button></div>
                            </form>
                            <a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/edit.php?id=<?= (int) $quest['habit_id']; ?>">Edit this quest blueprint</a>
                        </dialog>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($completedQuests): ?>
                <section class="completed-quests" aria-labelledby="completedTitle">
                    <div class="completed-heading"><h3 id="completedTitle">Cared for today</h3><span><?= count($completedQuests); ?> completed</span></div>
                    <div class="completed-quest-list">
                        <?php foreach ($completedQuests as $quest): ?>
                            <a href="<?= BASE_URL; ?>/modules/habits/log.php?id=<?= (int) $quest['log_id']; ?>" class="completed-quest"><span><i class="bi <?= escapeOutput(habitRealmOptions()[$quest['realm']]['icon']); ?>" aria-hidden="true"></i></span><strong><?= escapeOutput($quest['habit_name']); ?></strong><em><i class="bi bi-check-circle-fill" aria-hidden="true"></i></em></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </section>

        <section class="sanctuary-story" aria-labelledby="weeklyStoryTitle">
            <div class="story-spark" aria-hidden="true"><i class="bi bi-tree"></i></div>
            <div><p class="sanctuary-kicker">Your weekly story</p><h2 id="weeklyStoryTitle"><?= escapeOutput($weeklyStory); ?></h2><p>Patterns matter more than perfection. Your Momentum Trail keeps the evidence.</p></div>
            <a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/journey.php">Open Momentum Trail <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </section>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
