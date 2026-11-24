<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\EvaluationRules;
use App\Services\Rules\RuleViolation;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\Workflows;

/**
 * Workflow 5: independent proposal evaluation by two committee members (Section 9).
 * Independence (9.4): a member only ever reads their own evaluation until both are submitted.
 */
final class EvaluationService
{
    public const COMMENT_TYPES = ['strengths', 'weaknesses', 'technical', 'improvements', 'overall'];

    /** Evaluations of the logged-in committee member. */
    public static function mine(int $userId, bool $pendingOnly = false): array
    {
        return Database::connection()->fetchAll(
            "SELECT ce.*, ca.evaluation_deadline, ca.round_no, ca.status AS assignment_status, ca.proposal_id,
                    p.id AS project_id, p.project_code, p.title_th, g.group_code, pv.version_no, pv.file_id
               FROM committee_evaluations ce
               JOIN committee_assignments ca ON ca.id = ce.assignment_id AND ca.status <> 'cancelled'
               JOIN proposal_versions pv ON pv.id = ca.proposal_version_id
               JOIN proposal_submissions ps ON ps.id = ca.proposal_id
               JOIN projects p ON p.id = ps.project_id JOIN project_groups g ON g.id = p.group_id
              WHERE ce.evaluator_id = :u" . ($pendingOnly ? " AND ce.status <> 'submitted'" : '') . '
              ORDER BY ce.status = \'submitted\', ca.evaluation_deadline',
            ['u' => $userId]
        );
    }

    /** Loads an evaluation only for its own evaluator (FR-EVL-05). */
    public static function ownEvaluation(int $evaluationId, int $userId): array
    {
        $ev = Database::connection()->fetch(
            "SELECT ce.*, ca.proposal_id, ca.proposal_version_id, ca.evaluation_deadline, ca.round_no, ca.status AS assignment_status,
                    ps.project_id, p.offering_id, p.project_code, p.title_th, p.title_en, g.group_code, g.group_name, pv.version_no, pv.file_id
               FROM committee_evaluations ce JOIN committee_assignments ca ON ca.id = ce.assignment_id
               JOIN proposal_submissions ps ON ps.id = ca.proposal_id JOIN projects p ON p.id = ps.project_id
               JOIN project_groups g ON g.id = p.group_id JOIN proposal_versions pv ON pv.id = ca.proposal_version_id
              WHERE ce.id = :id AND ce.evaluator_id = :u AND ca.status <> 'cancelled'",
            ['id' => $evaluationId, 'u' => $userId]
        );
        if ($ev === null) {
            throw new RuleViolation('evaluation_not_yours', 'Evaluation not found');
        }
        return $ev;
    }

    public static function scores(int $evaluationId): array
    {
        $out = [];
        foreach (Database::connection()->fetchAll('SELECT * FROM evaluation_scores WHERE evaluation_id = :e', ['e' => $evaluationId]) as $r) {
            $out[(int) $r['criterion_id']] = $r;
        }
        return $out;
    }

    public static function comments(int $evaluationId): array
    {
        $out = [];
        foreach (Database::connection()->fetchAll('SELECT * FROM evaluation_comments WHERE evaluation_id = :e', ['e' => $evaluationId]) as $r) {
            $out[$r['comment_type']] = $r;
        }
        return $out;
    }

