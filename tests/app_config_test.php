<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/app.php';

test('detects Server-Side checkout', function (): void {
    assertSameValue('/Server-Side', detectBaseUrl('/Server-Side/modules/journal/index.php'));
});

test('detects documented checkout', function (): void {
    assertSameValue('/student-routine-organizer', detectBaseUrl('/student-routine-organizer/login.php'));
});

test('supports document root', function (): void {
    assertSameValue('', detectBaseUrl('/index.php'));
});

finishTests();
