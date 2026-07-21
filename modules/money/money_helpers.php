<?php
declare(strict_types=1);

function moneyTransactionTypeOptions(): array
{
    return [
        'expense' => 'Expense',
        'income' => 'Income',
    ];
}

function moneyCategoryOptions(): array
{
    return [
        'Food' => 'Food',
        'Transport' => 'Transport',
        'Shopping' => 'Shopping',
        'Bills' => 'Bills',
        'Education' => 'Education',
        'Entertainment' => 'Entertainment',
        'Healthcare' => 'Healthcare',
        'Salary' => 'Salary',
        'Allowance' => 'Allowance',
        'Freelance' => 'Freelance',
        'Others' => 'Others',
    ];
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

    if ($data['category'] === '' || !array_key_exists($data['category'], moneyCategoryOptions())) {
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

function moneyFiltersFromRequest(array $source): array
{
    $filters = [
        'search' => cleanInput((string) ($source['search'] ?? '')),
        'transaction_type' => cleanInput((string) ($source['transaction_type'] ?? '')),
        'category' => cleanInput((string) ($source['category'] ?? '')),
        'date_from' => cleanInput((string) ($source['date_from'] ?? '')),
        'date_to' => cleanInput((string) ($source['date_to'] ?? '')),
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

    if (!array_key_exists($filters['sort'], moneySortOptions())) {
        $filters['sort'] = 'newest';
    }

    return $filters;
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

function moneyGetSummary(mysqli $connection, int $userId): array
{
    $stmt = $connection->prepare("SELECT 
        COUNT(*) AS total_count,
        COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense,
        COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN 1 ELSE 0 END), 0) AS income_count,
        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN 1 ELSE 0 END), 0) AS expense_count
        FROM money_transactions 
        WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
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

function renderMoneyStyles(): void
{
    echo '<style>
        /* Money Tracker Premium Orange Theme & Responsive Styling */
        .money-theme-hero {
            background: linear-gradient(135deg, #ffffff 0%, #fff7ef 55%, #ffe8d0 100%);
            border: 1px solid #f0c590;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(217, 130, 43, 0.09);
            padding: 36px 40px;
            margin-bottom: 28px;
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
            background: linear-gradient(90deg, #d9822b 0%, #f5a65b 50%, #e65100 100%);
        }
        .money-theme-hero .eyebrow {
            color: #d9822b;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .money-theme-hero h1 {
            color: #3b2000;
            font-size: 32px;
            font-weight: 800;
            margin: 0 0 8px;
        }
        .money-theme-hero .hero-copy {
            color: #6e543c;
            font-size: 15px;
            margin: 0;
        }

        /* Top Summary Cards Grid */
        .money-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .money-dash-card {
            background: #ffffff;
            border: 1px solid #f0c590;
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
        .money-dash-card p {
            color: #7a634e;
            font-size: 13px;
            margin: 0;
        }

        /* Two Large Circular Progress Charts Section */
        .money-twin-rings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .money-ring-card {
            background: linear-gradient(145deg, #ffffff 0%, #fff8f2 100%);
            border: 1px solid #f0c590;
            border-radius: 18px;
            padding: 28px 30px;
            box-shadow: 0 8px 24px rgba(217, 130, 43, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 220ms ease-in-out;
        }
        .money-ring-card:hover {
            border-color: #d9822b;
            box-shadow: 0 14px 36px rgba(217, 130, 43, 0.18);
            transform: translateY(-4px);
        }
        .money-ring-card h3 {
            margin: 0 0 18px;
            font-size: 16px;
            font-weight: 800;
            color: #4a2800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .money-ring-svg-wrapper {
            position: relative;
            width: 160px;
            height: 160px;
            margin-bottom: 16px;
        }
        .money-ring-svg-wrapper svg {
            transform: rotate(-90deg);
            width: 160px;
            height: 160px;
        }
        .money-ring-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        .money-ring-center .pct {
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
            display: block;
        }
        .money-ring-center .pct-income {
            color: #1f7a4d;
        }
        .money-ring-center .pct-expense {
            color: #d9822b;
        }
        .money-ring-center .label {
            font-size: 11px;
            font-weight: 700;
            color: #8c6843;
            text-transform: uppercase;
            margin-top: 4px;
            display: block;
        }
        .money-ring-amount {
            font-size: 20px;
            font-weight: 800;
            color: #2b1800;
            margin-top: 4px;
        }
        .money-ring-subtext {
            font-size: 13px;
            color: #7a634e;
            margin-top: 2px;
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
            background: #fff6ed !important;
            color: #8c6843 !important;
            border-bottom: 2px solid #f0c590 !important;
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
    </style>';
}
