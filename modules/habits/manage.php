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
if (!array_key_exists($realm, habitRealmOptions())) { $realm = ''; }
if (!in_array($state, ['active', 'archived', 'all'], true)) { $state = 'active'; }
$records = []; $pageError = null;

try {
    $connection = getDatabaseConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $habitId = filter_var($_POST['habit_id'] ?? null, FILTER_VALIDATE_INT);
        $action = cleanInput((string) ($_POST['action'] ?? ''));
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { setFlash('error', 'Your session token expired. Please try again.'); }
        elseif (!$habitId || !in_array($action, ['archive', 'restore'], true) || !habitLoadForUser($connection, (int) $habitId, $userId)) { setFlash('error', 'That quest blueprint could not be updated.'); }
        else { $active = $action === 'restore' ? 1 : 0; $stmt = $connection->prepare('UPDATE habits SET is_active = ? WHERE habit_id = ? AND user_id = ?'); $stmt->bind_param('iii', $active, $habitId, $userId); $stmt->execute(); setFlash('success', $active ? 'Quest restored to the sanctuary.' : 'Quest archived. Its trail is safely preserved.'); }
        header('Location: ' . BASE_URL . '/modules/habits/manage.php'); exit;
    }
    $where = ['h.user_id = ?']; $types = 'i'; $params = [$userId];
    if ($search !== '') { $where[] = 'h.habit_name LIKE ?'; $types .= 's'; $params[] = '%' . $search . '%'; }
    if ($realm !== '') { $where[] = 'h.realm = ?'; $types .= 's'; $params[] = $realm; }
    if ($state !== 'all') { $where[] = 'h.is_active = ?'; $types .= 'i'; $params[] = $state === 'active' ? 1 : 0; }
    $sql = "SELECT h.*, COUNT(l.log_id) AS log_count, COALESCE(SUM(CASE WHEN l.completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS completion_count FROM habits h LEFT JOIN habit_logs l ON l.habit_id = h.habit_id AND l.deleted_at IS NULL WHERE " . implode(' AND ', $where) . ' GROUP BY h.habit_id ORDER BY h.is_active DESC, h.updated_at DESC, h.habit_name';
    $stmt = $connection->prepare($sql); $refs = []; foreach ($params as $key => &$value) { $refs[$key] = &$value; } $stmt->bind_param($types, ...$refs); $stmt->execute(); $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $streaks = habitStreaks($connection, $userId);
} catch (Throwable $exception) { $pageError = 'Quest blueprints are unavailable right now. Please check the database setup.'; $streaks = []; }
$pageTitle = 'Manage Quest Blueprints'; require __DIR__ . '/../../includes/header.php';
?>
<section class="manage-page" aria-labelledby="manageTitle"><header class="manage-header"><div><a class="sanctuary-back-link" href="<?= BASE_URL; ?>/modules/habits/index.php">← Back to sanctuary</a><p class="sanctuary-kicker">Blueprint library</p><h1 id="manageTitle">The routines behind your rhythm.</h1><p>Design, pause, restore, and remove reusable habits without disturbing the rest of your sanctuary.</p></div><a class="sanctuary-button sanctuary-button-primary" href="<?= BASE_URL; ?>/modules/habits/create.php">+ Plant a quest</a></header>
<?php if ($pageError): ?><div class="alert alert-error"><?= escapeOutput($pageError); ?></div><?php else: ?>
<form method="get" class="manage-filter"><label class="sr-only" for="search">Search blueprints</label><input id="search" name="search" type="search" value="<?= escapeOutput($search); ?>" placeholder="Search a routine"><select name="realm" aria-label="Filter by realm"><option value="">All realms</option><?php foreach (habitRealmOptions() as $value => $meta): ?><option value="<?= escapeOutput($value); ?>" <?= $realm === $value ? 'selected' : ''; ?>><?= escapeOutput($meta['label']); ?></option><?php endforeach; ?></select><select name="state" aria-label="Filter by state"><option value="active" <?= $state === 'active' ? 'selected' : ''; ?>>Active</option><option value="archived" <?= $state === 'archived' ? 'selected' : ''; ?>>Archived</option><option value="all" <?= $state === 'all' ? 'selected' : ''; ?>>All blueprints</option></select><button class="sanctuary-button sanctuary-button-quiet" type="submit">Filter</button></form>
<?php if (!$records): ?><section class="sanctuary-empty compact-empty"><p>No blueprints match this view.</p><a class="sanctuary-text-link" href="<?= BASE_URL; ?>/modules/habits/create.php">Plant a new quest</a></section><?php else: ?><div class="blueprint-library"><?php foreach ($records as $habit): $meta = habitRealmOptions()[$habit['realm']]; $streak = $streaks[(int) $habit['habit_id']] ?? ['current' => 0, 'best' => 0]; ?><article class="blueprint-card <?= (int) $habit['is_active'] === 1 ? '' : 'is-archived'; ?>"><div class="blueprint-card-top"><span class="blueprint-realm realm-<?= escapeOutput($habit['realm']); ?>"><?= escapeOutput($meta['symbol']); ?> <?= escapeOutput($meta['label']); ?></span><span class="blueprint-state"><?= (int) $habit['is_active'] ? 'Active' : 'Archived'; ?></span></div><h2><?= escapeOutput($habit['habit_name']); ?></h2><p><?= $habit['motivation'] ? escapeOutput($habit['motivation']) : 'A quiet promise in your ' . escapeOutput($meta['label']) . '.'; ?></p><div class="blueprint-facts"><span><?= escapeOutput(habitDisplaySchedule($habit)); ?></span><span><?= (int) $streak['current']; ?>-day rhythm</span><span><?= (int) $habit['completion_count']; ?>/<?= (int) $habit['log_count']; ?> completed</span></div><div class="blueprint-card-actions"><a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/edit.php?id=<?= (int) $habit['habit_id']; ?>">Edit</a><form method="post" action="<?= BASE_URL; ?>/modules/habits/manage.php"><?= csrfInput(); ?><input type="hidden" name="habit_id" value="<?= (int) $habit['habit_id']; ?>"><button class="sanctuary-button sanctuary-button-quiet" name="action" value="<?= (int) $habit['is_active'] ? 'archive' : 'restore'; ?>" type="submit"><?= (int) $habit['is_active'] ? 'Archive' : 'Restore'; ?></button></form><a class="sanctuary-link-danger" href="<?= BASE_URL; ?>/modules/habits/delete.php?id=<?= (int) $habit['habit_id']; ?>">Delete</a></div></article><?php endforeach; ?></div><?php endif; ?><?php endif; ?></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
