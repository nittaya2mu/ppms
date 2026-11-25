<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\CommitteeRules;
use App\Services\Rules\RuleViolation;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\Workflows;

/**
 * Workflows 2-4 and 6: proposal submission, advisor review, committee assignment,
 * revision and resubmission (Sections 6, 7, 8, 10; P1-P7, P14-P16).
 */
final class ProposalService
{
    /** Chapter 1 sections of the department template (B_05_Chapter_01): 1.1 - 1.6 */
    public const CHAPTER1 = ['ch1_background', 'ch1_objectives', 'ch1_scope', 'ch1_benefits', 'ch1_tools', 'ch1_plan'];

    /** Uploaded chapter documents: chapter number => proposal_versions column. */
    public const CHAPTER_FILES = [1 => 'file_id', 2 => 'ch2_file_id', 3 => 'ch3_file_id'];

    public static function forProject(int $projectId): ?array
    {
        return Database::connection()->fetch(
            'SELECT ps.*, pv.version_no, pv.title AS version_title, pv.file_id, pv.ch2_file_id, pv.ch3_file_id, pv.submitted_at AS version_submitted_at
               FROM proposal_submissions ps LEFT JOIN proposal_versions pv ON pv.id = ps.current_version_id
              WHERE ps.project_id = :p',
            ['p' => $projectId]
        );
    }

    public static function find(int $proposalId): ?array
    {
        return Database::connection()->fetch('SELECT * FROM proposal_submissions WHERE id = :id', ['id' => $proposalId]);
    }

    public static function versions(int $proposalId): array
    {
        return Database::connection()->fetchAll(
            'SELECT pv.*, f.original_name, f.file_size, f2.original_name AS ch2_name, f3.original_name AS ch3_name, u.first_name_th AS submitter,
                    rs.id AS response_id, rs.response_file_id, rs.response_note
               FROM proposal_versions pv JOIN document_files f ON f.id = pv.file_id JOIN users u ON u.id = pv.submitted_by
               LEFT JOIN document_files f2 ON f2.id = pv.ch2_file_id LEFT JOIN document_files f3 ON f3.id = pv.ch3_file_id
               LEFT JOIN revision_responses rs ON rs.new_version_id = pv.id
              WHERE pv.proposal_id = :p ORDER BY pv.version_no DESC',
            ['p' => $proposalId]
        );
    }

    public static function decisions(int $proposalId): array
    {
        return Database::connection()->fetchAll(
            'SELECT d.*, pv.version_no, u.title_th, u.first_name_th, u.last_name_th FROM approval_decisions d
               JOIN proposal_versions pv ON pv.id = d.proposal_version_id JOIN users u ON u.id = d.decided_by
              WHERE d.proposal_id = :p ORDER BY d.id DESC',
            ['p' => $proposalId]
        );
    }

    public static function openRevision(int $proposalId): ?array
    {
        return Database::connection()->fetch(
            "SELECT rr.*, d.decision, d.decision_level, d.comments FROM revision_requests rr JOIN approval_decisions d ON d.id = rr.decision_id
              WHERE rr.proposal_id = :p AND rr.status IN ('open','submitted') ORDER BY rr.id DESC LIMIT 1",
            ['p' => $proposalId]
        );
    }

