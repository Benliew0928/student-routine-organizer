<?php
declare(strict_types=1);

const PASSWORD_RESET_TOKEN_LENGTH = 64;
const PASSWORD_RESET_TOKEN_TTL_SECONDS = 1800;

function passwordResetTokenIsValidFormat(string $token): bool
{
    return preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1;
}

function passwordResetTokenHash(string $token): string
{
    return hash('sha256', $token);
}

function passwordResetFindUserByEmail(mysqli $connection, string $email): ?array
{
    $stmt = $connection->prepare('SELECT user_id, email FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    return $user ?: null;
}

function passwordResetIssueToken(mysqli $connection, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = passwordResetTokenHash($token);
    $expiryMinutes = intdiv(PASSWORD_RESET_TOKEN_TTL_SECONDS, 60);

    $connection->begin_transaction();

    try {
        $delete = $connection->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL');
        $delete->bind_param('i', $userId);
        $delete->execute();

        $insert = $connection->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) '
            . "VALUES (?, ?, DATE_ADD(NOW(), INTERVAL $expiryMinutes MINUTE))"
        );
        $insert->bind_param('is', $userId, $tokenHash);
        $insert->execute();

        $connection->commit();

        return $token;
    } catch (Throwable $exception) {
        $connection->rollback();
        throw $exception;
    }
}

function passwordResetDeleteOutstandingTokens(mysqli $connection, int $userId): void
{
    $stmt = $connection->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
}

function passwordResetFindValidToken(mysqli $connection, string $token): ?array
{
    if (!passwordResetTokenIsValidFormat($token)) {
        return null;
    }

    $tokenHash = passwordResetTokenHash($token);
    $stmt = $connection->prepare(
        'SELECT reset_id, user_id FROM password_resets '
        . 'WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();

    return $reset ?: null;
}

function passwordResetConsumeToken(mysqli $connection, string $token, string $passwordHash): bool
{
    if (!passwordResetTokenIsValidFormat($token)) {
        return false;
    }

    $tokenHash = passwordResetTokenHash($token);
    $connection->begin_transaction();

    try {
        $lookup = $connection->prepare(
            'SELECT reset_id, user_id FROM password_resets '
            . 'WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE'
        );
        $lookup->bind_param('s', $tokenHash);
        $lookup->execute();
        $reset = $lookup->get_result()->fetch_assoc();

        if (!$reset) {
            $connection->rollback();
            return false;
        }

        $userId = (int) $reset['user_id'];
        $passwordUpdate = $connection->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $passwordUpdate->bind_param('si', $passwordHash, $userId);
        $passwordUpdate->execute();

        $consume = $connection->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
        $consume->bind_param('i', $userId);
        $consume->execute();

        $connection->commit();
        return true;
    } catch (Throwable $exception) {
        $connection->rollback();
        throw $exception;
    }
}

function passwordResetUrl(string $token): string
{
    return APP_PUBLIC_URL . '/reset_password.php?token=' . rawurlencode($token);
}

function passwordResetSendMail(string $recipient, string $token): void
{
    $from = trim((string) ini_get('sendmail_from'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('XAMPP sendmail_from is not configured with a valid sender email address.');
    }

    $subject = APP_NAME . ' password reset';
    $message = "A password reset was requested for your " . APP_NAME . " account.\n\n"
        . "Open this link within 30 minutes to choose a new password:\n"
        . passwordResetUrl($token) . "\n\n"
        . "If you did not request this, you can ignore this email.\n";
    $headers = [
        'From: ' . APP_NAME . ' <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    if (!@mail($recipient, $subject, $message, implode("\r\n", $headers))) {
        throw new RuntimeException('Password reset email could not be sent.');
    }
}
