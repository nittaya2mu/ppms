<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;

/**
 * Scope approval (แบบฟอร์ม 7: แบบอนุมัติขอบเขตโครงงาน) - the last step.
 * The students list the project scope with weights totalling 100 %; the advisor and both
 * committee members must all approve. A revision request makes the students submit a new version.
 */
final class ScopeService
{
    public const MAX_ITEMS = 18;
    public const ROLES = ['advisor', 'committee1', 'committee2'];

    public static function current(int $projectId): ?array
    {
        $s = Database::connection()->fetch('SELECT * FROM scope_submissions WHERE project_id = :p ORDER BY version_no DESC LIMIT 1', ['p' => $projectId]);
        return $s ? self::hydrate($s) : null;
    }

    public static function find(int $scopeId): ?array
    {
        $s = Database::connection()->fetch('SELECT * FROM scope_submissions WHERE id = :id', ['id' => $scopeId]);
        return $s ? self::hydrate($s) : null;
    }

    public static function history(int $projectId): array
    {
        return array_map([self::class, 'hydrate'], Database::connection()->fetchAll(
            'SELECT * FROM scope_submissions WHERE project_id = :p ORDER BY version_no DESC', ['p' => $projectId]
        ));
    }

    private static function hydrate(array $s): array
    {
        $items = json_decode((string) $s['items_json'], true);
        $s['items'] = is_array($items) ? $items : [];
        $s['approvals'] = [];
        foreach (Database::connection()->fetchAll(
            'SELECT a.*, u.title_th, u.first_name_th, u.last_name_th FROM scope_approvals a JOIN users u ON u.id = a.approver_id WHERE a.scope_id = :s',
            ['s' => (int) $s['id']]
        ) as $a) {
            $s['approvals'][$a['approver_role']] = $a;
        }
        return $s;
    }

    /**
     * Who approves: the advisor and the two committee members of the latest evaluation round.
     * @return array<string,?int> role => user id
     */
    public static function approvers(int $projectId): array
    {
        $db = Database::connection();
        $project = $db->fetch('SELECT * FROM projects WHERE id = :p', ['p' => $projectId]);
        $out = ['advisor' => GroupService::advisorAssigned((int) $project['group_id']), 'committee1' => null, 'committee2' => null];
        $proposal = ProposalService::forProject($projectId);
        $a = $proposal ? ProposalService::currentAssignment((int) $proposal['id']) : null;
        if ($a !== null) {
            $out['committee1'] = (int) $a['member1_id'];
            $out['committee2'] = (int) $a['member2_id'];
        }
        return $out;
    }

    /** The approver roles a user holds on this project. */
    public static function rolesOf(int $projectId, int $userId): array
    {
        return array_keys(array_filter(self::approvers($projectId), fn ($id) => $id === $userId));
    }

    /** Why the students cannot submit yet (null = they can). */
    public static function blocker(int $projectId): ?string
    {
        $proposal = ProposalService::forProject($projectId);
        if ($proposal === null || $proposal['status'] !== 'approved') {
            return 'scope_after_proposal';
        }
        if (ProgressService::reviewedCount($projectId) < ProgressService::minReports()) {
            return 'scope_needs_progress';
        }
        $current = self::current($projectId);
        if ($current !== null && $current['status'] !== 'revision_required') {
            return $current['status'] === 'approved' ? 'scope_already_approved' : 'scope_pending';
        }
        return null;
    }

    /** @param array<int,array{detail?:string,weight?:string}> $rows */
    public static function submit(int $projectId, int $userId, array $rows): int
    {
        $project = Database::connection()->fetch('SELECT * FROM projects WHERE id = :p AND deleted_at IS NULL', ['p' => $projectId])
            ?? throw new RuleViolation('project_missing', 'Project not found');
        if (!AccessPolicy::isLeader((int) $project['group_id'], $userId)) {
            throw new RuleViolation('not_leader', 'Only the group leader submits the scope form');
        }
        if ($blocker = self::blocker($projectId)) {
            throw new RuleViolation($blocker, 'Scope cannot be submitted now');
        }
        $items = [];
        foreach ($rows as $row) {
            $detail = trim((string) ($row['detail'] ?? ''));
            $weight = trim((string) ($row['weight'] ?? ''));
            if ($detail === '' && $weight === '') {
                continue;
            }
            if ($detail === '' || !is_numeric($weight) || (float) $weight <= 0) {
                throw new RuleViolation('scope_item_invalid', 'Each scope item needs a description and a positive weight');
            }
            $items[] = ['detail' => mb_substr($detail, 0, 1000), 'weight' => round((float) $weight, 2)];
        }
        if (!$items || count($items) > self::MAX_ITEMS) {
            throw new RuleViolation('scope_items_count', 'Between 1 and 18 scope items are required');
        }
        if (abs(array_sum(array_column($items, 'weight')) - 100) > 0.001) {
            throw new RuleViolation('scope_weights', 'Weights must total 100%');
        }
        $approvers = self::approvers($projectId);
        if (in_array(null, $approvers, true)) {
            throw new RuleViolation('scope_no_committee', 'The advisor and committee must be assigned');
        }
        $db = Database::connection();
        return $db->transaction(function (Database $db) use ($projectId, $userId, $items, $project, $approvers) {
            $version = 1 + (int) $db->value('SELECT COALESCE(MAX(version_no), 0) FROM scope_submissions WHERE project_id = :p', ['p' => $projectId]);
            $id = $db->insert('scope_submissions', [
                'project_id' => $projectId, 'version_no' => $version, 'items_json' => json_encode($items, JSON_UNESCAPED_UNICODE), 'submitted_by' => $userId,
            ]);
            $db->insert('status_histories', ['entity_type' => 'scope', 'entity_id' => $id, 'from_status' => null, 'to_status' => 'pending', 'changed_by' => $userId]);
            AuditLogger::log('scope.submitted', 'scope', $id, null, ['version' => $version, 'items' => count($items)], userId: $userId);
            NotificationService::notify(array_values(array_unique($approvers)), 'scope.submitted', 'แบบอนุมัติขอบเขตรอการอนุมัติ',
                "โครงงาน {$project['project_code']} ส่งแบบอนุมัติขอบเขต version $version", '/projects/' . $projectId . '/scope', 'scope', $id);
            return $id;
        });
    }