    public static function assignments(int $proposalId): array
    {
        return Database::connection()->fetchAll(
            'SELECT ca.*, pv.version_no,
                    CONCAT(COALESCE(u1.title_th,\'\'), u1.first_name_th, \' \', u1.last_name_th) AS member1_name,
                    CONCAT(COALESCE(u2.title_th,\'\'), u2.first_name_th, \' \', u2.last_name_th) AS member2_name
               FROM committee_assignments ca JOIN proposal_versions pv ON pv.id = ca.proposal_version_id
               JOIN users u1 ON u1.id = ca.member1_id JOIN users u2 ON u2.id = ca.member2_id
              WHERE ca.proposal_id = :p ORDER BY ca.round_no DESC',
            ['p' => $proposalId]
        );
    }

    public static function currentAssignment(int $proposalId): ?array
    {
        return Database::connection()->fetch(
            "SELECT * FROM committee_assignments WHERE proposal_id = :p AND status <> 'cancelled' ORDER BY round_no DESC LIMIT 1",
            ['p' => $proposalId]
        );
    }

    /** Users eligible as committee members (active committee role). */
    public static function committeeChoices(): array
    {
        return Database::connection()->fetchAll(
            "SELECT u.id, u.title_th, u.first_name_th, u.last_name_th,
                    (SELECT COUNT(*) FROM presentation_evaluations pe JOIN committee_assignments ca ON ca.id = pe.assignment_id
                      WHERE pe.evaluator_id = u.id AND ca.status = 'active' AND pe.status <> 'submitted') AS pending_count
               FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.is_active = 1
               JOIN roles r ON r.id = ur.role_id AND r.code = 'committee'
              WHERE u.is_active = 1 AND u.deleted_at IS NULL ORDER BY u.first_name_th"
        );
    }

    /** The latest submitted version (used to pre-fill chapter 1 and to carry chapter files forward). */
    public static function latestVersion(int $proposalId): ?array
    {
        return Database::connection()->fetch('SELECT * FROM proposal_versions WHERE proposal_id = :p ORDER BY version_no DESC LIMIT 1', ['p' => $proposalId]);
    }

    /**
     * P1 (first submission) or P14 (resubmission after a revision request).
     * @param array<string,string> $chapter1 CHAPTER1 key => text (sections 1.1 - 1.6)
     * @param array<int,?array> $files chapter number (1..3) => uploaded file; on resubmission a missing
     *                                  chapter keeps the file of the previous version
     * @param array|null $response response-to-comments document (required on resubmission)
     * @param array{ch2_json?:string, ch3_json?:string} $extra chapters 2-3 typed on the web (JSON)
     *
     * A $files entry may also be the id of an already stored file (from the proposal draft).
     */
    public static function submit(int $projectId, array $chapter1, array $files, ?array $response, ?string $notes, int $userId, array $extra = []): int
    {
        $proposal = self::forProject($projectId) ?? throw new RuleViolation('topic_not_approved', 'The topic must be officially approved first');
        $project = Database::connection()->fetch('SELECT * FROM projects WHERE id = :p', ['p' => $projectId]);
        $isResubmission = in_array($proposal['status'], ['revision_required', 'approved_minor'], true);
        if (!$isResubmission && $proposal['status'] !== 'draft') {
            throw new RuleViolation('proposal_locked', 'The proposal is under review');
        }
        $text = [];
        foreach (self::CHAPTER1 as $k) {
            $text[$k] = trim((string) ($chapter1[$k] ?? ''));
            if ($text[$k] === '') {
                throw new RuleViolation('chapter1_incomplete', 'All chapter 1 sections are required');
            }
        }
        $previous = self::latestVersion((int) $proposal['id']);
        foreach (self::CHAPTER_FILES as $ch => $col) {
            $has = isset($files[$ch]) && (is_int($files[$ch]) || (int) ($files[$ch]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
            if (!$has && empty($previous[$col])) {
                throw new RuleViolation('chapter_files_required', 'Chapter 1-3 documents are required');
            }
        }
        $revision = $isResubmission ? self::openRevision((int) $proposal['id']) : null;
        if ($isResubmission && ($response === null)) {
            throw new RuleViolation('response_required', 'A response-to-comments document is required');
        }
        $late = OfferingService::checkDeadline((int) $project['offering_id'], $isResubmission ? 'revision_submission' : 'proposal_submission');
        $allowed = SettingsService::get('upload.chapter_allowed_ext', ['pdf', 'docx', 'doc']);
        $allowed = is_array($allowed) ? $allowed : ['pdf'];

        $db = Database::connection();
        $extra = array_intersect_key($extra, ['ch2_json' => 1, 'ch3_json' => 1]);
        return $db->transaction(function (Database $db) use ($proposal, $project, $text, $files, $previous, $allowed, $response, $notes, $userId, $isResubmission, $revision, $late, $extra) {
            $fileIds = [];
            $fresh = [];
            foreach (self::CHAPTER_FILES as $ch => $col) {
                $upload = $files[$ch] ?? null;
                if (is_int($upload)) {
                    $fileIds[$col] = $upload;
                    $fresh[] = $upload;
                } elseif ($upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $fileIds[$col] = FileService::store($upload, 'proposal_version', null, $allowed, 'upload.chapter_max_mb', $userId);
                    $fresh[] = $fileIds[$col];
                } else {
                    $fileIds[$col] = (int) $previous[$col];
                }
            }
            $versionNo = 1 + (int) $db->value('SELECT COALESCE(MAX(version_no), 0) FROM proposal_versions WHERE proposal_id = :p', ['p' => (int) $proposal['id']]);
            $db->query("UPDATE proposal_versions SET version_status = 'superseded' WHERE proposal_id = :p AND version_status <> 'superseded'", ['p' => (int) $proposal['id']]);
            $vid = $db->insert('proposal_versions', $text + $fileIds + $extra + [
                'proposal_id' => (int) $proposal['id'], 'version_no' => $versionNo,
                'title' => (string) ($project['title_th'] ?: $project['title_en'] ?: $project['project_code']),
                'revision_notes' => trim((string) $notes) ?: null,
                'version_status' => 'submitted', 'is_late' => $late ? 1 : 0, 'submitted_by' => $userId,
            ]);
            foreach ($fresh as $fid) {
                FileService::attach($fid, 'proposal_version', $vid);
            }
            if ($isResubmission) {
                $respFileId = FileService::store($response, 'revision_response', null, ['pdf', 'docx'], 'upload.response_max_mb', $userId);
                if ($revision !== null) {
                    $rid = $db->insert('revision_responses', [
                        'revision_request_id' => (int) $revision['id'], 'new_version_id' => $vid, 'response_file_id' => $respFileId,
                        'response_note' => trim((string) $notes) ?: null, 'submitted_by' => $userId,
                    ]);
                    FileService::attach($respFileId, 'revision_response', $rid);
                    $db->update('revision_requests', ['status' => 'submitted'], 'id = :id', ['id' => (int) $revision['id']]);
                }
            }
            (new WorkflowEngine(Workflows::proposal()))->apply((int) $proposal['id'], $isResubmission ? 'resubmit' : 'submit', 'leader', $userId, null, ['current_version_id' => $vid]);

            $advisor = GroupService::advisorAssigned((int) $project['group_id']);
            NotificationService::notify(array_merge($advisor ? [$advisor] : [], NotificationService::admins()), 'proposal.submitted', 'มี Proposal ส่งมาใหม่',
                "โครงงาน {$project['project_code']} ส่ง Proposal version $versionNo", '/projects/' . $project['id'], 'proposal', (int) $proposal['id']);
            return $vid;
        });
    }

    /** P2-P6: advisor review. $decision = ready | revise | not_approved */
    public static function advisorDecide(int $projectId, int $advisorId, string $decision, ?string $comments): void
    {
        $proposal = self::forProject($projectId) ?? throw new RuleViolation('proposal_missing', 'No proposal');
        $project = Database::connection()->fetch('SELECT * FROM projects WHERE id = :p', ['p' => $projectId]);
        if (GroupService::advisorAssigned((int) $project['group_id']) !== $advisorId) {
            throw new RuleViolation('not_advisor', 'Only the project advisor can review');
        }
        $map = [
            'ready'        => ['advisor_ready', 'ready_for_committee'],
            'revise'       => ['advisor_revise', 'revision_required'],
            'not_approved' => ['advisor_not_approve', 'not_approved'],
        ];
        if (!isset($map[$decision])) {
            throw new RuleViolation('invalid', 'Invalid decision');
        }
        [$action, $value] = $map[$decision];
        if ($value !== 'ready_for_committee' && trim((string) $comments) === '') {
            throw new RuleViolation('reason_required', 'Comments are required');
        }
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($proposal, $project, $advisorId, $action, $value, $comments) {
            $engine = new WorkflowEngine(Workflows::proposal());
            if ($proposal['status'] === 'submitted') {
                $engine->apply((int) $proposal['id'], 'start_review', 'advisor', $advisorId);
                $db->update('proposal_versions', ['version_status' => 'under_advisor_review'], 'id = :id', ['id' => (int) $proposal['current_version_id']]);
            }
            $engine->apply((int) $proposal['id'], $action, 'advisor', $advisorId, $comments);
            $decisionId = $db->insert('approval_decisions', [
                'proposal_id' => (int) $proposal['id'], 'proposal_version_id' => (int) $proposal['current_version_id'],
                'decision_level' => 'advisor_review', 'decision' => $value, 'comments' => trim((string) $comments) ?: null, 'decided_by' => $advisorId,
            ]);
            if ($action === 'advisor_revise') {
                $db->update('proposal_versions', ['version_status' => 'returned'], 'id = :id', ['id' => (int) $proposal['current_version_id']]);
                self::closeOpenRevisions($db, (int) $proposal['id']);
                $db->insert('revision_requests', [
                    'proposal_id' => (int) $proposal['id'], 'decision_id' => $decisionId, 'source_version_id' => (int) $proposal['current_version_id'],
                    'requested_by_level' => 'advisor', 'status' => 'open',
                ]);
            }
            if ($action === 'advisor_ready' && (int) $proposal['current_round_no'] === 0) {
                $engine->apply((int) $proposal['id'], 'await_committee', 'system', $advisorId, 'First evaluation round');
            }
            $recipients = array_merge(GroupService::memberUserIds((int) $project['group_id']), NotificationService::admins());
            NotificationService::notify($recipients, 'proposal.advisor_reviewed', 'Advisor พิจารณา Proposal แล้ว',
                'ผลการพิจารณา: ' . __('decision.' . $value), '/projects/' . $project['id'], 'proposal', (int) $proposal['id']);
        });
    }

    /** P7: the course administrator assigns exactly two committee members (Section 8). */
    public static function assignCommittee(int $proposalId, int $member1, int $member2, string $deadline, int $adminId): int
    {
        $proposal = self::find($proposalId) ?? throw new RuleViolation('proposal_missing', 'No proposal');
        if ($proposal['status'] !== 'committee_assignment') {
            throw new RuleViolation('proposal_state', 'The proposal is not awaiting committee assignment');
        }
        return self::createRound($proposal, $member1, $member2, $deadline, $adminId, 'assign_committee');
    }

    /**
     * P15 / P16: after the advisor accepts a revised version, the administrator explicitly
     * selects the revision mode (Section 10). Mode B re-evaluates the NEW version.
     */
    public static function selectRevisionMode(int $proposalId, string $mode, int $adminId, ?int $member1 = null, ?int $member2 = null, ?string $deadline = null): void
    {
        $proposal = self::find($proposalId) ?? throw new RuleViolation('proposal_missing', 'No proposal');
        if ($proposal['status'] !== 'advisor_approved' || (int) $proposal['current_round_no'] === 0) {
            throw new RuleViolation('proposal_state', 'No revised proposal is waiting for a revision mode');
        }
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($proposal, $mode, $adminId, $member1, $member2, $deadline) {
            $revision = self::openRevision((int) $proposal['id']);
            $modeValue = $mode === 'B' ? 're_evaluation' : 'advisor_only';
            if ($revision !== null) {
                $db->update('revision_requests', ['revision_mode' => $modeValue, 'mode_selected_by' => $adminId, 'mode_selected_at' => date('Y-m-d H:i:s'), 'status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $revision['id']]);
            }
            AuditLogger::log('proposal.revision_mode', 'proposal', (int) $proposal['id'], null, ['mode' => $modeValue], userId: $adminId);
            $engine = new WorkflowEngine(Workflows::proposal());
            if ($mode === 'A') {
                $engine->apply((int) $proposal['id'], 'mode_advisor_only', 'admin', $adminId, 'Revision mode A: advisor review only');
                return;
            }
            if ($mode !== 'B') {
                throw new RuleViolation('invalid', 'Invalid revision mode');
            }
            $engine->apply((int) $proposal['id'], 'mode_re_evaluation', 'admin', $adminId, 'Revision mode B: re-evaluation by both committee members');
            $previous = self::currentAssignment((int) $proposal['id']);
            $m1 = $member1 ?: (int) ($previous['member1_id'] ?? 0);
            $m2 = $member2 ?: (int) ($previous['member2_id'] ?? 0);
            $fresh = self::find((int) $proposal['id']);
            self::createRound($fresh, $m1, $m2, (string) $deadline, $adminId, 'start_re_evaluation');
        });
    }

    /** Creates an evaluation round on the CURRENT version with two empty evaluations. */
    private static function createRound(array $proposal, int $member1, int $member2, string $deadline, int $adminId, string $action): int
    {
        $db = Database::connection();
        $project = $db->fetch('SELECT * FROM projects WHERE id = :p', ['p' => (int) $proposal['project_id']]);
        $advisorId = GroupService::advisorAssigned((int) $project['group_id']) ?? throw new RuleViolation('advisor_missing', 'Advisor missing');
        CommitteeRules::assertAssignable($advisorId, $member1, $member2);
        $ts = strtotime($deadline);
        if ($ts === false || $ts < time()) {
            throw new RuleViolation('deadline_invalid', 'The evaluation deadline must be in the future');
        }
        return $db->transaction(function (Database $db) use ($proposal, $project, $member1, $member2, $ts, $adminId, $advisorId, $action) {
            $round = (int) $proposal['current_round_no'] + 1;
            $aid = $db->insert('committee_assignments', [
                'proposal_id' => (int) $proposal['id'], 'proposal_version_id' => (int) $proposal['current_version_id'], 'round_no' => $round,
                'advisor_id' => $advisorId, 'member1_id' => $member1, 'member2_id' => $member2, 'assigned_by' => $adminId,
                'evaluation_deadline' => date('Y-m-d H:i:s', $ts),
            ]);
            // แบบฟอร์ม 2: the advisor and both committee members each evaluate the presentation.
            $db->insert('presentation_evaluations', ['assignment_id' => $aid, 'evaluator_id' => $advisorId, 'evaluator_role' => 'advisor']);
            $db->insert('presentation_evaluations', ['assignment_id' => $aid, 'evaluator_id' => $member1, 'evaluator_role' => 'committee1']);
            $db->insert('presentation_evaluations', ['assignment_id' => $aid, 'evaluator_id' => $member2, 'evaluator_role' => 'committee2']);
            $db->update('proposal_versions', ['version_status' => 'accepted_for_evaluation'], 'id = :id', ['id' => (int) $proposal['current_version_id']]);
            (new WorkflowEngine(Workflows::proposal()))->apply((int) $proposal['id'], $action, 'admin', $adminId, "Evaluation round $round", ['current_round_no' => $round, 'needs_admin_attention' => 0]);
            NotificationService::notify([$advisorId, $member1, $member2], 'committee.assigned', 'ได้รับมอบหมายให้ประเมินการนำเสนอโครงงาน',
                "โครงงาน {$project['project_code']} (รอบที่ $round) กำหนดส่ง " . thai_date(date('Y-m-d H:i:s', $ts), true), '/evaluations', 'assignment', $aid);
            NotificationService::notify(GroupService::memberUserIds((int) $project['group_id']), 'committee.assigned', 'มอบหมาย Committee แล้ว',
                'Proposal ของกลุ่มอยู่ระหว่างการประเมินโดย Committee', '/projects/' . $project['id'], 'proposal', (int) $proposal['id']);
            return $aid;
        });
    }

    /** Change a committee member after assignment (Section 8: authorization + audit). */
    public static function replaceCommitteeMember(int $assignmentId, int $slot, int $newMember, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuleViolation('reason_required', 'Reason required');
        }
        $db = Database::connection();
        $a = $db->fetch("SELECT * FROM committee_assignments WHERE id = :id AND status = 'active'", ['id' => $assignmentId]) ?? throw new RuleViolation('assignment_missing', 'No active assignment');
        $ev = $db->fetch('SELECT * FROM presentation_evaluations WHERE assignment_id = :a AND evaluator_role = :r', ['a' => $assignmentId, 'r' => 'committee' . $slot]);
        if ($ev === null || $ev['status'] === 'submitted') {
            throw new RuleViolation('evaluation_locked', 'A submitted evaluation cannot be reassigned');
        }
        $m1 = $slot === 1 ? $newMember : (int) $a['member1_id'];
        $m2 = $slot === 2 ? $newMember : (int) $a['member2_id'];
        CommitteeRules::assertAssignable((int) $a['advisor_id'], $m1, $m2);
        $db->transaction(function (Database $db) use ($a, $ev, $m1, $m2, $newMember, $adminId, $reason, $slot) {
            $db->update('committee_assignments', ['member1_id' => $m1, 'member2_id' => $m2], 'id = :id', ['id' => (int) $a['id']]);
            $db->update('presentation_evaluations', [
                'evaluator_id' => $newMember, 'status' => 'not_started', 'grades_json' => null, 'total_score' => null, 'letter_grade' => null,
                'result' => null, 'suggestions' => null, 'objectives_ok' => null, 'scope_ok' => null, 'methodology_ok' => null, 'literature_ok' => null,
            ], 'id = :id', ['id' => (int) $ev['id']]);
            AuditLogger::log('committee.replaced', 'assignment', (int) $a['id'], ['slot' => $slot, 'member' => (int) $ev['evaluator_id']], ['slot' => $slot, 'member' => $newMember], $reason, $adminId);
            NotificationService::notify([$newMember], 'committee.assigned', 'ได้รับมอบหมายให้ประเมิน Proposal', 'มีโครงงานรอการประเมิน', '/evaluations', 'assignment', (int) $a['id']);
        });
    }

    public static function closeOpenRevisions(Database $db, int $proposalId): void
    {
        $db->query("UPDATE revision_requests SET status = 'closed', closed_at = NOW() WHERE proposal_id = :p AND status IN ('open','submitted')", ['p' => $proposalId]);
    }

    /** List for the menus. $filter: pending_advisor | awaiting_assignment | awaiting_mode | all */
    public static function queue(int $offeringId, string $role, int $userId, string $filter): array
    {
        $where = ['p.offering_id = :o', 'p.deleted_at IS NULL'];
        $params = ['o' => $offeringId];
        if ($role === 'advisor') {
            $where[] = "EXISTS (SELECT 1 FROM project_advisors pa WHERE pa.group_id = p.group_id AND pa.advisor_id = :u AND pa.status = 'assigned')";
            $params['u'] = $userId;
        }
        $statusFilter = [
            'pending_advisor'     => "ps.status IN ('submitted','advisor_review')",
            'awaiting_assignment' => "ps.status = 'committee_assignment'",
            'awaiting_mode'       => "ps.status = 'advisor_approved' AND ps.current_round_no > 0",
            'final'               => "ps.status IN ('evaluation_completed','final_review')",
            'revision'            => "ps.status IN ('revision_required','approved_minor')",
            'evaluation'          => "ps.status = 'committee_evaluation'",
        ][$filter] ?? null;
        if ($statusFilter !== null) {
            $where[] = $statusFilter;
        }
        return Database::connection()->fetchAll(
            'SELECT p.id AS project_id, p.project_code, p.title_th, g.group_code, g.group_name, ps.id AS proposal_id, ps.status,
                    ps.current_round_no, ps.needs_admin_attention, pv.version_no, pv.submitted_at, pv.is_late,
                    (SELECT ca.average_score FROM committee_assignments ca WHERE ca.proposal_id = ps.id AND ca.status = \'completed\' ORDER BY ca.round_no DESC LIMIT 1) AS average_score
               FROM proposal_submissions ps JOIN projects p ON p.id = ps.project_id JOIN project_groups g ON g.id = p.group_id
               LEFT JOIN proposal_versions pv ON pv.id = ps.current_version_id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY pv.submitted_at IS NULL, pv.submitted_at, g.group_code',
            $params
        );
    }
}
