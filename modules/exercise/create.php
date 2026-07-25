<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/exercise_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$repeatId = filter_var($_GET['repeat'] ?? null, FILTER_VALIDATE_INT);
$data = exerciseDefaultFormData();
$errors = [];
$pageError = null;
$isRepeatPreset = false;

try {
    $connection = getDatabaseConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $repeatId) {
        $repeatExercise = exerciseLoadForUser($connection, (int) $repeatId, $userId);

        if ($repeatExercise) {
            $data = [
                'activity_type' => $repeatExercise['activity_type'],
                'duration_minutes' => (string) $repeatExercise['duration_minutes'],
                'calories_burned' => (string) $repeatExercise['calories_burned'],
                'exercise_date' => date('Y-m-d'),
                'custom_activity_type' => array_key_exists($repeatExercise['activity_type'], exerciseActivityOptions()) ? '' : $repeatExercise['activity_type'],
            ];
            $isRepeatPreset = true;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = exerciseDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, exerciseValidateData($data));

        if (!$errors) {
            $duration = (int) $data['duration_minutes'];
            $calories = (int) $data['calories_burned'];

            $existingStmt = $connection->prepare('SELECT exercise_id FROM exercise_records WHERE user_id = ? AND activity_type = ? AND exercise_date = ? LIMIT 1');
            $existingStmt->bind_param('iss', $userId, $data['activity_type'], $data['exercise_date']);
            $existingStmt->execute();
            $existingRecord = $existingStmt->get_result()->fetch_assoc();

            if ($existingRecord) {
                $existingExerciseId = (int) $existingRecord['exercise_id'];
                $stmt = $connection->prepare('UPDATE exercise_records SET duration_minutes = ?, calories_burned = ? WHERE exercise_id = ? AND user_id = ?');
                $stmt->bind_param(
                    'iiii',
                    $duration,
                    $calories,
                    $existingExerciseId,
                    $userId
                );
                $stmt->execute();

                setFlash('success', 'Today\'s exercise record updated successfully.');
            } else {
                $stmt = $connection->prepare('INSERT INTO exercise_records (user_id, activity_type, duration_minutes, calories_burned, exercise_date) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param(
                    'isiis',
                    $userId,
                    $data['activity_type'],
                    $duration,
                    $calories,
                    $data['exercise_date']
                );
                $stmt->execute();

                setFlash('success', 'Exercise record added successfully.');
            }

            header('Location: ' . BASE_URL . '/modules/exercise/index.php?view=workouts');
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Exercise creation is unavailable right now. Please check the database setup.';
}

$selectedActivityType = array_key_exists($data['activity_type'], exerciseActivityOptions()) ? $data['activity_type'] : 'Other';
$customActivityType = $selectedActivityType === 'Other' && $data['activity_type'] !== 'Other'
    ? $data['activity_type']
    : ($data['custom_activity_type'] ?? '');

$pageTitle = 'Add Exercise';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1><?= $isRepeatPreset ? 'Log Today\'s Exercise' : 'Add Exercise'; ?></h1>
    <p class="muted"><?= $isRepeatPreset ? 'Reuse this routine, adjust today\'s duration or calories, and save it with today\'s date.' : 'Save a workout session with activity, duration, calories, and date.'; ?></p>

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

    <form method="post" action="<?= BASE_URL; ?>/modules/exercise/create.php">
        <?= csrfInput(); ?>

        <label for="activity_type">Activity Type</label>
        <select id="activity_type" name="activity_type" required>
            <?php foreach (exerciseActivityOptions() as $value => $label): ?>
                <option value="<?= escapeOutput($value); ?>" <?= $selectedActivityType === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
            <?php endforeach; ?>
        </select>

        <div data-custom-activity-wrap <?= $selectedActivityType === 'Other' ? '' : 'hidden'; ?>>
            <label for="custom_activity_type">Exercise Name</label>
            <input id="custom_activity_type" name="custom_activity_type" type="text" maxlength="60" value="<?= escapeOutput($customActivityType); ?>" placeholder="Example: Boxing, Pilates, Futsal" <?= $selectedActivityType === 'Other' ? 'required' : ''; ?>>
        </div>

        <label for="duration_minutes">Duration Minutes</label>
        <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" step="1" value="<?= escapeOutput($data['duration_minutes']); ?>" required>

        <label for="calories_burned">Calories Burned</label>
        <input id="calories_burned" name="calories_burned" type="number" min="0" max="20000" step="1" value="<?= escapeOutput($data['calories_burned']); ?>" required>

        <label for="exercise_date">Exercise Date</label>
        <input id="exercise_date" name="exercise_date" type="date" value="<?= escapeOutput($data['exercise_date']); ?>" required>

        <div class="button-row">
            <button class="button primary" type="submit"><?= $isRepeatPreset ? 'Save Today' : 'Save Exercise'; ?></button>
            <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php?view=workouts">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
