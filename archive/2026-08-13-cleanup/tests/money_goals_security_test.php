<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';

$source = file_get_contents(__DIR__ . '/../modules/money/goals.php');

test('savings-goal updates require a CSRF token', function () use ($source): void {
    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, 'verifyCsrfToken('));
});

test('every savings-goal post form includes a CSRF input', function () use ($source): void {
    assertTrueValue(is_string($source));
    preg_match_all('/<form method="post"/', $source, $forms);
    preg_match_all('/<\\?= csrfInput\(\); \\?>/', $source, $tokens);

    assertSameValue(count($forms[0]), count($tokens[0]));
});

finishTests();