    /**
     * Saves a draft or submits (locks) the evaluation (FR-EVL-02..04, FR-EVL-06).
     * @param array<int,int> $ratings criterion_id => 1..5
     * @param array<string,string> $comments comment_type => text
     * @param int[] $critical comment types flagged as critical
     */
    public static function save(int $evaluationId, int $userId, array $ratings, ?string $recommendation, array $comments, array $critical, bool $submit): void
    {
        $ev = self::ownEvaluation($evaluationId, $userId);
        EvaluationRules::assertEditable($evaluationId);
        if ($ev['assignment_status'] === 'cancelled') {
            throw new RuleViolation('assignment_cancelled', 'Assignment cancelled');
        }
        $criteria = AcademicService::rubric((int) $ev['offering_id'], true);
        $weights = [];
        foreach ($criteria as $c) {
            $weights[(int) $c['id']] = $c['weight'];
        }
        $ratings = array_filter(array_map('intval', array_intersect_key($ratings, $weights)), fn ($r) => $r >= 1 && $r <= 5);
        if ($recommendation !== null && $recommendation !== '' && !in_array($recommendation, ScoreCalculator::RECOMMENDATIONS, true)) {
            throw new RuleViolation('invalid', 'Invalid recommendation');
        }

        $total = null;
        if ($submit) {
            if (count($ratings) !== count($weights)) {
                throw new RuleViolation('evaluation_incomplete', 'Every criterion must be rated');
            }
            if (!$recommendation) {
                throw new RuleViolation('evaluation_incomplete', 'A recommendation is required');
            }
            foreach (self::COMMENT_TYPES as $t) {
                if (trim((string) ($comments[$t] ?? '')) === '') {
                    throw new RuleViolation('evaluation_comments', 'All five comment fields are required');
                }
            }
            $total = ScoreCalculator::total($ratings, $weights)['total'];
        }

        $db = Database::connection();
        $db->transaction(function (Database $db) use ($ev, $evaluationId, $userId, $ratings, $weights, $recommendation, $comments, $critical, $submit, $total) {
            foreach ($weights as $cid => $w) {
                if (!isset($ratings[$cid])) {
                    $db->query('DELETE FROM evaluation_scores WHERE evaluation_id = :e AND criterion_id = :c', ['e' => $evaluationId, 'c' => $cid]);
                    continue;
                }
                $db->query(
                    'INSERT INTO evaluation_scores (evaluation_id, criterion_id, rating, weight_snapshot, weighted_score) VALUES (:e, :c, :r, :w, :s)
                     ON DUPLICATE KEY UPDATE rating = VALUES(rating), weight_snapshot = VALUES(weight_snapshot), weighted_score = VALUES(weighted_score)',
                    ['e' => $evaluationId, 'c' => $cid, 'r' => $ratings[$cid], 'w' => (string) $w, 's' => (string) ScoreCalculator::criterionScore($ratings[$cid], $w)]
                );
            }
            foreach (self::COMMENT_TYPES as $t) {
                $text = trim((string) ($comments[$t] ?? ''));
                if ($text === '') {
                    $db->query('DELETE FROM evaluation_comments WHERE evaluation_id = :e AND comment_type = :t', ['e' => $evaluationId, 't' => $t]);
                    continue;
                }
                $db->query(
                    'INSERT INTO evaluation_comments (evaluation_id, comment_type, content, is_critical) VALUES (:e, :t, :c, :k)
                     ON DUPLICATE KEY UPDATE content = VALUES(content), is_critical = VALUES(is_critical)',
                    ['e' => $evaluationId, 't' => $t, 'c' => $text, 'k' => in_array($t, $critical, true) ? 1 : 0]
                );
            }
            $data = ['recommendation' => $recommendation ?: null];
            if ($submit) {
                $data += ['status' => 'submitted', 'total_score' => number_format((float) $total, 2, '.', ''), 'submitted_at' => date('Y-m-d H:i:s')];
            } elseif ($ev['status'] === 'not_started') {
                $data['status'] = 'draft';
            }
            $db->update('committee_evaluations', $data, 'id = :id', ['id' => $evaluationId]);
            $db->insert('status_histories', ['entity_type' => 'evaluation', 'entity_id' => $evaluationId, 'from_status' => $ev['status'], 'to_status' => $data['status'] ?? $ev['status'], 'changed_by' => $userId]);
            if ($submit) {
                AuditLogger::log('evaluation.submitted', 'evaluation', $evaluationId, null, ['total_score' => $total, 'recommendation' => $recommendation, 'ratings' => $ratings], userId: $userId);
                AcademicService::lockRubric((int) $ev['offering_id']);
                NotificationService::notify(NotificationService::admins(), 'evaluation.submitted', 'Committee ส่งผลประเมินแล้ว',
                    "โครงงาน {$ev['project_code']}: กรรมการคนที่ {$ev['member_slot']} ส่งผลประเมินแล้ว", '/projects/' . $ev['project_id'], 'evaluation', $evaluationId);
                self::completeIfBothSubmitted((int) $ev['assignment_id'], $userId);
            }
        });
    }

    /**
     * P8: when both evaluations are submitted, compute S_avg, flag disagreement and move the
     * proposal to Evaluation Completed (system transition). Also re-run after a reopened
     * evaluation is submitted again.
     */
    public static function completeIfBothSubmitted(int $assignmentId, int $actorId): void
    {
        $db = Database::connection();
        $evals = $db->fetchAll('SELECT * FROM committee_evaluations WHERE assignment_id = :a ORDER BY member_slot', ['a' => $assignmentId]);
        if (count($evals) !== 2 || $evals[0]['status'] !== 'submitted' || $evals[1]['status'] !== 'submitted') {
            return;
        }
        $assignment = $db->fetch('SELECT * FROM committee_assignments WHERE id = :a', ['a' => $assignmentId]);
        $avg = ScoreCalculator::average((float) $evals[0]['total_score'], (float) $evals[1]['total_score']);
        $differ = ScoreCalculator::recommendationsDiffer($evals[0]['recommendation'], $evals[1]['recommendation']);
        $gap = SettingsService::int('evaluation.score_gap_flag', 0);
        $gapFlag = $gap > 0 && abs((float) $evals[0]['total_score'] - (float) $evals[1]['total_score']) >= $gap;

        $db->update('committee_assignments', [
            'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s'),
            'average_score' => number_format($avg, 2, '.', ''), 'recommendations_differ' => $differ ? 1 : 0,
        ], 'id = :id', ['id' => $assignmentId]);
        $db->update('proposal_versions', ['version_status' => 'evaluated'], 'id = :id', ['id' => (int) $assignment['proposal_version_id']]);

