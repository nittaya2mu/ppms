<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\Workflows;

/**
 * Group registration module (Section 4, FR-GRP-01..08).
 * Business rules: one leader; a student is in one active group per offering; membership
 * changes after registration need an administrator and are recorded in group_member_histories.
 */
final class GroupService
{
    public static function find(int $groupId): ?array
    {
        return Database::connection()->fetch(
            "SELECT g.*, p.id AS project_id, p.project_code, p.title_th, p.title_en, p.stage
               FROM project_groups g LEFT JOIN projects p ON p.group_id = g.id
              WHERE g.id = :id AND g.deleted_at IS NULL",
            ['id' => $groupId]
        );
    }

    /** The active group of a student in an offering (or a pending invitation's group). */
    public static function forStudent(int $studentId, int $offeringId): ?array
    {
        $id = Database::connection()->value(
            "SELECT g.id FROM project_members m JOIN project_groups g ON g.id = m.group_id
              WHERE m.student_id = :s AND m.offering_id = :o AND m.status = 'active' AND g.deleted_at IS NULL
              ORDER BY m.id DESC LIMIT 1",
            ['s' => $studentId, 'o' => $offeringId]
        );
        return $id === null ? null : self::find((int) $id);
    }

    public static function invitations(int $studentId, int $offeringId): array
    {
        return Database::connection()->fetchAll(
            "SELECT m.id AS member_id, g.id AS group_id, g.group_code, g.group_name, u.title_th, u.first_name_th, u.last_name_th
               FROM project_members m JOIN project_groups g ON g.id = m.group_id
               JOIN students ls ON ls.id = g.leader_student_id JOIN users u ON u.id = ls.user_id
              WHERE m.student_id = :s AND m.offering_id = :o AND m.status = 'invited' AND g.deleted_at IS NULL",
            ['s' => $studentId, 'o' => $offeringId]
        );
    }

    public static function members(int $groupId, bool $includePending = true): array
    {
        $statuses = $includePending ? "'active','invited'" : "'active'";
        return Database::connection()->fetchAll(
            "SELECT m.id AS member_id, m.member_role, m.status, m.joined_at, s.id AS student_id, s.student_code, s.program, s.department,
                    u.id AS user_id, u.title_th, u.first_name_th, u.last_name_th, u.email
               FROM project_members m JOIN students s ON s.id = m.student_id JOIN users u ON u.id = s.user_id
              WHERE m.group_id = :g AND m.status IN ($statuses)
              ORDER BY m.member_role = 'leader' DESC, m.status, s.student_code",
            ['g' => $groupId]
        );
    }

    public static function advisor(int $groupId): ?array
    {
        return Database::connection()->fetch(
            "SELECT pa.*, u.title_th, u.first_name_th, u.last_name_th, u.email FROM project_advisors pa JOIN users u ON u.id = pa.advisor_id
              WHERE pa.group_id = :g AND pa.status IN ('assigned','requested') ORDER BY pa.status = 'assigned' DESC, pa.id DESC LIMIT 1",
            ['g' => $groupId]
        );
    }

    public static function history(int $groupId): array
    {
        return Database::connection()->fetchAll(
            "SELECT h.*, s.student_code, su.first_name_th, su.last_name_th, ru.first_name_th AS req_first, au.first_name_th AS appr_first
               FROM group_member_histories h JOIN students s ON s.id = h.student_id JOIN users su ON su.id = s.user_id
               JOIN users ru ON ru.id = h.requested_by LEFT JOIN users au ON au.id = h.approved_by
              WHERE h.group_id = :g ORDER BY h.id DESC",
            ['g' => $groupId]
        );
    }

    /** All advisors that students may request (active advisor role). */
    public static function advisorChoices(): array
    {
        return Database::connection()->fetchAll(
            "SELECT u.id, u.title_th, u.first_name_th, u.last_name_th,
                    (SELECT COUNT(*) FROM project_advisors pa WHERE pa.advisor_id = u.id AND pa.status = 'assigned') AS load_count
               FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.is_active = 1
               JOIN roles r ON r.id = ur.role_id AND r.code = 'advisor'
              WHERE u.is_active = 1 AND u.deleted_at IS NULL ORDER BY u.first_name_th"
        );
    }

