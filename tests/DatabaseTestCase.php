<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\IntegrityViolation;
use App\Services\Rules\RuleViolation;

/**
 * Rebuilds a throw-away database (default `ppms_test`) before EVERY test, so tests are independent.
 *
 *   PPMS_SCHEMA=full    (default) database/schema.sql            - with foreign keys and triggers
 *   PPMS_SCHEMA=shared            database/schema_shared_hosting.sql - InfinityFree variant
 *
 * Connection settings come from config/database.php / PPMS_DB_* environment variables;
 * the account needs CREATE/DROP on the test database (and TRIGGER for the full schema).
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Database $db;

    public static function schemaMode(): string
    {
        return getenv('PPMS_SCHEMA') === 'shared' ? 'shared' : 'full';
    }

    public function setUp(): void
    {
        $config = require dirname(__DIR__) . '/config/database.php';
        $testDb = getenv('PPMS_TEST_DB') ?: 'ppms_test';
        if (!preg_match('/^[a-z0-9_]+$/', $testDb) || $testDb === $config['database']) {
            throw new \RuntimeException('Refusing to use the main database for tests');
        }

        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']),
            $config['username'],
            $config['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("DROP DATABASE IF EXISTS `$testDb`");
        // Deliberately latin1: shared hosts (InfinityFree) create databases with a latin1 default,
        // so every table must declare utf8mb4 itself or Thai text is lost.
        $pdo->exec("CREATE DATABASE `$testDb` CHARACTER SET latin1");
        $pdo->exec("USE `$testDb`");

        $schema = self::schemaMode() === 'shared' ? 'schema_shared_hosting.sql' : 'schema.sql';
        foreach ([$schema, 'seed.sql', 'seed_demo.sql'] as $file) {
            SqlScript::run($pdo, (string) file_get_contents(dirname(__DIR__) . '/database/' . $file));
        }

        Database::reset(array_merge($config, ['database' => $testDb]));
        $this->db = Database::connection();
        \App\Services\SettingsService::flush();
    }

    /** Skips a test that checks a database-only safeguard (trigger or foreign key). */
    protected function requireFullSchema(): void
    {
        if (self::schemaMode() !== 'full') {
            throw new SkipTest('needs triggers/foreign keys (full schema)');
        }
    }

    /** Raw SQL that bypasses App\Core\IntegrityGuard, to prove the database itself refuses it. */
    protected function rawExec(string $sql): void
    {
        $this->db->pdo()->exec($sql);
    }

    /** Asserts that the data layer refuses an operation (constraint, trigger or PHP integrity guard). */
    protected function assertDbRejects(callable $fn, string $messageContains = ''): void
    {
        $this->assertions++;
        try {
            $fn();
        } catch (\PDOException | IntegrityViolation | RuleViolation $e) {
            if ($messageContains !== '' && !str_contains($e->getMessage(), $messageContains)) {
                throw new AssertionFailed("Rejected, but message '{$e->getMessage()}' lacks '$messageContains'");
            }
            return;
        }
        throw new AssertionFailed('Expected the operation to be rejected');
    }
}

final class SkipTest extends \RuntimeException
{
}

/** Executes a .sql file that may contain DELIMITER blocks (triggers). */
final class SqlScript
{
    public static function run(\PDO $pdo, string $sql): void
    {
        $delimiter = ';';
        $buffer = '';
        foreach (preg_split('/\R/', $sql) as $line) {
            $trim = trim($line);
            if ($buffer === '' && ($trim === '' || str_starts_with($trim, '--'))) {
                continue;
            }
            if (preg_match('/^DELIMITER\s+(\S+)$/i', $trim, $m)) {
                $delimiter = $m[1];
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with($trim, $delimiter)) {
                $statement = substr(rtrim($buffer), 0, -strlen($delimiter));
                if (trim($statement) !== '') {
                    $pdo->exec($statement);
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            $pdo->exec($buffer);
        }
    }
}
