<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\Workflows;

/**
 * Final decision by the course administrator (Section 9.6, P9-P13) and the official
 * approval summary (Section 11). The system never approves on the average score alone.
 */
final class DecisionService
{
    public const DECISIONS = ['approved', 'approved_minor', 'major_revisions', 'not_approved'];

    /**
     * @return array{differs:bool, recommendations:string[], average:?float, assignment:?array}
     */
    public static function committeeView(int $proposalId): array
    {
        $db = Database::connection();
        $a = $db->fetch(
            "SELECT * FROM committee_assignments WHERE proposal_id = :p AND status = 'completed' ORDER BY round_no DESC LIMIT 1",
            ['p' => $proposalId]
        );
        if ($a === null) {
            return ['differs' => false, 'recommendations' => [], 'average' => null, 'assignment' => null];
        }
        $recs = array_column($db->fetchAll('SELECT recommendation FROM committee_evaluations WHERE assignment_id = :a ORDER BY member_slot', ['a' => (int) $a['id']]), 'recommendation');
        return ['differs' => (bool) $a['recommendations_differ'], 'recommendations' => $recs, 'average' => (float) $a['average_score'], 'assignment' => $a];
    }

    /**
     * Records the final decision.
     * $decision: approved | approved_minor | major_revisions | not_approved
     */
    public static function decide(int $proposalId, int $adminId, string $decision, ?string $comments, ?string $reasons, ?string $justification, ?string $revisionDue = null): void
    {
        if (!in_array($decision, self::DECISIONS, true)) {
            throw new RuleViolation('invalid', 'Invalid decision');
        }
        $db = Database::connection();
        $proposal = ProposalService::find($proposalId) ?? throw new RuleViolation('proposal_missing', 'No proposal');
        if (!in_array($proposal['status'], ['evaluation_completed', 'final_review'], true)) {
            throw new RuleViolation('proposal_state', 'The proposal is not ready for a final decision');
        }
        $project = $db->fetch('SELECT * FROM projects WHERE id = :p', ['p' => (int) $proposal['project_id']]);
        self::assertNoConflict((int) $project['group_id'], $proposalId, $adminId);

        $view = self::committeeView($proposalId);
        // Compare with the recommendations of the latest completed round on the CURRENT version only.
        $relevant = $view['assignment'] !== null && (int) $view['assignment']['proposal_version_id'] === (int) $proposal['current_version_id'];
        $differs = false;
        if ($relevant && count($view['recommendations']) === 2) {
            $differs = ScoreCalculator::requiresJustification($decision, $view['recommendations'][0], $view['recommendations'][1], (string) SettingsService::get('decision.justify_when', 'any'));
        }
        if ($differs && trim((string) $justification) === '') {
            throw new RuleViolation('justification_required', 'A justification is required when the decision differs from the committee');
        }

        $db->transaction(function (Database $db) use ($proposal, $project, $adminId, $decision, $comments, $reasons, $justification, $differs, $view, $relevant, $revisionDue) {
            $engine = new WorkflowEngine(Workflows::proposal());
            if ($proposal['status'] === 'evaluation_completed') {
                $engine->apply((int) $proposal['id'], 'open_final_review', 'admin', $adminId);
            }
            $decisionId = $db->insert('approval_decisions', [
                'proposal_id' => (int) $proposal['id'], 'proposal_version_id' => (int) $proposal['current_version_id'],
                'assignment_id' => $relevant ? (int) $view['assignment']['id'] : null,
                'decision_level' => 'final_decision', 'decision' => $decision,
                'comments' => trim((string) $comments) ?: null, 'supporting_reasons' => trim((string) $reasons) ?: null,
                'justification' => trim((string) $justification) ?: null, 'differs_from_committee' => $differs ? 1 : 0,
                'average_score_snapshot' => $relevant ? $view['average'] : null, 'decided_by' => $adminId,
            ]);
            $release = SettingsService::bool('evaluation.release_on_decision', true) ? ['released_at' => date('Y-m-d H:i:s')] : [];
            $action = ['approved' => 'final_approve', 'approved_minor' => 'final_minor', 'major_revisions' => 'final_major', 'not_approved' => 'final_reject'][$decision];
            $engine->apply((int) $proposal['id'], $action, 'admin', $adminId, $justification ?: $comments, $release + ['needs_admin_attention' => 0]);

            $needsRevision = $decision === 'major_revisions' || ($decision === 'approved_minor' && SettingsService::bool('decision.minor_requires_revision', true));
            ProposalService::closeOpenRevisions($db, (int) $proposal['id']);
            if ($needsRevision) {
                $db->insert('revision_requests', [
                    'proposal_id' => (int) $proposal['id'], 'decision_id' => $decisionId, 'source_version_id' => (int) $proposal['current_version_id'],
                    'requested_by_level' => 'admin', 'status' => 'open',
                    'due_at' => $revisionDue ? date('Y-m-d H:i:s', strtotime($revisionDue)) : null,
                ]);
            }
            if ($decision === 'approved') {
                $db->update('projects', ['stage' => 'approved', 'final_approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $project['id']]);
            }

            $recipients = GroupService::memberUserIds((int) $project['group_id']);
            $advisor = GroupService::advisorAssigned((int) $project['group_id']);
            if ($advisor) {
                $recipients[] = $advisor;
            }
            if ($view['assignment']) {
                $recipients[] = (int) $view['assignment']['member1_id'];
                $recipients[] = (int) $view['assignment']['member2_id'];
            }
            NotificationService::notify($recipients, 'decision.final', 'บันทึกผลการพิจารณา Proposal ขั้นสุดท้ายแล้ว',
                "โครงงาน {$project['project_code']}: " . __('decision.' . $decision), '/projects/' . $project['id'], 'proposal', (int) $proposal['id']);
        });
    }

    /** OQ-08: an admin who is the advisor or a committee member of this project may not decide when another admin exists. */
    public static function assertNoConflict(int $groupId, int $proposalId, int $adminId): void
    {
        if (!SettingsService::bool('decision.block_conflicted_admin', true)) {
            return;
        }
        $conflicted = GroupService::advisorAssigned($groupId) === $adminId || AccessPolicy::isCommittee($proposalId, $adminId);
        if ($conflicted && count(array_diff(NotificationService::admins(), [$adminId])) > 0) {
            throw new RuleViolation('conflict_of_interest', 'Another administrator must record this decision');
        }
        if ($conflicted) {
            AuditLogger::log('decision.conflict_noted', 'proposal', $proposalId, null, ['admin_id' => $adminId], 'Only administrator available', $adminId);
        }
    }

    /** Data for the official approval summary (Section 11). */
    public static function approvalSummary(int $projectId): ?array
    {
        $db = Database::connection();
        $project = $db->fetch(
            "SELECT p.*, g.group_code, g.group_name, o.id AS offering_id, c.course_code, c.name_th AS course_name_th, s.term_no, y.year_be
               FROM projects p JOIN project_groups g ON g.id = p.group_id JOIN course_offerings o ON o.id = p.offering_id
               JOIN courses c ON c.id = o.course_id JOIN semesters s ON s.id = o.semester_id JOIN academic_years y ON y.id = s.academic_year_id
              WHERE p.id = :p",
            ['p' => $projectId]
        );
        if ($project === null) {
            return null;
        }
        $proposal = ProposalService::forProject($projectId);
        $final = $proposal ? $db->fetch(
            "SELECT d.*, u.title_th, u.first_name_th, u.last_name_th FROM approval_decisions d JOIN users u ON u.id = d.decided_by
              WHERE d.proposal_id = :p AND d.decision_level = 'final_decision' ORDER BY d.id DESC LIMIT 1",
            ['p' => (int) $proposal['id']]
        ) : null;
        $view = $proposal ? self::committeeView((int) $proposal['id']) : ['assignment' => null];
        return [
            'project'   => $project,
            'members'   => GroupService::members((int) $project['group_id'], false),
            'advisor'   => GroupService::advisor((int) $project['group_id']),
            'proposal'  => $proposal,
            'final'     => $final,
            'committee' => $view['assignment'] ? EvaluationService::summary((int) $view['assignment']['id'], false) : null,
        ];
    }
}
