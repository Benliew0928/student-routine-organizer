<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/habit_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$search = cleanInput((string) ($_GET['search'] ?? ''));
$realm = cleanInput((string) ($_GET['realm'] ?? ''));
$state = cleanInput((string) ($_GET['state'] ?? 'active'));

if (!array_key_exists($realm, habitRealmOptions())) {
    $realm = '';
}
if (!in_array($state, ['active', 'archived', 'all'], true)) {
    $state = 'active';
}

$records = [];
$counts = ['active' => 0, 'archived' => 0, 'all' => 0];
$streaks = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $habitId = filter_var($_POST['habit_id'] ?? null, FILTER_VALIDATE_INT);
        $action = cleanInput((string) ($_POST['action'] ?? ''));
        $returnState = cleanInput((string) ($_POST['return_state'] ?? 'active'));

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Your session token expired. Please try again.');
        } elseif (!$habitId || !in_array($action, ['archive', 'restore'], true) || !habitLoadForUser($connection, (int) $habitId, $userId)) {
            setFlash('error', 'That quest blueprint could not be updated.');
        } else {
            $active = $action === 'restore' ? 1 : 0;
            $stmt = $connection->prepare('UPDATE habits SET is_active = ? WHERE habit_id = ? AND user_id = ?');
            $stmt->bind_param('iii', $active, $habitId, $userId);
            $stmt->execute();
            setFlash('success', $active ? 'Quest restored to the sanctuary.' : 'Quest archived. Its trail is safely preserved.');
        }

        if (!in_array($returnState, ['active', 'archived', 'all'], true)) {
            $returnState = 'active';
        }
        header('Location: ' . BASE_URL . '/modules/habits/manage.php?state=' . rawurlencode($returnState));
        exit;
    }

    $countStmt = $connection->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_count, COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS archived_count FROM habits WHERE user_id = ?");
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $counts = [
        'active' => (int) $countRow['active_count'],
        'archived' => (int) $countRow['archived_count'],
        'all' => (int) $countRow['total'],
    ];

    $where = ['h.user_id = ?'];
    $types = 'i';
    $params = [$userId];

    if ($search !== '') {
        $where[] = 'h.habit_name LIKE ?';
        $types .= 's';
        $params[] = '%' . $search . '%';
    }
    if ($realm !== '') {
        $where[] = 'h.realm = ?';
        $types .= 's';
        $params[] = $realm;
    }
    if ($state !== 'all') {
        $where[] = 'h.is_active = ?';
        $types .= 'i';
        $params[] = $state === 'active' ? 1 : 0;
    }

    $sql = "SELECT h.*, COUNT(l.log_id) AS log_count, COALESCE(SUM(CASE WHEN l.completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS completion_count
            FROM habits h
            LEFT JOIN habit_logs l ON l.habit_id = h.habit_id AND l.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . '
            GROUP BY h.habit_id
            ORDER BY h.is_active DESC, h.updated_at DESC, h.habit_name';
    $stmt = $connection->prepare($sql);
    $refs = [];
    foreach ($params as $key => &$value) {
        $refs[$key] = &$value;
    }
    $stmt->bind_param($types, ...$refs);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $streaks = habitStreaks($connection, $userId);
} catch (Throwable $exception) {
    logApplicationException($exception, 'habit manage');
    $pageError = 'Quest blueprints are unavailable right now. Please check the database setup.';
}

$pageTitle = 'Manage Quest Blueprints';
require __DIR__ . '/../../includes/header.php';
?>

