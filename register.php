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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = cleanInput($_POST['full_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

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

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

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
        <label for="full_name">Full Name</label>
        <input id="full_name" name="full_name" type="text" value="<?= escapeOutput($fullName); ?>" required>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= escapeOutput($email); ?>" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>

        <label for="confirm_password">Confirm Password</label>
        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>

        <button class="button primary" type="submit">Create Account</button>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
