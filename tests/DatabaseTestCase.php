<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;

/**
 * Rebuilds a throw-away database (default `ppms_test`) from database/schema.sql + seed.sql
 * + seed_demo.sql before EVERY test, so tests are independent.
 * Connection settings come from config/database.php / PPMS_DB_* environment variables;
 * the account needs CREATE/DROP on the test database and TRIGGER privilege.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Database $db;

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
        foreach (['schema.sql', 'seed.sql', 'seed_demo.sql'] as $file) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/' . $file);
            $sql = preg_replace(
                ['/DROP DATABASE IF EXISTS ppms;/', '/CREATE DATABASE ppms /', '/USE ppms;/'],
                ["DROP DATABASE IF EXISTS $testDb;", "CREATE DATABASE $testDb ", "USE $testDb;"],
                $sql
            );
            SqlScript::run($pdo, $sql);
        }

        Database::reset(array_merge($config, ['database' => $testDb]));
        $this->db = Database::connection();
        \App\Services\SettingsService::flush();
    }

    /** Asserts that a statement is rejected by the database (constraint or trigger). */
    protected function assertDbRejects(callable $fn, string $messageContains = ''): void
    {
        $this->assertThrows(\PDOException::class, $fn, $messageContains === '' ? null : $messageContains);
    }
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
