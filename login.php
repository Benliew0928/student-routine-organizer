<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/validation.php';

$pageTitle = 'Login';
$email = $_COOKIE['remember_email'] ?? '';
$errors = [];

if (isLoggedIn()) {
    redirectAfterLogin(currentUserRole() ?? 'student');
}

if (sessionAccessState() === 'expired') {
    expireAuthenticatedSession();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberEmail = isset($_POST['remember_email']);

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token expired. Please try again.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (!$errors) {
        try {
            $connection = getDatabaseConnection();
            $stmt = $connection->prepare('SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                unset($_SESSION['csrf_token']);
                refreshAuthenticatedSession();

                if ($rememberEmail) {
                    setcookie('remember_email', $email, applicationCookieOptions(time() + (60 * 60 * 24 * 30)));
                } else {
                    setcookie('remember_email', '', applicationCookieOptions(time() - 3600));
                }

                setFlash('success', 'Welcome back, ' . $user['full_name'] . '.');
                redirectAfterLogin($user['role']);
            }

            $errors[] = 'Invalid email or password.';
        } catch (Throwable $exception) {
            logApplicationException($exception, 'login');
            $errors[] = 'Login is unavailable right now. Please try again later.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<style>
:root {
    --color-primary: #236a54;
    --color-primary-active: #174d3d;
    --color-primary-disabled: #e2f1ea;
    --color-ink: #17231b;
    --color-body: #344338;
    --color-body-strong: #17231b;
    --color-muted: #647064;
    --color-muted-soft: #9bb59e;
    --color-hairline: #dbe4d7;
    --color-hairline-soft: #eaf1e5;
    --color-canvas: #f4f7f2;
    --color-surface-soft: #f9fbf7;
    --color-surface-card: #eaf1e5;
    --color-surface-cream-strong: #c7d4c3;
    --color-surface-dark: #174d3d;
    --color-surface-dark-elevated: #1e5b49;
    --color-surface-dark-soft: #1b5443;
    --color-on-primary: #ffffff;
    --color-on-dark: #f9fbf7;
    --color-on-dark-soft: #bfc9dc;
    --color-accent-teal: #a9d6ab;
    --color-accent-amber: #f1bc76;
    --color-success: #1f7a4d;
    --color-warning: #d9822b;
    --color-error: #b42318;

    --font-primary: "Lora", "Playfair Display", Georgia, serif;
    --font-family-2: "Inter", "Plus Jakarta Sans", system-ui, sans-serif;
    --font-family-3: "JetBrains Mono", ui-monospace, monospace;
    
    --spacing-xxs: 4px;
    --spacing-xs: 8px;
    --spacing-sm: 12px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    --spacing-xxl: 48px;
    --spacing-section: 96px;

    --radius-xs: 4px;
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
    --radius-pill: 9999px;
    --radius-full: 9999px;
}

html, body {
    height: 100vh !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
}

body {
    background: var(--color-canvas) !important;
    color: var(--color-body) !important;
    font-family: var(--font-family-2) !important;
    display: flex !important;
    flex-direction: column !important;
}

.topbar {
    background: var(--color-canvas) !important;
    border-bottom: 1px solid var(--color-hairline) !important;
    backdrop-filter: none !important;
    flex-shrink: 0 !important;
    height: 68px !important;
    box-sizing: border-box !important;
}

.topbar .brand {
    color: var(--color-ink) !important;
    font-family: var(--font-primary) !important;
    font-weight: 500 !important;
    font-size: 20px !important;
}

.topbar .brand::before {
    background: var(--color-primary) !important;
    box-shadow: none !important;
    border-radius: var(--radius-sm) !important;
}

.topbar .nav-links a {
    color: var(--color-muted) !important;
    font-family: var(--font-family-2) !important;
    font-weight: 500 !important;
}

.topbar .nav-links a:hover,
.topbar .nav-links a.is-active {
    background: var(--color-surface-soft) !important;
    color: var(--color-primary) !important;
}

.page-shell {
    height: calc(100vh - 68px - 44px) !important;
    min-height: 0 !important;
    flex: 1 !important;
    display: flex !important;
    align-items: stretch !important;
    justify-content: stretch !important;
    background: var(--color-canvas) !important;
    padding: 0 !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
}

.footer {
    display: block !important;
    background: var(--color-canvas) !important;
    color: var(--color-muted) !important;
    border-top: none !important;
    padding: 12px var(--spacing-xl) !important;
    text-align: center;
    font-family: var(--font-family-2) !important;
    font-size: 13px !important;
    height: 44px !important;
    box-sizing: border-box !important;
    flex-shrink: 0 !important;
    margin: 0 !important;
}

.footer p {
    margin: 0 !important;
    line-height: 1.4 !important;
}

.claude-login-container {
    display: grid;
    grid-template-columns: 35% 65%;
    height: 100% !important;
    max-height: 100% !important;
    background: #ffffff;
    border: none !important;
    border-radius: 0 !important;
    overflow: hidden;
    box-shadow: none !important;
    margin: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}

.claude-login-showcase {
    background-color: var(--color-surface-dark);
    color: var(--color-on-dark);
    padding: var(--spacing-xxl) var(--spacing-xl);
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
}

.showcase-content {
    max-width: 440px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.showcase-badge {
    background-color: rgba(35, 106, 84, 0.15);
    color: var(--color-accent-teal);
    border: 1px solid rgba(169, 214, 171, 0.3);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    width: fit-content;
}

.showcase-title {
    font-family: var(--font-primary);
    font-size: 38px;
    line-height: 1.15;
    font-weight: 400;
    color: var(--color-on-dark) !important;
    margin: 0;
}

.showcase-text {
    font-family: var(--font-family-2);
    font-size: 16px;
    line-height: 1.6;
    color: var(--color-on-dark-soft) !important;
    margin: 0 0 10px 0;
}

.showcase-card {
    background-color: var(--color-surface-dark-elevated);
    border: 1px solid var(--color-surface-dark-soft);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-top: 15px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.showcase-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--color-surface-dark-soft);
    padding-bottom: var(--spacing-sm);
    margin-bottom: var(--spacing-md);
}

.showcase-card-title {
    font-family: var(--font-primary);
    font-size: 16px;
    font-weight: 500;
    color: var(--color-on-dark);
}

.showcase-card-badge {
    font-family: var(--font-family-2);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--color-accent-teal);
    letter-spacing: 0.5px;
}

.showcase-card-body {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.showcase-stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: var(--font-family-2);
    font-size: 14px;
}

.stat-label {
    color: var(--color-on-dark-soft);
}

.stat-value {
    color: var(--color-on-dark);
    font-weight: 600;
}

.claude-login-form-side {
    background-color: var(--color-canvas);
    padding: var(--spacing-xxl) var(--spacing-xl);
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.claude-login-form-side-content {
    max-width: 506px;
    width: 100%;
    margin: 0 auto;
    background: #ffffff;
    border: 1px solid var(--color-hairline) !important;
    border-radius: var(--radius-lg) !important;
    padding: var(--spacing-xl) !important;
    box-shadow: 0 10px 30px rgba(23, 35, 27, 0.03);
    position: relative;
    z-index: 10;
}

.claude-bg-icon {
    position: absolute;
    color: var(--color-primary);
    opacity: 0.08;
    font-size: 110px;
    line-height: 1;
    pointer-events: none;
    user-select: none;
    z-index: 1;
}

.icon-dashboard {
    top: 6%;
    left: 8%;
    transform: rotate(-15deg);
}

.icon-exercise {
    bottom: 8%;
    left: 6%;
    transform: rotate(20deg);
}

.icon-journal {
    top: 10%;
    right: 6%;
    transform: rotate(15deg);
}

.icon-money {
    bottom: 6%;
    right: 8%;
    transform: rotate(-25deg);
}

.icon-habits {
    top: 50%;
    right: 8%;
    font-size: 80px;
    transform: translateY(-50%) rotate(5deg);
}

.icon-clock {
    top: 8%;
    left: 45%;
    font-size: 70px;
    transform: rotate(-10deg);
}

.icon-trophy {
    bottom: 8%;
    left: 45%;
    font-size: 75px;
    transform: rotate(15deg);
}

.icon-pencil {
    top: 30%;
    left: 12%;
    font-size: 85px;
    transform: rotate(-20deg);
}

.icon-heart {
    bottom: 30%;
    right: 10%;
    font-size: 90px;
    transform: rotate(12deg);
}

.claude-logo-area {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: var(--spacing-xl);
}

.claude-radial-spike {
    width: 28px;
    height: 28px;
    position: relative;
    background-color: var(--color-primary);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.claude-radial-spike::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    background-color: #ffffff;
    clip-path: polygon(
        50% 0%, 55% 35%, 85% 15%, 65% 45%, 100% 50%, 65% 55%, 85% 85%, 55% 65%, 50% 100%, 45% 65%, 15% 85%, 35% 55%, 0% 50%, 35% 45%, 15% 15%, 45% 35%
    );
}

.claude-brand-name {
    font-family: var(--font-primary);
    font-weight: 500;
    font-size: 20px;
    color: var(--color-ink);
}

.claude-login-title {
    font-family: var(--font-primary);
    font-size: 32px;
    line-height: 1.2;
    font-weight: 400;
    color: var(--color-ink) !important;
    margin: 0 0 8px 0 !important;
}

.claude-login-subtitle {
    font-family: var(--font-family-2);
    font-size: 15px;
    line-height: 1.4;
    color: var(--color-muted) !important;
    margin: 0 0 var(--spacing-xl) 0 !important;
}

/* Style global alert banner to float as a centered toast alert on login/register */
.alert {
    position: absolute !important;
    top: 20px !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    z-index: 9999 !important;
    padding: 12px 24px !important;
    border-radius: var(--radius-md) !important;
    font-family: var(--font-family-2) !important;
    font-size: 14px !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    box-shadow: 0 10px 25px rgba(23, 35, 27, 0.1) !important;
    animation: slideDown 0.3s ease-out !important;
}

.alert-error {
    background-color: #fce8e6 !important;
    border: 1px solid #f5c2c7 !important;
    color: #a82e2e !important;
}

.alert-success {
    background-color: #e6f4ea !important;
    border: 1px solid #b7e1cd !important;
    color: var(--color-success) !important;
}

@keyframes slideDown {
    from {
        transform: translate(-50%, -20px);
        opacity: 0;
    }
    to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
}

.claude-alert {
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-md);
    font-family: var(--font-family-2);
    font-size: 14px;
}

.claude-alert-error {
    background-color: rgba(180, 35, 24, 0.08);
    border: 1px solid rgba(180, 35, 24, 0.2);
    color: var(--color-error);
}

.claude-alert p {
    margin: 0;
    line-height: 1.4;
}

.claude-alert p + p {
    margin-top: 4px;
}

.claude-form {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
    margin-top: 0 !important;
}

.claude-field-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xxs);
}

