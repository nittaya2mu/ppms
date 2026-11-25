<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\Workflows;

/**
 * Presentation evaluation (แบบฟอร์ม 2) by the project advisor and both committee members.
 * Independence: an evaluator only reads their own form until all three are submitted.
 */
final class EvaluationService
{
    public const ROLES = ['advisor', 'committee1', 'committee2'];

    /** Evaluations of the logged-in advisor / committee member. */
    public static function mine(int $userId, bool $pendingOnly = false): array
    {
        return Database::connection()->fetchAll(
            "SELECT pe.*, ca.evaluation_deadline, ca.round_no, ca.status AS assignment_status, ca.proposal_id,
                    p.id AS project_id, p.project_code, p.title_th, g.group_code, pv.version_no, pv.file_id
               FROM presentation_evaluations pe
               JOIN committee_assignments ca ON ca.id = pe.assignment_id AND ca.status <> 'cancelled'
               JOIN proposal_versions pv ON pv.id = ca.proposal_version_id
               JOIN proposal_submissions ps ON ps.id = ca.proposal_id
               JOIN projects p ON p.id = ps.project_id JOIN project_groups g ON g.id = p.group_id
              WHERE pe.evaluator_id = :u" . ($pendingOnly ? " AND pe.status <> 'submitted'" : '') . "
              ORDER BY pe.status = 'submitted', ca.evaluation_deadline",
            ['u' => $userId]
        );
    }

    /** Loads an evaluation only for its own evaluator. */
    public static function ownEvaluation(int $evaluationId, int $userId): array
    {
        $ev = Database::connection()->fetch(
            "SELECT pe.*, ca.proposal_id, ca.proposal_version_id, ca.evaluation_deadline, ca.round_no, ca.status AS assignment_status,
                    ps.project_id, p.offering_id, p.project_code, p.title_th, p.title_en, g.group_code, g.group_name, pv.version_no,
                    pv.file_id, pv.ch2_file_id, pv.ch3_file_id
               FROM presentation_evaluations pe JOIN committee_assignments ca ON ca.id = pe.assignment_id
               JOIN proposal_submissions ps ON ps.id = ca.proposal_id JOIN projects p ON p.id = ps.project_id
               JOIN project_groups g ON g.id = p.group_id JOIN proposal_versions pv ON pv.id = ca.proposal_version_id
              WHERE pe.id = :id AND pe.evaluator_id = :u AND ca.status <> 'cancelled'",
            ['id' => $evaluationId, 'u' => $userId]
        );
        if ($ev === null) {
            throw new RuleViolation('evaluation_not_yours', 'Evaluation not found');
        }
        return $ev;
    }

    public static function grades(array $evaluation): array
    {
        $g = json_decode((string) ($evaluation['grades_json'] ?? ''), true);
        return is_array($g) ? $g : [];
    }

