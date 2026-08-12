<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/validation.php';
require __DIR__ . '/includes/password_reset.php';

header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');

if (isLoggedIn()) {
    redirectAfterLogin(currentUserRole() ?? 'student');
}

$rawToken = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');
$token = is_string($rawToken) ? $rawToken : '';
$errors = [];
$pageError = null;
$validToken = null;

try {
    $connection = getDatabaseConnection();
    $validToken = passwordResetFindValidToken($connection, $token);

    if (!$validToken) {
        $pageError = 'This password reset link is invalid or has expired.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, passwordPolicyErrors($password));

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (!$errors) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if (passwordResetConsumeToken($connection, $token, $passwordHash)) {
                setFlash('success', 'Your password has been reset. Please log in with your new password.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }

            $pageError = 'This password reset link is invalid or has expired.';
            $validToken = null;
        }
    }
} catch (Throwable $exception) {
    logApplicationException($exception, 'password reset completion');
    $pageError = 'Password reset is unavailable right now. Please request a new link later.';
}

$pageTitle = 'Reset Password';
require __DIR__ . '/includes/header.php';
?>

<section class="panel narrow">
    <h1>Reset Password</h1>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
        <p class="muted"><a href="<?= BASE_URL; ?>/forgot_password.php">Request a new password reset link</a></p>
    <?php elseif ($validToken): ?>
        <p class="muted">Choose a new password for your account.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= escapeOutput($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL; ?>/reset_password.php">
            <?= csrfInput(); ?>
            <input name="token" type="hidden" value="<?= escapeOutput($token); ?>">

            <label for="password">New Password</label>
            <div class="password-input-control">
                <button class="password-visibility-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Show new password" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" data-password-primary required>
            </div>
            <p class="field-hint">Use 12-128 characters with uppercase, lowercase, a number, and a symbol. Spaces are not allowed.</p>

            <div class="password-assistance" data-password-assistance>
                <div class="password-requirement-bar" role="progressbar" aria-label="Password requirements met" aria-valuemin="0" aria-valuemax="5" aria-valuenow="0">
                    <span class="password-requirement-segment" data-password-rule="length"></span>
                    <span class="password-requirement-segment" data-password-rule="uppercase"></span>
                    <span class="password-requirement-segment" data-password-rule="lowercase"></span>
                    <span class="password-requirement-segment" data-password-rule="number"></span>
                    <span class="password-requirement-segment" data-password-rule="symbol"></span>
                </div>
                <p class="password-requirement-summary" data-password-summary aria-live="polite">0 of 5 password requirements met</p>
                <ul class="password-requirement-list">
                    <li data-password-rule="length"><i class="bi bi-circle" aria-hidden="true"></i>12-128 characters</li>
                    <li data-password-rule="uppercase"><i class="bi bi-circle" aria-hidden="true"></i>Uppercase letter</li>
                    <li data-password-rule="lowercase"><i class="bi bi-circle" aria-hidden="true"></i>Lowercase letter</li>
                    <li data-password-rule="number"><i class="bi bi-circle" aria-hidden="true"></i>Number</li>
                    <li data-password-rule="symbol"><i class="bi bi-circle" aria-hidden="true"></i>Symbol and no spaces</li>
                </ul>
            </div>

            <label for="confirm_password">Confirm New Password</label>
            <div class="password-input-control">
                <button class="password-visibility-toggle" type="button" data-password-toggle aria-controls="confirm_password" aria-label="Show new password confirmation" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="128" data-password-confirmation required>
            </div>
            <p class="password-confirmation-status" data-password-confirmation-status aria-live="polite">Enter the password again to confirm it.</p>

            <button class="button primary" type="submit">Reset Password</button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