.claude-label {
    font-family: var(--font-family-2);
    font-size: 13px;
    font-weight: 500;
    color: var(--color-muted);
}

.claude-input {
    background-color: #ffffff !important;
    color: var(--color-ink) !important;
    border: 1px solid var(--color-hairline) !important;
    border-radius: var(--radius-md) !important;
    padding: 12px 16px !important;
    height: 44px !important;
    font-family: var(--font-family-2) !important;
    font-size: 15px !important;
    box-shadow: none !important;
    width: 100% !important;
    transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
}

.claude-input:hover {
    border-color: var(--color-muted-soft) !important;
}

.claude-input:focus {
    background-color: #ffffff !important;
    border-color: var(--color-primary) !important;
    box-shadow: 0 0 0 3px rgba(35, 106, 84, 0.15) !important;
    outline: none !important;
}

.claude-password-wrapper {
    position: relative;
    display: flex;
    width: 100%;
}

.claude-password-wrapper .claude-input {
    width: 100% !important;
    padding-right: 50px !important;
}

.claude-password-toggle {
    position: absolute;
    right: 1px;
    top: 1px;
    bottom: 1px;
    width: 44px;
    background: transparent !important;
    border: none !important;
    color: var(--color-muted-soft);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    transition: color 0.15s ease;
}

