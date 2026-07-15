<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/app.php';

test('detects Server-Side checkout', function (): void {
    assertSameValue('/Server-Side', detectBaseUrl('/Server-Side/modules/journal/index.php', 'Server-Side'));
});

test('detects documented checkout', function (): void {
    assertSameValue(
        '/student-routine-organizer',
        detectBaseUrl('/student-routine-organizer/login.php', 'student-routine-organizer')
    );
});

test('supports document root', function (): void {
    assertSameValue('', detectBaseUrl('/index.php', 'diary-journal'));
});

test('does not mistake nested route for project folder at document root', function (): void {
    assertSameValue('', detectBaseUrl('/modules/journal/index.php', 'diary-journal'));
});

test('uses the assignment local timezone', function (): void {
    assertSameValue('Asia/Kuala_Lumpur', date_default_timezone_get());
});

finishTests();
