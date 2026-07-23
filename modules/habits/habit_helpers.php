<?php
declare(strict_types=1);

function habitRealmOptions(): array
{
    return [
        'focus' => ['label' => 'Focus Garden', 'icon' => 'bi-leaf', 'description' => 'Study, reading, and assignments.'],
        'energy' => ['label' => 'Energy Grove', 'icon' => 'bi-lightning-charge', 'description' => 'Water, movement, sleep, and recovery.'],
        'mind' => ['label' => 'Mind Clearing', 'icon' => 'bi-wind', 'description' => 'Reflection, calm, and connection.'],
        'life_admin' => ['label' => 'Life Garden', 'icon' => 'bi-calendar2-check', 'description' => 'Planning, budgeting, and practical care.'],
    ];
}

function habitFrequencyOptions(): array
{
    return [
        'daily' => 'Every day',
        'weekdays' => 'Weekdays',
        'weekly' => 'Once a week',
        'custom' => 'Choose days',
    ];
}

function habitPriorityOptions(): array
{
    return ['low' => 'Gentle', 'medium' => 'Steady', 'high' => 'Important'];
}

function habitLogStatusOptions(): array
{
    return [
        'scheduled' => 'Ready today',
        'completed' => 'Completed',
        'skipped' => 'Skipped intentionally',
        'missed' => 'Missed',
    ];
}

function habitDayOptions(): array
{
    return [
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu',
        'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun',
    ];
}

function habitDefaultFormData(): array
{
    return [
        'habit_name' => '', 'realm' => 'focus', 'target_frequency' => 'daily',
        'scheduled_days' => array_keys(habitDayOptions()), 'preferred_time' => '',
        'duration_minutes' => '', 'motivation' => '', 'priority' => 'medium',
    ];
}

function habitDataFromRequest(array $source): array
{
    $days = $source['scheduled_days'] ?? [];
    if (!is_array($days)) {
        $days = [];
    }

    return [
        'habit_name' => cleanInput((string) ($source['habit_name'] ?? '')),
        'realm' => cleanInput((string) ($source['realm'] ?? 'focus')),
        'target_frequency' => cleanInput((string) ($source['target_frequency'] ?? 'daily')),
        'scheduled_days' => array_values(array_unique(array_map(static fn ($day): string => cleanInput((string) $day), $days))),
        'preferred_time' => cleanInput((string) ($source['preferred_time'] ?? '')),
        'duration_minutes' => cleanInput((string) ($source['duration_minutes'] ?? '')),
        'motivation' => cleanInput((string) ($source['motivation'] ?? '')),
        'priority' => cleanInput((string) ($source['priority'] ?? 'medium')),
    ];
}

function habitScheduledDaysForData(array $data): array
{
    return match ($data['target_frequency']) {
        'daily' => array_keys(habitDayOptions()),
        'weekdays' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        default => $data['scheduled_days'],
    };
}

function habitValidateData(array &$data): array
{
    $errors = [];
    if ($data['habit_name'] === '') {
        $errors[] = 'Give this quest a name.';
    } elseif (mb_strlen($data['habit_name']) > 100) {
        $errors[] = 'Quest names must be 100 characters or fewer.';
    }
    if (!array_key_exists($data['realm'], habitRealmOptions())) {
        $errors[] = 'Choose a valid sanctuary realm.';
    }
    if (!array_key_exists($data['target_frequency'], habitFrequencyOptions())) {
        $errors[] = 'Choose a valid schedule.';
    }
    if (!array_key_exists($data['priority'], habitPriorityOptions())) {
        $errors[] = 'Choose a valid priority.';
    }

    $data['scheduled_days'] = habitScheduledDaysForData($data);
    $validDays = array_keys(habitDayOptions());
    $invalidDays = array_diff($data['scheduled_days'], $validDays);
    if ($invalidDays) {
        $errors[] = 'Choose valid schedule days.';
    }
    if (in_array($data['target_frequency'], ['weekly', 'custom'], true) && !$data['scheduled_days']) {
        $errors[] = 'Choose at least one day for this quest.';
    }
    if ($data['target_frequency'] === 'weekly' && count($data['scheduled_days']) !== 1) {
        $errors[] = 'A weekly quest needs one scheduled day.';
    }
    if ($data['preferred_time'] !== '' && !preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $data['preferred_time'])) {
        $errors[] = 'Choose a valid preferred time.';
    }
    if ($data['duration_minutes'] !== '' && (!ctype_digit($data['duration_minutes']) || (int) $data['duration_minutes'] < 1 || (int) $data['duration_minutes'] > 1440)) {
        $errors[] = 'Duration must be between 1 and 1440 minutes.';
    }
    if (mb_strlen($data['motivation']) > 180) {
        $errors[] = 'Your motivation must be 180 characters or fewer.';
    }

    return $errors;
}

