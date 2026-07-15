<?php
declare(strict_types=1);

function journalTemplateOptions(): array
{
    return [
        'blank' => [
            'label' => 'Blank Page',
            'description' => 'Start with a clean page and write freely.',
            'content' => '',
        ],
        'daily_reflection' => [
            'label' => 'Daily Reflection',
            'description' => 'Review the day, its lessons, and what comes next.',
            'content' => "Highlights of the day\n\nWhat challenged me?\n\nWhat did I learn?\n\nTomorrow's focus",
        ],
        'gratitude' => [
            'label' => 'Gratitude Journal',
            'description' => 'Notice the people, moments, and wins you appreciate.',
            'content' => "Today I'm grateful for...\n\nA positive moment I want to remember\n\nOne small win today",
        ],
        'mood_checkin' => [
            'label' => 'Mood Check-in',
            'description' => 'Explore how you feel and what you need right now.',
            'content' => "How am I feeling right now?\n\nWhat may have influenced this feeling?\n\nWhat do I need?\n\nOne helpful action I can take",
        ],
        'study_notes' => [
            'label' => 'Study Notes',
            'description' => 'Organize a topic, key ideas, questions, and next steps.',
            'content' => "Topic\n\nKey ideas\n\nDetailed notes\n\nQuestions to revisit\n\nNext steps",
        ],
    ];
}

function journalSortOptions(): array
{
    return [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
    ];
}

function journalDefaultFormData(): array
{
    return [
        'title' => '',
        'content' => '',
        'mood_status' => '',
        'entry_date' => date('Y-m-d'),
        'template_key' => 'blank',
    ];
}

function journalDataFromRequest(array $source): array
{
    return [
        'title' => cleanInput((string) ($source['title'] ?? '')),
        'content' => trim((string) ($source['content'] ?? '')),
        'mood_status' => cleanInput((string) ($source['mood_status'] ?? '')),
        'entry_date' => cleanInput((string) ($source['entry_date'] ?? '')),
        'template_key' => cleanInput((string) ($source['template_key'] ?? 'blank')),
    ];
}

function journalIsValidDate(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function journalValidateData(array $data): array
{
    $errors = [];
    $title = (string) ($data['title'] ?? '');
    $content = (string) ($data['content'] ?? '');
    $mood = (string) ($data['mood_status'] ?? '');
    $entryDate = (string) ($data['entry_date'] ?? '');
    $templateKey = (string) ($data['template_key'] ?? 'blank');

    if ($title === '') {
        $errors[] = 'Please enter a journal title.';
    } elseif (mb_strlen($title) > 120) {
        $errors[] = 'Journal title must be 120 characters or fewer.';
    }

    if ($content === '') {
        $errors[] = 'Please write some journal content.';
    } elseif (mb_strlen($content) > 10000) {
        $errors[] = 'Journal content must be 10,000 characters or fewer.';
    }

    if ($mood === '') {
        $errors[] = 'Please describe your mood.';
    } elseif (mb_strlen($mood) > 50) {
        $errors[] = 'Mood must be 50 characters or fewer.';
    }

    if ($entryDate === '' || !journalIsValidDate($entryDate)) {
        $errors[] = 'Please choose a valid entry date.';
    }

    if (!array_key_exists($templateKey, journalTemplateOptions())) {
        $errors[] = 'Please choose a valid journal template.';
    }

    return $errors;
}

function journalFiltersFromRequest(array $source): array
{
    $filters = [
        'search' => cleanInput((string) ($source['search'] ?? '')),
        'mood' => cleanInput((string) ($source['mood'] ?? '')),
        'date_from' => cleanInput((string) ($source['date_from'] ?? '')),
        'date_to' => cleanInput((string) ($source['date_to'] ?? '')),
        'sort' => cleanInput((string) ($source['sort'] ?? 'newest')),
    ];

    if ($filters['date_from'] !== '' && !journalIsValidDate($filters['date_from'])) {
        $filters['date_from'] = '';
    }

    if ($filters['date_to'] !== '' && !journalIsValidDate($filters['date_to'])) {
        $filters['date_to'] = '';
    }

    if (!array_key_exists($filters['sort'], journalSortOptions())) {
        $filters['sort'] = 'newest';
    }

    return $filters;
}

function journalFilterQuery(array $filters, int $userId): array
{
    $where = ['user_id = ?'];
    $types = 'i';
    $params = [$userId];

    if ($filters['search'] !== '') {
        $where[] = '(title LIKE ? OR content LIKE ?)';
        $types .= 'ss';
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }

    if ($filters['mood'] !== '') {
        $where[] = 'mood_status = ?';
        $types .= 's';
        $params[] = $filters['mood'];
    }

    if ($filters['date_from'] !== '') {
        $where[] = 'entry_date >= ?';
        $types .= 's';
        $params[] = $filters['date_from'];
    }

    if ($filters['date_to'] !== '') {
        $where[] = 'entry_date <= ?';
        $types .= 's';
        $params[] = $filters['date_to'];
    }

    return [
        'where' => implode(' AND ', $where),
        'types' => $types,
        'params' => $params,
    ];
}

function journalOrderBy(string $sort): string
{
    return $sort === 'oldest'
        ? 'entry_date ASC, journal_id ASC'
        : 'entry_date DESC, journal_id DESC';
}

function journalBindParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }

    $references = [];
    foreach ($params as $key => &$value) {
        $references[$key] = &$value;
    }

    $stmt->bind_param($types, ...$references);
}

function journalLoadForUser(mysqli $connection, int $journalId, int $userId): ?array
{
    $stmt = $connection->prepare(
        'SELECT journal_id, user_id, title, content, mood_status, entry_date, created_at, updated_at '
        . 'FROM journal_entries WHERE journal_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $journalId, $userId);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();

    return $entry ?: null;
}

function journalMoodSuggestions(mysqli $connection, int $userId): array
{
    $stmt = $connection->prepare(
        "SELECT DISTINCT mood_status FROM journal_entries WHERE user_id = ? AND mood_status <> '' ORDER BY mood_status ASC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return array_map(static fn (array $row): string => (string) $row['mood_status'], $rows);
}

function journalPreview(string $content, int $limit = 170): string
{
    $normalized = preg_replace('/\s+/u', ' ', trim($content)) ?? trim($content);

    if ($limit < 1 || mb_strlen($normalized) <= $limit) {
        return $normalized;
    }

    return mb_substr($normalized, 0, $limit) . '…';
}

function journalReturnQuery(array $filters): string
{
    $active = array_filter(
        $filters,
        static fn (mixed $value, string $key): bool => $value !== '' && !($key === 'sort' && $value === 'newest'),
        ARRAY_FILTER_USE_BOTH
    );

    return http_build_query($active);
}