.claude-password-toggle:hover {
    color: var(--color-ink);
}

.claude-password-toggle:focus-visible {
    outline: 2px solid var(--color-primary) !important;
    outline-offset: -2px;
}

.claude-flex-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    font-family: var(--font-family-2);
    margin-top: 4px;
}

.claude-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-muted);
    font-weight: 400 !important;
    cursor: pointer;
    user-select: none;
}

.claude-checkbox-label input[type="checkbox"] {
    width: 18px !important;
    height: 18px !important;
    min-height: auto !important;
    margin: 0 !important;
    border-radius: var(--radius-xs) !important;
    border: 1px solid var(--color-hairline) !important;
    background-color: #ffffff !important;
    cursor: pointer;
    accent-color: var(--color-primary);
}

.claude-forgot-link {
    color: var(--color-primary) !important;
    text-decoration: none;
    transition: color 0.15s ease;
    font-weight: 500;
}

.claude-forgot-link:hover {
    color: var(--color-primary-active) !important;
    text-decoration: underline;
}

.claude-button-primary {
    background-color: var(--color-primary) !important;
    color: var(--color-on-primary) !important;
    font-family: var(--font-family-2) !important;
    font-size: 15px !important;
    font-weight: 500 !important;
    border: none !important;
    border-radius: var(--radius-md) !important;
    padding: 12px 20px !important;
    height: 44px !important;
    cursor: pointer !important;
    box-shadow: none !important;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.15s ease, transform 0.1s ease !important;
    width: 100%;
    margin-top: var(--spacing-sm);
}