        $proposal = ProposalService::find((int) $assignment['proposal_id']);
        $extra = ['needs_admin_attention' => ($differ || $gapFlag) ? 1 : 0];
        if ($proposal['status'] === 'committee_evaluation') {
            (new WorkflowEngine(Workflows::proposal()))->apply((int) $proposal['id'], 'complete_evaluation', 'system', $actorId, 'Both evaluations submitted', $extra);
        } else {
            $db->update('proposal_submissions', $extra, 'id = :id', ['id' => (int) $proposal['id']]);
        }
        $project = $db->fetch('SELECT * FROM projects WHERE id = :p', ['p' => (int) $proposal['project_id']]);
        NotificationService::notify(array_merge(NotificationService::admins(), [(int) $assignment['advisor_id']]), 'evaluation.completed',
            'Committee ประเมินครบทั้งสองคนแล้ว', "โครงงาน {$project['project_code']} คะแนนเฉลี่ย " . number_format($avg, 2) . ($differ ? ' (ข้อเสนอแนะไม่ตรงกัน)' : ''),
            '/projects/' . $project['id'], 'proposal', (int) $proposal['id']);
    }

    /** Admin authorizes a change to a submitted evaluation (Section 3.4). */
    public static function reopen(int $evaluationId, int $adminId, string $reason): void
    {
        EvaluationRules::assertReopenable($evaluationId, $adminId, $reason);
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($evaluationId, $adminId, $reason) {
            $ev = $db->fetch('SELECT * FROM committee_evaluations WHERE id = :id', ['id' => $evaluationId]);
            $old = ['evaluation' => $ev, 'scores' => array_values(self::scores($evaluationId)), 'comments' => array_values(self::comments($evaluationId))];
            $db->update('committee_evaluations', ['status' => 'reopened', 'reopened_by' => $adminId, 'reopened_at' => date('Y-m-d H:i:s'), 'reopen_reason' => $reason], 'id = :id', ['id' => $evaluationId]);
            $db->insert('status_histories', ['entity_type' => 'evaluation', 'entity_id' => $evaluationId, 'from_status' => 'submitted', 'to_status' => 'reopened', 'reason' => $reason, 'changed_by' => $adminId]);
            AuditLogger::log('evaluation.reopened', 'evaluation', $evaluationId, $old, null, $reason, $adminId);
            NotificationService::notify([(int) $ev['evaluator_id']], 'evaluation.reopened', 'เปิดให้แก้ไขผลประเมิน', 'ผู้ดูแลระบบเปิดให้แก้ไขผลประเมิน: ' . $reason, '/evaluations/' . $evaluationId, 'evaluation', $evaluationId);
        });
    }

    /**
     * Summary for viewers other than the evaluator. Returns null while evaluations are incomplete
     * unless $partialStatusOnly is used (admin progress view shows status only, never scores).
     */
    public static function summary(int $assignmentId, bool $withComments = true): ?array
    {
        $db = Database::connection();
        $a = $db->fetch('SELECT * FROM committee_assignments WHERE id = :a', ['a' => $assignmentId]);
        if ($a === null || $a['status'] !== 'completed') {
            return null;
        }
        $evals = $db->fetchAll(
            'SELECT ce.*, u.title_th, u.first_name_th, u.last_name_th FROM committee_evaluations ce JOIN users u ON u.id = ce.evaluator_id
              WHERE ce.assignment_id = :a ORDER BY ce.member_slot',
            ['a' => $assignmentId]
        );
        foreach ($evals as &$e) {
            $e['scores'] = $db->fetchAll(
                'SELECT es.*, c.name_th, c.seq_no FROM evaluation_scores es JOIN evaluation_criteria c ON c.id = es.criterion_id
                  WHERE es.evaluation_id = :e ORDER BY c.seq_no',
                ['e' => (int) $e['id']]
            );
            $e['comments'] = $withComments ? self::comments((int) $e['id']) : [];
        }
        unset($e);
        return ['assignment' => $a, 'evaluations' => $evals];
    }

    /** Status-only progress (no scores) for admin/advisor while evaluation is in progress. */
    public static function progress(int $assignmentId): array
    {
        return Database::connection()->fetchAll(
            'SELECT ce.member_slot, ce.status, ce.submitted_at, u.title_th, u.first_name_th, u.last_name_th
               FROM committee_evaluations ce JOIN users u ON u.id = ce.evaluator_id WHERE ce.assignment_id = :a ORDER BY ce.member_slot',
            ['a' => $assignmentId]
        );
    }

    /** OQ-11: may this committee member see the other member's evaluation? */
    public static function peerVisible(int $assignmentId): bool
    {
        return SettingsService::bool('evaluation.peer_visible_after_both', true)
            && Database::connection()->value('SELECT status FROM committee_assignments WHERE id = :a', ['a' => $assignmentId]) === 'completed';
    }
}
