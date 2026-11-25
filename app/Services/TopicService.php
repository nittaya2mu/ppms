<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;
use App\Services\Rules\VersionRules;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\Workflows;

/**
 * Workflow 1: topic submission and two-level approval (Section 5, T1-T10).
 */
final class TopicService
{
    /** Fields of the department's topic approval form (แบบฟอร์ม 1). */
    public const FIELDS = ['title_th', 'title_en', 'project_type', 'project_type_other', 'co_advisor_name'];

    public const REQUIRED = ['title_th', 'title_en', 'project_type'];

    /** Project types on form 1. */
    public const PROJECT_TYPES = ['web', 'mobile', 'network', 'api', 'game', 'ml_ai', 'embedded_iot', 'other'];

    /** The current (non-rejected) topic of a project, with its latest submission. */
    public static function current(int $projectId): ?array
    {
        return Database::connection()->fetch(
            'SELECT t.*, ts.version_no, ts.is_draft FROM project_topics t
               LEFT JOIN topic_submissions ts ON ts.id = t.current_submission_id
              WHERE t.project_id = :p AND t.current_key = 1',
            ['p' => $projectId]
        );
    }

    public static function rejectedTopics(int $projectId): array
    {
        return Database::connection()->fetchAll(
            "SELECT t.*, ts.title_th FROM project_topics t LEFT JOIN topic_submissions ts ON ts.id = t.current_submission_id
              WHERE t.project_id = :p AND t.status = 'topic_rejected' ORDER BY t.id DESC",
            ['p' => $projectId]
        );
    }

    public static function submission(int $submissionId): ?array
    {
        return Database::connection()->fetch('SELECT * FROM topic_submissions WHERE id = :id', ['id' => $submissionId]);
    }

    public static function versions(int $topicId): array
    {
        return Database::connection()->fetchAll(
            'SELECT ts.*, u.first_name_th AS submitter FROM topic_submissions ts LEFT JOIN users u ON u.id = ts.submitted_by
              WHERE ts.topic_id = :t ORDER BY ts.version_no DESC',
            ['t' => $topicId]
        );
    }

    public static function decisions(int $topicId): array
    {
        return Database::connection()->fetchAll(
            'SELECT a.*, ts.version_no, u.title_th, u.first_name_th, u.last_name_th FROM topic_approvals a
               JOIN topic_submissions ts ON ts.id = a.submission_id JOIN users u ON u.id = a.decided_by
              WHERE a.topic_id = :t ORDER BY a.id DESC',
            ['t' => $topicId]
        );
    }

    /**
     * Returns the editable draft submission, creating the topic and/or a new version when needed.
     * Editing is possible only in Draft or Revision Required (Section 3.2).
     */
    public static function draftFor(int $projectId, int $userId): array
    {
        $db = Database::connection();
        return $db->transaction(function (Database $db) use ($projectId, $userId) {
            $topic = self::current($projectId);
            if ($topic === null) {
                $topicId = $db->insert('project_topics', ['project_id' => $projectId, 'status' => 'draft']);
                $db->insert('status_histories', ['entity_type' => 'topic', 'entity_id' => $topicId, 'from_status' => null, 'to_status' => 'draft', 'changed_by' => $userId]);
                $topic = ['id' => $topicId, 'status' => 'draft', 'current_submission_id' => null];
            }
            if (!in_array($topic['status'], ['draft', 'revision_required'], true)) {
                throw new RuleViolation('topic_locked', 'The topic is under review and cannot be edited');
            }
            $topicId = (int) $topic['id'];
            $current = $topic['current_submission_id'] ? self::submission((int) $topic['current_submission_id']) : null;
            if ($current !== null && (int) $current['is_draft'] === 1) {
                return $current;
            }
            // New version: copy the previous content so the student revises rather than retypes.
            $data = ['topic_id' => $topicId, 'version_no' => $current ? (int) $current['version_no'] + 1 : 1, 'is_draft' => 1];
            foreach (self::FIELDS as $f) {
                $data[$f] = $current[$f] ?? ($f === 'title_th' || $f === 'title_en' ? '' : null);
            }
            $sid = $db->insert('topic_submissions', $data);
            $db->update('project_topics', ['current_submission_id' => $sid], 'id = :id', ['id' => $topicId]);
            return self::submission($sid);
        });
    }

