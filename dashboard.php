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
    'habit_percentage' => 0,
];
$todayHabitsByRealm = [];
$dashboardError = null;
$userId = (int) $_SESSION['user_id'];

try {
    $connection = getDatabaseConnection();

    // Summary queries
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

    $stmt = $connection->prepare("SELECT COUNT(*) AS record_count, COALESCE(SUM(CASE WHEN l.completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_count FROM habit_logs l INNER JOIN habits h ON h.habit_id = l.habit_id WHERE l.user_id = ? AND h.is_active = 1 AND l.deleted_at IS NULL AND l.scheduled_date = CURDATE()");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $habit = $stmt->get_result()->fetch_assoc();
    $summary['habit_count'] = (int) $habit['record_count'];
    $summary['habit_completed'] = (int) $habit['completed_count'];
    $summary['habit_percentage'] = $summary['habit_count'] > 0 ? (int) round(($summary['habit_completed'] / $summary['habit_count']) * 100) : 0;

    // Today's habit progress by realm (for the Habit Progress overview card)
    $habitRealmLabels = ['focus' => 'Focus Garden', 'energy' => 'Energy Grove', 'mind' => 'Mind Clearing', 'life_admin' => 'Life Garden'];
    $habitRealmColors = ['focus' => 'fill-habits', 'energy' => 'fill-exercise', 'mind' => 'fill-journal', 'life_admin' => 'fill-money'];
    $todayHabitsByRealm = [];
    $stmt = $connection->prepare("SELECT h.realm, COUNT(l.log_id) AS total, COALESCE(SUM(CASE WHEN l.completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed FROM habit_logs l INNER JOIN habits h ON h.habit_id = l.habit_id WHERE l.user_id = ? AND h.is_active = 1 AND l.deleted_at IS NULL AND l.scheduled_date = CURDATE() GROUP BY h.realm");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $realm = $row['realm'];
        $total = (int) $row['total'];
        $completed = (int) $row['completed'];
        $todayHabitsByRealm[$realm] = [
            'label'     => $habitRealmLabels[$realm] ?? ucfirst($realm),
            'color'     => $habitRealmColors[$realm] ?? 'fill-habits',
            'total'     => $total,
            'completed' => $completed,
            'pct'       => $total > 0 ? (int) round($completed / $total * 100) : 0,
        ];
    }

    // Today's Spending query
    $todaySpending = [
        'Food' => 0.00,
        'Transport' => 0.00,
        'Other' => 0.00,
        'Total' => 0.00
    ];
    $stmt = $connection->prepare("SELECT category, SUM(amount) AS total FROM money_transactions WHERE user_id = ? AND transaction_date = CURDATE() AND transaction_type = 'expense' GROUP BY category");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $hasTodaySpending = false;
    while ($row = $res->fetch_assoc()) {
        $cat = strtolower($row['category']);
        $amt = (float) $row['total'];
        if (strpos($cat, 'food') !== false || strpos($cat, 'meal') !== false) {
            $todaySpending['Food'] += $amt;
        } elseif (strpos($cat, 'transport') !== false || strpos($cat, 'travel') !== false || strpos($cat, 'bus') !== false || strpos($cat, 'cab') !== false) {
            $todaySpending['Transport'] += $amt;
        } else {
            $todaySpending['Other'] += $amt;
        }
        $todaySpending['Total'] += $amt;
        $hasTodaySpending = true;
    }
    // No fallback — zero values are the correct empty state for new accounts

    // Weekly Activity query (last 7 days of exercise)
    $weeklyActivity = [
        'Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0,
        'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0
    ];
    $stmt = $connection->prepare("
        SELECT DAYNAME(exercise_date) AS day_name, SUM(duration_minutes) AS total_minutes 
        FROM exercise_records 
        WHERE user_id = ? AND exercise_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DAYNAME(exercise_date)
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $hasWeeklyActivity = false;
    while ($row = $res->fetch_assoc()) {
        if (isset($weeklyActivity[$row['day_name']])) {
            $weeklyActivity[$row['day_name']] = (int) $row['total_minutes'];
            $hasWeeklyActivity = true;
        }
    }
    // No fallback — all-zero bars are the correct empty state for new accounts

    // Recent Activity query
    $activities = [];
    
    $stmt = $connection->prepare("SELECT duration_minutes, activity_type, exercise_date FROM exercise_records WHERE user_id = ? ORDER BY exercise_date DESC, exercise_id DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $exerciseRec = $stmt->get_result()->fetch_assoc();
    if ($exerciseRec) {
        $activities[] = [
            'type' => 'exercise',
            'text' => 'Completed a workout: ' . htmlspecialchars($exerciseRec['activity_type']) . ' for ' . $exerciseRec['duration_minutes'] . ' mins',
            'date' => $exerciseRec['exercise_date']
        ];
    }
    
    $stmt = $connection->prepare("SELECT title, mood_status, entry_date FROM journal_entries WHERE user_id = ? ORDER BY entry_date DESC, journal_id DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $journalRec = $stmt->get_result()->fetch_assoc();
    if ($journalRec) {
        $activities[] = [
            'type' => 'journal',
            'text' => 'Added a journal entry: "' . htmlspecialchars($journalRec['title']) . '" (Feeling ' . htmlspecialchars($journalRec['mood_status']) . ')',
            'date' => $journalRec['entry_date']
        ];
    }
    
    $stmt = $connection->prepare("SELECT amount, transaction_type, category, transaction_date FROM money_transactions WHERE user_id = ? ORDER BY transaction_date DESC, transaction_id DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $moneyRec = $stmt->get_result()->fetch_assoc();
    if ($moneyRec) {
        $typeLabel = $moneyRec['transaction_type'] === 'income' ? 'income' : 'expense';
        $activities[] = [
            'type' => 'money',
            'text' => 'Added an ' . $typeLabel . ': RM ' . number_format($moneyRec['amount'], 2) . ' for ' . htmlspecialchars($moneyRec['category']),
            'date' => $moneyRec['transaction_date']
        ];
    }
    
    $stmt = $connection->prepare("SELECT h.habit_name, l.completion_status, l.scheduled_date FROM habit_logs l INNER JOIN habits h ON h.habit_id = l.habit_id WHERE l.user_id = ? AND l.completion_status = 'completed' ORDER BY l.scheduled_date DESC, l.log_id DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $habitRec = $stmt->get_result()->fetch_assoc();
    if ($habitRec) {
        $activities[] = [
            'type' => 'habit',
            'text' => 'Completed a habit: ' . htmlspecialchars($habitRec['habit_name']),
            'date' => $habitRec['scheduled_date']
        ];
    }
    
    usort($activities, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
    
    // $activities stays empty for new accounts — the template will show a friendly empty state

} catch (Throwable $exception) {
    logApplicationException($exception, 'dashboard');
    $dashboardError = 'Dashboard summaries are unavailable right now.';
}

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<style>
:root {
    --color-primary: #236a54;
    --color-primary-active: #174d3d;
    --color-primary-disabled: #e2f1ea;
    --color-ink: #17231b;
    --color-body: #344338;
    --color-muted: #647064;
    --color-muted-soft: #9bb59e;
    --color-hairline: #dbe4d7;
    --color-hairline-soft: #eaf1e5;
    --color-canvas: #f4f7f2;
    --color-surface-card: #ffffff;
    --color-surface-soft: #f9fbf7;
    
    --color-exercise: #236a54;
    --color-exercise-bg: #e2f1ea;
    --color-exercise-border: rgba(35, 106, 84, 0.15);
    
    --color-journal: #b42318;
    --color-journal-bg: #fdf2f2;
    --color-journal-border: rgba(180, 35, 24, 0.15);

    --color-money: #d9822b;
    --color-money-bg: #fff7ed;
    --color-money-border: rgba(217, 130, 43, 0.15);

    --color-habits: #1c54b2;
    --color-habits-bg: #eff6ff;
    --color-habits-border: rgba(28, 84, 178, 0.15);

    --font-primary: "Lora", "Playfair Display", Georgia, serif;
    --font-family-2: "Inter", "Plus Jakarta Sans", "SF Pro Display", system-ui, sans-serif;
    
    --spacing-xxs: 4px;
    --spacing-xs: 8px;
    --spacing-sm: 12px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    --spacing-xxl: 48px;

    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-pill: 9999px;
}

.dashboard-main-content {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xl);
    width: 100%;
}

.dashboard-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.dashboard-top-date {
    font-family: var(--font-family-2);
    font-size: 14px;
    color: var(--color-muted);
    font-weight: 500;
}

.dashboard-welcome-heading {
    margin-bottom: var(--spacing-md);
}

.dashboard-welcome-heading h1 {
    font-family: var(--font-primary);
    font-size: 38px;
    font-weight: 500;
    color: var(--color-ink);
    letter-spacing: -0.02em;
    line-height: 1.15;
    margin: 0 0 var(--spacing-xs) 0;
}

.dashboard-welcome-heading p {
    font-family: var(--font-family-2);
    font-size: 15px;
    color: var(--color-muted);
    margin: 0;
}

/* Summary Grid Row */
.dashboard-summary-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--spacing-md);
}

.dashboard-card {
    background-color: var(--color-surface-card);
    border: 1px solid var(--color-hairline);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    position: relative;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.01);
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(23, 35, 27, 0.03);
    border-color: var(--color-muted-soft);
}

.card-icon-tag {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 600;
}

.card-exercise .card-icon-tag { background-color: var(--color-exercise-bg); color: var(--color-exercise); border: 1px solid var(--color-exercise-border); }
.card-journal .card-icon-tag { background-color: var(--color-journal-bg); color: var(--color-journal); border: 1px solid var(--color-journal-border); }
.card-money .card-icon-tag { background-color: var(--color-money-bg); color: var(--color-money); border: 1px solid var(--color-money-border); }
.card-habits .card-icon-tag { background-color: var(--color-habits-bg); color: var(--color-habits); border: 1px solid var(--color-habits-border); }

.card-label {
    font-family: var(--font-family-2);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--color-muted);
}

