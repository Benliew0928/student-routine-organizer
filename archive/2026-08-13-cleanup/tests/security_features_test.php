<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../modules/exercise/exercise_helpers.php';

test('login and registration forms verify CSRF tokens', function (): void {
    foreach (['login.php', 'register.php'] as $filename) {
        $source = file_get_contents(__DIR__ . '/../' . $filename);

        assertTrueValue(is_string($source));
        assertTrueValue(str_contains($source, 'verifyCsrfToken('), $filename . ' must verify CSRF.');
        assertTrueValue(str_contains($source, 'csrfInput()'), $filename . ' must render CSRF input.');
    }
});

test('authenticated sessions expire after the configured idle timeout', function (): void {
    $_SESSION = [
        'user_id' => 7,
        'last_activity' => time() - SESSION_IDLE_TIMEOUT_SECONDS,
    ];

    assertSameValue('expired', sessionAccessState());
    assertSameValue(false, isLoggedIn());

    refreshAuthenticatedSession();
    assertSameValue('active', sessionAccessState());
});

test('session cookie settings are HTTP-only and SameSite Lax', function (): void {
    $source = file_get_contents(__DIR__ . '/../includes/auth.php');

    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, "'httponly' => true"));
    assertTrueValue(str_contains($source, "'samesite' => 'Lax'"));
    assertTrueValue(str_contains($source, 'applicationUsesHttps()'));
});

test('exercise photo metadata is restricted to safe generated names', function (): void {
    assertTrueValue(exercisePhotoFilenameIsSafe(str_repeat('a', 32) . '.jpg'));
    assertTrueValue(exercisePhotoFilenameIsSafe(str_repeat('b', 32) . '.png'));
    assertSameValue(false, exercisePhotoFilenameIsSafe('../photo.png'));
    assertSameValue(false, exercisePhotoFilenameIsSafe(str_repeat('a', 32) . '.gif'));
    assertSameValue(null, exercisePhotoStoragePath('../outside.jpg'));
});

test('exercise photo validation accepts PNG and rejects unsupported types and oversize files', function (): void {
    $temporaryFile = tempnam(sys_get_temp_dir(), 'exercise-photo-test-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Could not create test image.');
    }

    try {
        file_put_contents($temporaryFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLqWQAAAABJRU5ErkJggg==', true));
        $valid = exercisePhotoUploadFromRequest([
            'exercise_photo' => [
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($temporaryFile),
                'tmp_name' => $temporaryFile,
            ],
        ]);
        assertSameValue([], $valid['errors']);
        assertSameValue('image/png', $valid['photo']['mime_type']);

        $oversize = exercisePhotoUploadFromRequest([
            'exercise_photo' => [
                'error' => UPLOAD_ERR_OK,
                'size' => (2 * 1024 * 1024) + 1,
                'tmp_name' => $temporaryFile,
            ],
        ]);
        assertTrueValue($oversize['errors'] !== []);
    } finally {
        @unlink($temporaryFile);
    }
});

test('exercise photo route is authenticated and storage is not directly accessible', function (): void {
    $photoRoute = file_get_contents(__DIR__ . '/../modules/exercise/photo.php');
    $storageRule = file_get_contents(__DIR__ . '/../storage/exercise-photos/.htaccess');

    assertTrueValue(is_string($photoRoute));
    assertTrueValue(str_contains($photoRoute, 'requireLogin();'));
    assertTrueValue(str_contains($photoRoute, 'exerciseLoadForUser('));
    assertTrueValue(str_contains($photoRoute, 'X-Content-Type-Options: nosniff'));
    assertSameValue('Require all denied', trim((string) $storageRule));
});

test('central error logging is configured without displaying exception details', function (): void {
    $logger = file_get_contents(__DIR__ . '/../includes/error_handler.php');
    $appConfig = file_get_contents(__DIR__ . '/../config/app.php');

    assertTrueValue(is_string($logger));
    assertTrueValue(str_contains($logger, 'application.log'));
    assertTrueValue(str_contains($logger, "set_exception_handler('handleUncaughtApplicationException')"));
    assertSameValue(false, str_contains($logger, 'echo $exception->getMessage'));
    assertTrueValue(is_string($appConfig));
    assertTrueValue(str_contains($appConfig, 'includes/error_handler.php'));
});

finishTests();
