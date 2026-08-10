<?php
declare(strict_types=1);

function moneyTransactionTypeOptions(): array
{
    return [
        'expense' => 'Expense',
        'income' => 'Income',
    ];
}

function moneyExpenseCategoryOptions(): array
{
    return [
        'Food' => 'Food',
        'Transport' => 'Transport',
        'Shopping' => 'Shopping',
        'Bills' => 'Bills',
        'Education' => 'Education',
        'Entertainment' => 'Entertainment',
        'Healthcare' => 'Healthcare',
        'Others' => 'Others',
    ];
}

function moneyIncomeCategoryOptions(): array
{
    return [
        'Salary' => 'Salary',
        'Allowance' => 'Allowance',
        'Freelance' => 'Freelance',
        'Others' => 'Others',
    ];
}

function moneyCategoryOptions(): array
{
    return moneyExpenseCategoryOptions() + moneyIncomeCategoryOptions();
}

function moneyCategoriesForTransactionType(string $transactionType): array
{
    return $transactionType === 'income' ? moneyIncomeCategoryOptions() : moneyExpenseCategoryOptions();
}

function moneyCategoryTypes(string $category): array
{
    $types = [];
    if (array_key_exists($category, moneyIncomeCategoryOptions())) {
        $types[] = 'income';
    }
    if (array_key_exists($category, moneyExpenseCategoryOptions())) {
        $types[] = 'expense';
    }

    return $types;
}

function moneySortOptions(): array
{
    return [
        'newest' => 'Newest date first',
        'oldest' => 'Oldest date first',
        'amount_high' => 'Highest amount first',
        'amount_low' => 'Lowest amount first',
        'category' => 'Category (A-Z)',
    ];
}

function moneyDefaultFormData(): array
{
    return [
        'amount' => '',
        'category' => 'Food',
        'description' => '',
        'transaction_type' => 'expense',
        'transaction_date' => date('Y-m-d'),
    ];
}

function moneyDataFromRequest(array $source): array
{
    return [
        'amount' => cleanInput((string) ($source['amount'] ?? '')),
        'category' => cleanInput((string) ($source['category'] ?? 'Food')),
        'description' => cleanInput((string) ($source['description'] ?? '')),
        'transaction_type' => cleanInput((string) ($source['transaction_type'] ?? 'expense')),
        'transaction_date' => cleanInput((string) ($source['transaction_date'] ?? date('Y-m-d'))),
    ];
}

function moneyIsValidDate(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function moneyValidateData(array $data): array
{
    $errors = [];

    if ($data['amount'] === '') {
        $errors[] = 'Please enter an amount.';
    } elseif (!is_numeric($data['amount'])) {
        $errors[] = 'Amount must be a valid number.';
    } else {
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0.';
        } elseif ($amount > 99999999.99) {
            $errors[] = 'Amount cannot exceed 99,999,999.99.';
        }
    }

    if ($data['category'] === '' || !array_key_exists($data['category'], moneyCategoriesForTransactionType($data['transaction_type']))) {
        $errors[] = 'Please select a valid category from the dropdown.';
    }

    if (!array_key_exists($data['transaction_type'], moneyTransactionTypeOptions())) {
        $errors[] = 'Please select a valid transaction type (income or expense).';
    }

    if ($data['transaction_date'] === '' || !moneyIsValidDate($data['transaction_date'])) {
        $errors[] = 'Please choose a valid transaction date.';
    }

    if (mb_strlen($data['description']) > 255) {
        $errors[] = 'Description must be 255 characters or fewer.';
    }

    return $errors;
}

function moneyLoadForUser(mysqli $connection, int $transactionId, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT transaction_id, user_id, amount, category, description, transaction_type, transaction_date, created_at, updated_at FROM money_transactions WHERE transaction_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $transactionId, $userId);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    return $transaction ?: null;
}

function moneySavingsGoalForUser(mysqli $connection, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT g.*, COALESCE(SUM(c.amount), 0) AS saved_amount, COUNT(c.contribution_id) AS contribution_count FROM money_savings_goals g LEFT JOIN money_savings_contributions c ON c.goal_id = g.goal_id WHERE g.user_id = ? AND g.status = "active" GROUP BY g.goal_id ORDER BY CASE WHEN g.target_date IS NULL THEN 1 ELSE 0 END, g.target_date ASC, g.updated_at DESC LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $goal = $stmt->get_result()->fetch_assoc();
    return $goal ?: null;
}

