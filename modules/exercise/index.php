<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/exercise_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$filters = exerciseFiltersFromRequest($_GET);
$currentQuery = exerciseReturnQuery($filters);
$records = [];
$summary = [
    'total' => 0,
    'minutes' => 0,
    'calories' => 0,
    'average_duration' => 0,
    'week_minutes' => 0,
    'week_calories' => 0,
];
$mostFrequentActivity = 'No activity yet';
$pageError = null;

try {
    $connection = getDatabaseConnection();

    $summaryStmt = $connection->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(duration_minutes), 0) AS minutes, COALESCE(SUM(calories_burned), 0) AS calories, COALESCE(AVG(duration_minutes), 0) AS average_duration FROM exercise_records WHERE user_id = ?');
    $summaryStmt->bind_param('i', $userId);
    $summaryStmt->execute();
    $summaryRow = $summaryStmt->get_result()->fetch_assoc();
    $summary['total'] = (int) $summaryRow['total'];
    $summary['minutes'] = (int) $summaryRow['minutes'];
    $summary['calories'] = (int) $summaryRow['calories'];
    $summary['average_duration'] = (int) round((float) $summaryRow['average_duration']);

    $weekStmt = $connection->prepare('SELECT COALESCE(SUM(duration_minutes), 0) AS minutes, COALESCE(SUM(calories_burned), 0) AS calories FROM exercise_records WHERE user_id = ? AND exercise_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)');
    $weekStmt->bind_param('i', $userId);
    $weekStmt->execute();
    $weekRow = $weekStmt->get_result()->fetch_assoc();
    $summary['week_minutes'] = (int) $weekRow['minutes'];
    $summary['week_calories'] = (int) $weekRow['calories'];
    $mostFrequentActivity = exerciseMostFrequentActivity($connection, $userId);

    $filterQuery = exerciseFilterQuery($filters, $userId);
    $sql = 'SELECT exercise_id, activity_type, duration_minutes, calories_burned, exercise_date, notes FROM exercise_records WHERE ' . $filterQuery['where'] . ' ORDER BY ' . exerciseOrderBy($filters['sort']);
    $stmt = $connection->prepare($sql);
    $params = $filterQuery['params'];
    exerciseBindParams($stmt, $filterQuery['types'], $params);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="exercise-tracker-export.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Activity Type', 'Duration Minutes', 'Calories Burned', 'Exercise Date', 'Notes']);

        foreach ($records as $record) {
            fputcsv($output, [
                $record['activity_type'],
                $record['duration_minutes'],
                $record['calories_burned'],
                $record['exercise_date'],
                $record['notes'],
            ]);
        }

        fclose($output);
        exit;
    }
} catch (Throwable $exception) {
    $pageError = 'Exercise records are unavailable right now. Please check the database setup.';
}

$activeFilterLabels = [];
if ($filters['search'] !== '') {
    $activeFilterLabels[] = 'Search: ' . $filters['search'];
}
if ($filters['activity_type'] !== '') {
    $activeFilterLabels[] = 'Activity: ' . $filters['activity_type'];
}
if ($filters['date_from'] !== '') {
    $activeFilterLabels[] = 'From: ' . $filters['date_from'];
}
if ($filters['date_to'] !== '') {
    $activeFilterLabels[] = 'To: ' . $filters['date_to'];
}
if ($filters['sort'] !== 'newest') {
    $activeFilterLabels[] = 'Sort: ' . (exerciseSortOptions()[$filters['sort']] ?? $filters['sort']);
}

$pageTitle = 'Exercise Tracker';
require __DIR__ . '/../../includes/header.php';
?>

<section class="exercise-hero">
    <div class="exercise-hero-copy">
        <p class="eyebrow">Fitness Log</p>
        <h1>Exercise Tracker</h1>
        <p class="hero-copy">Record workouts, review effort, and keep your health routine visible.</p>
        <div class="exercise-hero-metrics" aria-label="Exercise overview">
            <span><strong><?= number_format($summary['total']); ?></strong> sessions</span>
            <span><strong><?= number_format($summary['minutes']); ?></strong> minutes</span>
            <span><strong><?= number_format($summary['calories']); ?></strong> calories</span>
        </div>
    </div>
    <div class="exercise-week-card" aria-label="Last 7 days progress">
        <span class="summary-label">Last 7 Days</span>
        <strong><?= number_format($summary['week_minutes']); ?> min</strong>
        <small><?= number_format($summary['week_calories']); ?> calories</small>
    </div>
</section>