    /** $decision: approved | revision_required */
    public static function decide(int $scopeId, int $userId, string $decision, ?string $comments): void
    {
        $scope = self::find($scopeId) ?? throw new RuleViolation('scope_missing', 'Scope form not found');
        if ($scope['status'] !== 'pending') {
            throw new RuleViolation('scope_not_pending', 'This scope form is not waiting for approval');
        }
        if (!in_array($decision, ['approved', 'revision_required'], true)) {
            throw new RuleViolation('invalid', 'Invalid decision');
        }
        if ($decision === 'revision_required' && trim((string) $comments) === '') {
            throw new RuleViolation('reason_required', 'Comments are required');
        }
        $roles = array_diff(self::rolesOf((int) $scope['project_id'], $userId), array_keys($scope['approvals']));
        if (!$roles) {
            throw new RuleViolation('scope_not_approver', 'You are not a pending approver of this scope form');
        }
        $role = reset($roles);
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($scope, $scopeId, $userId, $decision, $comments, $role) {
            $db->insert('scope_approvals', [
                'scope_id' => $scopeId, 'approver_id' => $userId, 'approver_role' => $role, 'decision' => $decision,
                'comments' => trim((string) $comments) ?: null,
            ]);
            $project = $db->fetch('SELECT * FROM projects WHERE id = :p', ['p' => (int) $scope['project_id']]);
            $students = GroupService::memberUserIds((int) $project['group_id']);
            $newStatus = null;
            if ($decision === 'revision_required') {
                $newStatus = 'revision_required';
            } elseif ((int) $db->value("SELECT COUNT(*) FROM scope_approvals WHERE scope_id = :s AND decision = 'approved'", ['s' => $scopeId]) === count(self::ROLES)) {
                $newStatus = 'approved';
            }
            AuditLogger::log('scope.' . $decision, 'scope', $scopeId, null, ['role' => $role], $comments, $userId);
            if ($newStatus !== null) {
                $db->update('scope_submissions', ['status' => $newStatus, 'decided_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $scopeId]);
                $db->insert('status_histories', ['entity_type' => 'scope', 'entity_id' => $scopeId, 'from_status' => 'pending', 'to_status' => $newStatus, 'reason' => $comments ?: null, 'changed_by' => $userId]);
                $title = $newStatus === 'approved' ? 'อนุมัติขอบเขตโครงงานครบทุกท่านแล้ว' : 'ขอให้แก้ไขแบบอนุมัติขอบเขต';
                NotificationService::notify(array_merge($students, NotificationService::admins()), 'scope.' . $newStatus, $title,
                    "โครงงาน {$project['project_code']}" . ($comments ? ': ' . $comments : ''), '/projects/' . $project['id'] . '/scope', 'scope', $scopeId);
            }
        });
    }

    /** Scope forms waiting for this user (advisor / committee) or all forms (admin). */
    public static function queue(int $offeringId, string $role, int $userId): array
    {
        $rows = Database::connection()->fetchAll(
            "SELECT s.*, p.project_code, p.title_th, g.group_code, g.group_name, p.id AS project_id
               FROM scope_submissions s JOIN projects p ON p.id = s.project_id JOIN project_groups g ON g.id = p.group_id
              WHERE p.offering_id = :o AND p.deleted_at IS NULL
                AND s.version_no = (SELECT MAX(x.version_no) FROM scope_submissions x WHERE x.project_id = s.project_id)
              ORDER BY s.status = 'pending' DESC, s.submitted_at",
            ['o' => $offeringId]
        );
        if ($role === 'admin') {
            return $rows;
        }
        $out = [];
        foreach ($rows as $r) {
            $scope = self::hydrate($r);
            $mine = array_diff(self::rolesOf((int) $r['project_id'], $userId), array_keys($scope['approvals']));
            $involved = self::rolesOf((int) $r['project_id'], $userId);
            if ($involved) {
                $r['awaiting_me'] = $r['status'] === 'pending' && $mine;
                $out[] = $r;
            }
        }
        return $out;
    }
}
