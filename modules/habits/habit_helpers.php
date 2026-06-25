<?php
declare(strict_types=1);

function habitStatusOptions(): array
{
    return [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'missed' => 'Missed',
    ];
}

function habitPriorityOptions(): array
{
    return [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];
}

function habitFrequencyOptions(): array
{
    return [
        'Daily' => 'Daily',
        'Weekdays' => 'Weekdays',
        'Weekly' => 'Weekly',
        'Monthly' => 'Monthly',
        'Custom' => 'Custom',
    ];
}

function habitSortOptions(): array
{
    return [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'name' => 'Habit name',
        'status' => 'Status',
    ];
}

function habitDefaultFormData(): array
{
    return [
        'habit_name' => '',
        'category' => 'General',
        'target_frequency' => 'Daily',
        'completion_status' => 'pending',
        'priority' => 'medium',
        'habit_date' => date('Y-m-d'),
        'notes' => '',
    ];
}

function habitDataFromRequest(array $source): array
{
    return [
        'habit_name' => cleanInput((string) ($source['habit_name'] ?? '')),
        'category' => cleanInput((string) ($source['category'] ?? 'General')),
        'target_frequency' => cleanInput((string) ($source['target_frequency'] ?? 'Daily')),
        'completion_status' => cleanInput((string) ($source['completion_status'] ?? 'pending')),
        'priority' => cleanInput((string) ($source['priority'] ?? 'medium')),
        'habit_date' => cleanInput((string) ($source['habit_date'] ?? date('Y-m-d'))),
        'notes' => cleanInput((string) ($source['notes'] ?? '')),
    ];
}

function habitIsValidDate(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function habitValidateData(mysqli $connection, int $userId, array $data, ?int $ignoreHabitId = null): array
{
    $errors = [];

    if ($data['habit_name'] === '') {
        $errors[] = 'Please enter the habit name.';
    } elseif (mb_strlen($data['habit_name']) > 100) {
        $errors[] = 'Habit name must be 100 characters or fewer.';
    }

    if ($data['category'] === '') {
        $errors[] = 'Please enter a category.';
    } elseif (mb_strlen($data['category']) > 60) {
        $errors[] = 'Category must be 60 characters or fewer.';
    }

    if (!array_key_exists($data['target_frequency'], habitFrequencyOptions())) {
        $errors[] = 'Please choose a valid target frequency.';
    }

    if (!array_key_exists($data['completion_status'], habitStatusOptions())) {
        $errors[] = 'Please choose a valid completion status.';
    }

    if (!array_key_exists($data['priority'], habitPriorityOptions())) {
        $errors[] = 'Please choose a valid priority.';
    }

    if ($data['habit_date'] === '' || !habitIsValidDate($data['habit_date'])) {
        $errors[] = 'Please choose a valid habit date.';
    }

    if (mb_strlen($data['notes']) > 255) {
        $errors[] = 'Notes must be 255 characters or fewer.';
    }

    if (!$errors && habitDuplicateExists($connection, $userId, $data['habit_name'], $data['habit_date'], $ignoreHabitId)) {
        $errors[] = 'A habit with this name already exists on this date.';
    }

    return $errors;
}

function habitDuplicateExists(mysqli $connection, int $userId, string $habitName, string $habitDate, ?int $ignoreHabitId = null): bool
{
    if ($ignoreHabitId === null) {
        $stmt = $connection->prepare('SELECT habit_id FROM habit_records WHERE user_id = ? AND LOWER(habit_name) = LOWER(?) AND habit_date = ? LIMIT 1');
        $stmt->bind_param('iss', $userId, $habitName, $habitDate);
    } else {
        $stmt = $connection->prepare('SELECT habit_id FROM habit_records WHERE user_id = ? AND LOWER(habit_name) = LOWER(?) AND habit_date = ? AND habit_id <> ? LIMIT 1');
        $stmt->bind_param('issi', $userId, $habitName, $habitDate, $ignoreHabitId);
    }

    $stmt->execute();

    return (bool) $stmt->get_result()->fetch_assoc();
}

function habitLoadForUser(mysqli $connection, int $habitId, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT * FROM habit_records WHERE habit_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $habitId, $userId);
    $stmt->execute();
    $habit = $stmt->get_result()->fetch_assoc();

    return $habit ?: null;
}

