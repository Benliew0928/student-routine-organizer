<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/password_reset.php';

$connection = null;
$userId = null;
$email = 'password-reset-' . bin2hex(random_bytes(6)) . '@example.test';

try {
    $connection = getDatabaseConnection();
    $name = 'Password Reset Test';
    $passwordHash = password_hash('Original!Password2026', PASSWORD_DEFAULT);
    $role = 'student';
    $insertUser = $connection->prepare(
        'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $insertUser->bind_param('ssss', $name, $email, $passwordHash, $role);
    $insertUser->execute();
    $userId = (int) $connection->insert_id;

    $firstToken = passwordResetIssueToken($connection, $userId);
    $secondToken = passwordResetIssueToken($connection, $userId);

    test('issuing a new reset token invalidates the previous active token', function () use ($connection, $firstToken, $secondToken): void {
        assertSameValue(null, passwordResetFindValidToken($connection, $firstToken));
        assertTrueValue(passwordResetFindValidToken($connection, $secondToken) !== null);
    });

    test('database stores a hash rather than the raw reset token', function () use ($connection, $secondToken): void {
        $hash = passwordResetTokenHash($secondToken);
        $stmt = $connection->prepare('SELECT token_hash FROM password_resets WHERE token_hash = ? LIMIT 1');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $stored = $stmt->get_result()->fetch_assoc();

        assertSameValue($hash, $stored['token_hash'] ?? null);
        assertSameValue(false, ($stored['token_hash'] ?? '') === $secondToken);
    });

    $expire = $connection->prepare('UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE token_hash = ?');
    $secondHash = passwordResetTokenHash($secondToken);
    $expire->bind_param('s', $secondHash);
    $expire->execute();

    test('expired reset tokens are rejected', function () use ($connection, $secondToken): void {
        assertSameValue(null, passwordResetFindValidToken($connection, $secondToken));
        assertSameValue(false, passwordResetConsumeToken($connection, $secondToken, password_hash('Another!Password2026', PASSWORD_DEFAULT)));
    });

    $usableToken = passwordResetIssueToken($connection, $userId);
    $newPassword = 'Updated!Password2026';

    test('a valid reset token changes the password once and cannot be reused', function () use ($connection, $userId, $usableToken, $newPassword): void {
        assertSameValue(true, passwordResetConsumeToken($connection, $usableToken, password_hash($newPassword, PASSWORD_DEFAULT)));
        assertSameValue(null, passwordResetFindValidToken($connection, $usableToken));
        assertSameValue(false, passwordResetConsumeToken($connection, $usableToken, password_hash('Third!Password2026', PASSWORD_DEFAULT)));

        $stmt = $connection->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        assertTrueValue(password_verify($newPassword, $user['password_hash'] ?? ''));
    });
} catch (Throwable $exception) {
    test('password reset database setup succeeds', function () use ($exception): void {
        throw $exception;
    });
} finally {
    if ($connection instanceof mysqli && $userId !== null) {
        $delete = $connection->prepare('DELETE FROM users WHERE user_id = ?');
        $delete->bind_param('i', $userId);
        $delete->execute();
    }
}

finishTests();