.dashboard-card strong {
    font-family: var(--font-primary);
    font-size: 24px;
    font-weight: 500;
    color: var(--color-ink);
    letter-spacing: -0.01em;
    line-height: 1.2;
}

.card-details {
    font-family: var(--font-family-2);
    font-size: 13px;
    color: var(--color-muted);
    margin-top: auto;
}

.card-detail-item {
    display: flex;
    justify-content: space-between;
    width: 100%;
}

/* Today's Overview Section & Bento Boxes */
.dashboard-section-title {
    font-family: var(--font-primary);
    font-size: 20px;
    font-weight: 500;
    color: var(--color-ink);
    letter-spacing: -0.01em;
    margin: var(--spacing-xl) 0 var(--spacing-sm) 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.dashboard-section-title i {
    font-size: 16px;
    color: var(--color-muted);
}

.dashboard-overview-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr;
    gap: var(--spacing-lg);
    width: 100%;
}

.overview-card {
    background-color: var(--color-surface-card);
    border: 1px solid var(--color-hairline);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.01);
}

.overview-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(23, 35, 27, 0.03);
}

.overview-card-title {
    font-family: var(--font-primary);
    font-size: 18px;
    font-weight: 500;
    color: var(--color-ink);
    margin: 0;
    letter-spacing: -0.01em;
    border-bottom: 1px solid var(--color-hairline-soft);
    padding-bottom: var(--spacing-xs);
}

