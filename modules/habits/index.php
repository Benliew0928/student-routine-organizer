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
                <a class="sanctuary-button sanctuary-button-primary" href="#todayQuests">See today’s quests <span aria-hidden="true">↓</span></a>
                <a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/manage.php">Manage blueprints</a>
            </div>
        </div>
        <div class="sanctuary-orbit" aria-label="<?= $todayCompleted; ?> of <?= $todayTotal; ?> quests completed today">
            <svg viewBox="0 0 180 180" role="img" aria-hidden="true">
                <circle class="orbit-track" cx="90" cy="90" r="71"></circle>
                <circle class="orbit-progress" cx="90" cy="90" r="71" style="--orbit-progress: <?= $todayTotal ? (int) round($todayCompleted / $todayTotal * 100) : 0; ?>"></circle>
                <path d="M90 47 96 78 128 90 96 102 90 133 84 102 52 90 84 78Z" class="orbit-star"></path>
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
                    <span class="realm-scene" aria-hidden="true">
                        <?php if ($realmKey === 'focus'): ?>
                            <svg viewBox="0 0 120 82"><path d="M20 70h80M44 70V43h32v27M51 43V28h18v15M33 70V55h11m32 15V55h11M25 25c10-14 21-10 24 4-13 2-20-3-24-4Zm70 0c-10-14-21-10-24 4 13 2 20-3 24-4Z"/></svg>
                        <?php elseif ($realmKey === 'energy'): ?>
                            <svg viewBox="0 0 120 82"><path d="M60 12v15m-27-4 10 12m44-12-10 12M20 50h21l8-15 15 29 11-21h25M29 68h62"/></svg>
                        <?php elseif ($realmKey === 'mind'): ?>
                            <svg viewBox="0 0 120 82"><path d="M18 63c16-27 34-27 44 0 10-27 28-27 40 0M33 26h1m16-12h1m17 16h1m20-8h1M26 70h68"/></svg>
                        <?php else: ?>
                            <svg viewBox="0 0 120 82"><path d="M25 68V28h70v40M35 28v-9h50v9M38 42h17m10 0h17M38 54h17m10 0h17M18 68h84"/></svg>
                        <?php endif; ?>
                    </span>
                    <span class="realm-card-top"><span><?= escapeOutput($stat['symbol']); ?> <?= escapeOutput($stat['label']); ?></span><strong><?= $stat['percentage']; ?>%</strong></span>
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
                    <a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/create.php">+ New quest</a>
                </div>
            </div>

            <?php if (!$allTodayQuests): ?>
                <article class="sanctuary-empty">
                    <span class="empty-orb" aria-hidden="true">✦</span>
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
                                <div class="quest-realm"><span><?= escapeOutput($realmMeta['symbol']); ?></span><?= escapeOutput($realmMeta['label']); ?></div>
                                <h3><?= escapeOutput($quest['habit_name']); ?></h3>
                                <p class="quest-motivation"><?= $quest['motivation'] !== '' && $quest['motivation'] !== null ? escapeOutput($quest['motivation']) : escapeOutput($realmMeta['description']); ?></p>
                                <div class="quest-meta"><span><?= escapeOutput(habitFormatTime($quest['preferred_time'])); ?></span><?php if ($quest['duration_minutes']): ?><span><?= (int) $quest['duration_minutes']; ?> min</span><?php endif; ?><span><?= (int) $streak['current']; ?>-day rhythm</span></div>
                            </div>
                            <div class="quest-card-actions">
                                <?php if ($quest['completion_status'] === 'scheduled'): ?>
                                    <form method="post" action="<?= BASE_URL; ?>/modules/habits/index.php<?= $realm !== '' ? '?realm=' . escapeOutput($realm) : ''; ?>">
                                        <?= csrfInput(); ?><input type="hidden" name="log_id" value="<?= (int) $quest['log_id']; ?>"><input type="hidden" name="completion_status" value="completed"><input type="hidden" name="realm" value="<?= escapeOutput($realm); ?>">
                                        <button class="sanctuary-button sanctuary-button-complete" type="submit">Complete quest <span aria-hidden="true">✓</span></button>
                                    </form>
                                <?php else: ?>
                                    <span class="quest-status-note"><?= escapeOutput(habitLogStatusOptions()[$quest['completion_status']]); ?></span>
                                <?php endif; ?>
                                <button class="sanctuary-icon-button" type="button" data-quest-adjust data-dialog-id="questDialog<?= (int) $quest['log_id']; ?>" aria-label="Adjust <?= escapeOutput($quest['habit_name']); ?>">•••</button>
                            </div>
                        </article>
                        <dialog class="quest-dialog" id="questDialog<?= (int) $quest['log_id']; ?>" aria-labelledby="questDialogTitle<?= (int) $quest['log_id']; ?>">
                            <form method="dialog" class="dialog-close-form"><button class="sanctuary-icon-button" aria-label="Close">×</button></form>
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
                            <a href="<?= BASE_URL; ?>/modules/habits/log.php?id=<?= (int) $quest['log_id']; ?>" class="completed-quest"><span><?= escapeOutput(habitRealmOptions()[$quest['realm']]['symbol']); ?></span><strong><?= escapeOutput($quest['habit_name']); ?></strong><em>✓</em></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </section>

        <section class="sanctuary-story" aria-labelledby="weeklyStoryTitle">
            <div class="story-spark" aria-hidden="true">✦</div>
            <div><p class="sanctuary-kicker">Your weekly story</p><h2 id="weeklyStoryTitle"><?= escapeOutput($weeklyStory); ?></h2><p>Patterns matter more than perfection. Your Momentum Trail keeps the evidence.</p></div>
            <a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/journey.php">Open Momentum Trail <span aria-hidden="true">→</span></a>
        </section>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
