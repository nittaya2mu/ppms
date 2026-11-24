<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

require_once __DIR__ . '/Fixtures.php';

/** Business rules enforced by the database itself (Phase 1 section 9.5). */
final class SchemaConstraintsTest extends DatabaseTestCase
{
    private Fixtures $fx;

    public function setUp(): void
    {
        parent::setUp();
        $this->fx = new Fixtures($this->db);
    }

    public function testSeedLoadsAllTablesAndDefaultRubric(): void
    {
        $this->assertSame(32, (int) $this->db->value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"));
        $this->assertSame('100.00', (string) $this->db->value('SELECT SUM(weight) FROM evaluation_criteria WHERE offering_id = 1'));
    }

    public function testStudentCannotBeInTwoActiveGroupsOfSameOffering(): void   // Section 4 BR-2
    {
        $this->fx->group('G1', 1, [2]);
        $g2 = $this->fx->group('G2', 3);
        $this->assertDbRejects(fn () => $this->fx->member($g2, 2), 'Duplicate');
        // ...but a removed membership does not block joining another group.
        $this->db->query("UPDATE project_members SET status = 'removed' WHERE student_id = 2");
        $this->fx->member($g2, 2);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM project_members WHERE student_id = 2 AND status = 'active'"));
    }

    public function testGroupHasExactlyOneActiveLeader(): void   // Section 4 BR-1
    {
        $g = $this->fx->group('G1', 1, [2]);
        $this->assertDbRejects(fn () => $this->fx->member($g, 3, 'leader'), 'Duplicate');
    }

    public function testGroupHasOneAssignedAdvisor(): void   // Section 3.3
    {
        $g = $this->fx->group('G1', 1);
        $this->fx->advisor($g, 101);
        $this->assertDbRejects(fn () => $this->fx->advisor($g, 102), 'Duplicate');
    }

    public function testCommitteeMembersMustBeDifferentUsers(): void   // Section 8
    {
        $g = $this->fx->group('G1', 1);
        $pp = $this->fx->proposalWithVersion($this->fx->project($g));
        $this->assertDbRejects(fn () => $this->fx->assignment($pp['proposal'], $pp['version'], 101, 103, 103));
    }

    public function testAdvisorCannotBeCommitteeMember(): void   // Section 3.3, 8
    {
        $g = $this->fx->group('G1', 1);
        $pp = $this->fx->proposalWithVersion($this->fx->project($g));
        $this->assertDbRejects(fn () => $this->fx->assignment($pp['proposal'], $pp['version'], 101, 101, 103), 'cannot be the project advisor');
    }

    public function testCommitteeMembersNeedActiveCommitteeRole(): void   // Section 8
    {
        $g = $this->fx->group('G1', 1);
        $pp = $this->fx->proposalWithVersion($this->fx->project($g));
        $this->db->query('UPDATE user_roles SET is_active = 0 WHERE user_id = 104 AND role_id = 4');
        $this->assertDbRejects(fn () => $this->fx->assignment($pp['proposal'], $pp['version'], 101, 103, 104), 'active committee role');
        $this->assertDbRejects(fn () => $this->fx->assignment($pp['proposal'], $pp['version'], 101, 103, 201), 'active committee role'); // a student
    }

    public function testAssignedVersionCannotChangeWithinARound(): void   // Section 3.4
    {
        $ids = $this->fx->evaluationReady();
        $this->assertDbRejects(fn () => $this->db->query('UPDATE committee_assignments SET proposal_version_id = 999 WHERE id = :id', ['id' => $ids['assignment']]));
    }

    public function testSubmittedEvaluationIsLockedUntilAdminReopens(): void   // Section 3.4, FR-EVL-04
    {
        $ids = $this->fx->evaluationReady();
        $crit = (int) $this->db->value('SELECT id FROM evaluation_criteria WHERE seq_no = 1');
        $this->db->insert('evaluation_scores', ['evaluation_id' => $ids['eval1'], 'criterion_id' => $crit, 'rating' => 4, 'weight_snapshot' => 15, 'weighted_score' => 12]);
        $this->db->query("UPDATE committee_evaluations SET status = 'submitted', total_score = 79, recommendation = 'approved', submitted_at = NOW() WHERE id = :id", ['id' => $ids['eval1']]);

        $this->assertDbRejects(fn () => $this->db->query('UPDATE committee_evaluations SET total_score = 95 WHERE id = :id', ['id' => $ids['eval1']]), 'locked');
        $this->assertDbRejects(fn () => $this->db->query('UPDATE evaluation_scores SET rating = 5 WHERE evaluation_id = :id', ['id' => $ids['eval1']]), 'locked');
        $this->assertDbRejects(fn () => $this->db->query('DELETE FROM evaluation_scores WHERE evaluation_id = :id', ['id' => $ids['eval1']]), 'locked');
        $this->assertDbRejects(fn () => $this->db->insert('evaluation_comments', ['evaluation_id' => $ids['eval1'], 'comment_type' => 'overall', 'content' => 'x']), 'locked');

        // Reopen needs an administrator and a reason.
        $this->assertDbRejects(fn () => $this->db->query("UPDATE committee_evaluations SET status = 'reopened' WHERE id = :id", ['id' => $ids['eval1']]), 'reason');
        $this->db->query("UPDATE committee_evaluations SET status = 'reopened', reopened_by = 1, reopened_at = NOW(), reopen_reason = 'คะแนนผิดพลาด' WHERE id = :id", ['id' => $ids['eval1']]);
        $this->db->query('UPDATE evaluation_scores SET rating = 5 WHERE evaluation_id = :id', ['id' => $ids['eval1']]);
        $this->assertSame(5, (int) $this->db->value('SELECT rating FROM evaluation_scores WHERE evaluation_id = :id', ['id' => $ids['eval1']]));
    }

    public function testCannotSubmitEvaluationWithoutScoreAndRecommendation(): void
    {
        $ids = $this->fx->evaluationReady();
        $this->assertDbRejects(fn () => $this->db->query("UPDATE committee_evaluations SET status = 'submitted' WHERE id = :id", ['id' => $ids['eval1']]));
    }

    public function testDecisionTablesAreAppendOnly(): void   // DD-11, Section 5 "no silent overwrite"
    {
        $g = $this->fx->group('G1', 1);
        $t = $this->fx->topicWithSubmission($this->fx->project($g), 'advisor_review');
        $id = $this->db->insert('topic_approvals', ['topic_id' => $t['topic'], 'submission_id' => $t['submission'], 'level' => 'advisor', 'decision' => 'approved', 'decided_by' => 101]);
        $this->assertDbRejects(fn () => $this->db->query("UPDATE topic_approvals SET decision = 'rejected' WHERE id = :id", ['id' => $id]), 'append-only');
        $this->assertDbRejects(fn () => $this->db->query('DELETE FROM topic_approvals WHERE id = :id', ['id' => $id]), 'append-only');
        // The same version cannot be decided twice at the same level.
        $this->assertDbRejects(fn () => $this->db->insert('topic_approvals', ['topic_id' => $t['topic'], 'submission_id' => $t['submission'], 'level' => 'advisor', 'decision' => 'rejected', 'comments' => 'x', 'decided_by' => 101]), 'Duplicate');

        $this->db->insert('audit_logs', ['action' => 'test']);
        $this->assertDbRejects(fn () => $this->db->query('DELETE FROM audit_logs'), 'append-only');
        $this->assertDbRejects(fn () => $this->db->query("UPDATE audit_logs SET action = 'x'"), 'append-only');
    }

    public function testRevisionOrRejectionNeedsComment(): void   // Section 5 Stage 2, Section 7
    {
        $g = $this->fx->group('G1', 1);
        $t = $this->fx->topicWithSubmission($this->fx->project($g), 'advisor_review');
        $this->assertDbRejects(fn () => $this->db->insert('topic_approvals', ['topic_id' => $t['topic'], 'submission_id' => $t['submission'], 'level' => 'advisor', 'decision' => 'revision_required', 'comments' => ' ', 'decided_by' => 101]));
    }

    public function testFinalDecisionDifferingFromCommitteeNeedsJustification(): void   // Section 9.6
    {
        $ids = $this->fx->evaluationReady();
        $base = ['proposal_id' => $ids['proposal'], 'proposal_version_id' => $ids['version'], 'assignment_id' => $ids['assignment'],
                 'decision_level' => 'final_decision', 'decision' => 'approved', 'decided_by' => 1, 'differs_from_committee' => 1];
        $this->assertDbRejects(fn () => $this->db->insert('approval_decisions', $base));
        $this->db->insert('approval_decisions', $base + ['justification' => 'ผลงานแก้ไขครบตามข้อเสนอแนะแล้ว']);
        // An advisor-level decision value cannot be recorded as a final decision.
        $this->assertDbRejects(fn () => $this->db->insert('approval_decisions', array_merge($base, ['decision' => 'ready_for_committee', 'differs_from_committee' => 0])));
    }

    public function testProposalVersionsAreNeverOverwrittenOrDeleted(): void   // Section 6
    {
        $g = $this->fx->group('G1', 1);
        $pp = $this->fx->proposalWithVersion($this->fx->project($g));
        $other = $this->fx->file();
        $this->assertDbRejects(fn () => $this->db->query('UPDATE proposal_versions SET file_id = :f WHERE id = :id', ['f' => $other, 'id' => $pp['version']]), 'immutable');
        $this->assertDbRejects(fn () => $this->db->query('DELETE FROM proposal_versions WHERE id = :id', ['id' => $pp['version']]));
        // Status of the version may still move along the workflow.
        $this->db->query("UPDATE proposal_versions SET version_status = 'superseded' WHERE id = :id", ['id' => $pp['version']]);
        $this->assertSame('superseded', $this->db->value('SELECT version_status FROM proposal_versions WHERE id = :id', ['id' => $pp['version']]));
    }

    public function testSubmittedTopicVersionIsImmutable(): void   // Section 3.2
    {
        $g = $this->fx->group('G1', 1);
        $t = $this->fx->topicWithSubmission($this->fx->project($g));
        $this->assertDbRejects(fn () => $this->db->query("UPDATE topic_submissions SET title_th = 'แก้หลังส่ง' WHERE id = :id", ['id' => $t['submission']]), 'immutable');
    }

    public function testOnlyOneNonRejectedTopicPerProject(): void
    {
        $p = $this->fx->project($this->fx->group('G1', 1));
        $this->db->insert('project_topics', ['project_id' => $p, 'status' => 'draft']);
        $this->assertDbRejects(fn () => $this->db->insert('project_topics', ['project_id' => $p, 'status' => 'draft']), 'Duplicate');
        $this->db->query("UPDATE project_topics SET status = 'topic_rejected' WHERE project_id = :p", ['p' => $p]);
        $this->db->insert('project_topics', ['project_id' => $p, 'status' => 'draft']); // OQ-09 default: new topic allowed
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM project_topics WHERE project_id = :p', ['p' => $p]));
    }
}