function habitLoadForUser(mysqli $connection, int $habitId, int $userId, bool $includeArchived = true): ?array
{
    $sql = 'SELECT * FROM habits WHERE habit_id = ? AND user_id = ?';
    if (!$includeArchived) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $connection->prepare($sql);
    $stmt->bind_param('ii', $habitId, $userId);
    $stmt->execute();
    $habit = $stmt->get_result()->fetch_assoc();

    return $habit ?: null;
}

function habitLoadLogForUser(mysqli $connection, int $logId, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT l.*, h.habit_name, h.realm, h.target_frequency, h.scheduled_days FROM habit_logs l INNER JOIN habits h ON h.habit_id = l.habit_id WHERE l.log_id = ? AND l.user_id = ? AND l.deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('ii', $logId, $userId);
    $stmt->execute();
    $log = $stmt->get_result()->fetch_assoc();

    return $log ?: null;
}

function habitDateKey(DateTimeInterface $date): string
{
    return strtolower($date->format('D'));
}

function habitIsScheduledOn(array $habit, DateTimeInterface $date): bool
{
    $days = array_filter(explode(',', (string) $habit['scheduled_days']));
    return in_array(habitDateKey($date), $days, true);
}

function habitEnsureLogsForRange(mysqli $connection, int $userId, DateTimeInterface $start, DateTimeInterface $end): void
{
    $today = new DateTimeImmutable('today');
    $lastDate = $end > $today ? $today : DateTimeImmutable::createFromInterface($end);
    if ($start > $lastDate) {
        return;
    }
    $stmt = $connection->prepare('SELECT habit_id, scheduled_days FROM habits WHERE user_id = ? AND is_active = 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $habits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if (!$habits) {
        return;
    }
    $insert = $connection->prepare('INSERT IGNORE INTO habit_logs (habit_id, user_id, scheduled_date) VALUES (?, ?, ?)');
    $period = new DatePeriod(DateTimeImmutable::createFromInterface($start), new DateInterval('P1D'), DateTimeImmutable::createFromInterface($lastDate)->modify('+1 day'));
    foreach ($period as $date) {
        foreach ($habits as $habit) {
            if (habitIsScheduledOn($habit, $date)) {
                $habitId = (int) $habit['habit_id'];
                $dateValue = $date->format('Y-m-d');
                $insert->bind_param('iis', $habitId, $userId, $dateValue);
                $insert->execute();
            }
        }
    }

    $todayValue = $today->format('Y-m-d');
    $markMissed = $connection->prepare("UPDATE habit_logs SET completion_status = 'missed' WHERE user_id = ? AND scheduled_date < ? AND completion_status = 'scheduled' AND deleted_at IS NULL");
    $markMissed->bind_param('is', $userId, $todayValue);
    $markMissed->execute();
}

function habitCurrentWeek(): array
{
    $today = new DateTimeImmutable('today');
    return [$today->modify('monday this week'), $today->modify('sunday this week')];
}

function habitRealmStats(mysqli $connection, int $userId, DateTimeInterface $start, DateTimeInterface $end): array
{
    $stats = [];
    foreach (habitRealmOptions() as $realm => $meta) {
        $stats[$realm] = array_merge($meta, ['realm' => $realm, 'planned' => 0, 'completed' => 0, 'percentage' => 0, 'state' => 'emerging']);
    }
    $stmt = $connection->prepare("SELECT h.realm, COUNT(l.log_id) AS planned, COALESCE(SUM(CASE WHEN l.completion_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed FROM habits h LEFT JOIN habit_logs l ON l.habit_id = h.habit_id AND l.deleted_at IS NULL AND l.scheduled_date BETWEEN ? AND ? WHERE h.user_id = ? AND h.is_active = 1 GROUP BY h.realm");
    $startValue = $start->format('Y-m-d');
    $endValue = $end->format('Y-m-d');
    $stmt->bind_param('ssi', $startValue, $endValue, $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        if (!isset($stats[$row['realm']])) {
            continue;
        }
        $planned = (int) $row['planned'];
        $completed = (int) $row['completed'];
        $percentage = $planned ? (int) round($completed / $planned * 100) : 0;
        $stats[$row['realm']]['planned'] = $planned;
        $stats[$row['realm']]['completed'] = $completed;
        $stats[$row['realm']]['percentage'] = $percentage;
        $stats[$row['realm']]['state'] = $percentage >= 70 ? 'thriving' : ($percentage >= 35 ? 'growing' : 'emerging');
    }

    return $stats;
}