    public static function saveDraft(int $projectId, array $input, int $userId): array
    {
        $draft = self::draftFor($projectId, $userId);
        VersionRules::assertTopicSubmissionEditable((int) $draft['id']);
        $data = [];
        foreach (self::FIELDS as $f) {
            $value = trim((string) ($input[$f] ?? ''));
            $data[$f] = $value === '' ? ($f === 'title_th' || $f === 'title_en' ? '' : null) : $value;
        }
        if (mb_strlen($data['title_th']) > 500 || mb_strlen($data['title_en']) > 500) {
            throw new RuleViolation('title_too_long', 'Title too long');
        }
        if ($data['project_type'] !== null && !in_array($data['project_type'], self::PROJECT_TYPES, true)) {
            throw new RuleViolation('invalid', 'Invalid project type');
        }
        if ($data['project_type'] !== 'other') {
            $data['project_type_other'] = null;
        }
        foreach (['project_type_other', 'co_advisor_name'] as $f) {
            if ($data[$f] !== null && mb_strlen($data[$f]) > 200) {
                throw new RuleViolation('title_too_long', 'Text too long');
            }
        }
        $data['revision_note'] = trim((string) ($input['revision_note'] ?? '')) ?: null;
        Database::connection()->update('topic_submissions', $data, 'id = :id', ['id' => (int) $draft['id']]);
        return self::submission((int) $draft['id']);
    }

    public static function attachDocument(int $projectId, array $upload, int $userId): int
    {
        $draft = self::draftFor($projectId, $userId);
        $ext = SettingsService::get('upload.topic_allowed_ext', ['pdf', 'docx', 'png', 'jpg', 'jpeg']);
        return FileService::store($upload, 'topic_submission', (int) $draft['id'], is_array($ext) ? $ext : ['pdf'], 'upload.topic_max_mb', $userId);
    }

