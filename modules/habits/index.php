<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/habit_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$filters = habitFiltersFromRequest($_GET);
$currentQuery = habitReturnQuery($filters);
$records = [];
$summary = [
    'total' => 0,
    'completed' => 0,
    'pending' => 0,
    'missed' => 0,
    'percentage' => 0,
];
$bestStreak = ['days' => 0, 'habit_name' => 'No completed streak yet'];
$pageError = null;

try {
    $connection = getDatabaseConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $returnQuery = cleanInput((string) ($_POST['return_query'] ?? ''));

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Your session token expired. Please try again.');
        } else {
            $habitId = filter_var($_POST['habit_id'] ?? null, FILTER_VALIDATE_INT);
            $newStatus = cleanInput((string) ($_POST['completion_status'] ?? ''));

            if (!$habitId || !array_key_exists($newStatus, habitStatusOptions())) {
                setFlash('error', 'Invalid quick update request.');
            } else {
                $habit = habitLoadForUser($connection, (int) $habitId, $userId);

                if (!$habit) {
                    setFlash('error', 'Habit record was not found.');
                } else {
                    $stmt = $connection->prepare('UPDATE habit_records SET completion_status = ? WHERE habit_id = ? AND user_id = ?');
                    $stmt->bind_param('sii', $newStatus, $habitId, $userId);
                    $stmt->execute();
                    setFlash('success', 'Habit status updated to ' . habitStatusOptions()[$newStatus] . '.');
                }
            }
        }

        header('Location: ' . BASE_URL . '/modules/habits/index.php' . ($returnQuery !== '' ? '?' . $returnQuery : ''));
        exit;
    }

    $summaryStmt = $connection->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed, COALESCE(SUM(CASE WHEN completion_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending, COALESCE(SUM(CASE WHEN completion_status = 'missed' THEN 1 ELSE 0 END), 0) AS missed FROM habit_records WHERE user_id = ?");
    $summaryStmt->bind_param('i', $userId);
    $summaryStmt->execute();
    $summaryRow = $summaryStmt->get_result()->fetch_assoc();
    $summary['total'] = (int) $summaryRow['total'];
    $summary['completed'] = (int) $summaryRow['completed'];
    $summary['pending'] = (int) $summaryRow['pending'];
    $summary['missed'] = (int) $summaryRow['missed'];
    $summary['percentage'] = habitCompletionPercentage($summary['completed'], $summary['total']);
    $bestStreak = habitBestStreak($connection, $userId);

    $filterQuery = habitFilterQuery($filters, $userId);
    $sql = 'SELECT habit_id, habit_name, category, target_frequency, completion_status, priority, habit_date, notes FROM habit_records WHERE ' . $filterQuery['where'] . ' ORDER BY ' . habitOrderBy($filters['sort']);
    $stmt = $connection->prepare($sql);
    $params = $filterQuery['params'];
    habitBindParams($stmt, $filterQuery['types'], $params);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="habit-tracker-export.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Habit Name', 'Category', 'Frequency', 'Status', 'Priority', 'Date', 'Notes']);

        foreach ($records as $record) {
            fputcsv($output, [
                $record['habit_name'],
                $record['category'],
                $record['target_frequency'],
                $record['completion_status'],
                $record['priority'],
                $record['habit_date'],
                $record['notes'],
            ]);
        }

        fclose($output);
        exit;
    }
} catch (Throwable $exception) {
    $pageError = 'Habit records are unavailable right now. Please check the database setup.';
}

