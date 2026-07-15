<?php
declare(strict_types=1);

$testCount = 0;
$testFailures = 0;

function test(string $name, callable $callback): void
{
    global $testCount, $testFailures;

    $testCount++;

    try {
        $callback();
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $exception) {
        $testFailures++;
        echo '[FAIL] ' . $name . ': ' . $exception->getMessage() . PHP_EOL;
    }
}

function assertSameValue(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assertTrueValue(bool $condition, string $message = 'Expected condition to be true.'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function finishTests(): never
{
    global $testCount, $testFailures;

    echo PHP_EOL . sprintf('%d test(s), %d failure(s).', $testCount, $testFailures) . PHP_EOL;
    exit($testFailures === 0 ? 0 : 1);
}