    /** T1 / T10: the leader submits the draft version. */
    public static function submit(int $projectId, int $userId): void
    {
        $db = Database::connection();
        $project = $db->fetch('SELECT p.*, g.status AS group_status, g.id AS gid FROM projects p JOIN project_groups g ON g.id = p.group_id WHERE p.id = :p', ['p' => $projectId]);
        if ($project === null || $project['group_status'] !== 'active') {
            throw new RuleViolation('group_not_active', 'Group must be active (verified with an advisor)');
        }
        $advisorId = GroupService::advisorAssigned((int) $project['gid']);
        if ($advisorId === null) {
            throw new RuleViolation('advisor_missing', 'An advisor must be assigned first');
        }
        $topic = self::current($projectId) ?? throw new RuleViolation('topic_missing', 'No topic draft');
        $draft = self::submission((int) $topic['current_submission_id']);
        if ($draft === null || (int) $draft['is_draft'] !== 1) {
            throw new RuleViolation('topic_locked', 'Nothing to submit');
        }
        $missing = array_filter(self::REQUIRED, fn ($f) => trim((string) $draft[$f]) === '');
        if ($draft['project_type'] === 'other' && trim((string) $draft['project_type_other']) === '') {
            $missing[] = 'project_type_other';
        }
        if ($missing) {
            throw new RuleViolation('topic_incomplete', 'All topic fields are required: ' . implode(', ', $missing));
        }
        $late = OfferingService::checkDeadline((int) $project['offering_id'], 'topic_submission');

        $db->transaction(function (Database $db) use ($topic, $draft, $userId, $late, $advisorId, $project) {
            // Unlock the draft flag first; after this the version is immutable (trigger + VersionRules).
            $db->update('topic_submissions', ['is_draft' => 0, 'is_late' => $late ? 1 : 0, 'submitted_by' => $userId, 'submitted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $draft['id']]);
            $engine = new WorkflowEngine(Workflows::topic());
            if ($topic['status'] === 'draft') {
                $engine->apply((int) $topic['id'], 'submit', 'leader', $userId);
                $notify = [$advisorId];
            } else {
                $direct = $topic['returned_from'] === 'admin' && SettingsService::get('topic.admin_revision_route', 'advisor') === 'admin';
                $engine->apply((int) $topic['id'], $direct ? 'resubmit_admin' : 'resubmit', 'leader', $userId, null, ['returned_from' => null]);
                $notify = $direct ? NotificationService::admins() : [$advisorId];
            }
            NotificationService::notify($notify, 'topic.submitted', 'มีหัวข้อโครงงานส่งมาให้พิจารณา',
                "โครงงาน {$project['project_code']} ส่งหัวข้อ version {$draft['version_no']}", '/projects/' . $project['id'], 'topic', (int) $topic['id']);
        });
    }

    /** T2-T6: advisor review. $decision = approve | revise | reject */
    public static function advisorDecide(int $projectId, int $advisorId, string $decision, ?string $comments): void
    {
        $topic = self::current($projectId) ?? throw new RuleViolation('topic_missing', 'No topic');
        $project = self::project($projectId);
        if (GroupService::advisorAssigned((int) $project['group_id']) !== $advisorId) {
            throw new RuleViolation('not_advisor', 'Only the project advisor can review');
        }
        $map = ['approve' => ['advisor_approve', 'approved'], 'revise' => ['advisor_revise', 'revision_required'], 'reject' => ['advisor_reject', 'rejected']];
        if (!isset($map[$decision])) {
            throw new RuleViolation('invalid', 'Invalid decision');
        }
        [$action, $value] = $map[$decision];
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($topic, $advisorId, $action, $value, $comments, $project) {
            $engine = new WorkflowEngine(Workflows::topic());
            if ($topic['status'] === 'submitted') {
                $engine->apply((int) $topic['id'], 'start_review', 'advisor', $advisorId);
            }
            $extra = $action === 'advisor_revise' ? ['returned_from' => 'advisor'] : [];
            $engine->apply((int) $topic['id'], $action, 'advisor', $advisorId, $comments, $extra);
            $db->insert('topic_approvals', [
                'topic_id' => (int) $topic['id'], 'submission_id' => (int) $topic['current_submission_id'],
                'level' => 'advisor', 'decision' => $value, 'comments' => trim((string) $comments) ?: null, 'decided_by' => $advisorId,
            ]);
            $students = GroupService::memberUserIds((int) $project['group_id']);
            if ($action === 'advisor_approve') {
                $engine->apply((int) $topic['id'], 'forward_admin', 'system', $advisorId, 'Forwarded to course administrator');
                NotificationService::notify(NotificationService::admins(), 'topic.advisor_approved', 'หัวข้อรออนุมัติขั้นสุดท้าย',
                    "โครงงาน {$project['project_code']} ผ่านการพิจารณาจาก Advisor แล้ว", '/projects/' . $project['id'], 'topic', (int) $topic['id']);
            }
            NotificationService::notify($students, 'topic.advisor_reviewed', 'Advisor พิจารณาหัวข้อแล้ว',
                'ผลการพิจารณา: ' . __('decision.' . $value), '/projects/' . $project['id'], 'topic', (int) $topic['id']);
        });
    }

    /** Advisor opens the review (T2) without deciding yet. */
    public static function startReview(int $projectId, int $advisorId): void
    {
        $topic = self::current($projectId);
        if ($topic !== null && $topic['status'] === 'submitted' && GroupService::advisorAssigned((int) self::project($projectId)['group_id']) === $advisorId) {
            (new WorkflowEngine(Workflows::topic()))->apply((int) $topic['id'], 'start_review', 'advisor', $advisorId);
        }
    }

    /** T7-T9: course administrator final topic approval. $decision = approve | revise | reject */
    public static function adminDecide(int $projectId, int $adminId, string $decision, ?string $comments): void
    {
        $topic = self::current($projectId) ?? throw new RuleViolation('topic_missing', 'No topic');
        $project = self::project($projectId);
        $map = ['approve' => ['admin_approve', 'approved'], 'revise' => ['admin_revise', 'revision_required'], 'reject' => ['admin_reject', 'rejected']];
        if (!isset($map[$decision])) {
            throw new RuleViolation('invalid', 'Invalid decision');
        }
        [$action, $value] = $map[$decision];
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($topic, $adminId, $action, $value, $comments, $project) {
            $extra = match ($action) {
                'admin_approve' => ['approved_submission_id' => (int) $topic['current_submission_id'], 'approved_at' => date('Y-m-d H:i:s')],
                'admin_revise'  => ['returned_from' => 'admin'],
                default         => [],
            };
            (new WorkflowEngine(Workflows::topic()))->apply((int) $topic['id'], $action, 'admin', $adminId, $comments, $extra);
            $db->insert('topic_approvals', [
                'topic_id' => (int) $topic['id'], 'submission_id' => (int) $topic['current_submission_id'],
                'level' => 'admin', 'decision' => $value, 'comments' => trim((string) $comments) ?: null, 'decided_by' => $adminId,
            ]);
            if ($action === 'admin_approve') {
                $sub = self::submission((int) $topic['current_submission_id']);
                $db->update('projects', ['title_th' => $sub['title_th'], 'title_en' => $sub['title_en'], 'stage' => 'proposal'], 'id = :id', ['id' => (int) $project['id']]);
                if (!$db->value('SELECT id FROM proposal_submissions WHERE project_id = :p', ['p' => (int) $project['id']])) {
                    $pid = $db->insert('proposal_submissions', ['project_id' => (int) $project['id'], 'status' => 'draft']);
                    $db->insert('status_histories', ['entity_type' => 'proposal', 'entity_id' => $pid, 'from_status' => null, 'to_status' => 'draft', 'changed_by' => $adminId]);
                }
            }
            $recipients = GroupService::memberUserIds((int) $project['group_id']);
            $advisor = GroupService::advisorAssigned((int) $project['group_id']);
            if ($advisor) {
                $recipients[] = $advisor;
            }
            NotificationService::notify($recipients, 'topic.admin_decided', 'ผลการอนุมัติหัวข้อขั้นสุดท้าย',
                'ผลการพิจารณา: ' . __('decision.final_topic_' . $value), '/projects/' . $project['id'], 'topic', (int) $topic['id']);
        });
    }

