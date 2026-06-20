<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';

requireLogin();

$summary = [
    'exercise_count' => 0,
    'exercise_minutes' => 0,
    'exercise_calories' => 0,
    'journal_count' => 0,
    'latest_mood' => 'No entries',
    'income_total' => 0.00,
    'expense_total' => 0.00,
    'money_balance' => 0.00,
    'habit_count' => 0,
    'habit_completed' => 0,
];
$dashboardError = null;
$userId = (int) $_SESSION['user_id'];

try {
    $connection = getDatabaseConnection();

    $stmt = $connection->prepare('SELECT COUNT(*) AS record_count, COALESCE(SUM(duration_minutes), 0) AS total_minutes, COALESCE(SUM(calories_burned), 0) AS total_calories FROM exercise_records WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $exercise = $stmt->get_result()->fetch_assoc();
    $summary['exercise_count'] = (int) $exercise['record_count'];
    $summary['exercise_minutes'] = (int) $exercise['total_minutes'];
    $summary['exercise_calories'] = (int) $exercise['total_calories'];

    $stmt = $connection->prepare('SELECT COUNT(*) AS record_count FROM journal_entries WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $summary['journal_count'] = (int) $stmt->get_result()->fetch_assoc()['record_count'];

    $stmt = $connection->prepare('SELECT mood_status FROM journal_entries WHERE user_id = ? ORDER BY entry_date DESC, journal_id DESC LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $latestMood = $stmt->get_result()->fetch_assoc();
    if ($latestMood) {
        $summary['latest_mood'] = $latestMood['mood_status'];
    }

    $stmt = $connection->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income_total, COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expense_total FROM money_transactions WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $money = $stmt->get_result()->fetch_assoc();
    $summary['income_total'] = (float) $money['income_total'];
    $summary['expense_total'] = (float) $money['expense_total'];
    $summary['money_balance'] = $summary['income_total'] - $summary['expense_total'];

    $stmt = $connection->prepare("SELECT COUNT(*) AS record_count, COALESCE(SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_count FROM habit_records WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $habit = $stmt->get_result()->fetch_assoc();
    $summary['habit_count'] = (int) $habit['record_count'];
    $summary['habit_completed'] = (int) $habit['completed_count'];
} catch (Throwable $exception) {
    $dashboardError = 'Dashboard summaries are unavailable right now.';
}

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<section class="section-heading">
    <h1>Student Dashboard</h1>
    <p class="muted">Welcome, <?= escapeOutput(currentUserName()); ?>.</p>
    <p class="muted">Your routine summary across all modules.</p>
</section>

<?php if ($dashboardError): ?>
    <div class="alert alert-error"><?= escapeOutput($dashboardError); ?></div>
<?php endif; ?>

<section class="summary-grid" aria-label="Student summaries">
    <article class="summary-card">
        <span class="summary-label">Exercise Records</span>
        <strong><?= number_format($summary['exercise_count']); ?></strong>
        <p><?= number_format($summary['exercise_minutes']); ?> minutes, <?= number_format($summary['exercise_calories']); ?> calories</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Journal Entries</span>
        <strong><?= number_format($summary['journal_count']); ?></strong>
        <p>Latest mood: <?= escapeOutput($summary['latest_mood']); ?></p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Money Balance</span>
        <strong>RM <?= number_format($summary['money_balance'], 2); ?></strong>
        <p>Income RM <?= number_format($summary['income_total'], 2); ?>, expenses RM <?= number_format($summary['expense_total'], 2); ?></p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Habits Completed</span>
        <strong><?= number_format($summary['habit_completed']); ?> / <?= number_format($summary['habit_count']); ?></strong>
        <p>Total habit records tracked</p>
    </article>
</section>

<section class="module-grid dashboard-section">
    <a class="module-card" href="<?= BASE_URL; ?>/modules/exercise/index.php">
        <h2>Exercise Tracker</h2>
        <p>Manage workout records, duration, calories, and exercise dates.</p>
    </a>
    <a class="module-card" href="<?= BASE_URL; ?>/modules/journal/index.php">
        <h2>Diary Journal</h2>
        <p>Record daily thoughts, moods, journal titles, and entry dates.</p>
    </a>
    <a class="module-card" href="<?= BASE_URL; ?>/modules/money/index.php">
        <h2>Money Tracker</h2>
        <p>Track income, expenses, categories, descriptions, and balance.</p>
    </a>
    <a class="module-card" href="<?= BASE_URL; ?>/modules/habits/index.php">
        <h2>Habit Tracker</h2>
        <p>Monitor habit targets, completion status, and habit dates.</p>
    </a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