.claude-button-primary:hover {
    background-color: var(--color-primary-active) !important;
}

.claude-button-primary:active {
    transform: scale(0.98);
}

@media (max-width: 860px) {
    .claude-login-container {
        grid-template-columns: 1fr;
        max-width: 100% !important;
        height: 100% !important;
        max-height: 100% !important;
    }
    .claude-login-showcase {
        display: none !important;
    }
}
</style>

<div class="claude-login-container">
    <!-- Showcase Side (Hidden on Mobile) -->
    <div class="claude-login-showcase">
        <div class="showcase-content">
            <div class="showcase-badge">Student Companion</div>
            <h2 class="showcase-title">Your daily routine, organized.</h2>
            <p class="showcase-text">Stay on top of your studies, track your daily habits, and manage your student routine with clarity and ease.</p>
            
            <div class="showcase-card">
                <div class="showcase-card-header">
                    <span class="showcase-card-title">What you can do</span>
                    <span class="showcase-card-badge">Key Features</span>
                </div>
                <div class="showcase-card-body">
                    <div class="showcase-stat-row">
                        <span class="stat-label">Organize routines & classes</span>
                        <span class="stat-value">📅</span>
                    </div>
                    <div class="showcase-stat-row">
                        <span class="stat-label">Track daily habits & health</span>
                        <span class="stat-value">🏃‍♂️</span>
                    </div>
                    <div class="showcase-stat-row">
                        <span class="stat-label">Reflect with personal journal</span>
                        <span class="stat-value">✍️</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Form Side -->
    <div class="claude-login-form-side">
        <!-- Floating Module Background Icons -->
        <div class="claude-bg-icon icon-dashboard"><i class="bi bi-calendar3"></i></div>
        <div class="claude-bg-icon icon-exercise"><i class="bi bi-activity"></i></div>
        <div class="claude-bg-icon icon-journal"><i class="bi bi-journal-text"></i></div>
        <div class="claude-bg-icon icon-money"><i class="bi bi-piggy-bank"></i></div>
        <div class="claude-bg-icon icon-habits"><i class="bi bi-check2-circle"></i></div>
        <div class="claude-bg-icon icon-clock"><i class="bi bi-clock"></i></div>
        <div class="claude-bg-icon icon-trophy"><i class="bi bi-trophy"></i></div>
        <div class="claude-bg-icon icon-pencil"><i class="bi bi-pencil-square"></i></div>
        <div class="claude-bg-icon icon-heart"><i class="bi bi-heart"></i></div>

        <div class="claude-login-form-side-content">
            <div class="claude-logo-area">
                <div class="claude-radial-spike"></div>
                <span class="claude-brand-name"><?= APP_NAME; ?></span>
            </div>

            <h1 class="claude-login-title">Welcome back</h1>
            <p class="claude-login-subtitle">Use your registered student account or the sample admin account.</p>

            <?php if ($errors): ?>
                <div class="claude-alert claude-alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= escapeOutput($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="claude-form" method="post" action="<?= BASE_URL; ?>/login.php">
                <?= csrfInput(); ?>
                
                <div class="claude-field-group">
                    <label for="email" class="claude-label">Email address</label>
                    <input id="email" name="email" class="claude-input" type="email" autocomplete="email" value="<?= escapeOutput($email); ?>" placeholder="name@example.com" required>
                </div>

                <div class="claude-field-group">
                    <label for="password" class="claude-label">Password</label>
                    <div class="claude-password-wrapper">
                        <input id="password" name="password" class="claude-input" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                        <button class="claude-password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Show password" aria-pressed="false">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="claude-flex-row">
                    <label class="claude-checkbox-label">
                        <input name="remember_email" type="checkbox" value="1" <?= $email !== '' ? 'checked' : ''; ?>>
                        <span>Remember my email</span>
                    </label>
                    <a href="<?= BASE_URL; ?>/forgot_password.php" class="claude-forgot-link">Forgot password?</a>
                </div>

                <button class="claude-button-primary" type="submit">Sign in</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