    /**
     * Saves a draft or submits (locks) the form.
     * @param array<string,string> $grades item key => letter grade (A ... F)
     * @param array<string,?string> $checks CHECKS key => '1' suitable | '0' not suitable
     */
    public static function save(int $evaluationId, int $userId, array $grades, array $checks, ?string $result, ?string $suggestions, bool $submit): void
    {
        $ev = self::ownEvaluation($evaluationId, $userId);
        if ($ev['status'] === 'submitted') {
            throw new RuleViolation('evaluation_locked', 'A submitted evaluation is locked');
        }
        if ($ev['assignment_status'] === 'cancelled') {
            throw new RuleViolation('assignment_cancelled', 'Assignment cancelled');
        }
        $grades = array_filter(array_intersect_key(array_map('strval', $grades), PresentationForm::itemMax()), fn ($g) => isset(PresentationForm::GRADES[$g]));
        $checkValues = [];
        foreach (PresentationForm::CHECKS as $k => $_) {
            $v = $checks[$k] ?? null;
            $checkValues[$k] = ($v === '1' || $v === 1) ? 1 : (($v === '0' || $v === 0) ? 0 : null);
        }
        $result = $result !== null && in_array($result, PresentationForm::RESULTS, true) ? $result : null;
        $suggestions = trim((string) $suggestions) ?: null;
        $score = PresentationForm::score($grades);

        if ($submit) {
            if (!$score['complete'] || in_array(null, $checkValues, true) || $result === null) {
                throw new RuleViolation('evaluation_incomplete', 'Every item, check and the result are required');
            }
            if ($result !== 'pass' && $suggestions === null) {
                throw new RuleViolation('evaluation_comments', 'Suggestions are required unless the result is pass');
            }
        }

        Database::connection()->transaction(function (Database $db) use ($ev, $evaluationId, $userId, $grades, $checkValues, $result, $suggestions, $score, $submit) {
            $data = $checkValues + [
                'grades_json' => json_encode($grades, JSON_UNESCAPED_UNICODE),
                'total_score' => $grades ? number_format($score['total'], 2, '.', '') : null,
                'letter_grade' => $score['complete'] ? $score['letter'] : null,
                'result' => $result, 'suggestions' => $suggestions,
            ];
            if ($submit) {
                $data += ['status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s')];
            } elseif ($ev['status'] === 'not_started') {
                $data['status'] = 'draft';
            }
            $db->update('presentation_evaluations', $data, 'id = :id', ['id' => $evaluationId]);
            $db->insert('status_histories', ['entity_type' => 'evaluation', 'entity_id' => $evaluationId, 'from_status' => $ev['status'], 'to_status' => $data['status'] ?? $ev['status'], 'changed_by' => $userId]);
            if ($submit) {
                AuditLogger::log('evaluation.submitted', 'evaluation', $evaluationId, null, ['total_score' => $score['total'], 'result' => $result, 'grades' => $grades], userId: $userId);
                NotificationService::notify(NotificationService::admins(), 'evaluation.submitted', 'ส่งผลประเมินการนำเสนอแล้ว',
                    "โครงงาน {$ev['project_code']}: " . __('evaluator_role.' . $ev['evaluator_role']) . ' ส่งผลประเมินแล้ว', '/projects/' . $ev['project_id'], 'evaluation', $evaluationId);
                self::completeIfAllSubmitted((int) $ev['assignment_id'], $userId);
            }
        });
    }

    /**
     * When the advisor and both committee members have submitted: average the totals, flag
     * differing results and move the proposal to Evaluation Completed.
     */
    public static function completeIfAllSubmitted(int $assignmentId, int $actorId): void
    {
        $db = Database::connection();
        $evals = $db->fetchAll('SELECT * FROM presentation_evaluations WHERE assignment_id = :a', ['a' => $assignmentId]);
        if (count($evals) !== count(self::ROLES) || array_filter($evals, fn ($e) => $e['status'] !== 'submitted')) {
            return;
        }
        $assignment = $db->fetch('SELECT * FROM committee_assignments WHERE id = :a', ['a' => $assignmentId]);
        $totals = array_map(fn ($e) => (float) $e['total_score'], $evals);
        $avg = round(array_sum($totals) / count($totals) + 1e-9, 2);
        $differ = count(array_unique(array_column($evals, 'result'))) > 1;
        $gap = SettingsService::int('evaluation.score_gap_flag', 0);
        $gapFlag = $gap > 0 && (max($totals) - min($totals)) >= $gap;

        $db->update('committee_assignments', [
            'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s'),
            'average_score' => number_format($avg, 2, '.', ''), 'recommendations_differ' => $differ ? 1 : 0,
        ], 'id = :id', ['id' => $assignmentId]);
        $db->update('proposal_versions', ['version_status' => 'evaluated'], 'id = :id', ['id' => (int) $assignment['proposal_version_id']]);

        $proposal = ProposalService::find((int) $assignment['proposal_id']);
        $extra = ['needs_admin_attention' => ($differ || $gapFlag) ? 1 : 0];
        if ($proposal['status'] === 'committee_evaluation') {
            (new WorkflowEngine(Workflows::proposal()))->apply((int) $proposal['id'], 'complete_evaluation', 'system', $actorId, 'All presentation evaluations submitted', $extra);
        } else {
            $db->update('proposal_submissions', $extra, 'id = :id', ['id' => (int) $proposal['id']]);
        }
        $project = $db->fetch('SELECT * FROM projects WHERE id = :p', ['p' => (int) $proposal['project_id']]);
        NotificationService::notify(array_merge(NotificationService::admins(), [(int) $assignment['advisor_id']]), 'evaluation.completed',
            'ประเมินการนำเสนอครบแล้ว', "โครงงาน {$project['project_code']} คะแนนเฉลี่ย " . number_format($avg, 2) . ($differ ? ' (ผลการประเมินไม่ตรงกัน)' : ''),
            '/projects/' . $project['id'], 'proposal', (int) $proposal['id']);
    }

    /** Admin authorizes a change to a submitted evaluation (reason required, audited). */
    public static function reopen(int $evaluationId, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuleViolation('reopen_reason', 'A reason is required');
        }
        $db = Database::connection();
        $ev = $db->fetch('SELECT * FROM presentation_evaluations WHERE id = :id', ['id' => $evaluationId]) ?? throw new RuleViolation('evaluation_missing', 'Not found');
        if ($ev['status'] !== 'submitted') {
            throw new RuleViolation('evaluation_not_submitted', 'Only submitted evaluations can be reopened');
        }
        $db->transaction(function (Database $db) use ($ev, $evaluationId, $adminId, $reason) {
            $db->update('presentation_evaluations', ['status' => 'reopened', 'reopened_by' => $adminId, 'reopened_at' => date('Y-m-d H:i:s'), 'reopen_reason' => $reason], 'id = :id', ['id' => $evaluationId]);
            $db->insert('status_histories', ['entity_type' => 'evaluation', 'entity_id' => $evaluationId, 'from_status' => 'submitted', 'to_status' => 'reopened', 'reason' => $reason, 'changed_by' => $adminId]);
            AuditLogger::log('evaluation.reopened', 'evaluation', $evaluationId, $ev, null, $reason, $adminId);
            NotificationService::notify([(int) $ev['evaluator_id']], 'evaluation.reopened', 'เปิดให้แก้ไขผลประเมิน', 'ผู้ดูแลระบบเปิดให้แก้ไขผลประเมิน: ' . $reason, '/evaluations/' . $evaluationId, 'evaluation', $evaluationId);
        });
    }

    /** All three forms of a completed round (null while incomplete). */
    public static function summary(int $assignmentId): ?array
    {
        $db = Database::connection();
        $a = $db->fetch('SELECT * FROM committee_assignments WHERE id = :a', ['a' => $assignmentId]);
        if ($a === null || $a['status'] !== 'completed') {
            return null;
        }
        return ['assignment' => $a, 'evaluations' => self::forAssignment($assignmentId)];
    }

    public static function forAssignment(int $assignmentId): array
    {
        $rows = Database::connection()->fetchAll(
            "SELECT pe.*, u.title_th, u.first_name_th, u.last_name_th FROM presentation_evaluations pe JOIN users u ON u.id = pe.evaluator_id
              WHERE pe.assignment_id = :a ORDER BY FIELD(pe.evaluator_role, 'advisor', 'committee1', 'committee2')",
            ['a' => $assignmentId]
        );
        foreach ($rows as &$r) {
            $r['grades'] = self::grades($r);
        }
        return $rows;
    }

    /** Status-only progress (no scores) while the round is in progress. */
    public static function progress(int $assignmentId): array
    {
        return Database::connection()->fetchAll(
            "SELECT pe.evaluator_role, pe.status, pe.submitted_at, u.title_th, u.first_name_th, u.last_name_th
               FROM presentation_evaluations pe JOIN users u ON u.id = pe.evaluator_id WHERE pe.assignment_id = :a
              ORDER BY FIELD(pe.evaluator_role, 'advisor', 'committee1', 'committee2')",
            ['a' => $assignmentId]
        );
    }

    /** May an evaluator see the other forms of this round? */
    public static function peerVisible(int $assignmentId): bool
    {
        return SettingsService::bool('evaluation.peer_visible_after_both', true)
            && Database::connection()->value('SELECT status FROM committee_assignments WHERE id = :a', ['a' => $assignmentId]) === 'completed';
    }
}