<section class="manage-page" aria-labelledby="manageTitle">
    <header class="manage-header">
        <div>
            <a class="sanctuary-back-link" href="<?= BASE_URL; ?>/modules/habits/index.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to sanctuary</a>
            <p class="sanctuary-kicker">Blueprint library</p>
            <h1 id="manageTitle">The routines behind your rhythm.</h1>
            <p>Design, pause, restore, and remove reusable habits without disturbing the rest of your sanctuary.</p>
        </div>
        <a class="sanctuary-button sanctuary-button-primary" href="<?= BASE_URL; ?>/modules/habits/create.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> Plant a quest</a>
    </header>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php else: ?>
        <nav class="blueprint-tabs" aria-label="Blueprint status">
            <a class="<?= $state === 'active' ? 'is-active' : ''; ?>" href="<?= BASE_URL; ?>/modules/habits/manage.php?state=active">
                <i class="bi bi-flower1" aria-hidden="true"></i><span>Active</span><strong><?= $counts['active']; ?></strong>
            </a>
            <a class="<?= $state === 'archived' ? 'is-active' : ''; ?>" href="<?= BASE_URL; ?>/modules/habits/manage.php?state=archived">
                <i class="bi bi-archive" aria-hidden="true"></i><span>Archived</span><strong><?= $counts['archived']; ?></strong>
            </a>
            <a class="<?= $state === 'all' ? 'is-active' : ''; ?>" href="<?= BASE_URL; ?>/modules/habits/manage.php?state=all">
                <i class="bi bi-collection" aria-hidden="true"></i><span>All blueprints</span><strong><?= $counts['all']; ?></strong>
            </a>
        </nav>

        <form method="get" class="manage-filter">
            <input type="hidden" name="state" value="<?= escapeOutput($state); ?>">
            <label class="sr-only" for="search">Search blueprints</label>
            <div class="filter-search-wrap"><i class="bi bi-search" aria-hidden="true"></i><input id="search" name="search" type="search" value="<?= escapeOutput($search); ?>" placeholder="Search a routine"></div>
            <select name="realm" aria-label="Filter by realm">
                <option value="">All realms</option>
                <?php foreach (habitRealmOptions() as $value => $meta): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $realm === $value ? 'selected' : ''; ?>><?= escapeOutput($meta['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="sanctuary-button sanctuary-button-quiet" type="submit"><i class="bi bi-sliders" aria-hidden="true"></i> Filter</button>
        </form>

        <?php if (!$records): ?>
            <section class="sanctuary-empty compact-empty">
                <div>
                    <h2><?= $state === 'archived' ? 'No archived blueprints yet.' : 'No blueprints match this view.'; ?></h2>
                    <p><?= $state === 'archived' ? 'Paused routines will stay here with their history intact.' : 'Try another filter or plant a new routine.'; ?></p>
                </div>
                <?php if ($state !== 'archived'): ?><a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/create.php">Plant a new quest</a><?php endif; ?>
            </section>
        <?php else: ?>
            <div class="blueprint-library">
                <?php foreach ($records as $habit): ?>
                    <?php $meta = habitRealmOptions()[$habit['realm']]; $streak = $streaks[(int) $habit['habit_id']] ?? ['current' => 0, 'best' => 0]; ?>
                    <article class="blueprint-card <?= (int) $habit['is_active'] === 1 ? '' : 'is-archived'; ?>">
                        <div class="blueprint-card-top">
                            <span class="blueprint-realm realm-<?= escapeOutput($habit['realm']); ?>"><i class="bi <?= escapeOutput($meta['icon']); ?>" aria-hidden="true"></i> <?= escapeOutput($meta['label']); ?></span>
                            <span class="blueprint-state"><i class="bi <?= (int) $habit['is_active'] ? 'bi-circle-fill' : 'bi-archive'; ?>" aria-hidden="true"></i><?= (int) $habit['is_active'] ? 'Active' : 'Archived'; ?></span>
                        </div>
                        <h2><?= escapeOutput($habit['habit_name']); ?></h2>
                        <p><?= $habit['motivation'] ? escapeOutput($habit['motivation']) : 'A quiet promise in your ' . escapeOutput($meta['label']) . '.'; ?></p>
                        <div class="blueprint-facts">
                            <span><i class="bi bi-calendar3" aria-hidden="true"></i><?= escapeOutput(habitDisplaySchedule($habit)); ?></span>
                            <span><i class="bi bi-fire" aria-hidden="true"></i><?= (int) $streak['current']; ?>-day rhythm</span>
                            <span><i class="bi bi-check-circle" aria-hidden="true"></i><?= (int) $habit['completion_count']; ?>/<?= (int) $habit['log_count']; ?> completed</span>
                        </div>
                        <div class="blueprint-card-actions">
                            <a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/edit.php?id=<?= (int) $habit['habit_id']; ?>"><i class="bi bi-pencil" aria-hidden="true"></i>Edit</a>
                            <form method="post" action="<?= BASE_URL; ?>/modules/habits/manage.php">
                                <?= csrfInput(); ?>
                                <input type="hidden" name="habit_id" value="<?= (int) $habit['habit_id']; ?>">
                                <input type="hidden" name="return_state" value="<?= escapeOutput($state); ?>">
                                <button class="sanctuary-button sanctuary-button-quiet" name="action" value="<?= (int) $habit['is_active'] ? 'archive' : 'restore'; ?>" type="submit">
                                    <i class="bi <?= (int) $habit['is_active'] ? 'bi-archive' : 'bi-arrow-counterclockwise'; ?>" aria-hidden="true"></i><?= (int) $habit['is_active'] ? 'Archive' : 'Restore'; ?>
                                </button>
                            </form>
                            <a class="sanctuary-link-danger" href="<?= BASE_URL; ?>/modules/habits/delete.php?id=<?= (int) $habit['habit_id']; ?>"><i class="bi bi-trash3" aria-hidden="true"></i>Delete</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
