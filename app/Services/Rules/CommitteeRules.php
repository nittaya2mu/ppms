<?php

declare(strict_types=1);

namespace App\Services\Rules;

use App\Core\Database;

/**
 * Section 8 validation rules for committee assignment. Mirrors trg_ca_before_insert /
 * trg_ca_before_update so the rules hold on hosts without triggers.
 */
final class CommitteeRules
{
    public static function assertAssignable(int $advisorId, int $member1Id, int $member2Id): void
    {
        if ($member1Id === $member2Id) {
            throw new RuleViolation('committee_distinct', 'The two committee members must be different users');
        }
        if ($member1Id === $advisorId || $member2Id === $advisorId) {
            throw new RuleViolation('committee_not_advisor', 'A committee member cannot be the project advisor');
        }
        $active = (int) Database::connection()->value(
            "SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur
               JOIN roles r ON r.id = ur.role_id AND r.code = 'committee'
               JOIN users u ON u.id = ur.user_id AND u.is_active = 1 AND u.deleted_at IS NULL
              WHERE ur.is_active = 1 AND ur.user_id IN (:a, :b)",
            ['a' => $member1Id, 'b' => $member2Id]
        );
        if ($active < 2) {
            throw new RuleViolation('committee_role', 'Both committee members must have an active committee role');
        }
    }

    /** The assigned proposal version of a round never changes; a new round is created instead. */
    public static function assertVersionUnchanged(int $assignmentId, int $newVersionId): void
    {
        $current = (int) Database::connection()->value(
            'SELECT proposal_version_id FROM committee_assignments WHERE id = :id',
            ['id' => $assignmentId]
        );
        if ($current !== $newVersionId) {
            throw new RuleViolation('assignment_version_fixed', 'Assigned proposal version cannot change; create a new round');
        }
    }
}
