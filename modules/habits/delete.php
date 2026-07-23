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
$habit = null; $pageError = null;
try {
    $connection = getDatabaseConnection();
    $habit = habitLoadForUser($connection, (int) $habitId, $userId);
    if (!$habit) { setFlash('error', 'Quest blueprint not found.'); header('Location: ' . BASE_URL . '/modules/habits/manage.php'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { setFlash('error', 'Your session token expired.'); header('Location: ' . BASE_URL . '/modules/habits/delete.php?id=' . (int) $habitId); exit; }
        $stmt = $connection->prepare('DELETE FROM habits WHERE habit_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $habitId, $userId); $stmt->execute();
        setFlash('success', 'Quest blueprint and its Momentum Trail entries were deleted.'); header('Location: ' . BASE_URL . '/modules/habits/manage.php'); exit;
    }
} catch (Throwable $exception) { $pageError = 'Quest deletion is unavailable right now. Please check the database setup.'; }
$pageTitle = 'Delete Quest'; require __DIR__ . '/../../includes/header.php';
?>
<section class="confirmation-page"><div class="confirmation-orb danger" aria-hidden="true">×</div><p class="sanctuary-kicker">Permanent removal</p><h1>Remove this quest blueprint?</h1><?php if ($pageError): ?><div class="alert alert-error"><?= escapeOutput($pageError); ?></div><?php elseif ($habit): ?><p>Removing <strong><?= escapeOutput($habit['habit_name']); ?></strong> also removes every dated entry attached to it. Archive it instead if you may want to return to it.</p><dl class="confirmation-details"><div><dt>Realm</dt><dd><?= escapeOutput(habitRealmOptions()[$habit['realm']]['label']); ?></dd></div><div><dt>Schedule</dt><dd><?= escapeOutput(habitDisplaySchedule($habit)); ?></dd></div><div><dt>Created</dt><dd><?= escapeOutput(date('j M Y', strtotime($habit['created_at']))); ?></dd></div></dl><form method="post" action="<?= BASE_URL; ?>/modules/habits/delete.php?id=<?= (int) $habitId; ?>"><?= csrfInput(); ?><div class="blueprint-actions"><button class="sanctuary-button sanctuary-button-danger" type="submit">Delete permanently</button><a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/manage.php">Keep it</a></div></form><?php endif; ?></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