$activeFilterLabels = [];
if ($filters['search'] !== '') {
    $activeFilterLabels[] = 'Search: ' . $filters['search'];
}
if ($filters['status'] !== '') {
    $activeFilterLabels[] = 'Status: ' . (habitStatusOptions()[$filters['status']] ?? $filters['status']);
}
if ($filters['frequency'] !== '') {
    $activeFilterLabels[] = 'Frequency: ' . $filters['frequency'];
}
if ($filters['priority'] !== '') {
    $activeFilterLabels[] = 'Priority: ' . (habitPriorityOptions()[$filters['priority']] ?? $filters['priority']);
}
if ($filters['category'] !== '') {
    $activeFilterLabels[] = 'Category: ' . $filters['category'];
}
if ($filters['date_from'] !== '') {
    $activeFilterLabels[] = 'From: ' . $filters['date_from'];
}
if ($filters['date_to'] !== '') {
    $activeFilterLabels[] = 'To: ' . $filters['date_to'];
}
if ($filters['sort'] !== 'newest') {
    $activeFilterLabels[] = 'Sort: ' . (habitSortOptions()[$filters['sort']] ?? $filters['sort']);
}

$pageTitle = 'Habit Tracker';
require __DIR__ . '/../../includes/header.php';
?>

<section class="habit-hero">
    <div class="habit-hero-copy">
        <p class="eyebrow">Routine Studio</p>
        <h1>Habit Tracker</h1>
        <p class="hero-copy">A cleaner board for daily habits, progress, and consistency.</p>
        <div class="habit-hero-metrics" aria-label="Habit overview">
            <span><strong><?= number_format($summary['completed']); ?></strong> completed</span>
            <span><strong><?= number_format($summary['pending']); ?></strong> pending</span>
            <span><strong><?= number_format($summary['missed']); ?></strong> missed</span>
        </div>
    </div>
    <div class="habit-progress-ring" style="--completion: <?= (int) $summary['percentage']; ?>;" aria-label="Completion rate <?= (int) $summary['percentage']; ?> percent">
        <span><?= number_format($summary['percentage']); ?>%</span>
        <small>completion</small>
    </div>
</section>

<section class="habit-action-bar" aria-label="Habit actions">
    <div>
        <p class="summary-label">Best Streak</p>
        <strong><?= number_format($bestStreak['days']); ?> day<?= $bestStreak['days'] === 1 ? '' : 's'; ?></strong>
        <span class="muted"><?= escapeOutput($bestStreak['habit_name']); ?></span>
    </div>
    <div class="button-row compact-actions">
        <button class="button icon-button filter-button" type="button" data-filter-open aria-controls="habitFilters" aria-expanded="false">
            <img src="<?= BASE_URL; ?>/assets/img/filter-icon.png" alt="" aria-hidden="true">
            Filter
            <?php if ($activeFilterLabels): ?>
                <span class="filter-count"><?= count($activeFilterLabels); ?></span>
            <?php endif; ?>
        </button>
        <a class="button" href="<?= BASE_URL; ?>/modules/habits/index.php<?= $currentQuery !== '' ? '?' . escapeOutput($currentQuery . '&export=csv') : '?export=csv'; ?>">Export CSV</a>
        <a class="button primary" href="<?= BASE_URL; ?>/modules/habits/create.php">Add Habit</a>
    </div>
