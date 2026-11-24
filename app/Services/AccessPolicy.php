<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Record-level authorization (layer 2 of the RBAC model, Phase 1 section 3).
 * Every lookup is by the logged-in user's id; the active role decides which relation counts.
 */
final class AccessPolicy
{
    public static function studentId(?int $userId = null): ?int
    {
        $id = Database::connection()->value(
            'SELECT id FROM students WHERE user_id = :u AND deleted_at IS NULL',
            ['u' => $userId ?? Auth::id()]
        );
        return $id === null ? null : (int) $id;
    }

    public static function isMember(int $groupId, ?int $userId = null): bool
    {
        return (bool) Database::connection()->value(
            "SELECT 1 FROM project_members m JOIN students s ON s.id = m.student_id
              WHERE m.group_id = :g AND s.user_id = :u AND m.status = 'active'",
            ['g' => $groupId, 'u' => $userId ?? Auth::id()]
        );
    }

    public static function isLeader(int $groupId, ?int $userId = null): bool
    {
        return (bool) Database::connection()->value(
            "SELECT 1 FROM project_members m JOIN students s ON s.id = m.student_id
              WHERE m.group_id = :g AND s.user_id = :u AND m.status = 'active' AND m.member_role = 'leader'",
            ['g' => $groupId, 'u' => $userId ?? Auth::id()]
        );
    }

    public static function isAdvisor(int $groupId, ?int $userId = null): bool
    {
        return (bool) Database::connection()->value(
            "SELECT 1 FROM project_advisors WHERE group_id = :g AND advisor_id = :u AND status = 'assigned'",
            ['g' => $groupId, 'u' => $userId ?? Auth::id()]
        );
    }

    /** Committee member of any round of this proposal. */
    public static function isCommittee(int $proposalId, ?int $userId = null): bool
    {
        $u = $userId ?? Auth::id();
        return (bool) Database::connection()->value(
            "SELECT 1 FROM committee_assignments WHERE proposal_id = :p AND status <> 'cancelled'
                AND (member1_id = :u1 OR member2_id = :u2)",
            ['p' => $proposalId, 'u1' => $u, 'u2' => $u]
        );
    }

    /** Can the current user (in the active role) see this group's records? */
    public static function canViewGroup(int $groupId): bool
    {
        return match (Auth::activeRole()) {
            'admin'     => true,
            'student'   => self::isMember($groupId),
            'advisor'   => self::isAdvisor($groupId),
            'committee' => (bool) Database::connection()->value(
                "SELECT 1 FROM projects p JOIN proposal_submissions ps ON ps.project_id = p.id
                   JOIN committee_assignments ca ON ca.proposal_id = ps.id AND ca.status <> 'cancelled'
                  WHERE p.group_id = :g AND (ca.member1_id = :u1 OR ca.member2_id = :u2)",
                ['g' => $groupId, 'u1' => Auth::id(), 'u2' => Auth::id()]
            ),
            default => false,
        };
    }

    public static function authorizeGroup(int $groupId): void
    {
        if (!self::canViewGroup($groupId)) {
            throw HttpException::forbidden();
        }
    }

    public static function groupOfProject(int $projectId): int
    {
        $g = Database::connection()->value('SELECT group_id FROM projects WHERE id = :p AND deleted_at IS NULL', ['p' => $projectId]);
        if ($g === null) {
            throw HttpException::notFound();
        }
        return (int) $g;
    }

    public static function groupOfProposal(int $proposalId): int
    {
        $g = Database::connection()->value(
            'SELECT p.group_id FROM proposal_submissions ps JOIN projects p ON p.id = ps.project_id WHERE ps.id = :id',
            ['id' => $proposalId]
        );
        if ($g === null) {
            throw HttpException::notFound();
        }
        return (int) $g;
    }

    /**
     * Download rule for a stored document (Section 15 "controlled download endpoints").
     */
    public static function canDownload(array $file): bool
    {
        $db = Database::connection();
        $role = Auth::activeRole();
        if ($role === 'admin') {
            return true;
        }
        $groupId = null;
        $proposalId = null;
        switch ($file['owner_type']) {
            case 'topic_submission':
                $groupId = $db->value(
                    'SELECT p.group_id FROM topic_submissions ts JOIN project_topics t ON t.id = ts.topic_id
                       JOIN projects p ON p.id = t.project_id WHERE ts.id = :id',
                    ['id' => (int) $file['owner_id']]
                );
                break;
            case 'proposal_version':
                $row = $db->fetch(
                    'SELECT p.group_id, pv.proposal_id FROM proposal_versions pv JOIN proposal_submissions ps ON ps.id = pv.proposal_id
                       JOIN projects p ON p.id = ps.project_id WHERE pv.id = :id',
                    ['id' => (int) $file['owner_id']]
                );
                $groupId = $row['group_id'] ?? null;
                $proposalId = $row['proposal_id'] ?? null;
                break;
            case 'revision_response':
                $row = $db->fetch(
                    'SELECT p.group_id, rr.proposal_id FROM revision_responses rs JOIN revision_requests rr ON rr.id = rs.revision_request_id
                       JOIN proposal_submissions ps ON ps.id = rr.proposal_id JOIN projects p ON p.id = ps.project_id WHERE rs.id = :id',
                    ['id' => (int) $file['owner_id']]
                );
                $groupId = $row['group_id'] ?? null;
                $proposalId = $row['proposal_id'] ?? null;
                break;
        }
        if ($groupId === null) {
            return false;
        }
        return match ($role) {
            'student'   => self::isMember((int) $groupId),
            'advisor'   => self::isAdvisor((int) $groupId),
            'committee' => $proposalId !== null && self::isCommittee((int) $proposalId),
            default     => false,
        };
    }
}
