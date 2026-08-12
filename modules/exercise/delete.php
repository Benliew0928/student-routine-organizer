<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/exercise_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$exerciseId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$exerciseId) {
    setFlash('error', 'Invalid exercise record.');
    header('Location: ' . BASE_URL . '/modules/exercise/index.php');
    exit;
}

$exercise = null;
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $exercise = exerciseLoadForUser($connection, (int) $exerciseId, $userId);

    if (!$exercise) {
        setFlash('error', 'Exercise record was not found.');
        header('Location: ' . BASE_URL . '/modules/exercise/index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Your session token expired. Please try again.');
            header('Location: ' . BASE_URL . '/modules/exercise/delete.php?id=' . (int) $exerciseId);
            exit;
        }

        $stmt = $connection->prepare('DELETE FROM exercise_records WHERE exercise_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $exerciseId, $userId);
        $stmt->execute();

        if (!exerciseDeleteStoredPhoto($exercise['photo_filename'] ?? null)) {
            logApplicationException(new RuntimeException('Could not remove deleted exercise photo.'), 'exercise photo delete');
        }

        setFlash('success', 'Exercise record deleted successfully.');
        header('Location: ' . BASE_URL . '/modules/exercise/index.php');
        exit;
    }
} catch (Throwable $exception) {
    logApplicationException($exception, 'exercise delete');
    $pageError = 'Exercise deletion is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Delete Exercise';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Delete Exercise</h1>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php elseif ($exercise): ?>
        <p class="muted">Confirm that you want to remove this exercise record. This action cannot be undone.</p>

        <dl class="detail-list">
            <div>
                <dt>Activity</dt>
                <dd><?= escapeOutput($exercise['activity_type']); ?></dd>
            </div>
            <div>
                <dt>Duration</dt>
                <dd><?= number_format((int) $exercise['duration_minutes']); ?> minutes</dd>
            </div>
            <div>
                <dt>Calories</dt>
                <dd><?= number_format((int) $exercise['calories_burned']); ?> calories</dd>
            </div>
            <div>
                <dt>Date</dt>
                <dd><?= escapeOutput($exercise['exercise_date']); ?></dd>
            </div>
            <?php if (exerciseHasPhoto($exercise)): ?>
                <div>
                    <dt>Photo</dt>
                    <dd>Attached exercise photo</dd>
                </div>
            <?php endif; ?>
        </dl>

        <form method="post" action="<?= BASE_URL; ?>/modules/exercise/delete.php?id=<?= (int) $exerciseId; ?>">
            <?= csrfInput(); ?>
            <div class="button-row">
                <button class="button primary danger-primary" type="submit">Delete Exercise</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