</section>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php else: ?>
    <div class="filter-backdrop" data-filter-backdrop hidden></div>
    <aside class="filter-drawer" id="habitFilters" data-filter-drawer aria-hidden="true" aria-label="Habit filters">
        <div class="filter-drawer-header">
            <div>
                <p class="summary-label">Filters</p>
                <h2>Tune your board</h2>
            </div>
            <button class="button small-button" type="button" data-filter-close>Close</button>
        </div>
        <form method="get" action="<?= BASE_URL; ?>/modules/habits/index.php" class="filter-form">
            <label for="search">Search</label>
            <input id="search" name="search" type="search" value="<?= escapeOutput($filters['search']); ?>" placeholder="Habit name or notes">

            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (habitStatusOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['status'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="frequency">Frequency</label>
            <select id="frequency" name="frequency">
                <option value="">All frequencies</option>
                <?php foreach (habitFrequencyOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['frequency'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="priority">Priority</label>
            <select id="priority" name="priority">
                <option value="">All priorities</option>
                <?php foreach (habitPriorityOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['priority'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="category">Category</label>
            <input id="category" name="category" type="text" value="<?= escapeOutput($filters['category']); ?>" placeholder="Study, Health, Finance">

            <label for="date_from">From</label>
            <input id="date_from" name="date_from" type="date" value="<?= escapeOutput($filters['date_from']); ?>">

            <label for="date_to">To</label>
            <input id="date_to" name="date_to" type="date" value="<?= escapeOutput($filters['date_to']); ?>">

            <label for="sort">Sort</label>
            <select id="sort" name="sort">
                <?php foreach (habitSortOptions() as $value => $label): ?>
                    <option value="<?= escapeOutput($value); ?>" <?= $filters['sort'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="filter-actions">
                <button class="button primary" type="submit">Apply Filters</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/habits/index.php">Reset</a>
            </div>
        </form>
    </aside>

    <section class="habit-board-header">
        <div>
            <p class="summary-label">Habit Board</p>
            <h2><?= number_format(count($records)); ?> routine<?= count($records) === 1 ? '' : 's'; ?> showing</h2>
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
            <h2>No habit records found</h2>
            <p class="muted">Add a habit or adjust the filters to review your routine progress.</p>
            <div class="button-row">
                <button class="button" type="button" data-filter-open>Open Filters</button>
                <a class="button primary" href="<?= BASE_URL; ?>/modules/habits/create.php">Add Habit</a>
            </div>
        </section>
    <?php else: ?>
        <section class="habit-card-grid" aria-label="Habit records">
            <?php foreach ($records as $record): ?>
                <article class="habit-card habit-card-<?= escapeOutput($record['completion_status']); ?>">
                    <div class="habit-card-topline">
                        <span class="habit-category"><?= escapeOutput($record['category']); ?></span>
                        <span class="status-pill status-<?= escapeOutput($record['completion_status']); ?>"><?= escapeOutput(habitStatusOptions()[$record['completion_status']] ?? $record['completion_status']); ?></span>
                    </div>
                    <h2><?= escapeOutput($record['habit_name']); ?></h2>
                    <div class="habit-meta-row">
                        <span><?= escapeOutput($record['target_frequency']); ?></span>
                        <span><?= escapeOutput($record['habit_date']); ?></span>
                        <span class="priority-pill priority-<?= escapeOutput($record['priority']); ?>"><?= escapeOutput(habitPriorityOptions()[$record['priority']] ?? $record['priority']); ?></span>
                    </div>
                    <p class="habit-note"><?= $record['notes'] !== '' && $record['notes'] !== null ? escapeOutput($record['notes']) : 'No notes added.'; ?></p>
                    <form class="habit-card-status-form" method="post" action="<?= BASE_URL; ?>/modules/habits/index.php<?= $currentQuery !== '' ? '?' . escapeOutput($currentQuery) : ''; ?>">
                        <?= csrfInput(); ?>
                        <input type="hidden" name="habit_id" value="<?= (int) $record['habit_id']; ?>">
                        <input type="hidden" name="return_query" value="<?= escapeOutput($currentQuery); ?>">
                        <label for="quick_status_<?= (int) $record['habit_id']; ?>">Quick status</label>
                        <div>
                            <select id="quick_status_<?= (int) $record['habit_id']; ?>" name="completion_status" aria-label="Quick status for <?= escapeOutput($record['habit_name']); ?>">
                                <?php foreach (habitStatusOptions() as $value => $label): ?>
                                    <option value="<?= escapeOutput($value); ?>" <?= $record['completion_status'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button small-button" type="submit">Update</button>
                        </div>
                    </form>
                    <div class="habit-card-actions">
                        <a class="button small-button" href="<?= BASE_URL; ?>/modules/habits/edit.php?id=<?= (int) $record['habit_id']; ?>">Edit</a>
                        <a class="button small-button danger-button" href="<?= BASE_URL; ?>/modules/habits/delete.php?id=<?= (int) $record['habit_id']; ?>">Delete</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