<section class="exercise-action-bar" aria-label="Exercise actions">
    <div>
        <p class="summary-label">Most Frequent Activity</p>
        <strong><?= escapeOutput($mostFrequentActivity); ?></strong>
        <span class="muted">Average duration: <?= number_format($summary['average_duration']); ?> minutes</span>
    </div>
    <div class="button-row compact-actions">
        <button class="button icon-button filter-button" type="button" data-filter-open aria-controls="exerciseFilters" aria-expanded="false">
            <img src="<?= BASE_URL; ?>/assets/img/filter-icon.png" alt="" aria-hidden="true">
            Filter
            <?php if ($activeFilterLabels): ?>
                <span class="filter-count"><?= count($activeFilterLabels); ?></span>
            <?php endif; ?>
        </button>
        <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php<?= $currentQuery !== '' ? '?' . escapeOutput($currentQuery . '&export=csv') : '?export=csv'; ?>">Export CSV</a>
        <a class="button primary" href="<?= BASE_URL; ?>/modules/exercise/create.php">Add Exercise</a>
    </div>
</section>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php else: ?>
    <div class="filter-backdrop" data-filter-backdrop hidden></div>
    <aside class="filter-drawer" id="exerciseFilters" data-filter-drawer aria-hidden="true" aria-label="Exercise filters">
        <div class="filter-drawer-header">
            <div>
                <p class="summary-label">Filters</p>
                <h2>Tune your workouts</h2>
            </div>
            <button class="button small-button" type="button" data-filter-close>Close</button>
        </div>
        <form method="get" action="<?= BASE_URL; ?>/modules/exercise/index.php" class="filter-form">
            <label for="search">Search</label>
            <input id="search" name="search" type="search" value="<?= escapeOutput($filters['search']); ?>" placeholder="Activity or notes">

            <label for="activity_type">Activity Type</label>
            <select id="activity_type" name="activity_type">
                <option value="">All activities</option>
                <?php foreach (exerciseActivityOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['activity_type'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="date_from">From</label>
            <input id="date_from" name="date_from" type="date" value="<?= escapeOutput($filters['date_from']); ?>">

            <label for="date_to">To</label>
            <input id="date_to" name="date_to" type="date" value="<?= escapeOutput($filters['date_to']); ?>">

            <label for="sort">Sort</label>
            <select id="sort" name="sort">
                <?php foreach (exerciseSortOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['sort'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="filter-actions">
                <button class="button primary" type="submit">Apply Filters</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php">Reset</a>
            </div>
        </form>
    </aside>

    <section class="exercise-board-header">
        <div>
            <p class="summary-label">Workout Board</p>
            <h2><?= number_format(count($records)); ?> session<?= count($records) === 1 ? '' : 's'; ?> showing</h2>
        </div>
        <?php if ($activeFilterLabels): ?>
            <div class="active-filter-list" aria-label="Active filters">
                <?php foreach ($activeFilterLabels as $label): ?>
                    <span><?= escapeOutput($label); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$records): ?>
        <section class="panel empty-state">
            <h2>No exercise records found</h2>
            <p class="muted">Add a workout session or adjust the filters to review your exercise progress.</p>
            <div class="button-row">
                <button class="button" type="button" data-filter-open>Open Filters</button>
                <a class="button primary" href="<?= BASE_URL; ?>/modules/exercise/create.php">Add Exercise</a>
            </div>
        </section>
    <?php else: ?>
        <section class="exercise-card-grid" aria-label="Exercise records">
            <?php foreach ($records as $record): ?>
                <article class="exercise-card">
                    <div class="exercise-card-topline">
                        <span class="exercise-activity"><?= escapeOutput($record['activity_type']); ?></span>
                        <span class="status-pill"><?= escapeOutput($record['exercise_date']); ?></span>
                    </div>
                    <div class="exercise-stat-row">
                        <span><strong><?= number_format((int) $record['duration_minutes']); ?></strong> min</span>
                        <span><strong><?= number_format((int) $record['calories_burned']); ?></strong> cal</span>
                    </div>
                    <p class="exercise-note"><?= $record['notes'] !== '' && $record['notes'] !== null ? escapeOutput($record['notes']) : 'No notes added.'; ?></p>
                    <div class="exercise-card-actions">
                        <a class="button small-button" href="<?= BASE_URL; ?>/modules/exercise/edit.php?id=<?= (int) $record['exercise_id']; ?>">Edit</a>
                        <a class="button small-button danger-button" href="<?= BASE_URL; ?>/modules/exercise/delete.php?id=<?= (int) $record['exercise_id']; ?>">Delete</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