/* Tasks */
.task-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.task-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    cursor: pointer;
    font-family: var(--font-family-2);
    font-size: 14px;
    color: var(--color-body);
    user-select: none;
    padding: var(--spacing-xs) 0;
    border-bottom: 1px dashed var(--color-hairline-soft);
}

.task-item:last-child {
    border-bottom: none;
}

.task-checkbox {
    appearance: none;
    width: 18px;
    height: 18px;
    border: 1.5px solid var(--color-muted-soft);
    border-radius: 4px;
    position: relative;
    cursor: pointer;
    background-color: #ffffff;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.task-checkbox:checked {
    background-color: var(--color-primary);
    border-color: var(--color-primary);
}

.task-checkbox:checked::after {
    content: '\F26E';
    font-family: 'bootstrap-icons';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #ffffff;
    font-size: 10px;
}

.task-checkbox:checked + span {
    text-decoration: line-through;
    color: var(--color-muted);
}

/* Habit Progress */
.habit-progress-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.habit-progress-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.habit-progress-header {
    display: flex;
    justify-content: space-between;
    font-family: var(--font-family-2);
    font-size: 13px;
    font-weight: 500;
    color: var(--color-body);
}

.habit-progress-header span:last-child {
    font-family: var(--font-primary);
    color: var(--color-ink);
    font-weight: 600;
}

