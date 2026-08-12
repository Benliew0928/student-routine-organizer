<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/validation.php';

$pageTitle = 'Register';
$fullName = '';
$email = '';
$errors = [];

if (isLoggedIn()) {
    redirectAfterLogin(currentUserRole() ?? 'student');
}

if (sessionAccessState() === 'expired') {
    expireAuthenticatedSession();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = cleanInput($_POST['full_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token expired. Please try again.';
    }

    if ($fullName === '') {
        $errors[] = 'Please enter your full name.';
    } elseif (mb_strlen($fullName) > 100) {
        $errors[] = 'Full name must be 100 characters or fewer.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 120) {
        $errors[] = 'Email must be 120 characters or fewer.';
    }

    $errors = array_merge($errors, passwordPolicyErrors($password));

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        try {
            $connection = getDatabaseConnection();
            $stmt = $connection->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'This email is already registered.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'student';
                $insert = $connection->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $insert->bind_param('ssss', $fullName, $email, $passwordHash, $role);
                $insert->execute();

                setFlash('success', 'Registration successful. Please log in.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }
        } catch (Throwable $exception) {
            logApplicationException($exception, 'registration');
            $errors[] = 'Registration is unavailable right now. Please try again later.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="panel narrow">
    <h1>Register</h1>
    <p class="muted">Create a student account to manage your own routine records.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= escapeOutput($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL; ?>/register.php">
        <?= csrfInput(); ?>
        <label for="full_name">Full Name</label>
        <input id="full_name" name="full_name" type="text" value="<?= escapeOutput($fullName); ?>" required>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= escapeOutput($email); ?>" required>

        <label for="password">Password</label>
        <div class="password-input-control">
            <button class="password-visibility-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Show password" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" data-password-primary required>
        </div>

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

        <label for="confirm_password">Confirm Password</label>
        <div class="password-input-control">
            <button class="password-visibility-toggle" type="button" data-password-toggle aria-controls="confirm_password" aria-label="Show password confirmation" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="128" data-password-confirmation required>
        </div>
        <p class="password-confirmation-status" data-password-confirmation-status aria-live="polite">Enter the password again to confirm it.</p>

        <button class="button primary" type="submit">Create Account</button>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
