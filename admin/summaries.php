<?php
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

requireAdmin();

$moduleSummaries = [];
$summaryError = null;

try {
    $connection = getDatabaseConnection();
    $moduleSummaries = [
        [
            'module' => 'Exercise Tracker',
            'records' => (int) $connection->query('SELECT COUNT(*) AS total FROM exercise_records')->fetch_assoc()['total'],
            'detail' => (int) $connection->query('SELECT COALESCE(SUM(duration_minutes), 0) AS total FROM exercise_records')->fetch_assoc()['total'] . ' total minutes',
        ],
        [
            'module' => 'Diary Journal',
            'records' => (int) $connection->query('SELECT COUNT(*) AS total FROM journal_entries')->fetch_assoc()['total'],
            'detail' => 'Mood entries across all students',
        ],
        [
            'module' => 'Money Tracker',
            'records' => (int) $connection->query('SELECT COUNT(*) AS total FROM money_transactions')->fetch_assoc()['total'],
            'detail' => 'RM ' . number_format((float) $connection->query("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END), 0) AS balance FROM money_transactions")->fetch_assoc()['balance'], 2) . ' combined balance',
        ],
        [
            'module' => 'Habit Tracker',
            'records' => (int) $connection->query('SELECT COUNT(*) AS total FROM habit_records')->fetch_assoc()['total'],
            'detail' => (int) $connection->query("SELECT COALESCE(SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS total FROM habit_records")->fetch_assoc()['total'] . ' completed habits',
        ],
    ];
} catch (Throwable $exception) {
    $summaryError = 'System summaries are unavailable right now.';
}

$pageTitle = 'System Summaries';
require __DIR__ . '/../includes/header.php';
?>

<section class="section-heading">
    <h1>System Summaries</h1>
    <p class="muted">Record counts and simple totals across all modules.</p>
</section>

<?php if ($summaryError): ?>
    <div class="alert alert-error"><?= escapeOutput($summaryError); ?></div>
<?php else: ?>
    <section class="panel table-panel">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Total Records</th>
                    <th>Summary Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($moduleSummaries as $summary): ?>
                    <tr>
                        <td><?= escapeOutput($summary['module']); ?></td>
                        <td><?= number_format($summary['records']); ?></td>
                        <td><?= escapeOutput($summary['detail']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
