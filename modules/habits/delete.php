<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/habit_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$habitId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$habitId) {
    setFlash('error', 'Invalid habit record.');
    header('Location: ' . BASE_URL . '/modules/habits/index.php');
    exit;
}

$habit = null;
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $habit = habitLoadForUser($connection, (int) $habitId, $userId);

    if (!$habit) {
        setFlash('error', 'Habit record was not found.');
        header('Location: ' . BASE_URL . '/modules/habits/index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Your session token expired. Please try again.');
            header('Location: ' . BASE_URL . '/modules/habits/delete.php?id=' . (int) $habitId);
            exit;
        }

        $stmt = $connection->prepare('DELETE FROM habit_records WHERE habit_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $habitId, $userId);
        $stmt->execute();

        setFlash('success', 'Habit record deleted successfully.');
        header('Location: ' . BASE_URL . '/modules/habits/index.php');
        exit;
    }
} catch (Throwable $exception) {
    $pageError = 'Habit deletion is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Delete Habit';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Delete Habit</h1>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php elseif ($habit): ?>
        <p class="muted">Confirm that you want to remove this habit record. This action cannot be undone.</p>

        <dl class="detail-list">
            <div>
                <dt>Habit</dt>
                <dd><?= escapeOutput($habit['habit_name']); ?></dd>
            </div>
            <div>
                <dt>Category</dt>
                <dd><?= escapeOutput($habit['category']); ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><?= escapeOutput(habitStatusOptions()[$habit['completion_status']] ?? $habit['completion_status']); ?></dd>
            </div>
            <div>
                <dt>Date</dt>
                <dd><?= escapeOutput($habit['habit_date']); ?></dd>
            </div>
        </dl>

        <form method="post" action="<?= BASE_URL; ?>/modules/habits/delete.php?id=<?= (int) $habitId; ?>">
            <?= csrfInput(); ?>
            <div class="button-row">
                <button class="button primary danger-primary" type="submit">Delete Habit</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/habits/index.php">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
