<?php
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';

requireAdmin();

$users = [];
$usersError = null;

try {
    $connection = getDatabaseConnection();
    $result = $connection->query('SELECT user_id, full_name, email, role, created_at FROM users ORDER BY created_at DESC, user_id DESC');
    $users = $result->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $exception) {
    $usersError = 'Registered users are unavailable right now.';
}

$pageTitle = 'Registered Users';
require __DIR__ . '/../includes/header.php';
?>

<section class="section-heading">
    <h1>Registered Users</h1>
    <p class="muted">All student and admin accounts in the system.</p>
</section>

<?php if ($usersError): ?>
    <div class="alert alert-error"><?= escapeOutput($usersError); ?></div>
<?php elseif (!$users): ?>
    <section class="panel empty-state">
        <h2>No registered users found</h2>
        <p class="muted">New student and admin accounts will appear here after registration or database import.</p>
    </section>
<?php else: ?>
    <section class="panel table-panel">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Registered At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int) $user['user_id']; ?></td>
                        <td><?= escapeOutput($user['full_name']); ?></td>
                        <td><?= escapeOutput($user['email']); ?></td>
                        <td><span class="status-pill"><?= escapeOutput(ucfirst($user['role'])); ?></span></td>
                        <td><?= escapeOutput($user['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
