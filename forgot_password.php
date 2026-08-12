<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/validation.php';
require __DIR__ . '/includes/password_reset.php';

if (isLoggedIn()) {
    redirectAfterLogin(currentUserRole() ?? 'student');
}

$pageTitle = 'Forgot Password';
$email = '';
$errors = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput((string) ($_POST['email'] ?? ''));

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token expired. Please try again.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        try {
            $connection = getDatabaseConnection();
            $user = passwordResetFindUserByEmail($connection, $email);

            if ($user) {
                $token = passwordResetIssueToken($connection, (int) $user['user_id']);

                try {
                    passwordResetSendMail($user['email'], $token);
                } catch (Throwable $exception) {
                    passwordResetDeleteOutstandingTokens($connection, (int) $user['user_id']);
                    logApplicationException($exception, 'password reset mail delivery');
                }
            }
        } catch (Throwable $exception) {
            logApplicationException($exception, 'password reset request');
        }

        $successMessage = 'If an account matches that email address, a password reset link has been sent.';
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="panel narrow">
    <h1>Forgot Password</h1>
    <p class="muted">Enter your registered email address and we will send a password reset link.</p>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= escapeOutput($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= escapeOutput($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL; ?>/forgot_password.php">
        <?= csrfInput(); ?>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= escapeOutput($email); ?>" required>
        <button class="button primary" type="submit">Send Reset Link</button>
    </form>

    <p class="muted"><a href="<?= BASE_URL; ?>/login.php">Back to Login</a></p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
