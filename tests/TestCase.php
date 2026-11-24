<?php

declare(strict_types=1);

namespace Tests;

/**
 * Minimal, dependency-free test base (runs on XAMPP without Composer packages).
 * Public methods whose names start with "test" are executed by tests/run.php.
 */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void
    {
    }

    protected function assertTrue(bool $condition, string $message = 'Expected true'): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new AssertionFailed($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'Expected false'): void
    {
        $this->assertTrue(!$condition, $message);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed(($message ? "$message: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    /** @param class-string<\Throwable> $class */
    protected function assertThrows(string $class, callable $fn, ?string $messageContains = null): void
    {
        $this->assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                throw new AssertionFailed("Expected $class, got " . get_class($e) . ': ' . $e->getMessage());
            }
            if ($messageContains !== null && !str_contains($e->getMessage(), $messageContains)) {
                throw new AssertionFailed("Exception message '{$e->getMessage()}' does not contain '$messageContains'");
            }
            return;
        }
        throw new AssertionFailed("Expected $class to be thrown");
    }
}

final class AssertionFailed extends \RuntimeException
{
}