function habitTodayQuests(mysqli $connection, int $userId, string $date, string $realm = ''): array
{
    $sql = 'SELECT l.*, h.habit_name, h.realm, h.preferred_time, h.duration_minutes, h.motivation, h.priority FROM habit_logs l INNER JOIN habits h ON h.habit_id = l.habit_id WHERE l.user_id = ? AND h.is_active = 1 AND l.deleted_at IS NULL AND l.scheduled_date = ?';
    $types = 'is';
    $params = [$userId, $date];
    if ($realm !== '' && array_key_exists($realm, habitRealmOptions())) {
        $sql .= ' AND h.realm = ?';
        $types .= 's';
        $params[] = $realm;
    }
    $sql .= " ORDER BY FIELD(l.completion_status, 'scheduled', 'missed', 'skipped', 'completed'), h.preferred_time IS NULL, h.preferred_time, h.priority DESC, h.habit_name";
    $stmt = $connection->prepare($sql);
    $refs = [];
    foreach ($params as $key => &$value) {
        $refs[$key] = &$value;
    }
    $stmt->bind_param($types, ...$refs);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function habitStreaks(mysqli $connection, int $userId): array
{
    $stmt = $connection->prepare("SELECT h.habit_id, h.habit_name, l.completion_status FROM habits h LEFT JOIN habit_logs l ON l.habit_id = h.habit_id AND l.deleted_at IS NULL WHERE h.user_id = ? ORDER BY h.habit_id, l.scheduled_date ASC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $grouped = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $id = (int) $row['habit_id'];
        if (!isset($grouped[$id])) {
            $grouped[$id] = ['habit_name' => $row['habit_name'], 'statuses' => []];
        }
        if ($row['completion_status'] !== null) {
            $grouped[$id]['statuses'][] = $row['completion_status'];
        }
    }
    $result = [];
    foreach ($grouped as $id => $habit) {
        $best = 0;
        $run = 0;
        foreach ($habit['statuses'] as $status) {
            if ($status === 'completed') {
                $run++;
                $best = max($best, $run);
            } elseif ($status !== 'scheduled') {
                $run = 0;
            }
        }
        $current = 0;
        foreach (array_reverse($habit['statuses']) as $status) {
            if ($status === 'completed') {
                $current++;
            } elseif ($status !== 'scheduled') {
                break;
            }
        }
        $result[$id] = ['current' => $current, 'best' => $best, 'habit_name' => $habit['habit_name']];
    }

    return $result;
}

function habitWeeklyStory(array $realmStats): string
{
    $best = null;
    foreach ($realmStats as $realm) {
        if ($realm['planned'] === 0) {
            continue;
        }
        if ($best === null || $realm['percentage'] > $best['percentage']) {
            $best = $realm;
        }
    }
    if ($best === null) {
        return 'Your sanctuary is waiting for its first small promise.';
    }
    if ($best['completed'] === 0) {
        return 'Every sanctuary begins with one gentle action. Choose the smallest quest first.';
    }
    return sprintf('%s is lighting the way: %d of %d planned quests completed this week.', $best['label'], $best['completed'], $best['planned']);
}

function habitFormatTime(?string $time): string
{
    if (!$time) {
        return 'Anytime';
    }
    return date('g:i A', strtotime($time));
}

function habitDisplaySchedule(array $habit): string
{
    $frequency = habitFrequencyOptions()[$habit['target_frequency']] ?? $habit['target_frequency'];
    if (in_array($habit['target_frequency'], ['weekly', 'custom'], true)) {
        $labels = habitDayOptions();
        $days = array_map(static fn ($day) => $labels[$day] ?? $day, array_filter(explode(',', (string) $habit['scheduled_days'])));
        return $frequency . ' · ' . implode(', ', $days);
    }
    return $frequency;
}

function habitJourneyStartFromRequest(array $source): DateTimeImmutable
{
    $candidate = cleanInput((string) ($source['week'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $candidate);
    if (!$date || $date->format('Y-m-d') !== $candidate) {
        return (new DateTimeImmutable('today'))->modify('monday this week');
    }
    return $date->modify('monday this week');
}

function habitJourneyRows(mysqli $connection, int $userId, DateTimeInterface $start, DateTimeInterface $end): array
{
    $stmt = $connection->prepare('SELECT h.*, l.log_id, l.scheduled_date, l.completion_status, l.reflection_note FROM habits h LEFT JOIN habit_logs l ON l.habit_id = h.habit_id AND l.deleted_at IS NULL AND l.scheduled_date BETWEEN ? AND ? WHERE h.user_id = ? AND h.is_active = 1 ORDER BY FIELD(h.realm, \'focus\', \'energy\', \'mind\', \'life_admin\'), h.habit_name, l.scheduled_date');
    $startValue = $start->format('Y-m-d');
    $endValue = $end->format('Y-m-d');
    $stmt->bind_param('ssi', $startValue, $endValue, $userId);
    $stmt->execute();
    $rows = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $id = (int) $row['habit_id'];
        if (!isset($rows[$id])) {
            $rows[$id] = ['habit' => $row, 'logs' => []];
        }
        if ($row['log_id'] !== null) {
            $rows[$id]['logs'][$row['scheduled_date']] = $row;
        }
    }
    return $rows;
}
