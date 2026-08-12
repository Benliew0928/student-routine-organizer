<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../includes/validation.php';

test('strong password policy accepts a compliant password', function (): void {
    assertSameValue([], passwordPolicyErrors('Routine!Plan2026'));
});

test('strong password policy rejects passwords shorter than 12 characters', function (): void {
    $errors = passwordPolicyErrors('Short!2a');

    assertTrueValue(in_array('Password must be between 12 and 128 characters.', $errors, true));
});

test('strong password policy requires every character category', function (): void {
    assertTrueValue(in_array('Password must include an uppercase letter.', passwordPolicyErrors('routine!plan2026'), true));
    assertTrueValue(in_array('Password must include a lowercase letter.', passwordPolicyErrors('ROUTINE!PLAN2026'), true));
    assertTrueValue(in_array('Password must include a number.', passwordPolicyErrors('Routine!Planner'), true));
    assertTrueValue(in_array('Password must include a symbol.', passwordPolicyErrors('RoutinePlan2026'), true));
});

test('strong password policy rejects whitespace and overlength passwords', function (): void {
    assertTrueValue(in_array('Password cannot contain spaces or other whitespace.', passwordPolicyErrors('Routine Plan!2026'), true));
    assertTrueValue(in_array('Password must be between 12 and 128 characters.', passwordPolicyErrors('A1!' . str_repeat('a', 126)), true));
});

test('login no longer publishes demo credentials and password pages describe the policy', function (): void {
    $login = file_get_contents(__DIR__ . '/../login.php');
    $registration = file_get_contents(__DIR__ . '/../register.php');
    $reset = file_get_contents(__DIR__ . '/../reset_password.php');
    $appScript = file_get_contents(__DIR__ . '/../assets/js/app.js');

    assertTrueValue(is_string($login));
    assertSameValue(false, str_contains($login, 'password123'));
    assertSameValue(false, str_contains($login, 'admin123'));
    assertSameValue(false, str_contains($login, 'Sample student:'));
    assertSameValue(false, str_contains($login, 'Sample admin:'));

    assertTrueValue(is_string($registration));
    assertTrueValue(str_contains($registration, 'passwordPolicyErrors($password)'));
    assertTrueValue(str_contains($registration, 'minlength="12"'));
    assertTrueValue(str_contains($registration, 'maxlength="128"'));
    assertTrueValue(str_contains($login, 'data-password-toggle'));
    assertTrueValue(str_contains($registration, 'data-password-assistance'));
    assertTrueValue(str_contains($registration, 'data-password-confirmation'));
    assertTrueValue(is_string($reset));
    assertTrueValue(str_contains($reset, 'data-password-assistance'));
    assertTrueValue(is_string($appScript));
    assertTrueValue(str_contains($appScript, 'password-requirement-bar'));
    assertTrueValue(str_contains($appScript, '&& !/\\s/.test(password)'));
});

finishTests();
