<?php
declare(strict_types=1);

function exerciseActivityOptions(): array
{
    return [
        'Jogging' => 'Jogging',
        'Cycling' => 'Cycling',
        'Gym Session' => 'Gym Session',
        'Swimming' => 'Swimming',
        'Walking' => 'Walking',
        'Yoga' => 'Yoga',
        'Sports' => 'Sports',
        'Other' => 'Other',
    ];
}

function exerciseSortOptions(): array
{
    return [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'duration_high' => 'Longest duration',
        'calories_high' => 'Most calories',
        'activity' => 'Activity type',
    ];
}

function exerciseDefaultFormData(): array
{
    return [
        'activity_type' => 'Jogging',
        'duration_minutes' => '',
        'calories_burned' => '',
        'exercise_date' => date('Y-m-d'),
        'notes' => '',
    ];
}

function exerciseDataFromRequest(array $source): array
{
    return [
        'activity_type' => cleanInput((string) ($source['activity_type'] ?? 'Jogging')),
        'duration_minutes' => cleanInput((string) ($source['duration_minutes'] ?? '')),
        'calories_burned' => cleanInput((string) ($source['calories_burned'] ?? '')),
        'exercise_date' => cleanInput((string) ($source['exercise_date'] ?? date('Y-m-d'))),
        'notes' => cleanInput((string) ($source['notes'] ?? '')),
    ];
}

function exerciseIsValidDate(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function exerciseValidateData(array $data): array
{
    $errors = [];

    if ($data['activity_type'] === '') {
        $errors[] = 'Please choose an activity type.';
    } elseif (!array_key_exists($data['activity_type'], exerciseActivityOptions())) {
        $errors[] = 'Please choose a valid activity type.';
    }

    if ($data['duration_minutes'] === '' || !ctype_digit($data['duration_minutes']) || (int) $data['duration_minutes'] <= 0) {
        $errors[] = 'Duration must be a whole number greater than 0.';
    } elseif ((int) $data['duration_minutes'] > 1440) {
        $errors[] = 'Duration cannot be more than 1440 minutes.';
    }

    if ($data['calories_burned'] === '' || !ctype_digit($data['calories_burned']) || (int) $data['calories_burned'] < 0) {
        $errors[] = 'Calories burned must be a whole number of 0 or more.';
    } elseif ((int) $data['calories_burned'] > 20000) {
        $errors[] = 'Calories burned cannot be more than 20000.';
    }

    if ($data['exercise_date'] === '' || !exerciseIsValidDate($data['exercise_date'])) {
        $errors[] = 'Please choose a valid exercise date.';
    }

    if (mb_strlen($data['notes']) > 255) {
        $errors[] = 'Notes must be 255 characters or fewer.';
    }

    return $errors;
}

function exerciseLoadForUser(mysqli $connection, int $exerciseId, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT * FROM exercise_records WHERE exercise_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $exerciseId, $userId);
    $stmt->execute();
    $exercise = $stmt->get_result()->fetch_assoc();

    return $exercise ?: null;
}

function exerciseFiltersFromRequest(array $source): array
{
    $filters = [
        'search' => cleanInput((string) ($source['search'] ?? '')),
        'activity_type' => cleanInput((string) ($source['activity_type'] ?? '')),
        'date_from' => cleanInput((string) ($source['date_from'] ?? '')),
        'date_to' => cleanInput((string) ($source['date_to'] ?? '')),
        'sort' => cleanInput((string) ($source['sort'] ?? 'newest')),
    ];

    if ($filters['activity_type'] !== '' && !array_key_exists($filters['activity_type'], exerciseActivityOptions())) {
        $filters['activity_type'] = '';
    }

    if ($filters['date_from'] !== '' && !exerciseIsValidDate($filters['date_from'])) {
        $filters['date_from'] = '';
    }

    if ($filters['date_to'] !== '' && !exerciseIsValidDate($filters['date_to'])) {
        $filters['date_to'] = '';
    }

    if (!array_key_exists($filters['sort'], exerciseSortOptions())) {
        $filters['sort'] = 'newest';
    }

    return $filters;
}

function exerciseFilterQuery(array $filters, int $userId): array
{
    $where = ['user_id = ?'];
    $types = 'i';
    $params = [$userId];

    if ($filters['search'] !== '') {
        $where[] = '(activity_type LIKE ? OR notes LIKE ?)';
        $types .= 'ss';
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }

    if ($filters['activity_type'] !== '') {
        $where[] = 'activity_type = ?';
        $types .= 's';
        $params[] = $filters['activity_type'];
    }

    if ($filters['date_from'] !== '') {
        $where[] = 'exercise_date >= ?';
        $types .= 's';
        $params[] = $filters['date_from'];
    }

    if ($filters['date_to'] !== '') {
        $where[] = 'exercise_date <= ?';
        $types .= 's';
        $params[] = $filters['date_to'];
    }

    return [
        'where' => implode(' AND ', $where),
        'types' => $types,
        'params' => $params,
    ];
}

function exerciseOrderBy(string $sort): string
{
    return match ($sort) {
        'oldest' => 'exercise_date ASC, exercise_id ASC',
        'duration_high' => 'duration_minutes DESC, exercise_date DESC, exercise_id DESC',
        'calories_high' => 'calories_burned DESC, exercise_date DESC, exercise_id DESC',
        'activity' => 'activity_type ASC, exercise_date DESC, exercise_id DESC',
        default => 'exercise_date DESC, exercise_id DESC',
    };
}

function exerciseBindParams(mysqli_stmt $stmt, string $types, array &$params): void
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

function exerciseReturnQuery(array $filters): string
{
    $query = array_filter($filters, static fn ($value) => $value !== '' && $value !== 'newest');

    return http_build_query($query);
}

function exerciseMostFrequentActivity(mysqli $connection, int $userId): string
{
    $stmt = $connection->prepare('SELECT activity_type, COUNT(*) AS total FROM exercise_records WHERE user_id = ? GROUP BY activity_type ORDER BY total DESC, activity_type ASC LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_assoc();

    return $activity ? $activity['activity_type'] : 'No activity yet';
}
