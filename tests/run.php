<?php

declare(strict_types=1);

/**
 * Test runner:  php tests/run.php [filter]
 *   filter = optional substring of "ClassName::testMethod"
 *   PPMS_SCHEMA=shared php tests/run.php   runs against the shared-hosting schema
 * Exit code 0 when all tests pass.
 */

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        if (str_starts_with($class, 'App\\')) {
            $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    require $root . '/app/Helpers/functions.php';
}
require __DIR__ . '/TestCase.php';
require __DIR__ . '/DatabaseTestCase.php';

date_default_timezone_set('Asia/Bangkok');
$_SESSION = [];

$filter = $argv[1] ?? '';
$files = array_merge(glob(__DIR__ . '/Unit/*Test.php') ?: [], glob(__DIR__ . '/Integration/*Test.php') ?: []);
$passed = $failed = $skipped = $assertions = 0;
$failures = [];
$start = microtime(true);

foreach ($files as $file) {
    require_once $file;
    $class = 'Tests\\' . basename(dirname($file)) . '\\' . basename($file, '.php');
    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $name = basename($file, '.php') . '::' . $method;
        if ($filter !== '' && !str_contains($name, $filter)) {
            continue;
        }
        $test = new $class();
        try {
            $test->setUp();
            $test->$method();
            $passed++;
            echo '.';
        } catch (Tests\SkipTest $e) {
            $skipped++;
            echo 'S';
        } catch (Throwable $e) {
            $failed++;
            $failures[] = [$name, $e];
            echo 'F';
        }
        $assertions += $test->assertions;
    }
}

$mode = getenv('PPMS_SCHEMA') === 'shared' ? 'shared' : 'full';
printf("\n\nschema: %s | %d passed, %d failed, %d skipped, %d assertions (%.2fs)\n", $mode, $passed, $failed, $skipped, $assertions, microtime(true) - $start);
foreach ($failures as [$name, $e]) {
    printf("\nFAIL %s\n  %s: %s\n  at %s:%d\n", $name, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
}
exit($failed === 0 ? 0 : 1);
