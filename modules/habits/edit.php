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

$data = habitDefaultFormData();
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $habit = habitLoadForUser($connection, (int) $habitId, $userId);

    if (!$habit) {
        setFlash('error', 'Habit record was not found.');
        header('Location: ' . BASE_URL . '/modules/habits/index.php');
        exit;
    }

    $data = array_merge($data, [
        'habit_name' => $habit['habit_name'],
        'category' => $habit['category'],
        'target_frequency' => $habit['target_frequency'],
        'completion_status' => $habit['completion_status'],
        'priority' => $habit['priority'],
        'habit_date' => $habit['habit_date'],
        'notes' => $habit['notes'] ?? '',
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = habitDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, habitValidateData($connection, $userId, $data, (int) $habitId));

        if (!$errors) {
            $stmt = $connection->prepare('UPDATE habit_records SET habit_name = ?, category = ?, target_frequency = ?, completion_status = ?, priority = ?, habit_date = ?, notes = ? WHERE habit_id = ? AND user_id = ?');
            $stmt->bind_param(
                'sssssssii',
                $data['habit_name'],
                $data['category'],
                $data['target_frequency'],
                $data['completion_status'],
                $data['priority'],
                $data['habit_date'],
                $data['notes'],
                $habitId,
                $userId
            );
            $stmt->execute();

            setFlash('success', 'Habit record updated successfully.');
            header('Location: ' . BASE_URL . '/modules/habits/index.php');
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Habit editing is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Edit Habit';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Edit Habit</h1>
    <p class="muted">Update the habit details while keeping this record linked to your account.</p>

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

    <form method="post" action="<?= BASE_URL; ?>/modules/habits/edit.php?id=<?= (int) $habitId; ?>">
        <?= csrfInput(); ?>

        <label for="habit_name">Habit Name</label>
        <input id="habit_name" name="habit_name" type="text" maxlength="100" value="<?= escapeOutput($data['habit_name']); ?>" required>

        <label for="category">Category</label>
        <input id="category" name="category" type="text" maxlength="60" value="<?= escapeOutput($data['category']); ?>" required>

        <label for="target_frequency">Target Frequency</label>
        <select id="target_frequency" name="target_frequency" required>
            <?php foreach (habitFrequencyOptions() as $value => $label): ?>
                <option value="<?= escapeOutput($value); ?>" <?= $data['target_frequency'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="completion_status">Completion Status</label>
        <select id="completion_status" name="completion_status" required>
            <?php foreach (habitStatusOptions() as $value => $label): ?>
                <option value="<?= escapeOutput($value); ?>" <?= $data['completion_status'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="priority">Priority</label>
        <select id="priority" name="priority" required>
            <?php foreach (habitPriorityOptions() as $value => $label): ?>
                <option value="<?= escapeOutput($value); ?>" <?= $data['priority'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="habit_date">Habit Date</label>
        <input id="habit_date" name="habit_date" type="date" value="<?= escapeOutput($data['habit_date']); ?>" required>

        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" maxlength="255"><?= escapeOutput($data['notes']); ?></textarea>

        <div class="button-row">
            <button class="button primary" type="submit">Save Changes</button>
            <a class="button" href="<?= BASE_URL; ?>/modules/habits/index.php">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
