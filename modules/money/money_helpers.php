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

function moneyGetCategoryBreakdown(mysqli $connection, int $userId, string $transactionType): array
{
    $stmt = $connection->prepare('SELECT category, COALESCE(SUM(amount), 0) AS total FROM money_transactions WHERE user_id = ? AND transaction_type = ? GROUP BY category ORDER BY total DESC, category ASC');
    $stmt->bind_param('is', $userId, $transactionType);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        .money-theme-hero {
            align-items: center;
            background: linear-gradient(135deg, #fffdf9 0%, #fff7ef 58%, #ffe8d0 100%);
            border: 1px solid #f0c590;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(217, 130, 43, 0.09);
            display: grid;
            gap: 28px;
            grid-template-columns: minmax(0, 1fr) auto;
            padding: 34px;
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
            background: linear-gradient(90deg, #d9822b 0%, #f5a65b 50%, #e65100 100%);
        }
        .money-theme-hero::after {
            background: radial-gradient(circle, rgba(217, 130, 43, 0.16) 1px, transparent 1.5px);
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
        .money-hero-metrics { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
        .money-hero-metrics span {
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(240, 197, 144, 0.85);
            border-radius: 999px;
            color: #7a634e;
            display: inline-flex;
            font-size: 13px;
            font-weight: 800;
            gap: 6px;
            min-height: 32px;
            padding: 6px 10px;
        }
        .money-hero-metrics strong { color: #945315; }
        .money-overview-card {
            background: rgba(255, 255, 255, 0.74);
            border: 1px solid rgba(240, 197, 144, 0.92);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(217, 130, 43, 0.1);
            min-width: 215px;
            padding: 18px 20px;
        }
        .money-overview-card .summary-label { color: #8c6843; display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; }
        .money-overview-card strong { color: #3b2000; display: block; font-size: 24px; margin: 6px 0 3px; }
        .money-overview-card small { color: #7a634e; font-size: 12px; font-weight: 700; }

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
        .money-analysis-heading { border-bottom: 1px solid rgba(240, 197, 144, 0.65); padding: 20px 24px; }
        .money-analysis-heading small { color: #a45a1c; display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; }
        .money-analysis-heading h2 { color: #3b2000; font-size: 20px; margin: 3px 0 0; }
        .money-analysis-heading p { color: #7a634e; font-size: 13px; margin: 6px 0 0; }
        .money-analysis-grid { display: grid; gap: 0; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .money-analysis-card { display: block; padding: 24px 28px 26px; }
        .money-analysis-card + .money-analysis-card { border-left: 1px solid rgba(240, 197, 144, 0.65); }
        .money-analysis-card h3 { background: #ffffff; color: #4a2800; display: block; font-size: 15px; margin: 0; padding: 0 12px; position: relative; text-align: center; z-index: 2; }
        .money-analysis-income h3 { color: #17673f; }
        .money-analysis-expense h3 { color: #ae4f13; }
        .money-donut-wrap { display: block; height: 220px; margin: 14px auto 0; overflow: visible; position: relative; width: 220px; }
        .money-category-donut { display: block; height: 220px; overflow: visible; width: 220px; }
        .money-donut-segment { cursor: pointer; transform-origin: center; transition: filter 180ms ease, opacity 180ms ease, stroke-width 180ms ease; }
        .money-donut-wrap:hover .money-donut-segment:not(.is-active) { opacity: 0.38; }
        .money-donut-segment:hover, .money-donut-segment.is-active { filter: brightness(1.08) drop-shadow(0 3px 4px rgba(60, 35, 0, 0.2)); opacity: 1 !important; stroke-width: 33px; }
        .money-donut-summary { margin: 8px 0 18px; text-align: center; }
        .money-donut-summary span { color: #8c6843; display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .money-donut-summary strong { color: #3b2000; display: block; font-size: 20px; margin-top: 5px; }
        .money-category-details { border-top: 1px solid rgba(240, 197, 144, 0.55); padding-top: 14px; width: 100%; }
        .money-category-list { display: grid; gap: 12px; }
        .money-category-row { align-items: center; border-radius: 12px; display: grid; gap: 12px; grid-template-columns: 42px minmax(0, 1fr) 44px; padding: 7px 8px; transition: background 160ms ease, transform 160ms ease; }
        .money-category-row:hover, .money-category-row.is-active { background: #fff7ef; transform: translateX(3px); }
        .money-category-icon { align-items: center; background: var(--category-color); border-radius: 50%; box-shadow: 0 4px 10px color-mix(in srgb, var(--category-color) 28%, transparent); color: #ffffff; display: flex; font-size: 14px; font-weight: 900; height: 40px; justify-content: center; width: 40px; }
        .money-category-progress > div { display: flex; gap: 14px; justify-content: space-between; }
        .money-category-progress strong { color: #4a2800; font-size: 14px; }
        .money-category-progress span { color: #7a634e; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .money-category-progress i { background: #f4ede6; border-radius: 999px; display: block; height: 6px; margin-top: 7px; overflow: hidden; }
        .money-category-progress b { background: var(--category-color); border-radius: inherit; display: block; height: 100%; }
        .money-category-row em { color: #4a2800; font-size: 14px; font-style: normal; font-weight: 900; text-align: right; }
        .money-analysis-empty { color: #7a634e; margin: 0; padding: 75px 0; text-align: center; }

        @media (min-width: 641px) {
            .money-analysis-card { position: relative; }
            .money-analysis-card .money-donut-wrap {
                left: 50%;
                margin: 0;
                position: absolute;
                top: -180px;
                transform: translateX(-50%);
                z-index: 1;
            }
            .money-analysis-card .money-donut-summary { margin-top: 246px; }
        }

        /* Page controls and transaction area */
        .exercise-action-bar,
        .exercise-board-header {
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(217, 130, 43, 0.06);
        }
        .exercise-action-bar { background: linear-gradient(135deg, #fffdf9, #fff6ec); }
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
            .money-theme-hero { grid-template-columns: 1fr; padding: 26px; }
            .money-overview-card { min-width: 0; }
            .money-theme-hero::after { width: 100%; }
            .money-summary-grid { gap: 14px; }
            .money-dash-card { padding: 20px; }
            .money-dash-card strong { font-size: 26px; }
            .money-analysis-grid { grid-template-columns: 1fr; }
            .money-analysis-card { padding: 22px 20px; }
            .money-analysis-card + .money-analysis-card { border-left: 0; border-top: 1px solid rgba(240, 197, 144, 0.65); }
            .money-donut-wrap { height: 220px; width: 220px; }
            .money-category-row { grid-template-columns: 38px minmax(0, 1fr) 38px; }
            .money-category-icon { height: 36px; width: 36px; }
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