function habitFiltersFromRequest(array $source): array
{
    $filters = [
        'search' => cleanInput((string) ($source['search'] ?? '')),
        'status' => cleanInput((string) ($source['status'] ?? '')),
        'frequency' => cleanInput((string) ($source['frequency'] ?? '')),
        'priority' => cleanInput((string) ($source['priority'] ?? '')),
        'category' => cleanInput((string) ($source['category'] ?? '')),
        'date_from' => cleanInput((string) ($source['date_from'] ?? '')),
        'date_to' => cleanInput((string) ($source['date_to'] ?? '')),
        'sort' => cleanInput((string) ($source['sort'] ?? 'newest')),
    ];

    if (!array_key_exists($filters['status'], habitStatusOptions())) {
        $filters['status'] = '';
    }

    if (!array_key_exists($filters['frequency'], habitFrequencyOptions())) {
        $filters['frequency'] = '';
    }

    if (!array_key_exists($filters['priority'], habitPriorityOptions())) {
        $filters['priority'] = '';
    }

    if ($filters['date_from'] !== '' && !habitIsValidDate($filters['date_from'])) {
        $filters['date_from'] = '';
    }

    if ($filters['date_to'] !== '' && !habitIsValidDate($filters['date_to'])) {
        $filters['date_to'] = '';
    }

    if (!array_key_exists($filters['sort'], habitSortOptions())) {
        $filters['sort'] = 'newest';
    }

    return $filters;
}

function habitFilterQuery(array $filters, int $userId): array
{
    $where = ['user_id = ?'];
    $types = 'i';
    $params = [$userId];

    if ($filters['search'] !== '') {
        $where[] = '(habit_name LIKE ? OR notes LIKE ?)';
        $types .= 'ss';
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }

    if ($filters['status'] !== '') {
        $where[] = 'completion_status = ?';
        $types .= 's';
        $params[] = $filters['status'];
    }

    if ($filters['frequency'] !== '') {
        $where[] = 'target_frequency = ?';
        $types .= 's';
        $params[] = $filters['frequency'];
    }

    if ($filters['priority'] !== '') {
        $where[] = 'priority = ?';
        $types .= 's';
        $params[] = $filters['priority'];
    }

    if ($filters['category'] !== '') {
        $where[] = 'category LIKE ?';
        $types .= 's';
        $params[] = '%' . $filters['category'] . '%';
    }

    if ($filters['date_from'] !== '') {
        $where[] = 'habit_date >= ?';
        $types .= 's';
        $params[] = $filters['date_from'];
    }

    if ($filters['date_to'] !== '') {
        $where[] = 'habit_date <= ?';
        $types .= 's';
        $params[] = $filters['date_to'];
    }

    return [
        'where' => implode(' AND ', $where),
        'types' => $types,
        'params' => $params,
    ];
}

function habitOrderBy(string $sort): string
{
    return match ($sort) {
        'oldest' => 'habit_date ASC, habit_id ASC',
        'name' => 'habit_name ASC, habit_date DESC, habit_id DESC',
        'status' => "FIELD(completion_status, 'pending', 'completed', 'missed'), habit_date DESC, habit_id DESC",
        default => 'habit_date DESC, habit_id DESC',
    };
}

function habitBindParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }

    $refs = [];
    foreach ($params as $key => &$value) {
        $refs[$key] = &$value;
    }

    $stmt->bind_param($types, ...$refs);
}

function habitCompletionPercentage(int $completed, int $total): int
{
    if ($total === 0) {
        return 0;
    }

    return (int) round(($completed / $total) * 100);
}

function habitBestStreak(mysqli $connection, int $userId): array
{
    $stmt = $connection->prepare("SELECT habit_name, habit_date FROM habit_records WHERE user_id = ? AND completion_status = 'completed' ORDER BY habit_name ASC, habit_date ASC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $best = ['days' => 0, 'habit_name' => 'No completed streak yet'];
    $currentHabit = '';
    $currentDays = 0;
    $previousDate = null;

    foreach ($records as $record) {
        $date = new DateTime($record['habit_date']);

        if ($record['habit_name'] !== $currentHabit) {
            $currentHabit = $record['habit_name'];
            $currentDays = 1;
            $previousDate = $date;
        } else {
            $diff = $previousDate instanceof DateTime ? (int) $previousDate->diff($date)->format('%a') : 0;
            $currentDays = $diff === 1 ? $currentDays + 1 : 1;
            $previousDate = $date;
        }

        if ($currentDays > $best['days']) {
            $best = [
                'days' => $currentDays,
                'habit_name' => $record['habit_name'],
            ];
        }
    }

    return $best;
}

function habitReturnQuery(array $filters): string
{
    $query = array_filter($filters, static fn ($value) => $value !== '' && $value !== 'newest');

    return http_build_query($query);
}