function moneySavingsGoalsForUser(mysqli $connection, int $userId): array
{
    $stmt = $connection->prepare('SELECT g.*, COALESCE(SUM(c.amount), 0) AS saved_amount, COUNT(c.contribution_id) AS contribution_count FROM money_savings_goals g LEFT JOIN money_savings_contributions c ON c.goal_id = g.goal_id WHERE g.user_id = ? GROUP BY g.goal_id ORDER BY FIELD(g.status, "active", "paused", "completed", "archived"), CASE WHEN g.target_date IS NULL THEN 1 ELSE 0 END, g.target_date ASC, g.updated_at DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function moneySavingsGoalsByStatus(mysqli $connection, int $userId, array $statuses): array
{
    $allowedStatuses = ['active', 'paused', 'completed', 'archived'];
    $requestedStatuses = array_values(array_intersect($allowedStatuses, $statuses));
    if (!$requestedStatuses) {
        return [];
    }

    return array_values(array_filter(
        moneySavingsGoalsForUser($connection, $userId),
        static fn (array $goal): bool => in_array($goal['status'], $requestedStatuses, true)
    ));
}

function moneySavingsGoalProgress(array $goal): float
{
    $target = (float) ($goal['target_amount'] ?? 0);
    $saved = (float) ($goal['saved_amount'] ?? 0);
    return $target > 0 ? min(100, round(($saved / $target) * 100, 1)) : 0.0;
}

function moneySavingsContributions(mysqli $connection, int $goalId, int $userId): array
{
    $stmt = $connection->prepare('SELECT contribution_id, amount, note, contribution_date FROM money_savings_contributions WHERE goal_id = ? AND user_id = ? ORDER BY contribution_date DESC, contribution_id DESC');
    $stmt->bind_param('ii', $goalId, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function moneySavingsGoalById(mysqli $connection, int $goalId, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT goal_id, user_id, goal_name, target_amount, target_date, weekly_amount, auto_save_enabled, reminders_enabled, status, created_at, completed_at FROM money_savings_goals WHERE goal_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $goalId, $userId);
    $stmt->execute();
    $goal = $stmt->get_result()->fetch_assoc();
    return $goal ?: null;
}

function moneyLatestSavingsGoalForUser(mysqli $connection, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT g.*, COALESCE(SUM(c.amount), 0) AS saved_amount, COUNT(c.contribution_id) AS contribution_count FROM money_savings_goals g LEFT JOIN money_savings_contributions c ON c.goal_id = g.goal_id WHERE g.user_id = ? GROUP BY g.goal_id ORDER BY CASE WHEN g.status = "active" THEN 0 ELSE 1 END, g.updated_at DESC LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $goal = $stmt->get_result()->fetch_assoc();
    return $goal ?: null;
}

function moneySavingsContributionById(mysqli $connection, int $contributionId, int $userId): ?array
{
    $stmt = $connection->prepare('SELECT contribution_id, goal_id, amount, note, contribution_date FROM money_savings_contributions WHERE contribution_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $contributionId, $userId);
    $stmt->execute();
    $contribution = $stmt->get_result()->fetch_assoc();
    return $contribution ?: null;
}

function moneyFiltersFromRequest(array $source): array
{
    $filters = [
        'search' => cleanInput((string) ($source['search'] ?? '')),
        'transaction_type' => cleanInput((string) ($source['transaction_type'] ?? '')),
        'category' => cleanInput((string) ($source['category'] ?? '')),
        'date_from' => cleanInput((string) ($source['date_from'] ?? '')),
        'date_to' => cleanInput((string) ($source['date_to'] ?? '')),
        'period_year' => cleanInput((string) ($source['period_year'] ?? '')),
        'period_month' => cleanInput((string) ($source['period_month'] ?? '')),
        'period_date' => cleanInput((string) ($source['period_date'] ?? '')),
        'sort' => cleanInput((string) ($source['sort'] ?? 'newest')),
    ];

    if ($filters['transaction_type'] !== '' && !array_key_exists($filters['transaction_type'], moneyTransactionTypeOptions())) {
        $filters['transaction_type'] = '';
    }

    if ($filters['category'] !== '' && !array_key_exists($filters['category'], moneyCategoryOptions())) {
        $filters['category'] = '';
    }

    if ($filters['date_from'] !== '' && !moneyIsValidDate($filters['date_from'])) {
        $filters['date_from'] = '';
    }

    if ($filters['date_to'] !== '' && !moneyIsValidDate($filters['date_to'])) {
        $filters['date_to'] = '';
    }

    if ($filters['period_year'] !== '' && (!ctype_digit($filters['period_year']) || (int) $filters['period_year'] < 2000 || (int) $filters['period_year'] > 2100)) {
        $filters['period_year'] = '';
    }

    if ($filters['period_month'] !== '' && (!ctype_digit($filters['period_month']) || (int) $filters['period_month'] < 1 || (int) $filters['period_month'] > 12)) {
        $filters['period_month'] = '';
    }

    if ($filters['period_month'] !== '' && $filters['period_year'] === '') {
        $filters['period_month'] = '';
    }

    if ($filters['period_date'] !== '' && !moneyIsValidDate($filters['period_date'])) {
        $filters['period_date'] = '';
    }

    if (!array_key_exists($filters['sort'], moneySortOptions())) {
        $filters['sort'] = 'newest';
    }

    return $filters;
}

function moneyAppendPeriodFilter(array $filters, array &$where, string &$types, array &$params): void
{
    if (($filters['period_date'] ?? '') !== '') {
        $where[] = 'transaction_date = ?';
        $types .= 's';
        $params[] = $filters['period_date'];
        return;
    }

    if (($filters['period_year'] ?? '') === '') {
        return;
    }

    $year = (int) $filters['period_year'];
    $month = ($filters['period_month'] ?? '') === '' ? null : (int) $filters['period_month'];
    $startDate = $month === null ? sprintf('%04d-01-01', $year) : sprintf('%04d-%02d-01', $year, $month);
    $endDate = $month === null
        ? sprintf('%04d-12-31', $year)
        : date('Y-m-t', strtotime($startDate));

    $where[] = 'transaction_date BETWEEN ? AND ?';
    $types .= 'ss';
    $params[] = $startDate;
    $params[] = $endDate;
}

function moneyFilterQuery(array $filters, int $userId): array
{
    $where = ['user_id = ?'];
    $types = 'i';
    $params = [$userId];

    if ($filters['search'] !== '') {
        $where[] = '(category LIKE ? OR description LIKE ? OR CAST(amount AS CHAR) LIKE ?)';
        $types .= 'sss';
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    if ($filters['transaction_type'] !== '') {
        $where[] = 'transaction_type = ?';
        $types .= 's';
        $params[] = $filters['transaction_type'];
    }

    if ($filters['category'] !== '') {
        $where[] = 'category = ?';
        $types .= 's';
        $params[] = $filters['category'];
    }

    if ($filters['date_from'] !== '') {
        $where[] = 'transaction_date >= ?';
        $types .= 's';
        $params[] = $filters['date_from'];
    }

    if ($filters['date_to'] !== '') {
        $where[] = 'transaction_date <= ?';
        $types .= 's';
        $params[] = $filters['date_to'];
    }

    moneyAppendPeriodFilter($filters, $where, $types, $params);

    return [
        'where' => implode(' AND ', $where),
        'types' => $types,
        'params' => $params,
    ];
}

function moneyOrderBy(string $sort): string
{
    return match ($sort) {
        'oldest' => 'transaction_date ASC, transaction_id ASC',
        'amount_high' => 'amount DESC, transaction_date DESC, transaction_id DESC',
        'amount_low' => 'amount ASC, transaction_date DESC, transaction_id DESC',
        'category' => 'category ASC, transaction_date DESC, transaction_id DESC',
        default => 'transaction_date DESC, transaction_id DESC',
    };
}

function moneyBindParams(mysqli_stmt $stmt, string $types, array &$params): void
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

function moneyReturnQuery(array $filters): string
{
    $query = array_filter($filters, static fn ($value) => $value !== '' && $value !== 'newest');

    return http_build_query($query);
}

function moneyGetSummary(mysqli $connection, int $userId, array $filters = []): array
{
    $where = ['user_id = ?'];
    $types = 'i';
    $params = [$userId];
    moneyAppendPeriodFilter($filters, $where, $types, $params);
    $stmt = $connection->prepare("SELECT 
        COUNT(*) AS total_count,
        COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense,
        COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN 1 ELSE 0 END), 0) AS income_count,
        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN 1 ELSE 0 END), 0) AS expense_count
        FROM money_transactions 
        WHERE " . implode(' AND ', $where));
    moneyBindParams($stmt, $types, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];

    $income = (float) ($row['total_income'] ?? 0);
    $expense = (float) ($row['total_expense'] ?? 0);
    $totalFlow = $income + $expense;

    $incomePct = $totalFlow > 0 ? (int) round(($income / $totalFlow) * 100) : 0;
    $expensePct = $totalFlow > 0 ? (int) round(($expense / $totalFlow) * 100) : 0;

    return [
        'total_count' => (int) ($row['total_count'] ?? 0),
        'total_income' => $income,
        'total_expense' => $expense,
        'balance' => $income - $expense,
        'income_count' => (int) ($row['income_count'] ?? 0),
        'expense_count' => (int) ($row['expense_count'] ?? 0),
        'total_flow' => $totalFlow,
        'income_pct' => $incomePct,
        'expense_pct' => $expensePct,
    ];
}

function moneyGetCategoryBreakdown(mysqli $connection, int $userId, string $transactionType, array $filters = []): array
{
    $where = ['user_id = ?', 'transaction_type = ?'];
    $types = 'is';
    $params = [$userId, $transactionType];
    moneyAppendPeriodFilter($filters, $where, $types, $params);
    $stmt = $connection->prepare('SELECT category, COALESCE(SUM(amount), 0) AS total FROM money_transactions WHERE ' . implode(' AND ', $where) . ' GROUP BY category ORDER BY total DESC, category ASC');
    moneyBindParams($stmt, $types, $params);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function moneyGetExpenseTrend(mysqli $connection, int $userId, array $filters = []): array
{
    $where = ['user_id = ?', "transaction_type = 'expense'"];
    $types = 'i';
    $params = [$userId];
    moneyAppendPeriodFilter($filters, $where, $types, $params);
    $stmt = $connection->prepare('SELECT transaction_date, COALESCE(SUM(amount), 0) AS total FROM money_transactions WHERE ' . implode(' AND ', $where) . ' GROUP BY transaction_date ORDER BY transaction_date ASC LIMIT 14');
    moneyBindParams($stmt, $types, $params);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function moneyGetExpenseTotalForMonth(mysqli $connection, int $userId, DateTimeInterface $month): float
{
    $monthStart = $month->format('Y-m-01');
    $nextMonthStart = (new DateTimeImmutable($monthStart))->modify('+1 month')->format('Y-m-d');
    $stmt = $connection->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM money_transactions WHERE user_id = ? AND transaction_type = 'expense' AND transaction_date >= ? AND transaction_date < ?");
    $stmt->bind_param('iss', $userId, $monthStart, $nextMonthStart);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (float) ($row['total'] ?? 0);
}

function renderMoneyCategorySelectorScript(): void
{
    echo '<script>
        (() => {
            const typeSelect = document.getElementById("transaction_type");
            const categorySelect = document.getElementById("category");
            if (!typeSelect || !categorySelect) return;

            const updateCategories = () => {
                let firstAvailable = null;
                Array.from(categorySelect.options).forEach((option) => {
                    const isAvailable = option.dataset.types.split(" ").includes(typeSelect.value);
                    option.disabled = !isAvailable;
                    option.hidden = !isAvailable;
                    if (isAvailable && firstAvailable === null) firstAvailable = option.value;
                });

                if (categorySelect.selectedOptions[0]?.disabled && firstAvailable !== null) {
                    categorySelect.value = firstAvailable;
                }
            };

            typeSelect.addEventListener("change", updateCategories);
            updateCategories();
        })();
    </script>';
}

function renderMoneyStyles(): void
{
    echo '<style>
        /* Money Tracker Premium Orange Theme & Responsive Styling */
        body {
            font-family: Inter, "Segoe UI", Arial, sans-serif !important;
        }
        body .bi {
            font-family: "bootstrap-icons" !important;
        }
        body h1,
        body h2,
        body h3 {
            font-family: Georgia, "Times New Roman", serif !important;
            font-weight: 400 !important;
            letter-spacing: -0.025em !important;
        }
        .money-theme-hero h1,
        .money-overview-card strong,
        .money-dash-card strong,
        .saving-goal-main strong,
        .money-insight-heading h2,
        .exercise-board-header h2 {
            font-family: Georgia, "Times New Roman", serif !important;
        }
        .money-theme-hero .eyebrow,
        .summary-label,
        .money-hero-metrics,
        .money-table,
        .button,
        .money-view-all,
        .money-mini-total,
        .saving-note {
            font-family: Inter, "Segoe UI", Arial, sans-serif !important;
        }
        .money-theme-hero {
            align-items: center;
            background: linear-gradient(135deg, #fff9f2 0%, #fff0df 56%, #f7c58a 100%);
            border: 1px solid #e7a85e;
            border-radius: 22px;
            box-shadow: 0 20px 44px rgba(190, 102, 25, 0.18);
            display: grid;
            gap: 42px;
            grid-template-columns: minmax(0, 1fr) auto;
            min-height: 360px;
            padding: 58px 60px;
            margin-bottom: 18px;
            position: relative;
            overflow: hidden;
        }
        .money-theme-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #c75b18 0%, #f5b15d 50%, #e87520 100%);
        }
        .money-theme-hero::after {
            background: radial-gradient(circle, rgba(190, 102, 25, 0.16) 1px, transparent 1.5px);
            background-size: 18px 18px;
            content: "";
            height: 200px;
            opacity: 0.55;
            position: absolute;
            right: 0;
            top: 0;
            width: 34%;
        }
        .money-hero-copy,
        .money-overview-card { position: relative; z-index: 1; }
        .money-theme-hero .eyebrow {
            color: #b8551c;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-size: 14px;
            margin-bottom: 6px;
        }
        .money-theme-hero h1 {
            color: #44210b;
            font-size: clamp(48px, 5.3vw, 76px);
            line-height: 1.02;
            margin: 0 0 16px;
        }
        .money-theme-hero .hero-copy {
            color: #75482b;
            font-size: 19px;
            margin: 0;
        }
        .money-hero-metrics { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
        .money-hero-metrics span {
            background: rgba(255, 255, 255, 0.62);
            border: 1px solid rgba(231, 168, 94, 0.62);
            border-radius: 999px;
            color: #7a563c;
            display: inline-flex;
            font-size: 13px;
            font-weight: 800;
            gap: 6px;
            min-height: 32px;
            padding: 6px 10px;
        }
        .money-hero-metrics strong { color: #9d4514; }
        .money-overview-card {
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(231, 168, 94, 0.72);
            border-radius: 16px;
            box-shadow: none;
            min-width: 240px;
            padding: 22px 24px;
        }
        .money-overview-card .summary-label { color: #a44b16; display: block; font-size: 12px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; }
        .money-overview-card strong { color: #44210b; display: block; font-size: 30px; margin: 8px 0 4px; }
        .money-overview-card small { color: #7a563c; font-size: 13px; font-weight: 700; }
        .money-period-picker { align-items: center; background: #fffdfa; border: 1px solid #dfd2c5; border-radius: 12px; display: flex; flex: 0 0 auto; gap: 7px; margin: 0; min-height: 48px; padding: 7px 10px; }
        .money-period-icon { align-items: center; background: #fff3e6; border-radius: 9px; color: #a94d12; display: inline-flex; flex: 0 0 auto; font-size: 17px; height: 34px; justify-content: center; width: 34px; }
        .money-period-picker select { appearance: auto; background: transparent; border: 0; color: #3b3028; font: 700 13px Arial, sans-serif; min-height: 34px; min-width: 76px; outline: none; padding: 0 3px; }
        .money-period-picker select[name="analysis_month"] { min-width: 88px; }
        .money-period-picker .button { min-height: 30px; padding: 5px 9px; }

        /* Top Summary Cards Grid */
        .money-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .money-dash-card {
            background: linear-gradient(145deg, #ffffff, #fffaf5);
            border: 1px solid rgba(240, 197, 144, 0.82);
            border-radius: 16px;
            padding: 24px 26px;
            box-shadow: 0 8px 24px rgba(217, 130, 43, 0.07);
            position: relative;
            overflow: hidden;
            transition: all 220ms ease-in-out;
        }
        .money-dash-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #d9822b;
        }
        .money-dash-card-income::before {
            background: #1f7a4d;
        }
        .money-dash-card-income { background: linear-gradient(145deg, #ffffff, #f3fbf6); }
        .money-dash-card-expense { background: linear-gradient(145deg, #ffffff, #fff7ef); }
        .money-dash-card-balance { background: linear-gradient(145deg, #ffffff, #fffaf4); }
        .money-dash-card-expense::before {
            background: #c85a17;
        }
        .money-dash-card-balance::before {
            background: linear-gradient(90deg, #d9822b, #f5a65b);
        }
        .money-dash-card:hover {
            border-color: #d9822b;
            box-shadow: 0 14px 34px rgba(217, 130, 43, 0.18);
            transform: translateY(-4px);
        }
        .money-dash-card .summary-label {
            color: #8c6843;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        .money-dash-card strong {
            display: block;
            font-size: 30px;
            font-weight: 800;
            line-height: 1.2;
            margin: 8px 0 4px;
            color: #2b1800;
        }
        .money-dash-card-income strong { color: #17673f; }
        .money-dash-card-expense strong { color: #ae4f13; }
        .money-dash-card p {
            color: #7a634e;
            font-size: 13px;
            margin: 0;
        }

        /* Collapsible category analysis */
        .money-analysis {
            background: #ffffff;
            border: 1px solid rgba(240, 197, 144, 0.85);
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(217, 130, 43, 0.08);
            margin: 0 0 24px;
            overflow: hidden;
        }
        .money-analysis-heading { align-items: center; border-bottom: 1px solid rgba(240, 197, 144, 0.65); display: flex; gap: 20px; justify-content: space-between; padding: 20px 24px; }
        .money-analysis-title { min-width: 0; }
        .money-analysis-heading small { color: #a45a1c; display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; }
        .money-cash-health, .money-analysis { font-family: Arial, sans-serif; }
        .money-analysis-heading h2 { color: #3b2000; font-family: Arial, sans-serif; font-size: 23px; font-weight: 800; letter-spacing: -.02em; margin: 3px 0 0; }
        .money-analysis-heading p { color: #7a634e; font-size: 13px; line-height: 1.45; margin: 6px 0 0; }
        .money-cash-health { background: transparent; border: 0; margin: 0 0 16px; overflow: visible; }
        .money-cash-health-heading { border: 0; display: block; padding: 0 0 10px; }
        .money-cash-health-heading small { color: #3b2000; font-family: Arial, sans-serif; font-size: 16px; font-weight: 800; letter-spacing: 0; text-transform: none; }
        .money-cash-health-heading p { display: none; }
        .money-cash-health-grid { background: #fffdfa; border: 1px solid rgba(240, 197, 144, 0.9); border-radius: 14px; display: grid; gap: 0; grid-template-columns: repeat(4, minmax(0, 1fr)); overflow: hidden; }
        .money-health-card { align-items: center; display: flex; gap: 18px; min-height: 94px; padding: 16px 18px; }
        .money-health-card + .money-health-card { border-left: 1px solid rgba(240, 197, 144, 0.72); }
        .money-health-card > i { align-items: center; background: #fffdfa; border: 2px solid currentColor; border-radius: 50%; color: #176b4d; display: inline-flex; flex: 0 0 auto; font-size: 27px; height: 62px; justify-content: center; width: 62px; }
        .money-health-card span { color: #3b332a; display: block; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; }
        .money-health-card strong { color: #176b4d; display: block; font-family: Arial, sans-serif; font-size: 21px; font-weight: 800; line-height: 1.15; margin-top: 7px; white-space: nowrap; }
        .money-health-expense > i, .money-health-highlight > i { color: #e8752b; }
        .money-health-expense strong, .money-health-highlight strong { color: #d85c18; }
        .money-analysis-grid { display: grid; gap: 0; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .money-analysis-card { display: flex; flex-direction: column; padding: 24px 28px 26px; }
        .money-analysis-card + .money-analysis-card { border-left: 1px solid rgba(240, 197, 144, 0.65); }
        .money-analysis-card h3 { background: #ffffff; color: #4a2800; display: block; font-family: Arial, sans-serif; font-size: 15px; font-weight: 800; margin: 0; order: 1; padding: 0 12px; position: relative; text-align: center; z-index: 2; }
        .money-analysis-income h3 { color: #17673f; }
        .money-analysis-expense h3 { color: #ae4f13; }
        .money-analysis-ring { align-items: center; display: flex; flex: 0 0 220px; height: 220px; justify-content: center; margin: 14px auto 6px; order: 2; overflow: visible; position: static; transform: none; width: 220px; }
        .money-analysis-chart { display: block; height: 220px; overflow: visible; width: 220px; }
        .money-analysis-donut-label { fill: #80654d; font: 800 10px Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; }
        .money-analysis-donut-value { fill: #3b2000; font: 800 15px Arial, sans-serif; }
        .money-donut-segment { cursor: pointer; transition: filter 180ms ease, opacity 180ms ease, stroke-width 180ms ease; }
        .money-analysis-ring:hover .money-donut-segment:not(.is-active) { opacity: 0.38; }
        .money-donut-segment:hover, .money-donut-segment.is-active { filter: brightness(1.08) drop-shadow(0 3px 4px rgba(60, 35, 0, 0.2)); opacity: 1 !important; stroke-width: 33px; }
        .money-analysis-total { margin: 8px 0 18px; order: 3; text-align: center; }
        .money-analysis-total span { color: #8c6843; display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .money-analysis-total strong { color: #3b2000; display: block; font-size: 20px; margin-top: 5px; }
        .money-analysis-card .money-category-details { border-top: 1px solid rgba(240, 197, 144, 0.55); clear: both; margin-top: 12px; order: 3; padding-top: 14px; width: 100%; }
        .money-category-list { display: grid; gap: 12px; }
        .money-category-row { align-items: center; border-radius: 12px; display: grid; gap: 12px; grid-template-columns: 42px minmax(0, 1fr) 44px; padding: 7px 8px; transition: background 160ms ease, transform 160ms ease; }
        .money-category-row:hover, .money-category-row.is-active { background: #fff7ef; transform: translateX(3px); }
        .money-category-icon { align-items: center; background: var(--category-color); border-radius: 50%; box-shadow: 0 4px 10px color-mix(in srgb, var(--category-color) 28%, transparent); color: #ffffff; display: flex; font-size: 14px; font-weight: 900; height: 40px; justify-content: center; width: 40px; }
        .money-category-progress > div { display: flex; gap: 14px; justify-content: space-between; }
        .money-category-progress strong { color: #4a2800; font-size: 14px; font-weight: 800; }
        .money-category-progress span { color: #7a634e; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .money-category-progress i { background: #f4ede6; border-radius: 999px; display: block; height: 6px; margin-top: 7px; overflow: hidden; }
        .money-category-progress b { background: var(--category-color); border-radius: inherit; display: block; height: 100%; }
        .money-category-row em { color: #4a2800; font-size: 14px; font-style: normal; font-weight: 900; text-align: right; }
        .money-analysis-empty { color: #7a634e; margin: 0; padding: 75px 0; text-align: center; }

        /* Page controls and transaction area */
        .exercise-action-bar,
        .exercise-board-header {
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(217, 130, 43, 0.06);
        }
        .exercise-action-bar { background: linear-gradient(135deg, #fffdf9, #fff6ec); }
        .exercise-action-bar .compact-actions { flex-direction: row; flex-wrap: nowrap; }
        .exercise-board-header { background: transparent; border: 0; box-shadow: none; margin-bottom: 14px; padding-left: 0; padding-right: 0; }
        .exercise-action-bar .summary-label,
        .exercise-board-header .summary-label { color: #a45a1c; }
        .exercise-action-bar strong,
        .exercise-board-header h2 { color: #3b2000; }
        .table-panel { border: 1px solid rgba(240, 197, 144, 0.82); border-radius: 16px; box-shadow: 0 10px 28px rgba(217, 130, 43, 0.07); }
        .money-table td { border-color: rgba(240, 197, 144, 0.48) !important; }
        .money-table td:first-child { color: #8c6843; font-weight: 700; }
        .money-table tbody tr { transition: background 160ms ease; }

        @media (max-width: 640px) {
            .money-theme-hero { grid-template-columns: 1fr; min-height: 0; padding: 32px 26px; }
            .money-overview-card { min-width: 0; }
            .money-theme-hero::after { width: 100%; }
            .money-analysis-heading { align-items: flex-start; flex-direction: column; }
            .money-summary-grid { gap: 14px; }
            .money-cash-health-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .money-health-card:nth-child(3) { border-left: 0; border-top: 1px solid rgba(240, 197, 144, 0.55); }
            .money-health-card:nth-child(4) { border-top: 1px solid rgba(240, 197, 144, 0.55); }
            .money-dash-card { padding: 20px; }
            .money-dash-card strong { font-size: 26px; }
            .money-analysis-grid { grid-template-columns: 1fr; }
            .money-analysis-card { padding: 22px 20px; }
            .money-analysis-card + .money-analysis-card { border-left: 0; border-top: 1px solid rgba(240, 197, 144, 0.65); }
            .money-analysis-ring { height: 220px; width: 220px; }
            .money-category-row { grid-template-columns: 38px minmax(0, 1fr) 38px; }
            .money-category-icon { height: 36px; width: 36px; }
            .exercise-action-bar .compact-actions { flex-wrap: wrap; }
        }

        /* Modern Segmented Control for Income/Expense Toggle */
        .segmented-control-label {
            display: block;
            font-weight: 700;
            color: #4a2800;
            margin-bottom: 8px;
        }
        .segmented-control {
            display: flex;
            background: #f7ede2;
            border: 1px solid #f0c590;
            border-radius: 999px;
            padding: 4px;
            gap: 4px;
            margin-bottom: 22px;
            position: relative;
        }
        .segmented-control input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }
        .segmented-option {
            flex: 1;
            text-align: center;
            padding: 12px 18px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 14px;
            color: #8c6843;
            cursor: pointer;
            transition: all 200ms cubic-bezier(.2, .8, .2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            user-select: none;
        }
        .segmented-option .type-icon {
            font-size: 16px;
            font-weight: 900;
            display: inline-block;
            transition: transform 200ms ease;
        }
        .segmented-control input[value="expense"]:checked + .expense-option {
            background: linear-gradient(135deg, #d9822b 0%, #b86518 100%);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(217, 130, 43, 0.35);
        }
        .segmented-control input[value="income"]:checked + .income-option {
            background: linear-gradient(135deg, #1f7a4d 0%, #145936 100%);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(31, 122, 77, 0.35);
        }
        .segmented-control input[type="radio"]:focus-visible + .segmented-option {
            outline: 3px solid rgba(217, 130, 43, 0.4);
        }

        /* Buttons, Forms & Tables */
        .button.primary,
        .money-btn-primary {
            background: linear-gradient(135deg, #d9822b 0%, #b86518 100%) !important;
            border-color: #b86518 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 14px rgba(217, 130, 43, 0.3) !important;
            transition: all 180ms cubic-bezier(.2, .8, .2, 1) !important;
        }
        .button.primary:hover,
        .money-btn-primary:hover {
            background: linear-gradient(135deg, #b86518 0%, #945315 100%) !important;
            border-color: #945315 !important;
            box-shadow: 0 6px 20px rgba(217, 130, 43, 0.4) !important;
            transform: translateY(-2px) !important;
        }
        .filter-button:hover,
        .button:hover:not(.primary):not(.danger-primary):not(.danger-button) {
            background: #fff0de !important;
            border-color: #f0c590 !important;
            color: #945315 !important;
        }
        .filter-count {
            background: #d9822b !important;
            color: #ffffff !important;
        }
        .pill-income {
            background: #e8f6ee !important;
            border: 1px solid #b9ddc8 !important;
            color: #1f7a4d !important;
        }
        .pill-expense {
            background: #fff0de !important;
            border: 1px solid #f0c590 !important;
            color: #945315 !important;
        }
        .status-pill {
            border-radius: 999px;
            display: inline-block;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 12px;
            text-transform: capitalize;
        }
        .money-table {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #f0c590;
        }
        .money-table th {
            background: #9e3d0d !important;
            color: #fff8ef !important;
            border-bottom: 2px solid #8a3108 !important;
        }
        .money-table tbody tr:hover {
            background: #fffdf9 !important;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #d9822b !important;
            box-shadow: 0 0 0 3px rgba(217, 130, 43, 0.25) !important;
            outline: none !important;
        }
        .active-filter-list span {
            background: #fff0de;
            border: 1px solid #f0c590;
            color: #945315;
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
        }
        .panel.narrow {
            background: #ffffff;
            border: 1px solid #f0c590;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(217, 130, 43, 0.08);
            padding: 32px 36px;
        }
        .money-workspace { display: grid; gap: 22px; grid-template-columns: minmax(0, 1.85fr) minmax(300px, .85fr); margin-top: 22px; }
        .money-transaction-column { min-width: 0; }
        .money-insights-column { align-content: start; display: grid; gap: 16px; }
        .money-insight-card { background: #fffdfa; border: 1px solid #f0c590; border-radius: 18px; box-shadow: 0 12px 28px rgba(138, 72, 20, .08); overflow: hidden; padding: 20px; }
        .money-insight-heading { align-items: flex-start; display: flex; gap: 12px; justify-content: space-between; }
        .money-insight-heading .summary-label { color: #b55a1b; font-size: 10px; margin: 0 0 2px; }
        .money-insight-heading h2 { color: #3b2000; font-size: 21px; margin: 0; }
        .money-insight-value { color: #d35413; font-size: 13px; font-weight: 800; padding-top: 7px; }
        .money-trend-chart { display: block; height: 142px; margin-top: 12px; width: 100%; }
        .money-trend-card { border-color: #ff812f; border-radius: 20px; padding: 16px 20px; }
        .money-trend-card .money-insight-heading .summary-label { color: #f15a14; font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .money-trend-card .money-insight-heading h2 { font-size: 28px; letter-spacing: -.03em; line-height: 1; }
        .money-trend-card .money-insight-value { color: #ed5010; font-family: Georgia, "Times New Roman", serif; font-size: 25px; font-weight: 400; padding-top: 4px; white-space: nowrap; }
        .money-trend-change { align-items: center; background: #eff8e9; border: 1px solid #d7ebcc; border-radius: 999px; color: #278538; display: inline-flex; font-size: 11px; font-weight: 800; gap: 5px; margin: 8px 0 0; padding: 6px 10px; }
        .money-trend-change.is-up { background: #fff0e7; border-color: #f7d6c1; color: #c9531c; }
        .money-trend-card .money-trend-chart { height: 112px; margin-top: 0; }
        .money-trend-footer { align-items: center; display: flex; font-family: Arial, sans-serif; font-size: 11px; font-weight: 700; justify-content: space-between; margin-top: 0; }
        .money-trend-footer > span { align-items: center; color: #4e321e; display: inline-flex; gap: 7px; }
        .money-trend-footer > span i { background: #f15a14; border-radius: 999px; display: inline-block; height: 4px; width: 16px; }
        .money-trend-footer .money-view-all { color: #f15a14; font-size: 11px; }
        .money-trend-category { align-items: center; border-top: 1px solid #f1dfcf; color: #43230c; display: flex; font-family: Georgia, "Times New Roman", serif; font-size: 12px; gap: 8px; margin: 9px 0 0; padding-top: 9px; }
        .money-trend-category > span { align-items: center; background: #fff0e4; border: 1px solid #f8dcc6; border-radius: 50%; color: #ef621f; display: inline-flex; height: 28px; justify-content: center; width: 28px; }
        .money-insight-empty { color: #80654d; font-size: 13px; margin: 28px 0 10px; text-align: center; }
        .money-view-all { color: #c95516; font-family: Arial, sans-serif; font-size: 12px; font-weight: 800; text-decoration: none; white-space: nowrap; }
        .money-view-all:hover { color: #7f330d; text-decoration: underline; }
        .money-mini-chart-grid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 14px; }
        .money-analysis-launch { background: #fffdfa; color: inherit; cursor: pointer; display: block; padding: 14px; text-decoration: none; transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease; }
        .money-analysis-launch:hover { border-color: #df7a35; box-shadow: 0 16px 30px rgba(183, 72, 10, .12); transform: translateY(-2px); }
        .money-analysis-launch:hover .money-view-all { color: #8f3508; }
        .money-analysis-launch:focus-visible { outline: 3px solid rgba(233, 84, 12, .35); outline-offset: 3px; }
        .money-flow-heading { align-items: start; }
        .money-flow-heading .summary-label { font-size: 9px; letter-spacing: .11em; margin-bottom: 4px; text-transform: uppercase; }
        .money-flow-heading h2 { font-family: Georgia, "Times New Roman", serif; font-size: clamp(20px, 2vw, 25px); line-height: 1.1; }
        .money-flow-heading .money-view-all { padding-top: 3px; }
        .money-mini-chart { align-items: flex-start; background: #fff; border: 1px solid #f0dfcc; border-radius: 14px; display: flex; flex-direction: column; min-width: 0; padding: 12px; text-align: left; }
        .money-mini-chart + .money-mini-chart { border-left: 1px solid #f0dfcc; }
        .money-flow-label { align-items: center; display: flex; gap: 9px; }
        .money-flow-icon { align-items: center; background: #ecf7f5; border-radius: 50%; color: #0f766e; display: inline-flex; font-size: 15px; height: 33px; justify-content: center; width: 33px; }
        .money-mini-chart-expense .money-flow-icon { background: #fff0e6; color: #df5b17; }
        .money-mini-chart h3 { color: #80552f; font-family: Arial, sans-serif; font-size: 13px; margin: 0; text-transform: none; }
        .money-flow-data { align-items: center; display: grid; gap: 7px; grid-template-columns: minmax(0, 1fr) 64px; margin-top: 12px; width: 100%; }
        .money-flow-amount { color: #183b38; display: block; font-family: Georgia, "Times New Roman", serif; font-size: clamp(14px, 1.15vw, 17px); line-height: 1; white-space: nowrap; }
        .money-mini-chart-expense .money-flow-amount { color: #c94b12; }
        .money-flow-period { color: #8b7664; display: block; font-size: 10px; margin-top: 5px; }
        .money-mini-chart svg { display: block; max-width: 100%; }
        .money-flow-data svg { height: 64px; justify-self: end; width: 64px; }
        .money-mini-chart-income h3 { color: #0f766e; }
        .money-mini-chart-expense h3 { color: #d85310; }
        .money-mini-total { color: #80552f; font-family: Arial, sans-serif; font-size: 11px; font-weight: 700; margin: -4px 0 0; }
        .money-net-balance { align-items: center; background: #fffaf4; border: 1px solid #f0dfcc; border-radius: 14px; color: #4a2a12; display: grid; font-family: Arial, sans-serif; font-size: 13px; font-weight: 700; gap: 10px; grid-template-columns: auto auto auto; justify-content: center; margin-top: 12px; padding: 10px 13px; }
        .money-balance-icon { align-items: center; background: #fff0df; border-radius: 50%; color: #77411c; display: inline-flex; height: 32px; justify-content: center; width: 32px; }
        .money-balance-icon svg { fill: none; height: 19px; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; width: 19px; }
        .money-net-balance strong { border-left: 1px solid #ebd2bd; color: #3b2000; font-family: Georgia, "Times New Roman", serif; font-size: 18px; padding-left: 13px; white-space: nowrap; }
        body.money-analysis-expanded > .money-theme-hero,
        body.money-analysis-expanded > .money-summary-grid,
        body.money-analysis-expanded > .money-analysis,
        body.money-analysis-expanded > .money-workspace { opacity: .36; pointer-events: none; transform: scale(.84); transform-origin: center top; transition: opacity 240ms ease, transform 280ms ease; }
        body > .money-theme-hero,
        body > .money-summary-grid,
        body > .money-analysis,
        body > .money-workspace { transition: opacity 240ms ease, transform 280ms ease; }
        .money-analysis-backdrop { background: rgba(47, 30, 18, .16); inset: 0; position: fixed; z-index: 1000; }
        .money-analysis-dialog { background: #fffdfa; border: 1px solid #e9a365; border-radius: 24px; box-shadow: 0 26px 70px rgba(59, 32, 0, .28); color: inherit; inset: 24px; margin: auto; max-height: calc(100vh - 48px); padding: 0; position: fixed; width: min(1080px, calc(100vw - 48px)); z-index: 1001; }
        .money-analysis-dialog[open] { animation: moneyAnalysisZoom 220ms ease-out; }
        .money-analysis-dialog-shell { max-height: calc(100vh - 48px); overflow-y: auto; padding: clamp(26px, 4vw, 52px); position: relative; }
        .money-analysis-dialog-loading { color: #80654d; font-family: Arial, sans-serif; margin: 70px 0; text-align: center; }
        .money-analysis-dialog-close { align-items: center; background: #fff3e6; border: 1px solid #e6aa78; border-radius: 999px; color: #8f3508; cursor: pointer; display: inline-flex; font-family: Arial, sans-serif; font-size: 12px; font-weight: 800; gap: 7px; padding: 9px 13px; position: absolute; right: 20px; top: 20px; }
        .money-analysis-dialog-close:hover { background: #e9540c; color: #fff; }
        .money-analysis-dialog-close:focus-visible { outline: 3px solid rgba(233, 84, 12, .35); outline-offset: 2px; }
        .money-analysis-dialog .money-theme-hero { margin-bottom: 22px; padding: clamp(24px, 4vw, 42px); }
        .money-analysis-dialog .money-theme-hero h1 { font-size: clamp(30px, 4vw, 46px); }
        .money-analysis-dialog .money-summary-grid { margin-bottom: 22px; }
        .money-analysis-dialog .money-analysis { margin-bottom: 0; }
        .money-analysis-dialog .money-flow-heading { padding-right: 92px; }
        .money-analysis-dialog .money-flow-heading h2 { font-size: clamp(30px, 4vw, 46px); }
        .money-analysis-dialog .money-flow-heading .summary-label { font-size: 11px; }
        .money-analysis-dialog .money-flow-heading .money-view-all { display: none; }
        .money-analysis-dialog .money-mini-chart-grid { gap: 24px; margin-top: 28px; }
        .money-analysis-dialog .money-mini-chart { border-radius: 20px; padding: 26px; }
        .money-analysis-dialog .money-flow-icon { font-size: 19px; height: 44px; width: 44px; }
        .money-analysis-dialog .money-mini-chart h3 { font-size: 18px; }
        .money-analysis-dialog .money-flow-data { gap: 16px; grid-template-columns: minmax(0, 1fr) 144px; margin-top: 24px; }
        .money-analysis-dialog .money-flow-amount { font-size: clamp(25px, 3vw, 38px); }
        .money-analysis-dialog .money-flow-period { font-size: 13px; margin-top: 8px; }
        .money-analysis-dialog .money-flow-data svg { height: 144px; width: 144px; }
        .money-analysis-dialog .money-net-balance { border-radius: 18px; font-size: 17px; gap: 16px; margin-top: 24px; padding: 18px 24px; }
        .money-analysis-dialog .money-balance-icon { height: 46px; width: 46px; }
        .money-analysis-dialog .money-balance-icon svg { height: 24px; width: 24px; }
        .money-analysis-dialog .money-net-balance strong { font-size: 25px; padding-left: 18px; }
        @keyframes moneyAnalysisZoom { from { opacity: 0; transform: scale(.92); } to { opacity: 1; transform: scale(1); } }
        .money-analysis-launch[role="button"] { cursor: pointer; }
        .money-analysis-stage { display: none; margin: 22px 0; }
        .money-analysis-stage:has(.money-analysis-launch.is-expanded) { display: block; }
        .money-analysis-stage:has(.money-analysis-launch.is-expanded) ~ .money-workspace { animation: moneySectionsMakeRoom 380ms cubic-bezier(.22, .8, .25, 1); }
        .money-spending-stage { display: none; margin: 22px 0; }
        .money-spending-stage:has(.money-trend-card.is-expanded) { display: block; }
        .money-spending-stage:has(.money-trend-card.is-expanded) ~ .money-workspace { animation: moneySectionsMakeRoom 380ms cubic-bezier(.22, .8, .25, 1); }
        .money-analysis-launch.is-expanded { animation: moneyAnalysisExpand 440ms cubic-bezier(.22, .8, .25, 1); cursor: default; padding: 70px clamp(24px, 3vw, 38px) clamp(24px, 3vw, 38px); position: relative; transform-origin: 78% 22%; }
        body:has(.money-analysis-launch.is-expanded) .filter-drawer,
        body:has(.money-analysis-launch.is-expanded) .filter-backdrop { display: none !important; }
        .filter-drawer:not(.is-open) { visibility: hidden !important; }
        .filter-drawer.is-open { visibility: visible; }
        .money-analysis-launch.is-expanded:hover { border-color: #f0c590; box-shadow: 0 12px 28px rgba(138, 72, 20, .08); transform: none; }
        .money-analysis-inline-close { align-items: center; background: #fffaf4; border: 1px solid #e6aa78; border-radius: 999px; color: #8f3508; cursor: pointer; display: inline-flex; font-family: Arial, sans-serif; font-size: 12px; font-weight: 800; gap: 7px; padding: 9px 13px; position: absolute; right: 24px; top: 18px; z-index: 2; }
        .money-analysis-inline-close:hover { background: #e9540c; color: #fff; }
        .money-analysis-inline-close:focus-visible { outline: 3px solid rgba(233, 84, 12, .35); outline-offset: 2px; }
        .money-analysis-inline-loading { color: #80654d; font-family: Arial, sans-serif; margin: 80px 0; text-align: center; }
        .money-trend-card[role="button"] { cursor: pointer; }
        .money-spending-launch { transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease; }
        .money-spending-launch:hover { border-color: #ef5a16; box-shadow: 0 16px 30px rgba(183, 72, 10, .14); transform: translateY(-2px); }
        .money-spending-launch:focus-visible { outline: 3px solid rgba(239, 90, 22, .35); outline-offset: 3px; }
        .money-trend-card.is-expanded { animation: moneyAnalysisExpand 440ms cubic-bezier(.22, .8, .25, 1); cursor: default; padding: clamp(24px, 4vw, 42px); position: relative; transform-origin: 78% 22%; }
        .money-trend-card.is-expanded .money-spending-detail { display: block; }
        .money-spending-detail-heading { align-items: flex-start; display: flex; justify-content: space-between; margin: 0 74px 22px 0; }
        .money-spending-detail-heading .summary-label { color: #e9540c; font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .money-spending-detail-heading h2 { color: #3b2000; font-size: clamp(30px, 4vw, 48px); margin: 4px 0 0; }
        .money-spending-period { align-items: center; border: 1px solid #ecd7c2; border-radius: 10px; color: #4e321e; display: inline-flex; font-size: 13px; gap: 8px; padding: 11px 14px; white-space: nowrap; }
        .money-spending-period input { background: transparent; border: 0; box-shadow: none; color: #4e321e; font: inherit; min-width: 116px; padding: 0; }
        .money-spending-period input:focus { box-shadow: none !important; outline: 0; }
        .money-spending-header-actions { align-items: center; display: flex; gap: 10px; }
        .money-spending-stat-grid { display: grid; gap: 14px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .money-spending-stat-grid article { align-items: center; background: #fff; border: 1px solid #efd9c4; border-radius: 14px; display: grid; gap: 3px 13px; grid-template-columns: 42px minmax(0, 1fr); padding: 15px; }
        .money-spending-stat-grid article > i { align-items: center; background: #fff0e6; border-radius: 50%; color: #e9540c; display: inline-flex; font-size: 18px; grid-row: 1 / span 2; height: 42px; justify-content: center; width: 42px; }
        .money-spending-stat-grid span { color: #563923; font-size: 12px; font-weight: 700; }
        .money-spending-stat-grid strong { color: #3b2000; font-family: Georgia, "Times New Roman", serif; font-size: clamp(17px, 2vw, 25px); font-weight: 400; white-space: nowrap; }
        .money-spending-chart-panel, .money-spending-list-panel { background: #fff; border: 1px solid #efd9c4; border-radius: 14px; }
        .money-spending-chart-panel { margin-top: 16px; padding: 16px 18px 10px; }
        .money-spending-chart-panel h3, .money-spending-list-panel h3 { color: #3b2000; font-size: 19px; margin: 0; }
        .money-spending-chart-panel svg { display: block; height: 210px; margin-top: 8px; width: 100%; }
        .money-spending-chart-label { fill: #80654d; font-family: Arial, sans-serif; font-size: 11px; font-weight: 700; }
        .money-spending-axis-label { fill: #80654d; font-family: Arial, sans-serif; font-size: 8px; }
        .money-spending-grid-line { stroke: #eadfd4; stroke-dasharray: 2 2; stroke-width: .7; }
        .money-spending-grid-line.is-vertical { stroke: #e6d9cd; }
        .money-spending-peak-line { stroke: #ef5a16; stroke-dasharray: 3 3; stroke-width: 1; }
        .money-spending-peak-dot { fill: #fffdfa; stroke: #ef5a16; stroke-width: 2; }
        .money-spending-tooltip rect { fill: #3b2000; }
        .money-spending-tooltip text { fill: #fffdfa; font-family: Arial, sans-serif; font-size: 7px; }
        .money-spending-simple-guide { stroke: #f1d8c3; stroke-dasharray: 3 3; stroke-width: .8; }
        .money-spending-simple-dot { fill: #fffdfa; stroke: #ef5a16; stroke-width: 2.5; }
        .money-spending-simple-tooltip { fill: #ffffff; stroke: #f2d6bf; filter: drop-shadow(0 3px 5px rgba(70, 38, 12, .15)); }
        .money-spending-simple-tooltip-text { fill: #3b2000; font-family: Arial, sans-serif; font-size: 11px; font-weight: 800; }
        .money-spending-detail-grid { display: grid; gap: 16px; grid-template-columns: 1fr 1fr; margin-top: 16px; }
        .money-spending-list-panel { padding: 18px; }
        .money-spending-category-row { align-items: center; display: grid; gap: 10px; grid-template-columns: 36px 78px minmax(60px, 1fr) auto 32px; margin-top: 16px; }
        .money-spending-row-icon, .money-spending-category-row .money-spending-row-icon { align-items: center; background: #fff0e5; border-radius: 50%; color: #ff5a00; display: inline-flex; font-size: 15px; height: 34px; justify-content: center; width: 34px; }
        .money-spending-category-row strong { color: #4e321e; font-family: Arial, sans-serif; font-size: 12px; }
        .money-spending-category-row > i { background: #f9e6d6; border-radius: 999px; height: 7px; overflow: hidden; }
        .money-spending-category-row > i b { background: #ef5a16; border-radius: inherit; display: block; height: 100%; }
        .money-spending-category-row span, .money-spending-recent-row em { color: #4e321e; font-family: Arial, sans-serif; font-size: 12px; font-style: normal; font-weight: 700; white-space: nowrap; }
        .money-spending-category-row em { color: #e9540c; font-family: Arial, sans-serif; font-size: 12px; font-style: normal; font-weight: 800; text-align: right; }
        .money-spending-recent-row { align-items: center; border-bottom: 1px solid #f0e1d3; display: grid; gap: 11px; grid-template-columns: 34px minmax(0, 1fr) auto; padding: 12px 0; }
        .money-spending-recent-row strong, .money-spending-recent-row small { display: block; }
        .money-spending-recent-row strong { color: #4e321e; font-family: Arial, sans-serif; font-size: 12px; }
        .money-spending-recent-row small { color: #95755e; font-size: 10px; margin-top: 3px; }
        .money-spending-list-panel .money-view-all { display: block; margin-top: 14px; text-align: center; }
        .money-analysis-launch.is-expanded .money-theme-hero { margin-bottom: 22px; padding: clamp(24px, 4vw, 42px); }
        .money-analysis-launch.is-expanded .money-theme-hero h1 { font-size: clamp(30px, 4vw, 46px); }
        .money-analysis-launch.is-expanded .money-summary-grid { margin-bottom: 22px; }
        .money-analysis-launch.is-expanded .money-analysis { margin-bottom: 0; }
        @keyframes moneyAnalysisExpand { 0% { opacity: .2; transform: scale(.84) translateY(-28px); } 65% { opacity: 1; transform: scale(1.015) translateY(2px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes moneySectionsMakeRoom { from { opacity: .55; transform: translateY(-48px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .money-analysis-launch.is-expanded, .money-trend-card.is-expanded, .money-analysis-stage:has(.money-analysis-launch.is-expanded) + .money-workspace, .money-spending-stage:has(.money-trend-card.is-expanded) ~ .money-workspace { animation: none; } }
        .money-donut-label { fill: #8b6545; font: 700 10px Arial, sans-serif; }
        .money-donut-total { fill: #43230c; font: 700 10px Arial, sans-serif; }
        .money-saving-card { background: #fff7eb; border-color: #f09844; min-height: 250px; padding: 17px 20px; position: relative; }
        .money-saving-card::before { border: 1px dashed rgba(232, 103, 11, .24); border-radius: 50%; content: ""; height: 130px; position: absolute; right: -39px; top: -66px; width: 130px; }
        .money-saving-card::after { background: #ffe3bb; border-radius: 50%; content: ""; height: 99px; position: absolute; right: -27px; top: -49px; width: 99px; }
        .money-saving-card .money-insight-heading, .money-saving-card .saving-goal-main, .money-saving-card .saving-progress, .money-saving-card .saving-progress-line, .money-saving-card .saving-note { position: relative; z-index: 1; }
        .money-saving-card .money-insight-heading .summary-label { color: #d95a08; font-size: 9px; letter-spacing: .085em; margin-bottom: 4px; }
        .money-saving-card .money-insight-heading h2 { font-size: 24px; }
        .saving-icon { align-items: center; background: #e85e08; border-radius: 10px; color: #fffaf0; display: inline-flex; font-size: 18px; height: 34px; justify-content: center; width: 34px; }
        .saving-goal-main { align-items: end; display: flex; gap: 12px; justify-content: space-between; margin: 21px 0 17px; }
        .saving-goal-main strong { color: #e95508; display: block; font-size: 58px; line-height: .78; }
        .saving-goal-main p { color: #5b3214; font-size: 12px; font-weight: 700; margin: 10px 0 0; }
        .saving-goal-main > span { color: #663c1d; font-size: 12px; font-weight: 800; text-align: right; }
        .saving-goal-main small { color: #956d4b; display: block; font-size: 9px; font-weight: 700; margin-top: 4px; }
        .saving-progress { background: #f5d2a5; border-radius: 999px; height: 8px; overflow: hidden; }
        .saving-progress i { background: linear-gradient(90deg, #e9540c, #ff8a2a); border-radius: inherit; display: block; height: 100%; }
        .saving-progress-line { color: #9c623a; display: flex; font-size: 8px; font-weight: 700; justify-content: space-between; margin-top: 6px; }
        .saving-note { align-items: center; border-top: 1px solid #f2d9ba; color: #704328; display: flex; font-size: 9px; font-weight: 700; gap: 6px; line-height: 1.2; margin: 12px 0 0; padding-top: 9px; }
        .money-goal-launch { cursor: pointer; transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease; }
        .money-goal-launch:hover { border-color: #df7a35; box-shadow: 0 16px 30px rgba(183, 72, 10, .12); transform: translateY(-2px); }
        .money-goal-launch:focus-visible { outline: 3px solid rgba(233, 84, 12, .35); outline-offset: 3px; }
        .saving-goal-empty { margin-top: 45px; text-align: left; }
        .saving-goal-empty strong { color: #e95508; display: block; font-family: Georgia, "Times New Roman", serif; font-size: 27px; font-weight: 400; }
        .saving-goal-empty p { color: #704328; font-size: 11px; font-weight: 700; margin: 10px 0; }
        .saving-goal-empty span { color: #d85c0b; font-size: 11px; font-weight: 800; }
        .money-goal-stage { display: none; margin: 22px 0; scroll-margin-top: 82px; }
        .money-goal-stage:has(.money-goal-launch.is-expanded) { display: block; }
        .money-goal-launch.is-expanded { animation: moneyAnalysisExpand 440ms cubic-bezier(.22, .8, .25, 1); cursor: default; padding: clamp(24px, 4vw, 42px); position: relative; transform-origin: 78% 22%; }
        .money-goal-launch.is-expanded:hover { border-color: #f0c590; box-shadow: 0 12px 28px rgba(138, 72, 20, .08); transform: none; }
        .money-goal-detail-heading { margin: 0 74px 24px 0; }
        .money-goal-detail-heading .summary-label { color: #e9540c; font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .money-goal-detail-heading h2 { color: #3b2000; font-size: clamp(30px, 4vw, 48px); margin: 4px 0 0; }
        .money-goal-hero { background: #fff6e9; border: 1px solid #efd3ae; border-radius: 18px; display: grid; gap: 12px 24px; grid-template-columns: 1fr auto; padding: 24px; }
        .money-goal-hero p, .money-goal-hero b, .money-goal-hero small { display: block; }
        .money-goal-hero p { color: #704328; font-size: 13px; font-weight: 800; margin: 0; }
        .money-goal-hero strong { color: #e9540c; display: block; font-family: Georgia, "Times New Roman", serif; font-size: clamp(48px, 8vw, 78px); font-weight: 400; line-height: .8; margin: 13px 0; }
        .money-goal-hero b { color: #563017; font-size: 16px; }.money-goal-hero small { color: #956d4b; font-size: 11px; margin-top: 5px; }
        .money-goal-badge { align-items: center; align-self: center; background: #e9540c; border-radius: 18px; color: #fff; display: inline-flex; font-size: 30px; height: 74px; justify-content: center; width: 74px; }
        .money-goal-hero > i { background: #f2d1a4; border-radius: 999px; grid-column: 1 / -1; height: 10px; overflow: hidden; }.money-goal-hero > i b { background: linear-gradient(90deg, #e9540c, #ff9b4b); display: block; height: 100%; }
        .money-goal-detail-grid { display: grid; gap: 16px; grid-template-columns: 1fr 1fr; margin-top: 16px; }.money-goal-detail-grid > section, .money-goal-create { background: #fff; border: 1px solid #efd9c4; border-radius: 14px; padding: 18px; }.money-goal-detail h3 { color: #3b2000; font-size: 19px; margin: 0 0 14px; }
        .money-goal-detail form { display: grid; gap: 10px; }.money-goal-detail label { color: #704328; font-size: 11px; font-weight: 800; }.money-goal-detail label span { color: #9b7b61; font-weight: 600; }.money-goal-detail input { border: 1px solid #ecd4ba; border-radius: 9px; box-sizing: border-box; display: block; font: inherit; margin-top: 4px; padding: 9px 10px; width: 100%; }.money-goal-detail form button { background: #e9540c; border: 1px solid #d34c08; border-radius: 9px; color: #fff; cursor: pointer; font: 800 12px Arial, sans-serif; min-height: 36px; }
        .money-goal-activity > div { align-items: center; border-bottom: 1px solid #f0e1d3; display: grid; gap: 10px; grid-template-columns: 30px 1fr auto; padding: 10px 0; }.money-goal-activity > div:last-child { border-bottom: 0; }.money-goal-activity span { align-items: center; background: #fff0df; border-radius: 50%; color: #e9540c; display: inline-flex; height: 30px; justify-content: center; width: 30px; }.money-goal-activity p { margin: 0; }.money-goal-activity p b, .money-goal-activity p small { display: block; }.money-goal-activity p b { font-size: 11px; }.money-goal-activity p small { color: #95755e; font-size: 10px; margin-top: 2px; }.money-goal-activity strong { color: #14836f; font-size: 11px; }.money-goal-empty { color: #80654d; font-size: 12px; }.money-goal-create, .money-goal-smart-plan { margin-top: 16px; }.money-goal-create form { grid-template-columns: repeat(3, 1fr) auto; align-items: end; }.money-goal-smart-plan { background: #eff8e9; border: 1px solid #d5eaca; border-radius: 14px; padding: 18px; }.money-goal-smart-plan form { align-items: center; display: grid; gap: 13px; grid-template-columns: minmax(130px, .8fr) 1fr 1fr auto; }.money-goal-check { align-items: center; display: flex; gap: 7px; }.money-goal-check input { margin: 0; width: auto; }.money-goal-pace { color: #2d7c3c; font-size: 11px; font-weight: 800; margin: 12px 0 0; }
        .money-goal-detail form { align-items: end; }.money-goal-detail-grid form { grid-template-columns: 1fr 1fr; }.money-goal-detail-grid form label:first-of-type { grid-column: 1 / -1; }.money-goal-detail-grid form button { grid-column: 1 / -1; }.money-goal-status, .money-goal-activity-section { background: #fff; border: 1px solid #efd9c4; border-radius: 14px; margin-top: 16px; padding: 18px; }.money-goal-status p { color: #80654d; font-size: 12px; margin: 0 0 12px; }.money-goal-status form { display: flex; flex-wrap: wrap; gap: 9px; }.money-goal-status button { background: #fff6e9; border: 1px solid #efbd82; border-radius: 9px; color: #95470e; cursor: pointer; font: 800 11px Arial, sans-serif; min-height: 34px; padding: 0 12px; }.money-goal-status button[value="completed"] { background: #eff8e9; border-color: #cde7c4; color: #277337; }.money-goal-status button[value="archived"], .money-goal-delete { background: #fff0ed; border-color: #f0c7bd; color: #a23820; }.money-goal-activity details { border-bottom: 1px solid #f0e1d3; }.money-goal-activity details:last-child { border-bottom: 0; }.money-goal-activity summary { align-items: center; cursor: pointer; display: grid; gap: 10px; grid-template-columns: 30px 1fr auto; list-style: none; padding: 10px 0; }.money-goal-activity summary::-webkit-details-marker { display: none; }.money-goal-record-edit { border-top: 1px dashed #edcaa7; padding: 12px 0 14px 40px; }.money-goal-record-edit form { display: grid; gap: 8px; grid-template-columns: 1fr 1fr; }.money-goal-record-edit form label:first-of-type { grid-column: 1 / -1; }.money-goal-record-edit form button { grid-column: auto; }.money-goal-record-edit form + form { display: inline; }.money-goal-record-edit .money-goal-delete { margin-top: 8px; min-height: 32px; }.money-goal-create form { grid-template-columns: repeat(3, minmax(0, 1fr)); }.money-goal-create form button { grid-column: 1 / -1; }
        .money-goal-milestones { background: #fff; border: 1px solid #efd9c4; border-radius: 14px; margin-top: 16px; padding: 18px; }.money-goal-milestones h3 { margin-bottom: 14px; }.money-goal-milestones > div { display: grid; gap: 10px; grid-template-columns: repeat(4, minmax(0, 1fr)); }.money-goal-milestones span { border-top: 3px solid #f1c88f; color: #977255; font-size: 10px; font-weight: 700; padding-top: 8px; }.money-goal-milestones span b { display: block; font-size: 12px; margin-bottom: 3px; }.money-goal-milestones span.is-reached { border-color: #e9540c; color: #5d351b; }
        .money-goal-plans-heading { align-items: end; display: flex; justify-content: space-between; }.money-goal-plans-heading > span { background: #fff0df; border-radius: 999px; color: #a94b0d; font: 800 11px Arial, sans-serif; padding: 8px 11px; white-space: nowrap; }.money-goal-plans-heading small { color: #80654d; display: block; font: 600 12px/1.45 Arial, sans-serif; margin-top: 9px; max-width: 620px; }.money-goal-overview { background: #fff; border: 1px solid #efd9c4; border-radius: 16px; padding: 18px; }.money-goal-tabs { border-bottom: 1px solid #f0dfca; display: flex; gap: 8px; margin-bottom: 16px; }.money-goal-tabs button { background: transparent; border: 0; border-bottom: 3px solid transparent; color: #8a674d; cursor: pointer; font: 800 12px Arial, sans-serif; padding: 8px 10px 10px; }.money-goal-tabs button b { background: #f6e7d5; border-radius: 999px; font-size: 10px; margin-left: 4px; padding: 3px 6px; }.money-goal-tabs button.is-selected { border-color: #e9540c; color: #a84509; }.money-goal-tabs button.is-selected b { background: #e9540c; color: #fff; }.money-goal-plan-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }.money-goal-plan-card { background: #fffaf3; border: 1px solid #efd9c4; border-radius: 13px; display: grid; gap: 11px; grid-template-columns: minmax(0, 1fr) auto; padding: 15px; }.money-goal-plan-card.is-current { border-color: #e97829; box-shadow: 0 0 0 3px rgba(233, 84, 12, .09); }.money-goal-plan-card.is-past { background: #fcfbf9; }.money-goal-plan-card h3 { color: #43230c; font-size: 17px; margin: 8px 0 4px; }.money-goal-plan-card p { color: #80654d; font-size: 11px; font-weight: 700; margin: 0; }.money-goal-plan-card > strong { color: #e9540c; font-family: Georgia, "Times New Roman", serif; font-size: 25px; font-weight: 400; }.money-goal-plan-card > i { background: #f2d1a4; border-radius: 999px; grid-column: 1 / -1; height: 7px; overflow: hidden; }.money-goal-plan-card > i b { background: linear-gradient(90deg, #e9540c, #ff9b4b); display: block; height: 100%; }.money-goal-plan-card footer { align-items: center; color: #8c6a51; display: flex; font: 700 10px Arial, sans-serif; gap: 9px; grid-column: 1 / -1; justify-content: space-between; }.money-goal-plan-card footer button { background: #fff; border: 1px solid #edb874; border-radius: 7px; color: #a54a0d; cursor: pointer; font: 800 10px Arial, sans-serif; padding: 6px 8px; }.money-goal-status-chip { background: #eff8e9; border: 1px solid #cce7c3; border-radius: 999px; color: #267238; display: inline-flex; font: 800 9px Arial, sans-serif; padding: 4px 7px; text-transform: uppercase; }.money-goal-status-chip.is-paused { background: #fff6dd; border-color: #f0d48c; color: #9a680a; }.money-goal-status-chip.is-completed { background: #eaf7ef; border-color: #c2e5cf; color: #277337; }.money-goal-status-chip.is-archived { background: #f4f1ed; border-color: #ded4c9; color: #7a6859; }.money-goal-selected-detail { border-top: 1px dashed #e4bea0; margin-top: 24px; padding-top: 24px; scroll-margin-top: 82px; }.money-goal-selected-heading { align-items: center; display: flex; justify-content: space-between; margin-bottom: 14px; }.money-goal-selected-heading p { color: #d95a08; font: 800 10px Arial, sans-serif; letter-spacing: .09em; margin: 0 0 4px; text-transform: uppercase; }.money-goal-selected-heading h3 { color: #3b2000; font-family: Georgia, "Times New Roman", serif; font-size: 26px; margin: 0; }.money-goal-create > p { color: #80654d; font-size: 12px; margin: -6px 0 15px; }
        .money-goal-reminder { align-items: center; background: #eff8e9; border: 1px solid #d5eaca; border-radius: 12px; color: #2d7c3c; display: flex; font-size: 11px; font-weight: 800; gap: 9px; margin: 16px 0 0; padding: 12px 14px; }
        .money-table-actions { align-items: center; flex-wrap: nowrap; }
        .money-action-icon { align-items: center; background: #fff3e6; border: 1px solid #e6aa78; border-radius: 9px; color: #8f3508; display: inline-flex; height: 32px; justify-content: center; text-decoration: none; transition: 160ms ease; width: 32px; }
        .money-action-icon:hover { background: #e9540c; box-shadow: 0 6px 12px rgba(201, 70, 8, .24); color: #fff; transform: translateY(-2px); }
        .money-action-delete { color: #7f2414; }
        .money-action-delete:hover { background: #9d2d1a; }
        .money-action-icon:focus-visible { outline: 3px solid rgba(233, 84, 12, .35); outline-offset: 2px; }
        .money-transaction-category-icon { align-items: center; background: #ffc890; border: 1px solid #eea15e; border-radius: 50%; color: #813008; display: inline-flex; font-size: 14px; height: 28px; justify-content: center; margin-right: 8px; vertical-align: middle; width: 28px; }
        .money-table td:nth-child(2) { color: #4d301b; font-weight: 700; }
        .money-table td:nth-child(3) strong { font-weight: 600; }
        .money-amount { display: inline-block; font-family: Arial, sans-serif; font-size: 13px; white-space: nowrap; }
        .money-amount.is-income { color: #0f766e; }
        .money-amount.is-expense { color: #e9540c; }
        .money-actions-heading { width: 74px; }
        .money-table tbody tr .money-table-actions { opacity: .22; transition: opacity 160ms ease; }
        .money-table tbody tr:hover .money-table-actions, .money-table tbody tr:focus-within .money-table-actions { opacity: 1; }
        .money-table-footer { border-top: 1px solid #efc49c; padding: 14px; text-align: center; }
        .money-show-more-button { background: transparent; border: 0; cursor: pointer; padding: 0; }
        .money-show-more-button:hover { background: transparent !important; }
        .money-table-toolbar { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        .money-table-toolbar .button { min-height: 34px; }
        .money-transaction-column .exercise-board-header { align-items: center; background: #fff5ea; border: 1px solid #e9a365; border-bottom: 0; border-radius: 18px 18px 0 0; box-shadow: none; margin: 0; padding: 15px 18px; }
        .money-transaction-column .table-panel { background: #fff9f3; border-color: #e9a365; border-radius: 0 0 18px 18px; box-shadow: 0 12px 28px rgba(155, 61, 12, .11); }
        .money-transaction-column .money-table { border-color: #d57a38; }
        .money-transaction-column .money-table tbody tr { background: #fffdf9; }
        .money-transaction-column .money-table tbody tr:nth-child(even) { background: #fff8ef; }
        .money-transaction-column .money-table tbody tr:hover { background: #ffe6cf !important; }
        .page-shell:has(.money-workspace) { max-width: 1440px; }
        .money-transaction-column .table-panel { overflow: hidden; }
        .money-transaction-column .money-table { min-width: 0; table-layout: fixed; width: 100%; }
        .money-transaction-column .money-table th,
        .money-transaction-column .money-table td { white-space: nowrap; }
        .money-transaction-column .money-table th:first-child,
        .money-transaction-column .money-table td:first-child { width: 112px; }
        .money-transaction-column .money-table th:nth-child(2),
        .money-transaction-column .money-table td:nth-child(2) { width: 27%; }
        .money-transaction-column .money-table th:nth-child(3),
        .money-transaction-column .money-table td:nth-child(3) { width: 20%; }
        .money-transaction-column .money-table th:nth-child(4),
        .money-transaction-column .money-table td:nth-child(4) { width: 13%; }
        .money-transaction-column .money-table th:nth-child(5),
        .money-transaction-column .money-table td:nth-child(5) { width: 15%; }
        .money-transaction-column .money-table th:last-child,
        .money-transaction-column .money-table td:last-child { width: 76px; }
        .money-transaction-column .money-table td:nth-child(2) > span { display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        @media (max-width: 980px) { .money-workspace { grid-template-columns: 1fr; } .money-insights-column { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 640px) { .money-insights-column { grid-template-columns: 1fr; } .money-spending-detail-heading { display: block; margin-right: 54px; } .money-spending-period { margin-top: 10px; } .money-spending-stat-grid, .money-spending-detail-grid { grid-template-columns: 1fr; } .money-spending-chart-panel svg { height: 160px; } .money-analysis-launch { padding: 14px; } .money-mini-chart-grid { gap: 10px; } .money-mini-chart { padding: 12px; } .money-flow-data { gap: 6px; grid-template-columns: minmax(0, 1fr) 58px; } .money-flow-data svg { height: 58px; width: 58px; } .money-net-balance { gap: 9px; padding: 11px; } .money-net-balance strong { font-size: 17px; padding-left: 10px; } .money-analysis-launch.is-expanded { padding: 62px 16px 16px; } .money-analysis-inline-close { right: 14px; top: 14px; } .money-analysis-launch.is-expanded .money-theme-hero { padding: 22px; } .money-analysis-launch.is-expanded .money-analysis-grid { grid-template-columns: 1fr; } .money-analysis-launch.is-expanded .money-analysis-card + .money-analysis-card { border-left: 0; border-top: 1px solid rgba(240, 197, 144, .65); } .saving-goal-main strong { font-size: 38px; } }
        @media (max-width: 640px) { .money-goal-detail-grid { grid-template-columns: 1fr; } .money-goal-detail-grid form, .money-goal-create form { grid-template-columns: 1fr; } .money-goal-detail-grid form label:first-of-type, .money-goal-detail-grid form button { grid-column: auto; } .money-goal-hero { padding: 18px; } .money-goal-record-edit { padding-left: 0; } .money-goal-smart-plan form { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .money-goal-plans-heading { align-items: start; display: block; } .money-goal-plans-heading > span { display: inline-flex; margin-top: 12px; } .money-goal-plan-grid { grid-template-columns: 1fr; } .money-goal-tabs { overflow-x: auto; } .money-goal-create .money-goal-weekly-amount, .money-goal-create .money-goal-plan-options { grid-column: auto; } .money-goal-plan-options { flex-wrap: wrap; white-space: normal; } .money-goal-detail-grid .money-goal-plan-options { justify-content: flex-start; } }
        @media (max-width: 480px) { .money-goal-milestones > div { grid-template-columns: 1fr 1fr; } .money-goal-activity summary { grid-template-columns: 30px minmax(0, 1fr); } .money-goal-activity summary > strong { grid-column: 2; } }
        .money-goal-plan-options { align-items: center; align-self: end; display: flex; gap: 15px; white-space: nowrap; }.money-goal-weekly-amount { transition: opacity 160ms ease; }.money-goal-weekly-amount small { color: #95755e; display: block; font-size: 10px; font-weight: 600; margin-top: 5px; }.money-goal-weekly-amount.is-disabled { opacity: .48; }.money-goal-weekly-amount input:disabled { background: #f6f0e8; cursor: not-allowed; }.money-goal-create .money-goal-weekly-amount { grid-column: span 2; }.money-goal-create .money-goal-plan-options { grid-column: 3; }.money-goal-detail-grid > section:nth-child(2) form { grid-template-columns: repeat(2, minmax(0, 1fr)); }.money-goal-detail-grid > section:nth-child(2) .money-goal-plan-options { grid-column: auto; justify-content: flex-start; } @media (max-width: 640px) { .money-goal-detail-grid > section:nth-child(2) form { grid-template-columns: 1fr; } .money-goal-detail-grid > section:nth-child(2) .money-goal-plan-options { grid-column: auto; flex-wrap: wrap; white-space: normal; } }
    </style>';
}