    public static function create(int $studentId, int $userId, int $offeringId, string $name): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 200) {
            throw new RuleViolation('group_name', 'Group name is required');
        }
        if (self::forStudent($studentId, $offeringId) !== null) {
            throw new RuleViolation('already_in_group', 'Student already belongs to an active group');
        }
        $db = Database::connection();
        return $db->transaction(function (Database $db) use ($studentId, $userId, $offeringId, $name) {
            $offering = $db->fetch(
                'SELECT y.year_be, s.term_no FROM course_offerings o JOIN semesters s ON s.id = o.semester_id
                   JOIN academic_years y ON y.id = s.academic_year_id WHERE o.id = :o FOR UPDATE',
                ['o' => $offeringId]
            );
            $code = self::nextGroupCode($db, (int) $offering['year_be'], (int) $offering['term_no']);
            $gid = $db->insert('project_groups', [
                'offering_id' => $offeringId, 'group_code' => $code, 'group_name' => $name,
                'leader_student_id' => $studentId, 'status' => 'draft',
            ]);
            $db->insert('project_members', [
                'group_id' => $gid, 'student_id' => $studentId, 'offering_id' => $offeringId,
                'member_role' => 'leader', 'status' => 'active', 'joined_at' => date('Y-m-d H:i:s'),
            ]);
            $db->insert('group_member_histories', ['group_id' => $gid, 'student_id' => $studentId, 'action' => 'joined', 'requested_by' => $userId]);
            $db->insert('status_histories', ['entity_type' => 'group', 'entity_id' => $gid, 'from_status' => null, 'to_status' => 'draft', 'changed_by' => $userId]);
            AuditLogger::log('group.created', 'group', $gid, null, ['group_code' => $code, 'group_name' => $name], userId: $userId);
            return $gid;
        });
    }

    /**
     * Next free code for the term, e.g. G692-007. Uses the highest number already taken by any
     * group or project of that term (all offerings), so codes never repeat even when rows were
     * deleted directly in the database or two offerings share a term.
     */
    private static function nextGroupCode(Database $db, int $yearBe, int $termNo): string
    {
        $prefix = sprintf('%02d%d-', $yearBe % 100, $termNo);
        $max = 0;
        foreach ([['project_groups', 'group_code', 'G'], ['projects', 'project_code', 'P']] as [$table, $col, $letter]) {
            foreach ($db->fetchAll("SELECT $col AS code FROM $table WHERE $col LIKE :p", ['p' => $letter . $prefix . '%']) as $row) {
                $max = max($max, (int) substr((string) $row['code'], strlen($letter . $prefix)));
            }
        }
        return sprintf('G%s%03d', $prefix, $max + 1);
    }

    public static function rename(int $groupId, string $name, int $userId): void
    {
        $group = self::requireStatus($groupId, ['draft']);
        $name = trim($name);
        if ($name === '') {
            throw new RuleViolation('group_name', 'Group name is required');
        }
        Database::connection()->update('project_groups', ['group_name' => $name], 'id = :id', ['id' => $groupId]);
        AuditLogger::log('group.renamed', 'group', $groupId, ['group_name' => $group['group_name']], ['group_name' => $name], userId: $userId);
    }

    /** Leader invites a member by student code while the group is a draft (FR-GRP-02, OQ-02). */
    public static function invite(int $groupId, string $studentCode, int $userId): void
    {
        $group = self::requireStatus($groupId, ['draft']);
        $db = Database::connection();
        $student = $db->fetch(
            "SELECT s.* FROM students s JOIN users u ON u.id = s.user_id WHERE s.student_code = :c AND s.deleted_at IS NULL AND u.is_active = 1 AND u.deleted_at IS NULL",
            ['c' => trim($studentCode)]
        );
        if ($student === null) {
            throw new RuleViolation('student_not_found', 'Student not found');
        }
        if (self::forStudent((int) $student['id'], (int) $group['offering_id']) !== null) {
            throw new RuleViolation('already_in_group', 'Student already belongs to an active group');
        }
        if ($db->value("SELECT 1 FROM project_members WHERE group_id = :g AND student_id = :s AND status IN ('invited','active')", ['g' => $groupId, 's' => (int) $student['id']])) {
            throw new RuleViolation('already_invited', 'Already invited');
        }
        $count = (int) $db->value("SELECT COUNT(*) FROM project_members WHERE group_id = :g AND status IN ('invited','active')", ['g' => $groupId]);
        if ($count >= SettingsService::int('group.max_members', 3)) {
            throw new RuleViolation('group_full', 'Group is full');
        }
        $needsAccept = SettingsService::bool('group.invite_requires_accept', true);
        $db->transaction(function (Database $db) use ($groupId, $student, $group, $needsAccept, $userId) {
            $db->insert('project_members', [
                'group_id' => $groupId, 'student_id' => (int) $student['id'], 'offering_id' => (int) $group['offering_id'],
                'member_role' => 'member', 'status' => $needsAccept ? 'invited' : 'active',
                'joined_at' => $needsAccept ? null : date('Y-m-d H:i:s'),
            ]);
            $db->insert('group_member_histories', [
                'group_id' => $groupId, 'student_id' => (int) $student['id'],
                'action' => $needsAccept ? 'invited' : 'added', 'requested_by' => $userId,
            ]);
            NotificationService::notify([(int) $student['user_id']], 'group.invited', 'คำเชิญเข้ากลุ่มโครงงาน',
                "คุณได้รับคำเชิญเข้ากลุ่ม {$group['group_code']} {$group['group_name']}", '/groups', 'group', $groupId);
        });
    }

    public static function respond(int $memberId, int $studentId, bool $accept, int $userId): void
    {
        $db = Database::connection();
        $m = $db->fetch("SELECT * FROM project_members WHERE id = :id AND student_id = :s AND status = 'invited'", ['id' => $memberId, 's' => $studentId]);
        if ($m === null) {
            throw new RuleViolation('invitation_missing', 'Invitation not found');
        }
        self::requireStatus((int) $m['group_id'], ['draft']);
        if ($accept && self::forStudent($studentId, (int) $m['offering_id']) !== null) {
            throw new RuleViolation('already_in_group', 'Student already belongs to an active group');
        }
        $db->transaction(function (Database $db) use ($m, $accept, $userId) {
            $db->update('project_members', [
                'status' => $accept ? 'active' : 'declined',
                'joined_at' => $accept ? date('Y-m-d H:i:s') : null,
            ], 'id = :id', ['id' => (int) $m['id']]);
            $db->insert('group_member_histories', ['group_id' => (int) $m['group_id'], 'student_id' => (int) $m['student_id'], 'action' => $accept ? 'joined' : 'declined', 'requested_by' => $userId]);
            if ($accept) {
                // Other pending invitations of this student lapse.
                $db->query("UPDATE project_members SET status = 'declined' WHERE student_id = :s AND offering_id = :o AND status = 'invited'", ['s' => (int) $m['student_id'], 'o' => (int) $m['offering_id']]);
            }
        });
    }

    /** Leader removes a member / cancels an invitation before submission. */
    public static function removeDraftMember(int $groupId, int $memberId, int $userId): void
    {
        self::requireStatus($groupId, ['draft']);
        $db = Database::connection();
        $m = $db->fetch("SELECT * FROM project_members WHERE id = :id AND group_id = :g AND status IN ('invited','active') AND member_role = 'member'", ['id' => $memberId, 'g' => $groupId]);
        if ($m === null) {
            throw new RuleViolation('member_missing', 'Member not found');
        }
        $db->transaction(function (Database $db) use ($m, $userId) {
            $db->update('project_members', ['status' => 'removed', 'left_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $m['id']]);
            $db->insert('group_member_histories', ['group_id' => (int) $m['group_id'], 'student_id' => (int) $m['student_id'], 'action' => 'removed', 'requested_by' => $userId]);
        });
    }

    /** Leader submits the registration (FR-GRP-03). */
    public static function submit(int $groupId, int $userId): void
    {
        $group = self::requireStatus($groupId, ['draft']);
        $db = Database::connection();
        $active = (int) $db->value("SELECT COUNT(*) FROM project_members WHERE group_id = :g AND status = 'active'", ['g' => $groupId]);
        $pending = (int) $db->value("SELECT COUNT(*) FROM project_members WHERE group_id = :g AND status = 'invited'", ['g' => $groupId]);
        if ($pending > 0) {
            throw new RuleViolation('pending_invitations', 'Pending invitations');
        }
        $min = SettingsService::int('group.min_members', 2);
        $max = SettingsService::int('group.max_members', 3);
        if ($active < $min || $active > $max) {
            throw new RuleViolation('group_size', "Group must have $min-$max members");
        }
        OfferingService::checkDeadline((int) $group['offering_id'], 'group_registration');
        $db->transaction(function () use ($groupId, $userId) {
            (new WorkflowEngine(Workflows::group()))->apply($groupId, 'submit', 'leader', $userId, null, ['registered_at' => date('Y-m-d H:i:s'), 'reject_reason' => null]);
            NotificationService::notify(NotificationService::admins(), 'group.registered', 'มีกลุ่มโครงงานลงทะเบียนใหม่',
                'กลุ่มโครงงานรอการตรวจสอบ', '/groups/' . $groupId, 'group', $groupId);
        });
    }

    /** Admin verifies (creates the project record) or rejects back to draft (FR-GRP-04). */
    public static function verify(int $groupId, int $adminId): void
    {
        $group = self::requireStatus($groupId, ['submitted']);
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($groupId, $group, $adminId) {
            $engine = new WorkflowEngine(Workflows::group());
            $engine->apply($groupId, 'verify', 'admin', $adminId, null, ['verified_by' => $adminId, 'verified_at' => date('Y-m-d H:i:s')]);
            if (!$db->value('SELECT id FROM projects WHERE group_id = :g', ['g' => $groupId])) {
                $db->insert('projects', ['group_id' => $groupId, 'offering_id' => (int) $group['offering_id'], 'project_code' => 'P' . substr($group['group_code'], 1), 'stage' => 'topic']);
            }
            if (self::advisorAssigned($groupId)) {
                $engine->apply($groupId, 'activate', 'system', $adminId, 'Advisor already assigned');
            }
            NotificationService::notify(self::memberUserIds($groupId), 'group.verified', 'กลุ่มโครงงานผ่านการตรวจสอบ',
                "กลุ่ม {$group['group_code']} ผ่านการตรวจสอบแล้ว", '/groups', 'group', $groupId);
        });
    }

    public static function reject(int $groupId, int $adminId, string $reason): void
    {
        $group = self::requireStatus($groupId, ['submitted']);
        Database::connection()->transaction(function () use ($groupId, $group, $adminId, $reason) {
            (new WorkflowEngine(Workflows::group()))->apply($groupId, 'reject', 'admin', $adminId, $reason, ['reject_reason' => $reason]);
            NotificationService::notify(self::memberUserIds($groupId), 'group.rejected', 'กลุ่มโครงงานต้องแก้ไข',
                "กลุ่ม {$group['group_code']}: $reason", '/groups', 'group', $groupId);
        });
    }

    /** Leader requests an advisor (FR-GRP-05). */
    public static function requestAdvisor(int $groupId, int $advisorId, int $userId): void
    {
        $group = self::find($groupId) ?? throw new RuleViolation('group_missing', 'Group not found');
        if (self::advisorAssigned($groupId)) {
            throw new RuleViolation('advisor_already_assigned', 'Advisor already assigned');
        }
        self::assertAdvisorRole($advisorId);
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($groupId, $advisorId, $userId, $group) {
            $db->query("UPDATE project_advisors SET status = 'declined', ended_at = NOW() WHERE group_id = :g AND status = 'requested'", ['g' => $groupId]);
            $db->insert('project_advisors', ['group_id' => $groupId, 'advisor_id' => $advisorId, 'status' => 'requested', 'requested_by' => $userId]);
            AuditLogger::log('advisor.requested', 'group', $groupId, null, ['advisor_id' => $advisorId], userId: $userId);
            NotificationService::notify(NotificationService::admins(), 'advisor.requested', 'คำขออาจารย์ที่ปรึกษา',
                "กลุ่ม {$group['group_code']} ขออาจารย์ที่ปรึกษา", '/groups/' . $groupId, 'group', $groupId);
        });
    }

    /** Admin assigns (or replaces) the advisor (FR-GRP-06); activates a verified group. */
    public static function assignAdvisor(int $groupId, int $advisorId, int $adminId, ?string $reason = null): void
    {
        $group = self::find($groupId) ?? throw new RuleViolation('group_missing', 'Group not found');
        self::assertAdvisorRole($advisorId);
        $db = Database::connection();
        $current = $db->fetch("SELECT * FROM project_advisors WHERE group_id = :g AND status = 'assigned'", ['g' => $groupId]);
        if ($current !== null && (int) $current['advisor_id'] === $advisorId) {
            return;
        }
        if ($current !== null && trim((string) $reason) === '') {
            throw new RuleViolation('reason_required', 'Changing the advisor requires a reason'); // OQ-04
        }
        // The advisor may not be a committee member of an active round of this project.
        if ($group['project_id'] && $db->value(
            "SELECT 1 FROM committee_assignments ca JOIN proposal_submissions ps ON ps.id = ca.proposal_id
              WHERE ps.project_id = :p AND ca.status = 'active' AND (ca.member1_id = :a1 OR ca.member2_id = :a2)",
            ['p' => (int) $group['project_id'], 'a1' => $advisorId, 'a2' => $advisorId]
        )) {
            throw new RuleViolation('committee_not_advisor', 'Advisor is a committee member of this project');
        }
        $db->transaction(function (Database $db) use ($groupId, $advisorId, $adminId, $reason, $current, $group) {
            if ($current !== null) {
                $db->update('project_advisors', ['status' => 'replaced', 'ended_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $current['id']]);
            }
            $requested = $db->value("SELECT id FROM project_advisors WHERE group_id = :g AND status = 'requested' AND advisor_id = :a", ['g' => $groupId, 'a' => $advisorId]);
            $db->query("UPDATE project_advisors SET status = 'declined', ended_at = NOW() WHERE group_id = :g AND status = 'requested' AND advisor_id <> :a", ['g' => $groupId, 'a' => $advisorId]);
            $data = ['status' => 'assigned', 'assigned_by' => $adminId, 'assigned_at' => date('Y-m-d H:i:s'), 'note' => $reason];
            if ($requested) {
                $db->update('project_advisors', $data, 'id = :id', ['id' => (int) $requested]);
            } else {
                $db->insert('project_advisors', $data + ['group_id' => $groupId, 'advisor_id' => $advisorId]);
            }
            AuditLogger::log('advisor.assigned', 'group', $groupId, $current ? ['advisor_id' => (int) $current['advisor_id']] : null, ['advisor_id' => $advisorId], $reason, $adminId);
            if ($group['status'] === 'verified') {
                (new WorkflowEngine(Workflows::group()))->apply($groupId, 'activate', 'system', $adminId, 'Advisor assigned');
            }
            NotificationService::notify([$advisorId], 'advisor.assigned', 'ได้รับมอบหมายเป็นอาจารย์ที่ปรึกษา',
                "กลุ่ม {$group['group_code']} {$group['group_name']}", '/groups/' . $groupId, 'group', $groupId);
            NotificationService::notify(self::memberUserIds($groupId), 'advisor.assigned', 'มอบหมายอาจารย์ที่ปรึกษาแล้ว',
                'กลุ่มของคุณได้รับการมอบหมายอาจารย์ที่ปรึกษา', '/groups', 'group', $groupId);
        });
    }

    /** Membership change after registration: admin only, with a reason (Section 4 BR-4, BR-5). */
    public static function adminAddMember(int $groupId, string $studentCode, int $adminId, string $reason): void
    {
        $group = self::find($groupId) ?? throw new RuleViolation('group_missing', 'Group not found');
        if (trim($reason) === '') {
            throw new RuleViolation('reason_required', 'Reason required');
        }
        $db = Database::connection();
        $student = $db->fetch('SELECT * FROM students WHERE student_code = :c AND deleted_at IS NULL', ['c' => trim($studentCode)]);
        if ($student === null) {
            throw new RuleViolation('student_not_found', 'Student not found');
        }
        if (self::forStudent((int) $student['id'], (int) $group['offering_id']) !== null) {
            throw new RuleViolation('already_in_group', 'Student already belongs to an active group');
        }
        $db->transaction(function (Database $db) use ($groupId, $student, $group, $adminId, $reason) {
            $db->insert('project_members', ['group_id' => $groupId, 'student_id' => (int) $student['id'], 'offering_id' => (int) $group['offering_id'], 'member_role' => 'member', 'status' => 'active', 'joined_at' => date('Y-m-d H:i:s')]);
            $db->insert('group_member_histories', ['group_id' => $groupId, 'student_id' => (int) $student['id'], 'action' => 'added', 'reason' => $reason, 'requested_by' => $adminId, 'approved_by' => $adminId]);
            AuditLogger::log('group.member_added', 'group', $groupId, null, ['student_id' => (int) $student['id']], $reason, $adminId);
        });
    }

    public static function adminRemoveMember(int $groupId, int $memberId, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuleViolation('reason_required', 'Reason required');
        }
        $db = Database::connection();
        $m = $db->fetch("SELECT * FROM project_members WHERE id = :id AND group_id = :g AND status = 'active'", ['id' => $memberId, 'g' => $groupId]);
        if ($m === null) {
            throw new RuleViolation('member_missing', 'Member not found');
        }
        if ($m['member_role'] === 'leader') {
            throw new RuleViolation('leader_remove', 'Change the leader first');
        }
        $db->transaction(function (Database $db) use ($m, $groupId, $adminId, $reason) {
            $db->update('project_members', ['status' => 'removed', 'left_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $m['id']]);
            $db->insert('group_member_histories', ['group_id' => $groupId, 'student_id' => (int) $m['student_id'], 'action' => 'removed', 'reason' => $reason, 'requested_by' => $adminId, 'approved_by' => $adminId]);
            AuditLogger::log('group.member_removed', 'group', $groupId, ['student_id' => (int) $m['student_id']], null, $reason, $adminId);
        });
    }

    /** Admin changes the group leader (exactly one leader is kept, BR-1). */
    public static function adminChangeLeader(int $groupId, int $memberId, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuleViolation('reason_required', 'Reason required');
        }
        $db = Database::connection();
        $m = $db->fetch("SELECT * FROM project_members WHERE id = :id AND group_id = :g AND status = 'active'", ['id' => $memberId, 'g' => $groupId]);
        if ($m === null || $m['member_role'] === 'leader') {
            throw new RuleViolation('member_missing', 'Member not found');
        }
        $db->transaction(function (Database $db) use ($m, $groupId, $adminId, $reason) {
            $db->query("UPDATE project_members SET member_role = 'member' WHERE group_id = :g AND member_role = 'leader' AND status = 'active'", ['g' => $groupId]);
            $db->update('project_members', ['member_role' => 'leader'], 'id = :id', ['id' => (int) $m['id']]);
            $db->update('project_groups', ['leader_student_id' => (int) $m['student_id']], 'id = :id', ['id' => $groupId]);
            $db->insert('group_member_histories', ['group_id' => $groupId, 'student_id' => (int) $m['student_id'], 'action' => 'leader_changed', 'reason' => $reason, 'requested_by' => $adminId, 'approved_by' => $adminId]);
            AuditLogger::log('group.leader_changed', 'group', $groupId, null, ['student_id' => (int) $m['student_id']], $reason, $adminId);
        });
    }

    /** @return array{rows:array,total:int,page:int,per_page:int} */
    public static function search(int $offeringId, string $q = '', string $status = '', ?int $advisorId = null, int $page = 1, int $perPage = 25): array
    {
        $where = ['g.offering_id = :o', 'g.deleted_at IS NULL'];
        $params = ['o' => $offeringId];
        if ($q !== '') {
            $where[] = '(g.group_code LIKE :q1 OR g.group_name LIKE :q2 OR p.title_th LIKE :q3 OR EXISTS (SELECT 1 FROM project_members m2 JOIN students s2 ON s2.id = m2.student_id JOIN users u2 ON u2.id = s2.user_id WHERE m2.group_id = g.id AND m2.status = \'active\' AND (s2.student_code LIKE :q4 OR u2.first_name_th LIKE :q5)))';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) {
                $params[$k] = "%$q%";
            }
        }
        if ($status !== '') {
            $where[] = 'g.status = :st';
            $params['st'] = $status;
        }
        if ($advisorId !== null) {
            $where[] = "EXISTS (SELECT 1 FROM project_advisors pa2 WHERE pa2.group_id = g.id AND pa2.advisor_id = :adv AND pa2.status = 'assigned')";
            $params['adv'] = $advisorId;
        }
        $w = implode(' AND ', $where);
        $db = Database::connection();
        $total = (int) $db->value("SELECT COUNT(*) FROM project_groups g LEFT JOIN projects p ON p.group_id = g.id WHERE $w", $params);
        $page = max(1, $page);
        $rows = $db->fetchAll(
            "SELECT g.*, p.id AS project_id, p.title_th,
                    (SELECT COUNT(*) FROM project_members m WHERE m.group_id = g.id AND m.status = 'active') AS member_count,
                    (SELECT CONCAT(COALESCE(u.title_th,''), u.first_name_th, ' ', u.last_name_th) FROM project_advisors pa JOIN users u ON u.id = pa.advisor_id WHERE pa.group_id = g.id AND pa.status = 'assigned' LIMIT 1) AS advisor_name,
                    (SELECT CONCAT(COALESCE(u.title_th,''), u.first_name_th, ' ', u.last_name_th) FROM project_advisors pa JOIN users u ON u.id = pa.advisor_id WHERE pa.group_id = g.id AND pa.status = 'requested' LIMIT 1) AS requested_advisor,
                    (SELECT t.status FROM project_topics t WHERE t.project_id = p.id AND t.current_key = 1) AS topic_status,
                    (SELECT ps.status FROM proposal_submissions ps WHERE ps.project_id = p.id) AS proposal_status
               FROM project_groups g LEFT JOIN projects p ON p.group_id = g.id
              WHERE $w ORDER BY g.group_code LIMIT :lim OFFSET :off",
            array_merge($params, ['lim' => $perPage, 'off' => ($page - 1) * $perPage])
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /** @return int[] user ids of active members */
    public static function memberUserIds(int $groupId): array
    {
        return array_map('intval', array_column(Database::connection()->fetchAll(
            "SELECT s.user_id FROM project_members m JOIN students s ON s.id = m.student_id WHERE m.group_id = :g AND m.status = 'active'",
            ['g' => $groupId]
        ), 'user_id'));
    }

    public static function advisorAssigned(int $groupId): ?int
    {
        $id = Database::connection()->value("SELECT advisor_id FROM project_advisors WHERE group_id = :g AND status = 'assigned'", ['g' => $groupId]);
        return $id === null ? null : (int) $id;
    }

    private static function requireStatus(int $groupId, array $statuses): array
    {
        $group = self::find($groupId) ?? throw new RuleViolation('group_missing', 'Group not found');
        if (!in_array($group['status'], $statuses, true)) {
            throw new RuleViolation('group_locked', 'Group cannot be changed in its current status');
        }
        return $group;
    }

    private static function assertAdvisorRole(int $userId): void
    {
        if (!Database::connection()->value(
            "SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id AND r.code = 'advisor' JOIN users u ON u.id = ur.user_id
              WHERE ur.user_id = :u AND ur.is_active = 1 AND u.is_active = 1 AND u.deleted_at IS NULL",
            ['u' => $userId]
        )) {
            throw new RuleViolation('advisor_role', 'User is not an active advisor');
        }
    }
}
