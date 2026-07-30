<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/exercise_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$filters = exerciseFiltersFromRequest($_GET);
$showWorkoutHistory = ($_GET['history'] ?? '') === '1';
$currentQuery = exerciseReturnQuery($filters);
$records = [];
$workoutRecords = [];
$summary = [
    'total' => 0,
    'minutes' => 0,
    'calories' => 0,
    'average_duration' => 0,
    'week_minutes' => 0,
    'week_calories' => 0,
];
$dashboardStats = [
    'today_calories' => 0,
    'week_average_calories' => 0,
    'weekly_calories' => [],
    'category_totals' => [],
];
$progressStats = [
    'weekly_goal_calories' => 2000,
    'weekly_goal_minutes' => 150,
    'active_days' => 0,
    'best_day_calories' => 0,
    'best_day_label' => 'No workout yet',
];
$achievementStats = [
    'active_days_all_time' => 0,
    'longest_session' => 0,
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
    $dashboardStats['week_average_calories'] = (int) round($summary['week_calories'] / 7);
    $mostFrequentActivity = exerciseMostFrequentActivity($connection, $userId);

    $todayStmt = $connection->prepare('SELECT COALESCE(SUM(calories_burned), 0) AS calories FROM exercise_records WHERE user_id = ? AND exercise_date = CURDATE()');
    $todayStmt->bind_param('i', $userId);
    $todayStmt->execute();
    $todayRow = $todayStmt->get_result()->fetch_assoc();
    $dashboardStats['today_calories'] = (int) $todayRow['calories'];

    $achievementStmt = $connection->prepare('SELECT COUNT(DISTINCT exercise_date) AS active_days_all_time, COALESCE(MAX(duration_minutes), 0) AS longest_session FROM exercise_records WHERE user_id = ?');
    $achievementStmt->bind_param('i', $userId);
    $achievementStmt->execute();
    $achievementRow = $achievementStmt->get_result()->fetch_assoc();
    $achievementStats['active_days_all_time'] = (int) $achievementRow['active_days_all_time'];
    $achievementStats['longest_session'] = (int) $achievementRow['longest_session'];

    $dailyStmt = $connection->prepare('SELECT exercise_date, COALESCE(SUM(calories_burned), 0) AS calories FROM exercise_records WHERE user_id = ? AND exercise_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY exercise_date ORDER BY exercise_date ASC');
    $dailyStmt->bind_param('i', $userId);
    $dailyStmt->execute();
    $dailyResult = $dailyStmt->get_result();
    $dailyCalories = [];
    while ($dailyRow = $dailyResult->fetch_assoc()) {
        $dailyCalories[$dailyRow['exercise_date']] = (int) $dailyRow['calories'];
    }

    for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
        $date = new DateTime("-{$dayOffset} days");
        $dateKey = $date->format('Y-m-d');
        $dayCalories = $dailyCalories[$dateKey] ?? 0;
        if ($dayCalories > 0) {
            $progressStats['active_days']++;
        }
        if ($dayCalories > $progressStats['best_day_calories']) {
            $progressStats['best_day_calories'] = $dayCalories;
            $progressStats['best_day_label'] = $date->format('D, M j');
        }

        $dashboardStats['weekly_calories'][] = [
            'date' => $dateKey,
            'label' => $date->format('D'),
            'calories' => $dayCalories,
        ];
    }

    $categoryStmt = $connection->prepare('SELECT activity_type, COALESCE(SUM(duration_minutes), 0) AS total_minutes, COALESCE(SUM(calories_burned), 0) AS total_calories FROM exercise_records WHERE user_id = ? GROUP BY activity_type ORDER BY total_minutes DESC, activity_type ASC');
    $categoryStmt->bind_param('i', $userId);
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();
    while ($categoryRow = $categoryResult->fetch_assoc()) {
        $dashboardStats['category_totals'][] = [
            'activity_type' => $categoryRow['activity_type'],
            'total' => (int) $categoryRow['total_minutes'],
            'calories' => (int) $categoryRow['total_calories'],
        ];
    }

    $filterQuery = exerciseFilterQuery($filters, $userId);
    $sql = 'SELECT exercise_id, activity_type, duration_minutes, calories_burned, exercise_date FROM exercise_records WHERE ' . $filterQuery['where'] . ' ORDER BY ' . exerciseOrderBy($filters['sort']);
    $stmt = $connection->prepare($sql);
    $params = $filterQuery['params'];
    exerciseBindParams($stmt, $filterQuery['types'], $params);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $workoutRecords = $records;

    if (!$showWorkoutHistory) {
        usort($workoutRecords, static function (array $firstRecord, array $secondRecord): int {
            $dateCompare = strcmp((string) $secondRecord['exercise_date'], (string) $firstRecord['exercise_date']);

            return $dateCompare !== 0 ? $dateCompare : ((int) $secondRecord['exercise_id'] <=> (int) $firstRecord['exercise_id']);
        });

        $latestByActivity = [];
        foreach ($workoutRecords as $record) {
            $activityType = (string) $record['activity_type'];
            if (!isset($latestByActivity[$activityType])) {
                $latestByActivity[$activityType] = $record;
            }
        }

        $workoutRecords = array_values($latestByActivity);
    }

    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="exercise-tracker-export.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Activity Type', 'Duration Minutes', 'Calories Burned', 'Exercise Date']);

        foreach ($records as $record) {
            fputcsv($output, [
                $record['activity_type'],
                $record['duration_minutes'],
                $record['calories_burned'],
                $record['exercise_date'],
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

$exerciseViews = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    'workouts' => ['label' => 'Workouts', 'icon' => 'bi-clipboard2-pulse'],
    'achievements' => ['label' => 'Achievements', 'icon' => 'bi-trophy'],
    'progress' => ['label' => 'Progress', 'icon' => 'bi-graph-up-arrow'],
];
$currentView = cleanInput((string) ($_GET['view'] ?? 'dashboard'));
if ($currentView === 'tutorial') {
    $currentView = 'progress';
}
if ($currentView === 'blogs') {
    $currentView = 'achievements';
}
if (!array_key_exists($currentView, $exerciseViews)) {
    $currentView = 'dashboard';
}

$workoutsQuery = $currentQuery !== '' ? $currentQuery . '&view=workouts' : 'view=workouts';
$exportQuery = $workoutsQuery . '&export=csv';
$workoutModeQuery = $showWorkoutHistory ? $workoutsQuery : $workoutsQuery . '&history=1';
$latestRecord = $records[0] ?? null;
$maxWeeklyCalories = max(array_column($dashboardStats['weekly_calories'], 'calories') ?: [0]);
$categoryColors = ['#1f7258', '#d9822b', '#4f8fcb', '#8a6ad8', '#d65757', '#6aa56f', '#c49b2e', '#5f6f69'];
$categoryTotal = array_sum(array_column($dashboardStats['category_totals'], 'total'));
$pieSegments = [];
$pieSlices = [];
$pieStart = 0.0;
foreach ($dashboardStats['category_totals'] as $categoryIndex => $category) {
    if ($categoryTotal <= 0) {
        continue;
    }

    $slice = ($category['total'] / $categoryTotal) * 100;
    $pieEnd = $pieStart + $slice;
    $pieSegments[] = $categoryColors[$categoryIndex % count($categoryColors)] . ' ' . $pieStart . '% ' . $pieEnd . '%';
    $pieSlices[] = [
        'activity' => $category['activity_type'],
        'calories' => (int) $category['calories'],
        'start' => $pieStart,
        'end' => $pieEnd,
    ];
    $pieStart = $pieEnd;
}
$pieChartStyle = $pieSegments ? 'background: conic-gradient(' . implode(', ', $pieSegments) . ');' : '';
$pieTooltipText = $dashboardStats['category_totals'] ? 'Hover a category' : 'No workout categories recorded yet.';
$pieSlicesJson = json_encode($pieSlices, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
$calorieGoalPercent = min(100, (int) round(($summary['week_calories'] / $progressStats['weekly_goal_calories']) * 100));
$minuteGoalPercent = min(100, (int) round(($summary['week_minutes'] / $progressStats['weekly_goal_minutes']) * 100));
$achievementCards = [
    [
        'title' => 'First Workout',
        'description' => 'Log your first exercise routine.',
        'icon' => 'bi-flag',
        'current' => $summary['total'],
        'target' => 1,
        'unit' => 'session',
        'tone' => 'green',
    ],
    [
        'title' => 'Routine Builder',
        'description' => 'Build a steady habit by completing more workout sessions.',
        'icon' => 'bi-calendar2-check',
        'current' => $summary['total'],
        'levels' => [5, 15, 30],
        'unit' => 'sessions',
        'tone' => 'teal',
    ],
    [
        'title' => 'Calorie Burner',
        'description' => 'Burn more calories across your logged workouts.',
        'icon' => 'bi-fire',
        'current' => $summary['calories'],
        'levels' => [1000, 3000, 6000],
        'unit' => 'kcal',
        'tone' => 'orange',
    ],
    [
        'title' => 'Active Minutes',
        'description' => 'Collect more active minutes during the current week.',
        'icon' => 'bi-stopwatch',
        'current' => $summary['week_minutes'],
        'levels' => [150, 225, 300],
        'unit' => 'min',
        'tone' => 'blue',
    ],
    [
        'title' => 'Goal Crusher',
        'description' => 'Push past your weekly calorie goal in stronger stages.',
        'icon' => 'bi-lightning-charge',
        'current' => $summary['week_calories'],
        'levels' => [
            $progressStats['weekly_goal_calories'],
            (int) ceil(($progressStats['weekly_goal_calories'] * 1.25) / 50) * 50,
            (int) ceil(($progressStats['weekly_goal_calories'] * 1.5) / 50) * 50,
        ],
        'unit' => 'kcal',
        'tone' => 'purple',
    ],
    [
        'title' => 'Consistency Streak',
        'description' => 'Exercise on more different days during the current week.',
        'icon' => 'bi-repeat',
        'current' => $progressStats['active_days'],
        'levels' => [3, 5, 7],
        'unit' => 'days',
        'tone' => 'green',
    ],
    [
        'title' => 'Sport Explorer',
        'description' => 'Try 5 different workout categories.',
        'icon' => 'bi-compass',
        'current' => count($dashboardStats['category_totals']),
        'target' => 5,
        'unit' => 'types',
        'tone' => 'teal',
    ],
    [
        'title' => 'Endurance Session',
        'description' => 'Complete longer single workout sessions.',
        'icon' => 'bi-award',
        'current' => $achievementStats['longest_session'],
        'levels' => [60, 75, 90],
        'unit' => 'min',
        'tone' => 'blue',
    ],
];
$achievementCards = array_map(static function (array $achievementCard): array {
    $levels = $achievementCard['levels'] ?? null;
    $current = (int) $achievementCard['current'];
    $romanLevels = [1 => 'I', 2 => 'II', 3 => 'III'];

    if ($levels) {
        $levels = array_values(array_map('intval', $levels));
        $completedLevels = 0;
        foreach ($levels as $levelTarget) {
            if ($current >= $levelTarget) {
                $completedLevels++;
            }
        }

        $activeLevelIndex = min($completedLevels, count($levels) - 1);
        $target = $levels[$activeLevelIndex];
        $achievementCard['target'] = $target;
        $achievementCard['final_target'] = $levels[count($levels) - 1];
        $achievementCard['level'] = min($completedLevels + 1, count($levels));
        $achievementCard['level_count'] = count($levels);
        $achievementCard['completed_levels'] = $completedLevels;
        $achievementCard['is_unlocked'] = $completedLevels >= count($levels);
        $achievementCard['percent'] = min(100, (int) round(($current / max(1, $target)) * 100));
        $achievementCard['display_title'] = $achievementCard['title'] . ' ' . ($romanLevels[$achievementCard['level']] ?? (string) $achievementCard['level']);

        return $achievementCard;
    }

    $target = (int) $achievementCard['target'];
    $achievementCard['final_target'] = $target;
    $achievementCard['level'] = null;
    $achievementCard['level_count'] = null;
    $achievementCard['completed_levels'] = null;
    $achievementCard['is_unlocked'] = $current >= $target;
    $achievementCard['percent'] = min(100, (int) round(($current / max(1, $target)) * 100));
    $achievementCard['display_title'] = $achievementCard['title'];

    return $achievementCard;
}, $achievementCards);
$completedAchievements = 0;
foreach ($achievementCards as $achievementCard) {
    if ($achievementCard['is_unlocked']) {
        $completedAchievements++;
    }
}

$pageTitle = 'Exercise Tracker';
require __DIR__ . '/../../includes/header.php';
?>

<section class="exercise-hero">
    <iframe
        class="exercise-hero-lottie"
        src="https://embed.lottiefiles.com/animation/132610"
        title="Exercise background animation"
        aria-hidden="true"
        tabindex="-1"
    ></iframe>
    <div class="exercise-hero-copy">
        <h1>Exercise Tracker</h1>
        <p class="hero-copy">Record workouts, review effort, and keep your health routine moving.</p>
    </div>
</section>

<section class="exercise-action-bar" aria-label="Exercise actions">
    <?php foreach ($exerciseViews as $viewKey => $viewData): ?>
        <a class="exercise-view-button <?= $currentView === $viewKey ? 'active' : ''; ?>" href="<?= BASE_URL; ?>/modules/exercise/index.php?view=<?= escapeOutput($viewKey); ?>" <?= $currentView === $viewKey ? 'aria-current="page"' : ''; ?>>
            <i class="bi <?= escapeOutput($viewData['icon']); ?>" aria-hidden="true"></i>
            <span><?= escapeOutput($viewData['label']); ?></span>
        </a>
    <?php endforeach; ?>
</section>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php else: ?>
    <?php if ($currentView === 'dashboard'): ?>
        <section class="exercise-dashboard-grid" aria-label="Exercise dashboard">
            <article class="exercise-insight-card">
                <span class="summary-label">Calories Today</span>
                <strong><?= number_format($dashboardStats['today_calories']); ?> kcal</strong>
                <p class="muted">Calories burned from workouts logged today.</p>
            </article>
            <article class="exercise-insight-card">
                <span class="summary-label">Weekly Average</span>
                <strong><?= number_format($dashboardStats['week_average_calories']); ?> kcal</strong>
                <p class="muted">Average calories burned per day over the last 7 days.</p>
            </article>
            <article class="exercise-insight-card">
                <span class="summary-label">All-Time Calories</span>
                <strong><?= number_format($summary['calories']); ?> kcal</strong>
                <p class="muted">Estimated calories burned from workouts.</p>
            </article>
            <article class="exercise-insight-card">
                <span class="summary-label">This Week</span>
                <strong><?= number_format($summary['week_calories']); ?> kcal</strong>
                <p class="muted">Total calories burned in the last 7 days.</p>
            </article>
        </section>
        <section class="exercise-chart-grid" aria-label="Exercise charts">
            <article class="panel exercise-chart-card">
                <div class="exercise-chart-heading">
                    <div>
                        <p class="summary-label">Weekly Calories</p>
                        <h2>Last 7 days</h2>
                    </div>
                    <span><?= number_format($summary['week_calories']); ?> cal</span>
                </div>
                <div class="exercise-axis-chart" aria-label="Weekly calories burned bar chart">
                    <div class="exercise-y-axis" aria-hidden="true">
                        <span><?= number_format($maxWeeklyCalories); ?></span>
                        <span><?= number_format((int) round($maxWeeklyCalories / 2)); ?></span>
                        <span>0</span>
                    </div>
                    <div class="exercise-bar-chart">
                    <?php foreach ($dashboardStats['weekly_calories'] as $day): ?>
                        <?php $barHeight = $maxWeeklyCalories > 0 ? max(8, (int) round(($day['calories'] / $maxWeeklyCalories) * 100)) : 8; ?>
                        <div class="exercise-bar-item">
                            <span class="exercise-bar-value"><?= number_format($day['calories']); ?></span>
                            <span class="exercise-bar-fill" style="height: <?= $barHeight; ?>%;"></span>
                            <span class="exercise-bar-label"><?= escapeOutput($day['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <span class="exercise-y-axis-title" aria-hidden="true">Calories</span>
                    <span class="exercise-x-axis-title" aria-hidden="true">Days</span>
                </div>
            </article>
            <article class="panel exercise-chart-card">
                <div class="exercise-chart-heading">
                    <div>
                        <p class="summary-label">Workout Categories</p>
                    </div>
                    <span><?= number_format($categoryTotal); ?> min</span>
                </div>
                <div class="exercise-pie-layout">
                    <div class="exercise-pie-chart" style="<?= escapeOutput($pieChartStyle); ?>" aria-label="Workout category pie chart" data-pie-tooltip="<?= escapeOutput($pieTooltipText); ?>" data-pie-slices="<?= escapeOutput($pieSlicesJson ?: '[]'); ?>" tabindex="0"></div>
                    <div class="exercise-pie-legend">
                        <?php if ($dashboardStats['category_totals']): ?>
                            <?php foreach ($dashboardStats['category_totals'] as $categoryIndex => $category): ?>
                                <span>
                                    <i style="background: <?= escapeOutput($categoryColors[$categoryIndex % count($categoryColors)]); ?>;"></i>
                                    <?= escapeOutput($category['activity_type']); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="muted">No workout categories recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </section>
    <?php elseif ($currentView === 'workouts'): ?>
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
                <input name="view" type="hidden" value="workouts">
                <label for="search">Search</label>
                <input id="search" name="search" type="search" value="<?= escapeOutput($filters['search']); ?>" placeholder="Activity type">

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
                    <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php?view=workouts">Reset</a>
                </div>
            </form>
        </aside>

        <section class="exercise-board-header">
            <div>
                <p class="summary-label">Workout Board</p>
                <h2>
                    <?= number_format(count($workoutRecords)); ?>
                    <?= $showWorkoutHistory ? 'session' : 'routine'; ?><?= count($workoutRecords) === 1 ? '' : 's'; ?> showing
                </h2>
                <?php if (!$showWorkoutHistory && count($records) !== count($workoutRecords)): ?>
                    <p class="muted">Showing the latest record for each activity to keep daily routines tidy.</p>
                <?php endif; ?>
            </div>
            <div class="button-row compact-actions">
                <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php?<?= escapeOutput($workoutModeQuery); ?>">
                    <i class="bi <?= $showWorkoutHistory ? 'bi-grid' : 'bi-clock-history'; ?>" aria-hidden="true"></i>
                    <?= $showWorkoutHistory ? 'Latest Only' : 'View History'; ?>
                </a>
                <?php if ($showWorkoutHistory): ?>
                    <button class="button icon-button filter-button" type="button" data-filter-open aria-controls="exerciseFilters" aria-expanded="false">
                        <img src="<?= BASE_URL; ?>/assets/img/filter-icon.png" alt="" aria-hidden="true">
                        Filter
                        <?php if ($activeFilterLabels): ?>
                            <span class="filter-count"><?= count($activeFilterLabels); ?></span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
                <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php?<?= escapeOutput($exportQuery); ?>"><i class="bi bi-download" aria-hidden="true"></i> Export CSV</a>
                <a class="button primary" href="<?= BASE_URL; ?>/modules/exercise/create.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Exercise</a>
            </div>
            <?php if ($activeFilterLabels): ?>
                <div class="active-filter-list" aria-label="Active filters">
                    <?php foreach ($activeFilterLabels as $label): ?>
                        <span><?= escapeOutput($label); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!$workoutRecords): ?>
            <section class="panel empty-state">
                <h2>No exercise records found</h2>
                <p class="muted">Add a workout session or adjust the filters to review your exercise progress.</p>
                <div class="button-row">
                    <?php if ($showWorkoutHistory): ?>
                        <button class="button" type="button" data-filter-open>Open Filters</button>
                    <?php endif; ?>
                    <a class="button primary" href="<?= BASE_URL; ?>/modules/exercise/create.php">Add Exercise</a>
                </div>
            </section>
        <?php else: ?>
            <section class="exercise-card-grid" aria-label="Exercise records">
                <?php foreach ($workoutRecords as $record): ?>
                    <article
                        class="exercise-card exercise-card-<?= escapeOutput(exerciseActivityClass($record['activity_type'])); ?>"
                        data-exercise-routine
                        data-default-duration="<?= (int) $record['duration_minutes']; ?>"
                        data-default-calories="<?= (int) $record['calories_burned']; ?>"
                    >
                        <a class="exercise-card-delete" href="<?= BASE_URL; ?>/modules/exercise/delete.php?id=<?= (int) $record['exercise_id']; ?>" aria-label="Delete <?= escapeOutput($record['activity_type']); ?> record">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
                        <div class="exercise-card-topline">
                            <span class="exercise-activity"><?= exerciseActivityIconMarkup((string) $record['activity_type']); ?><?= escapeOutput($record['activity_type']); ?></span>
                            <span class="status-pill"><i class="bi bi-calendar-event" aria-hidden="true"></i><?= escapeOutput($record['exercise_date']); ?></span>
                        </div>
                        <div class="exercise-stat-row">
                            <div class="exercise-stat-adjust" aria-label="Adjust duration for <?= escapeOutput($record['activity_type']); ?>">
                                <i class="bi bi-stopwatch" aria-hidden="true"></i>
                                <span class="exercise-stat-value"><strong data-exercise-value="duration"><?= number_format((int) $record['duration_minutes']); ?></strong> min</span>
                                <div class="exercise-adjust-controls">
                                    <button type="button" data-exercise-adjust="duration" data-direction="up" aria-label="Increase duration by 1 minute"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                                    <button type="button" data-exercise-adjust="duration" data-direction="down" aria-label="Decrease duration by 1 minute"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                                </div>
                            </div>
                            <div class="exercise-stat-adjust" aria-label="Adjust calories for <?= escapeOutput($record['activity_type']); ?>">
                                <i class="bi bi-fire" aria-hidden="true"></i>
                                <span class="exercise-stat-value"><strong data-exercise-value="calories"><?= number_format((int) $record['calories_burned']); ?></strong> cal</span>
                                <div class="exercise-adjust-controls">
                                    <button type="button" data-exercise-adjust="calories" data-direction="up" aria-label="Increase calories by 1"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                                    <button type="button" data-exercise-adjust="calories" data-direction="down" aria-label="Decrease calories by 1"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="exercise-card-actions">
                            <form class="exercise-log-form" method="post" action="<?= BASE_URL; ?>/modules/exercise/create.php">
                                <?= csrfInput(); ?>
                                <input type="hidden" name="activity_type" value="<?= escapeOutput($record['activity_type']); ?>">
                                <input type="hidden" name="duration_minutes" value="<?= (int) $record['duration_minutes']; ?>" data-exercise-input="duration">
                                <input type="hidden" name="calories_burned" value="<?= (int) $record['calories_burned']; ?>" data-exercise-input="calories">
                                <input type="hidden" name="exercise_date" value="<?= date('Y-m-d'); ?>">
                                <button class="button small-button primary" type="submit">
                                    <i class="bi bi-calendar-plus" aria-hidden="true"></i> Log Today
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php elseif ($currentView === 'achievements'): ?>
        <section class="exercise-achievement-hero" aria-label="Exercise achievement overview">
            <div>
                <p class="summary-label">Achievement Room</p>
                <h2>Your Fitness Awards</h2>
                <p class="muted">Unlock badges as you log workouts, burn calories, keep active days, and try new activities.</p>
            </div>
            <div class="exercise-achievement-score">
                <strong><?= (int) $completedAchievements; ?></strong>
                <span>of <?= count($achievementCards); ?> unlocked</span>
            </div>
        </section>

        <section class="exercise-achievement-summary" aria-label="Achievement summary">
            <article>
                <span>Total workouts</span>
                <strong><?= number_format($summary['total']); ?></strong>
            </article>
            <article>
                <span>Total calories</span>
                <strong><?= number_format($summary['calories']); ?> kcal</strong>
            </article>
            <article>
                <span>Active days</span>
                <strong><?= number_format($achievementStats['active_days_all_time']); ?></strong>
            </article>
            <article>
                <span>Longest session</span>
                <strong><?= number_format($achievementStats['longest_session']); ?> min</strong>
            </article>
        </section>

        <?php if ($summary['total'] <= 0): ?>
            <section class="panel empty-state exercise-achievement-empty">
                <h2>No achievements unlocked yet</h2>
                <p class="muted">Log your first workout to start collecting badges.</p>
                <a class="button primary" href="<?= BASE_URL; ?>/modules/exercise/create.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Exercise</a>
            </section>
        <?php endif; ?>

        <section class="exercise-achievement-grid" aria-label="Exercise achievements">
            <?php foreach ($achievementCards as $achievementCard): ?>
                <?php
                    $achievementUnlocked = (bool) $achievementCard['is_unlocked'];
                    $achievementPercent = (int) $achievementCard['percent'];
                    $achievementTargetLabel = $achievementCard['level_count']
                        ? 'Level target'
                        : 'Target';
                ?>
                <article class="exercise-achievement-card exercise-achievement-<?= escapeOutput($achievementCard['tone']); ?> <?= $achievementUnlocked ? 'is-unlocked' : 'is-locked'; ?>">
                    <div class="exercise-achievement-icon">
                        <i class="bi <?= escapeOutput($achievementCard['icon']); ?>" aria-hidden="true"></i>
                    </div>
                    <div class="exercise-achievement-body">
                        <div class="exercise-achievement-kicker">
                            <span><?= $achievementUnlocked ? 'Unlocked' : 'In progress'; ?></span>
                        </div>
                        <h2><?= escapeOutput($achievementCard['display_title']); ?></h2>
                        <p><?= escapeOutput($achievementCard['description']); ?></p>
                    </div>
                    <div class="exercise-achievement-progress" aria-label="<?= escapeOutput($achievementCard['title']); ?> progress">
                        <div>
                            <span><?= escapeOutput($achievementTargetLabel); ?>: <?= number_format(min((int) $achievementCard['current'], (int) $achievementCard['target'])); ?> / <?= number_format((int) $achievementCard['target']); ?> <?= escapeOutput($achievementCard['unit']); ?></span>
                            <strong><?= $achievementPercent; ?>%</strong>
                        </div>
                        <i style="width: <?= $achievementPercent; ?>%;"></i>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php elseif ($currentView === 'progress'): ?>
        <section class="exercise-progress-grid" aria-label="Exercise progress tools">
            <article class="panel exercise-bmi-card">
                <div>
                    <p class="summary-label">BMI Calculator</p>
                    <h2>Body Check</h2>
                    <p class="muted">Enter your height and weight to estimate your BMI.</p>
                </div>
                <form class="exercise-bmi-form" data-bmi-calculator>
                    <label for="bmi_height">Height (cm)</label>
                    <input id="bmi_height" name="height" type="number" min="80" max="250" step="0.1" placeholder="170">

                    <label for="bmi_weight">Weight (kg)</label>
                    <input id="bmi_weight" name="weight" type="number" min="20" max="300" step="0.1" placeholder="65">

                    <button class="button primary" type="submit"><i class="bi bi-calculator" aria-hidden="true"></i> Calculate BMI</button>
                </form>
                <div class="exercise-bmi-result" data-bmi-result>
                    <span>Your BMI</span>
                    <strong>--</strong>
                    <p class="muted">Add your details to see the category.</p>
                </div>
            </article>

            <article class="panel exercise-goal-card">
                <p class="summary-label">Weekly Progress</p>
                <h2>Goal Tracker</h2>
                <form class="exercise-goal-form" data-exercise-goals>
                    <div>
                        <label for="weekly_calorie_goal">Calorie goal</label>
                        <input id="weekly_calorie_goal" type="number" min="50" max="50000" step="50" value="<?= (int) $progressStats['weekly_goal_calories']; ?>" data-goal-input="calories">
                    </div>
                    <div>
                        <label for="weekly_minute_goal">Minute goal</label>
                        <input id="weekly_minute_goal" type="number" min="5" max="10080" step="5" value="<?= (int) $progressStats['weekly_goal_minutes']; ?>" data-goal-input="minutes">
                    </div>
                    <button class="button small-button" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Save Goals</button>
                </form>
                <div class="exercise-goal-row">
                    <div>
                        <strong data-goal-current="calories"><?= number_format($summary['week_calories']); ?> kcal</strong>
                        <span data-goal-target="calories">of <?= number_format($progressStats['weekly_goal_calories']); ?> kcal</span>
                    </div>
                    <div class="exercise-goal-meter" style="--goal-progress: <?= $calorieGoalPercent; ?>%;" data-goal-meter="calories" data-current="<?= (int) $summary['week_calories']; ?>">
                        <span></span>
                    </div>
                </div>
                <div class="exercise-goal-row">
                    <div>
                        <strong data-goal-current="minutes"><?= number_format($summary['week_minutes']); ?> min</strong>
                        <span data-goal-target="minutes">of <?= number_format($progressStats['weekly_goal_minutes']); ?> active minutes</span>
                    </div>
                    <div class="exercise-goal-meter blue-meter" style="--goal-progress: <?= $minuteGoalPercent; ?>%;" data-goal-meter="minutes" data-current="<?= (int) $summary['week_minutes']; ?>">
                        <span></span>
                    </div>
                </div>
            </article>

            <article class="panel exercise-progress-summary">
                <p class="summary-label">Consistency</p>
                <h2><?= number_format($progressStats['active_days']); ?> active day<?= $progressStats['active_days'] === 1 ? '' : 's'; ?></h2>
                <p class="muted">Days with at least one workout in the last 7 days.</p>
                <div class="exercise-mini-stats">
                    <span><strong><?= number_format($progressStats['best_day_calories']); ?> kcal</strong> Best day</span>
                    <span><strong><?= escapeOutput($progressStats['best_day_label']); ?></strong> Peak date</span>
                </div>
            </article>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