.progress-bar-container {
    background-color: var(--color-hairline-soft);
    height: 6px;
    width: 100%;
    border-radius: var(--radius-pill);
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    border-radius: var(--radius-pill);
    transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.fill-exercise { background-color: var(--color-exercise); }
.fill-journal { background-color: var(--color-journal); }
.fill-money { background-color: var(--color-money); }
.fill-habits { background-color: var(--color-habits); }

/* Today's Spending */
.spending-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.spending-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: var(--font-family-2);
    font-size: 13px;
    color: var(--color-body);
    padding: var(--spacing-xs) 0;
    border-bottom: 1px dashed var(--color-hairline-soft);
}

.spending-item strong {
    font-family: var(--font-primary);
    font-weight: 500;
    color: var(--color-ink);
}

.spending-total {
    border-top: 1px solid var(--color-hairline) !important;
    border-bottom: none !important;
    margin-top: var(--spacing-xs);
    padding-top: var(--spacing-sm);
    font-weight: 600;
    font-size: 14px;
    color: var(--color-ink);
}

.spending-total span:last-child {
    font-family: var(--font-primary);
    font-size: 16px;
}

/* Weekly Activity */
.weekly-activity-card {
    background-color: var(--color-surface-card);
    border: 1px solid var(--color-hairline);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg) var(--spacing-xl);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.01);
}

.weekly-activity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(23, 35, 27, 0.03);
}

.chart-wrapper {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: var(--spacing-md);
    align-items: flex-end;
    height: 180px;
    padding-top: var(--spacing-lg);
}

.chart-bar-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--spacing-xs);
    height: 100%;
    justify-content: flex-end;
    position: relative;
}

.chart-bar-value {
    font-family: var(--font-primary);
    font-size: 11px;
    font-weight: 600;
    color: var(--color-muted);
    opacity: 0;
    transform: translateY(4px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: absolute;
    top: -18px;
}

.chart-bar-container:hover .chart-bar-value {
    opacity: 1;
    transform: translateY(0);
}

.chart-bar {
    width: 24px;
    background-color: var(--color-exercise-bg);
    border: 1px solid var(--color-exercise-border);
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    transition: height 0.8s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.3s ease;
}

.chart-bar-container:hover .chart-bar {
    background-color: var(--color-exercise);
}

.chart-day-label {
    font-family: var(--font-family-2);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-muted);
    margin-top: var(--spacing-xs);
}

/* Recent Activity */
.recent-activity-card {
    background-color: var(--color-surface-card);
    border: 1px solid var(--color-hairline);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.01);
}

.recent-activity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(23, 35, 27, 0.03);
}

.activity-feed {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.activity-feed-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--color-hairline-soft);
}

