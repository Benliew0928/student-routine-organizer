<?php
declare(strict_types=1);

function exerciseActivityOptions(): array
{
    return [
        'Running' => 'Running',
        'Cycling' => 'Cycling',
        'Gym Session' => 'Gym Session',
        'Swimming' => 'Swimming',
        'Walking' => 'Walking',
        'Yoga' => 'Yoga',
        'Basketball' => 'Basketball',
        'Pickleball' => 'Pickleball',
        'Badminton' => 'Badminton',
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

function exerciseActivityClass(string $activityType): string
{
    $classes = [
        'Running' => 'running',
        'Jogging' => 'running',
        'Cycling' => 'cycling',
        'Gym Session' => 'gym-session',
        'Swimming' => 'swimming',
        'Walking' => 'walking',
        'Yoga' => 'yoga',
        'Basketball' => 'basketball',
        'Pickleball' => 'pickleball',
        'Badminton' => 'badminton',
        'Other' => 'other',
    ];

    return $classes[$activityType] ?? 'other';
}

function exerciseActivityIcon(string $activityType): string
{
    $icons = [
        'Running' => 'bi-person-walking',
        'Jogging' => 'bi-person-walking',
        'Cycling' => 'bi-bicycle',
        'Gym Session' => 'bi-heart-pulse',
        'Swimming' => 'bi-water',
        'Walking' => 'bi-person-walking',
        'Yoga' => 'bi-flower1',
        'Basketball' => 'bi-circle',
        'Pickleball' => 'bi-circle-square',
        'Badminton' => 'bi-lightning-charge',
        'Other' => 'bi-activity',
    ];

    return $icons[$activityType] ?? 'bi-activity';
}

function exerciseActivityIconMarkup(string $activityType): string
{
    $type = match ($activityType) {
        'Running', 'Jogging' => 'running',
        'Basketball' => 'basketball',
        'Badminton' => 'badminton',
        'Pickleball' => 'pickleball',
        'Swimming' => 'swimming',
        'Yoga' => 'yoga',
        'Gym Session' => 'gym',
        'Cycling' => 'cycling',
        'Walking' => 'walking',
        default => 'other',
    };

    $icons = [
        'running' => '<img src="' . BASE_URL . '/assets/img/exercise-running-icon.png?v=20260725-transparent" alt="" aria-hidden="true">',
        'basketball' => '<svg viewBox="0 0 32 32" aria-hidden="true"><circle class="fill" cx="16" cy="16" r="13"/><path class="line light" d="M7 7c6 4 12 4 18 0M7 25c6-4 12-4 18 0M16 3c-4 8-4 18 0 26M3 16c8-4 18-4 26 0"/></svg>',
        'badminton' => '<img src="' . BASE_URL . '/assets/img/exercise-badminton-icon.png?v=20260725-transparent" alt="" aria-hidden="true">',
        'pickleball' => '<img src="' . BASE_URL . '/assets/img/exercise-pickleball-icon.png?v=20260725-transparent" alt="" aria-hidden="true">',
        'swimming' => '<svg viewBox="0 0 32 32" aria-hidden="true"><circle class="fill" cx="22" cy="9" r="4"/><path class="line dark" d="M4 17c5-4 10-4 15 0 2 1.5 5 2 8-1"/><path class="accent" d="M3 23c3 2 6 2 9 0 3-2 6-2 9 0 3 2 6 2 9 0M3 28c3 2 6 2 9 0 3-2 6-2 9 0 3 2 6 2 9 0"/></svg>',
        'yoga' => '<img src="' . BASE_URL . '/assets/img/exercise-yoga-icon.png?v=20260725-transparent" alt="" aria-hidden="true">',
        'gym' => '<svg viewBox="0 0 32 32" aria-hidden="true"><path class="line dark" d="M4 13h24M11 9v8M21 9v8M8 11v4M24 11v4M14 13c0 5 4 5 4 0"/><path class="accent" d="M13 20v8M19 20v8"/></svg>',
        'cycling' => '<svg viewBox="0 0 32 32" aria-hidden="true"><circle class="line dark" cx="9" cy="23" r="5"/><circle class="line dark" cx="24" cy="23" r="5"/><path class="line dark" d="M9 23l5-9h5l5 9M14 14l6 9M17 10h4M15 8l3 2"/><circle class="fill" cx="17" cy="6" r="3"/><path class="accent" d="M17 10l-5 4"/></svg>',
        'walking' => '<img src="' . BASE_URL . '/assets/img/exercise-walking-icon.png?v=20260725-transparent" alt="" aria-hidden="true">',
        'other' => '<svg viewBox="0 0 32 32" aria-hidden="true"><circle class="fill" cx="16" cy="16" r="12"/><path class="line light" d="M10 16h12M16 10v12"/></svg>',
    ];

    return '<span class="exercise-svg-icon exercise-svg-icon-' . $type . '">' . $icons[$type] . '</span>';
}

function exerciseDefaultFormData(): array
{
    return [
        'activity_type' => 'Running',
        'duration_minutes' => '',
        'calories_burned' => '',
        'exercise_date' => date('Y-m-d'),
        'custom_activity_type' => '',
    ];
}

function exerciseDataFromRequest(array $source): array
{
    $activityType = cleanInput((string) ($source['activity_type'] ?? 'Running'));
    $customActivityType = cleanInput((string) ($source['custom_activity_type'] ?? ''));

    if ($activityType === 'Other' && $customActivityType !== '') {
        $activityType = $customActivityType;
    }

    if ($activityType === 'Jogging') {
        $activityType = 'Running';
    }

    return [
        'activity_type' => $activityType,
        'duration_minutes' => cleanInput((string) ($source['duration_minutes'] ?? '')),
        'calories_burned' => cleanInput((string) ($source['calories_burned'] ?? '')),
        'exercise_date' => cleanInput((string) ($source['exercise_date'] ?? date('Y-m-d'))),
        'custom_activity_type' => $customActivityType,
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
    } elseif ($data['activity_type'] === 'Other' && ($data['custom_activity_type'] ?? '') === '') {
        $errors[] = 'Please write the exercise name for Other.';
    } elseif (strlen($data['activity_type']) > 60) {
        $errors[] = 'Activity type cannot be more than 60 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 &\/().+-]*$/', $data['activity_type'])) {
        $errors[] = 'Activity type can only contain letters, numbers, spaces, and simple symbols.';
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
        $where[] = 'activity_type LIKE ?';
        $types .= 's';
        $search = '%' . $filters['search'] . '%';
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

function exerciseEnsureBlogTable(mysqli $connection): void
{
    $connection->query(
        "CREATE TABLE IF NOT EXISTS exercise_blogs (
            blog_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(140) NOT NULL,
            content TEXT NOT NULL,
            blog_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_exercise_blog_user
                FOREIGN KEY (user_id) REFERENCES users(user_id)
                ON DELETE CASCADE,
            INDEX idx_exercise_blog_user_date (user_id, blog_date)
        )"
    );
}

function exerciseDefaultBlogData(): array
{
    return [
        'title' => '',
        'content' => '',
        'blog_date' => date('Y-m-d'),
    ];
}

function exerciseBlogDataFromRequest(array $source): array
{
    return [
        'title' => cleanInput((string) ($source['title'] ?? '')),
        'content' => cleanInput((string) ($source['content'] ?? '')),
        'blog_date' => cleanInput((string) ($source['blog_date'] ?? date('Y-m-d'))),
    ];
}

function exerciseValidateBlogData(array $data): array
{
    $errors = [];

    if ($data['title'] === '') {
        $errors[] = 'Please enter a blog title.';
    } elseif (mb_strlen($data['title']) > 140) {
        $errors[] = 'Blog title must be 140 characters or fewer.';
    }

    if ($data['content'] === '') {
        $errors[] = 'Please write your blog content.';
    } elseif (mb_strlen($data['content']) > 5000) {
        $errors[] = 'Blog content must be 5000 characters or fewer.';
    }

    if ($data['blog_date'] === '' || !exerciseIsValidDate($data['blog_date'])) {
        $errors[] = 'Please choose a valid blog date.';
    }

    return $errors;
}

function exerciseBlogLoadForUser(mysqli $connection, int $blogId, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT blog_id, user_id, title, content, blog_date, created_at, updated_at FROM exercise_blogs WHERE blog_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $blogId, $userId);
    $stmt->execute();
    $blog = $stmt->get_result()->fetch_assoc();

    return $blog ?: null;
}
