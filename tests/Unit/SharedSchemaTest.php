<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/** database/schema_shared_hosting.sql must be regenerated whenever schema.sql changes. */
final class SharedSchemaTest extends TestCase
{
    public function testGeneratedSchemaIsUpToDate(): void
    {
        $root = dirname(__DIR__, 2);
        if (!function_exists('build_shared_schema')) {
            // Load the generator function without running its CLI part.
            $code = (string) file_get_contents($root . '/tools/build-shared-schema.php');
            $fn = substr($code, (int) strpos($code, 'function build_shared_schema'));
            eval($fn);
        }
        $expected = build_shared_schema((string) file_get_contents($root . '/database/schema.sql'));
        $actual = (string) file_get_contents($root . '/database/schema_shared_hosting.sql');
        $this->assertTrue($expected === $actual, 'schema_shared_hosting.sql is stale: run php tools/build-shared-schema.php');
    }

    public function testGeneratedSchemaHasNoTriggersOrForeignKeys(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema_shared_hosting.sql');
        $this->assertFalse(str_contains($sql, 'FOREIGN KEY'));
        $this->assertFalse(str_contains($sql, 'TRIGGER'));
        $this->assertFalse(str_contains($sql, 'DELIMITER'));
        $this->assertFalse((bool) preg_match('/CREATE DATABASE|^USE /m', $sql));
        $this->assertSame(36, preg_match_all('/^CREATE TABLE /m', $sql));
    }
}
