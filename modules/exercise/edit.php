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

$data = exerciseDefaultFormData();
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $exercise = exerciseLoadForUser($connection, (int) $exerciseId, $userId);

    if (!$exercise) {
        setFlash('error', 'Exercise record was not found.');
        header('Location: ' . BASE_URL . '/modules/exercise/index.php');
        exit;
    }

    $data = array_merge($data, [
        'activity_type' => $exercise['activity_type'],
        'duration_minutes' => (string) $exercise['duration_minutes'],
        'calories_burned' => (string) $exercise['calories_burned'],
        'exercise_date' => $exercise['exercise_date'],
        'notes' => $exercise['notes'] ?? '',
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = exerciseDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, exerciseValidateData($data));

        if (!$errors) {
            $duration = (int) $data['duration_minutes'];
            $calories = (int) $data['calories_burned'];
            $stmt = $connection->prepare('UPDATE exercise_records SET activity_type = ?, duration_minutes = ?, calories_burned = ?, exercise_date = ?, notes = ? WHERE exercise_id = ? AND user_id = ?');
            $stmt->bind_param(
                'siissii',
                $data['activity_type'],
                $duration,
                $calories,
                $data['exercise_date'],
                $data['notes'],
                $exerciseId,
                $userId
            );
            $stmt->execute();

            setFlash('success', 'Exercise record updated successfully.');
            header('Location: ' . BASE_URL . '/modules/exercise/index.php');
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Exercise editing is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Edit Exercise';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Edit Exercise</h1>
    <p class="muted">Update your workout details while keeping this record linked to your account.</p>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= escapeOutput($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL; ?>/modules/exercise/edit.php?id=<?= (int) $exerciseId; ?>">
        <?= csrfInput(); ?>

        <label for="activity_type">Activity Type</label>
        <select id="activity_type" name="activity_type" required>
            <?php foreach (exerciseActivityOptions() as $value => $label): ?>
                <option value="<?= escapeOutput($value); ?>" <?= $data['activity_type'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="duration_minutes">Duration Minutes</label>
        <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" step="1" value="<?= escapeOutput($data['duration_minutes']); ?>" required>

        <label for="calories_burned">Calories Burned</label>
        <input id="calories_burned" name="calories_burned" type="number" min="0" max="20000" step="1" value="<?= escapeOutput($data['calories_burned']); ?>" required>

        <label for="exercise_date">Exercise Date</label>
        <input id="exercise_date" name="exercise_date" type="date" value="<?= escapeOutput($data['exercise_date']); ?>" required>

        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" maxlength="255"><?= escapeOutput($data['notes']); ?></textarea>

        <div class="button-row">
            <button class="button primary" type="submit">Save Changes</button>
            <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
