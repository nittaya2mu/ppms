<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Application-level copy of the database triggers (DD-11), applied to every SQL statement
 * sent through App\Core\Database. It keeps the rules in force on hosts that do not allow
 * triggers (database/schema_shared_hosting.sql) and is harmless where triggers exist.
 *
 * - Append-only tables never receive UPDATE or DELETE.
 * - Proposal versions are never deleted.
 * Row-level rules (locked evaluations, committee validity, immutable submitted versions)
 * live in App\Services\Rules and are called by the services that change those rows.
 */
final class IntegrityGuard
{
    public const APPEND_ONLY = [
        'topic_approvals',
        'approval_decisions',
        'status_histories',
        'audit_logs',
        'group_member_histories',
    ];

    public const NO_DELETE = ['proposal_versions'];

    public static function check(string $sql): void
    {
        // Strip leading comments/whitespace, then look at the statement verb and target table.
        $statement = ltrim((string) preg_replace('#^\s*(/\*.*?\*/|--[^\n]*\n)*#s', '', $sql));
        if (!preg_match('/^(UPDATE|DELETE\s+FROM|REPLACE\s+INTO|TRUNCATE(?:\s+TABLE)?)\s+`?(\w+)`?/i', $statement, $m)) {
            return;
        }
        $verb = strtoupper(preg_replace('/\s+.*/', '', $m[1]));
        $table = strtolower($m[2]);

        if (in_array($table, self::APPEND_ONLY, true)) {
            throw new IntegrityViolation("$table is append-only ($verb refused)");
        }
        if ($verb !== 'UPDATE' && in_array($table, self::NO_DELETE, true)) {
            throw new IntegrityViolation("$table rows cannot be deleted");
        }
    }
}
