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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberEmail = isset($_POST['remember_email']);

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

                if ($rememberEmail) {
                    setcookie('remember_email', $email, time() + (60 * 60 * 24 * 30), BASE_URL, '', false, true);
                } else {
                    setcookie('remember_email', '', time() - 3600, BASE_URL, '', false, true);
                }

                setFlash('success', 'Welcome back, ' . $user['full_name'] . '.');
                redirectAfterLogin($user['role']);
            }

            $errors[] = 'Invalid email or password.';
        } catch (Throwable $exception) {
            $errors[] = 'Login is unavailable right now. Please try again later.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="panel narrow">
    <h1>Login</h1>
    <p class="muted">Use your registered student account or the sample admin account.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= escapeOutput($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL; ?>/login.php">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= escapeOutput($email); ?>" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <label class="checkbox-line">
            <input name="remember_email" type="checkbox" value="1" <?= $email !== '' ? 'checked' : ''; ?>>
            Remember my email on this device
        </label>

        <button class="button primary" type="submit">Login</button>
    </form>

    <p class="muted account-hint">Sample student: student@example.com / password123</p>
    <p class="muted account-hint">Sample admin: admin@example.com / admin123</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
