<?php
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

requireAdmin();

$summary = [
    'users' => 0,
    'students' => 0,
    'admins' => 0,
    'exercise' => 0,
    'journal' => 0,
    'money' => 0,
    'habits' => 0,
    'habit_completion' => 0,
];
$dashboardError = null;

try {
    $connection = getDatabaseConnection();
    $userCounts = $connection->query("SELECT COUNT(*) AS total_users, SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) AS total_students, SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS total_admins FROM users")->fetch_assoc();
    $summary['users'] = (int) $userCounts['total_users'];
    $summary['students'] = (int) $userCounts['total_students'];
    $summary['admins'] = (int) $userCounts['total_admins'];
    $summary['exercise'] = (int) $connection->query('SELECT COUNT(*) AS total FROM exercise_records')->fetch_assoc()['total'];
    $summary['journal'] = (int) $connection->query('SELECT COUNT(*) AS total FROM journal_entries')->fetch_assoc()['total'];
    $summary['money'] = (int) $connection->query('SELECT COUNT(*) AS total FROM money_transactions')->fetch_assoc()['total'];
    $summary['habits'] = (int) $connection->query('SELECT COUNT(*) AS total FROM habits WHERE is_active = 1')->fetch_assoc()['total'];
    $habitTotals = $connection->query("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed FROM habit_logs WHERE deleted_at IS NULL AND scheduled_date BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)")->fetch_assoc();
    $summary['habit_completion'] = (int) $habitTotals['total'] ? (int) round(((int) $habitTotals['completed'] / (int) $habitTotals['total']) * 100) : 0;
} catch (Throwable $exception) {
    $dashboardError = 'Admin summaries are unavailable right now.';
}

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="section-heading">
    <h1>Admin Dashboard</h1>
    <p class="muted">Welcome, <?= escapeOutput(currentUserName()); ?>.</p>
    <p class="muted">System-wide user and module summaries.</p>
</section>

<?php if ($dashboardError): ?>
    <div class="alert alert-error"><?= escapeOutput($dashboardError); ?></div>
<?php endif; ?>

<section class="summary-grid" aria-label="Admin summaries">
    <article class="summary-card">
        <span class="summary-label">Registered Users</span>
        <strong><?= number_format($summary['users']); ?></strong>
        <p><?= number_format($summary['students']); ?> students, <?= number_format($summary['admins']); ?> admins</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Exercise Records</span>
        <strong><?= number_format($summary['exercise']); ?></strong>
        <p>Total workout records in the system</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Journal Entries</span>
        <strong><?= number_format($summary['journal']); ?></strong>
        <p>Total reflection records in the system</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Money Transactions</span>
        <strong><?= number_format($summary['money']); ?></strong>
        <p>Total finance records in the system</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Active Habit Blueprints</span>
        <strong><?= number_format($summary['habits']); ?></strong>
        <p><?= number_format($summary['habit_completion']); ?>% of scheduled quests completed this week</p>
    </article>
</section>

<section class="module-grid dashboard-section">
    <a class="module-card" href="<?= BASE_URL; ?>/admin/users.php">
        <h2>Registered Users</h2>
        <p>View student and admin accounts.</p>
    </a>
    <a class="module-card" href="<?= BASE_URL; ?>/admin/summaries.php">
        <h2>System Summaries</h2>
        <p>View total records across all modules.</p>
    </a>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