    /** Lists for the Project Topics / Topic Approval menus. */
    public static function queue(int $offeringId, string $role, int $userId, string $filter = 'pending'): array
    {
        $where = ['p.offering_id = :o', 'p.deleted_at IS NULL'];
        $params = ['o' => $offeringId];
        if ($role === 'advisor') {
            $where[] = "EXISTS (SELECT 1 FROM project_advisors pa WHERE pa.group_id = p.group_id AND pa.advisor_id = :u AND pa.status = 'assigned')";
            $params['u'] = $userId;
            if ($filter === 'pending') {
                $where[] = "t.status IN ('submitted','advisor_review')";
            }
        } elseif ($filter === 'pending') {
            $where[] = "t.status = 'admin_review'";
        }
        return Database::connection()->fetchAll(
            'SELECT p.id AS project_id, p.project_code, g.group_code, g.group_name, t.status, t.updated_at, ts.title_th, ts.version_no, ts.submitted_at, ts.is_late
               FROM projects p JOIN project_groups g ON g.id = p.group_id
               JOIN project_topics t ON t.project_id = p.id AND t.current_key = 1
               LEFT JOIN topic_submissions ts ON ts.id = t.current_submission_id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY ts.submitted_at IS NULL, ts.submitted_at, g.group_code',
            $params
        );
    }

    private static function project(int $projectId): array
    {
        return Database::connection()->fetch('SELECT * FROM projects WHERE id = :p AND deleted_at IS NULL', ['p' => $projectId])
            ?? throw new RuleViolation('project_missing', 'Project not found');
    }
}