.activity-feed-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.activity-icon-badge {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.badge-exercise { background-color: var(--color-exercise-bg); color: var(--color-exercise); border: 1px solid var(--color-exercise-border); }
.badge-journal { background-color: var(--color-journal-bg); color: var(--color-journal); border: 1px solid var(--color-journal-border); }
.badge-money { background-color: var(--color-money-bg); color: var(--color-money); border: 1px solid var(--color-money-border); }
.badge-habit { background-color: var(--color-habits-bg); color: var(--color-habits); border: 1px solid var(--color-habits-border); }

.activity-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.activity-text {
    font-family: var(--font-family-2);
    font-size: 13.5px;
    color: var(--color-body);
    font-weight: 500;
}

.activity-time {
    font-family: var(--font-family-2);
    font-size: 11px;
    color: var(--color-muted);
}

/* Responsiveness overrides */
@media (max-width: 990px) {
    .dashboard-overview-grid {
        grid-template-columns: 1.2fr 1fr;
    }
}

@media (max-width: 860px) {
    .dashboard-overview-grid {
        grid-template-columns: 1fr;
    }
    .dashboard-summary-row {
        grid-template-columns: 1fr 1fr;
    }
    .dashboard-welcome-heading h1 {
        font-size: 32px;
    }
}

@media (max-width: 520px) {
    .dashboard-summary-row {
        grid-template-columns: 1fr;
    }
    .chart-wrapper {
        height: 140px;
    }
}
</style>

<div class="dashboard-main-content">
    <div class="dashboard-top-row">
        <div class="dashboard-top-date">
            <?= date('l, j F Y'); ?>
        </div>
    </div>

    <div class="dashboard-welcome-heading">
        <h1><?= (int)date('H') < 12 ? 'Good morning' : ((int)date('H') < 17 ? 'Good afternoon' : 'Good evening'); ?>, <?= escapeOutput(currentUserName()); ?>.</h1>
        <p>Here's your routine overview for today.</p>
    </div>

    <?php if ($dashboardError): ?>
        <div class="alert alert-error"><?= escapeOutput($dashboardError); ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="dashboard-summary-row">
        <!-- Exercise Card -->
        <article class="dashboard-card card-exercise">
            <div class="card-icon-tag"><i class="bi bi-activity"></i></div>
            <span class="card-label">Exercise</span>
            <strong>
                <?= number_format($summary['exercise_count']); ?> <?= $summary['exercise_count'] === 1 ? 'record' : 'records'; ?>
            </strong>
            <div class="card-details">
                <span><?= number_format($summary['exercise_minutes']); ?> minutes</span>
                <span><?= number_format($summary['exercise_calories']); ?> calories</span>
            </div>
        </article>

        <!-- Journal Card -->
        <article class="dashboard-card card-journal">
            <div class="card-icon-tag"><i class="bi bi-journal-text"></i></div>
            <span class="card-label">Journal</span>
            <strong>
                <?= number_format($summary['journal_count']); ?> <?= $summary['journal_count'] === 1 ? 'journal entry' : 'journal entries'; ?>
            </strong>
            <div class="card-details">
                <span>Latest mood: <?= escapeOutput($summary['latest_mood']); ?></span>
            </div>
        </article>

        <!-- Money Card -->
        <article class="dashboard-card card-money">
            <div class="card-icon-tag"><i class="bi bi-piggy-bank"></i></div>
            <span class="card-label">Money</span>
            <strong>
                RM <?= number_format($summary['money_balance'], 2); ?>
            </strong>
            <div class="card-details">
                <span class="card-detail-item"><span>Income:</span> <span>RM <?= number_format($summary['income_total'], 2); ?></span></span>
                <span class="card-detail-item"><span>Expenses:</span> <span>RM <?= number_format($summary['expense_total'], 2); ?></span></span>
            </div>
        </article>

        <!-- Habits Card -->
        <article class="dashboard-card card-habits">
            <div class="card-icon-tag"><i class="bi bi-check2-circle"></i></div>
            <span class="card-label">Habits Today</span>
            <strong>
                <?= number_format($summary['habit_completed']); ?> / <?= number_format($summary['habit_count']); ?> completed
            </strong>
            <div class="progress-bar-container" style="margin: 6px 0;">
                <div class="progress-bar-fill fill-habits" style="width: <?= $summary['habit_percentage']; ?>%;"></div>
            </div>
            <p class="card-details"><?= $summary['habit_percentage']; ?>% completion rate today</p>
        </article>
    </div>

    <!-- Today's Overview Grid -->
    <div>
        <h2 class="dashboard-section-title"><i class="bi bi-calendar2-event"></i> Today's Overview</h2>
        <div class="dashboard-overview-grid">
            <!-- Today's Quick Actions -->
            <div class="overview-card">
                <h3 class="overview-card-title">Today's Quick Actions</h3>
                <div class="task-list">
                    <label class="task-item">
                        <input type="checkbox" class="task-checkbox" <?= $summary['exercise_count'] > 0 ? 'checked' : ''; ?>>
                        <span><?= $summary['exercise_count'] > 0 ? 'Logged exercise today' : 'Log today\'s exercise'; ?></span>
                    </label>
                    <label class="task-item">
                        <input type="checkbox" class="task-checkbox" <?= $summary['journal_count'] > 0 ? 'checked' : ''; ?>>
                        <span><?= $summary['journal_count'] > 0 ? 'Wrote a journal entry' : 'Write a journal entry'; ?></span>
                    </label>
                    <label class="task-item">
                        <input type="checkbox" class="task-checkbox" <?= ($summary['income_total'] > 0 || $summary['expense_total'] > 0) ? 'checked' : ''; ?>>
                        <span><?= ($summary['income_total'] > 0 || $summary['expense_total'] > 0) ? 'Tracked money today' : 'Track today\'s spending'; ?></span>
                    </label>
                    <label class="task-item">
                        <input type="checkbox" class="task-checkbox" <?= $summary['habit_completed'] > 0 ? 'checked' : ''; ?>>
                        <span><?= $summary['habit_completed'] > 0 ? $summary['habit_completed'] . ' habit' . ($summary['habit_completed'] === 1 ? '' : 's') . ' completed today' : 'Complete today\'s habits'; ?></span>
                    </label>
                </div>
            </div>

            <!-- Habit Progress -->
            <div class="overview-card">
                <h3 class="overview-card-title">Habit Progress Today</h3>
                <div class="habit-progress-list">
                    <?php if (empty($todayHabitsByRealm)): ?>
                        <p style="font-family: var(--font-family-2); font-size: 13px; color: var(--color-muted); margin: 0;">
                            No habits scheduled for today. <a href="<?= BASE_URL; ?>/modules/habits/create.php" style="color: var(--color-primary);">Add your first quest →</a>
                        </p>
                    <?php else: ?>
                        <?php foreach ($todayHabitsByRealm as $realmData): ?>
                            <div class="habit-progress-item">
                                <div class="habit-progress-header">
                                    <span><?= escapeOutput($realmData['label']); ?></span>
                                    <span><?= $realmData['completed']; ?>/<?= $realmData['total']; ?> &nbsp; <?= $realmData['pct']; ?>%</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill <?= escapeOutput($realmData['color']); ?>" style="width: <?= $realmData['pct']; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Today's Spending -->
            <div class="overview-card">
                <h3 class="overview-card-title">Today's Spending</h3>
                <div class="spending-list">
                    <div class="spending-item">
                        <span>Food</span>
                        <strong>RM <?= number_format($todaySpending['Food'], 2); ?></strong>
                    </div>
                    <div class="spending-item">
                        <span>Transport</span>
                        <strong>RM <?= number_format($todaySpending['Transport'], 2); ?></strong>
                    </div>
                    <div class="spending-item">
                        <span>Other</span>
                        <strong>RM <?= number_format($todaySpending['Other'], 2); ?></strong>
                    </div>
                    <div class="spending-item spending-total">
                        <span>Total Spending</span>
                        <span>RM <?= number_format($todaySpending['Total'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Activity -->
    <div>
        <h2 class="dashboard-section-title"><i class="bi bi-bar-chart-line"></i> Weekly Activity (Exercise Minutes)</h2>
        <div class="weekly-activity-card">
            <div class="chart-wrapper">
                <?php
                $maxVal = max(1, max($weeklyActivity));
                foreach ($weeklyActivity as $day => $mins):
                    $barHeight = round(($mins / $maxVal) * 80) + 5;
                ?>
                    <div class="chart-bar-container">
                        <span class="chart-bar-value"><?= $mins; ?>m</span>
                        <div class="chart-bar" style="height: <?= $barHeight; ?>%;"></div>
                        <span class="chart-day-label"><?= substr($day, 0, 3); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div>
        <h2 class="dashboard-section-title"><i class="bi bi-clock-history"></i> Recent Activity</h2>
        <div class="recent-activity-card">
            <div class="activity-feed">
                <?php if (empty($activities)): ?>
                    <p style="font-family: var(--font-family-2); font-size: 14px; color: var(--color-muted); margin: 0; padding: var(--spacing-md) 0;">
                        No activity yet. Start by logging an exercise, writing a journal entry, or completing a habit quest.
                    </p>
                <?php else: ?>
                    <?php foreach ($activities as $act): ?>
                        <div class="activity-feed-item">
                            <div class="activity-icon-badge badge-<?= $act['type'] ?>">
                                <?php if ($act['type'] === 'exercise'): ?>
                                    <i class="bi bi-activity"></i>
                                <?php elseif ($act['type'] === 'journal'): ?>
                                    <i class="bi bi-journal-text"></i>
                                <?php elseif ($act['type'] === 'money'): ?>
                                    <i class="bi bi-piggy-bank"></i>
                                <?php else: ?>
                                    <i class="bi bi-check2-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="activity-info">
                                <span class="activity-text"><?= $act['text']; ?></span>
                                <span class="activity-time"><?= date('F j, Y', strtotime($act['date'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
