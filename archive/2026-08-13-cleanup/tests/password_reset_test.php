<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../includes/password_reset.php';

test('reset tokens have the required format and are stored as SHA-256 hashes', function (): void {
    $token = bin2hex(random_bytes(32));

    assertSameValue(64, strlen($token));
    assertTrueValue(passwordResetTokenIsValidFormat($token));
    assertSameValue(hash('sha256', $token), passwordResetTokenHash($token));
    assertSameValue(false, passwordResetTokenIsValidFormat(strtoupper($token)));
    assertSameValue(false, passwordResetTokenIsValidFormat('not-a-reset-token'));
});

test('reset flow shares the strong password policy', function (): void {
    assertSameValue([], passwordPolicyErrors('Reset!Routine2026'));
    assertTrueValue(passwordPolicyErrors('resetpassword') !== []);
});

test('public reset pages include CSRF protection and generic request confirmation', function (): void {
    $forgot = file_get_contents(__DIR__ . '/../forgot_password.php');
    $reset = file_get_contents(__DIR__ . '/../reset_password.php');
    $login = file_get_contents(__DIR__ . '/../login.php');

    assertTrueValue(is_string($forgot));
    assertTrueValue(is_string($reset));
    assertTrueValue(is_string($login));
    assertTrueValue(str_contains($forgot, 'verifyCsrfToken'));
    assertTrueValue(str_contains($forgot, 'If an account matches that email address'));
    assertTrueValue(str_contains($reset, 'verifyCsrfToken'));
    assertTrueValue(str_contains($reset, 'passwordPolicyErrors($password)'));
    assertTrueValue(str_contains($login, 'Forgot password?'));
});

test('reset mail uses configured XAMPP sender and no repository credentials', function (): void {
    $helper = file_get_contents(__DIR__ . '/../includes/password_reset.php');

    assertTrueValue(is_string($helper));
    assertTrueValue(str_contains($helper, "ini_get('sendmail_from')"));
    assertTrueValue(str_contains($helper, 'mail($recipient'));
    assertSameValue(false, str_contains($helper, 'smtp.gmail.com'));
    assertSameValue(false, str_contains($helper, 'app password'));
});

finishTests();
