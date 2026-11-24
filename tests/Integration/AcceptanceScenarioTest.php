<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AcademicService;
use App\Services\DecisionService;
use App\Services\EvaluationService;
use App\Services\FileService;
use App\Services\GroupService;
use App\Services\OfferingService;
use App\Services\ProposalService;
use App\Services\Rules\RuleViolation;
use App\Services\TopicService;
use App\Services\Workflow\TransitionDenied;
use Tests\DatabaseTestCase;

/**
 * Section 22 acceptance scenario, end to end through the service layer, plus the required
 * negative cases: validation errors, unauthorized actions, incomplete evaluations,
 * conflicting recommendations and revision scenarios.
 *
 * Demo data: admin = user 1; advisor1 = 101 (advisor + committee); committee1/2 = 103/104;
 * students.id 1..6 = users 201..206; offering 1 is open.
 */
final class AcceptanceScenarioTest extends DatabaseTestCase
{
    private const ADMIN = 1;
    private const ADVISOR = 101;
    private const C1 = 103;
    private const C2 = 104;

    public function setUp(): void
    {
        parent::setUp();
        FileService::$testing = true;
        OfferingService::flush();
    }

    private function pdf(string $label = 'proposal'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ppms');
        file_put_contents($tmp, "%PDF-1.4\n% $label\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF\n");
        return ['name' => "$label.pdf", 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
    }

    private function topicFields(): array
    {
        $f = [];
        foreach (TopicService::FIELDS as $k) {
            $f[$k] = "ข้อมูล $k";
        }
        $f['title_th'] = 'ระบบตรวจนับผลผลิตมะเขือเทศด้วยภาพ';
        $f['title_en'] = 'Image-based Tomato Yield Counting';
        return $f;
    }

    private function rateAll(int $rating): array
    {
        $out = [];
        foreach (AcademicService::rubric(1, true) as $c) {
            $out[(int) $c['id']] = $rating;
        }
        return $out;
    }

    private function comments(): array
    {
        return array_fill_keys(EvaluationService::COMMENT_TYPES, 'ความเห็นทางวิชาการ');
    }

    /** Steps 2-4: register, verify, assign advisor. Returns [groupId, projectId]. */
    private function registeredGroup(): array
    {
        $gid = GroupService::create(1, 201, 1, 'ทีมมะเขือเทศ');
        GroupService::invite($gid, '6510000002', 201);
        $memberId = (int) $this->db->value("SELECT id FROM project_members WHERE group_id = :g AND student_id = 2", ['g' => $gid]);
        GroupService::respond($memberId, 2, true, 202);
        GroupService::submit($gid, 201);
        GroupService::verify($gid, self::ADMIN);
        GroupService::requestAdvisor($gid, self::ADVISOR, 201);
        GroupService::assignAdvisor($gid, self::ADVISOR, self::ADMIN);
        $pid = (int) $this->db->value('SELECT id FROM projects WHERE group_id = :g', ['g' => $gid]);
        return [$gid, $pid];
    }

    /** Steps 5-7: topic submitted, advisor approves, admin final approval. */
    private function approvedTopic(int $projectId): void
    {
        TopicService::saveDraft($projectId, $this->topicFields(), 201);
        TopicService::submit($projectId, 201);
        TopicService::advisorDecide($projectId, self::ADVISOR, 'approve', null);
        TopicService::adminDecide($projectId, self::ADMIN, 'approve', 'อนุมัติ');
    }

    private function evaluation(int $assignmentId, int $slot): int
    {
        return (int) $this->db->value('SELECT id FROM committee_evaluations WHERE assignment_id = :a AND member_slot = :s', ['a' => $assignmentId, 's' => $slot]);
    }

    private function proposalStatus(int $projectId): string
    {
        return (string) $this->db->value('SELECT status FROM proposal_submissions WHERE project_id = :p', ['p' => $projectId]);
    }

    public function testFullScenarioWithRevisionAndReEvaluation(): void
    {
        // 1. Administrator creates an academic year and course offering.
        $yearId = AcademicService::saveYear(2570, '2027-06-01', '2028-05-31', false, self::ADMIN);
        $semId = AcademicService::saveSemester($yearId, 1, '2027-06-21', '2027-10-31', self::ADMIN);
        $newOffering = AcademicService::createOffering(1, $semId, self::ADMIN, self::ADMIN);
        $this->assertSame(6, count(AcademicService::rubric($newOffering)));

        // 2-4. Group registered, verified, advisor assigned -> active.
        [$gid, $pid] = $this->registeredGroup();
        $this->assertSame('active', $this->db->value('SELECT status FROM project_groups WHERE id = :g', ['g' => $gid]));
        $this->assertSame(2, count(GroupService::members($gid, false)));

        // 5-7. Topic: advisor then administrator.
        TopicService::saveDraft($pid, $this->topicFields(), 201);
        TopicService::submit($pid, 201);
        $this->assertSame('submitted', TopicService::current($pid)['status']);
        // Proposal is impossible before official topic approval.
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::submit($pid, $this->pdf(), null, 'x', ProposalService::SECTIONS, null, 201));
        TopicService::advisorDecide($pid, self::ADVISOR, 'approve', null);
        $this->assertSame('admin_review', TopicService::current($pid)['status']);
        TopicService::adminDecide($pid, self::ADMIN, 'approve', 'อนุมัติหัวข้อ');
        $this->assertSame('topic_approved', TopicService::current($pid)['status']);
        $this->assertSame('ระบบตรวจนับผลผลิตมะเขือเทศด้วยภาพ', $this->db->value('SELECT title_th FROM projects WHERE id = :p', ['p' => $pid]));
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM topic_approvals'));

        // 8-9. Proposal v1 and advisor readiness.
        $v1 = ProposalService::submit($pid, $this->pdf('v1'), null, 'Proposal v1', ProposalService::SECTIONS, null, 201);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $this->assertSame('committee_assignment', $this->proposalStatus($pid));
        $proposalId = (int) ProposalService::forProject($pid)['id'];

        // 10. Two committee members (the advisor cannot be one of them).
        $deadline = date('Y-m-d H:i', strtotime('+14 days'));
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::assignCommittee($proposalId, self::ADVISOR, self::C1, $deadline, self::ADMIN));
        $a1 = ProposalService::assignCommittee($proposalId, self::C1, self::C2, $deadline, self::ADMIN);
        $this->assertSame('committee_evaluation', $this->proposalStatus($pid));

        // 11-12. Independent evaluation.
        $e1 = $this->evaluation($a1, 1);
        $e2 = $this->evaluation($a1, 2);
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::ownEvaluation($e2, self::C1));   // cannot open the other's form
        EvaluationService::save($e1, self::C1, $this->rateAll(4), 'approved', $this->comments(), [], true);
        $this->assertTrue(EvaluationService::summary($a1) === null, 'no summary before both submit');
        $this->assertFalse(EvaluationService::peerVisible($a1));
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::save($e1, self::C1, $this->rateAll(5), 'approved', $this->comments(), [], true), 'locked');
        EvaluationService::save($e2, self::C2, $this->rateAll(3), 'major_revisions', $this->comments(), ['weaknesses'], true);

        // 13-14. Scores, average and disagreement flag.
        $assignment = $this->db->fetch('SELECT * FROM committee_assignments WHERE id = :a', ['a' => $a1]);
        $this->assertSame('80.00', (string) $this->db->value('SELECT total_score FROM committee_evaluations WHERE id = :e', ['e' => $e1]));
        $this->assertSame('60.00', (string) $this->db->value('SELECT total_score FROM committee_evaluations WHERE id = :e', ['e' => $e2]));
        $this->assertSame('70.00', (string) $assignment['average_score']);
        $this->assertSame(1, (int) $assignment['recommendations_differ']);
        $this->assertSame('evaluation_completed', $this->proposalStatus($pid));
        $this->assertSame(1, (int) $this->db->value('SELECT needs_admin_attention FROM proposal_submissions WHERE id = :p', ['p' => $proposalId]));
        $this->assertTrue(EvaluationService::summary($a1) !== null);
        $this->assertTrue(AcademicService::rubricLocked(1));

        // 15-16. Final decision: differs from a recommendation -> justification required.
        $this->assertThrows(RuleViolation::class, fn () => DecisionService::decide($proposalId, self::ADMIN, 'major_revisions', 'แก้ไขบทที่ 3', null, ''), 'justification');
        DecisionService::decide($proposalId, self::ADMIN, 'major_revisions', 'แก้ไขบทที่ 3', 'วิธีดำเนินงานยังไม่ชัด', 'เห็นด้วยกับกรรมการคนที่ 2 เรื่องวิธีดำเนินงาน');
        $this->assertSame('revision_required', $this->proposalStatus($pid));
        $this->assertTrue(ProposalService::openRevision($proposalId) !== null);

        // 17. Revised proposal + response document -> new version; advisor accepts; admin selects mode B.
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::submit($pid, $this->pdf('v2'), null, 'Proposal v2', ProposalService::SECTIONS, 'แก้ไขแล้ว', 201), 'response');
        $v2 = ProposalService::submit($pid, $this->pdf('v2'), $this->pdf('response'), 'Proposal v2', ProposalService::SECTIONS, 'แก้ไขบทที่ 3 แล้ว', 201);
        $this->assertTrue($v2 !== $v1);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $this->assertSame('advisor_approved', $this->proposalStatus($pid));    // waits for the admin's explicit mode choice
        ProposalService::selectRevisionMode($proposalId, 'B', self::ADMIN, null, null, $deadline);
        $this->assertSame('committee_evaluation', $this->proposalStatus($pid));
        $a2 = (int) $this->db->value('SELECT id FROM committee_assignments WHERE proposal_id = :p AND round_no = 2', ['p' => $proposalId]);
        $this->assertSame($v2, (int) $this->db->value('SELECT proposal_version_id FROM committee_assignments WHERE id = :a', ['a' => $a2]));
        EvaluationService::save($this->evaluation($a2, 1), self::C1, $this->rateAll(5), 'approved', $this->comments(), [], true);
        EvaluationService::save($this->evaluation($a2, 2), self::C2, $this->rateAll(4), 'approved', $this->comments(), [], true);

        // 18. All versions, evaluations and decisions are kept.
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM proposal_versions WHERE proposal_id = :p', ['p' => $proposalId]));
        $this->assertSame(4, (int) $this->db->value('SELECT COUNT(*) FROM committee_evaluations'));
        $this->assertSame('superseded', $this->db->value('SELECT version_status FROM proposal_versions WHERE id = :v', ['v' => $v1]));

        // 19. Final approval (agrees with both -> no justification needed).
        DecisionService::decide($proposalId, self::ADMIN, 'approved', 'อนุมัติ', 'แก้ไขครบถ้วน', null);
        $this->assertSame('approved', $this->proposalStatus($pid));
        $this->assertSame('approved', $this->db->value('SELECT stage FROM projects WHERE id = :p', ['p' => $pid]));

        // 20. Approval summary.
        $summary = DecisionService::approvalSummary($pid);
        $this->assertSame('approved', $summary['final']['decision']);
        $this->assertSame(2, count($summary['members']));
        $this->assertSame('90.00', (string) $summary['committee']['assignment']['average_score']);
        $this->assertSame(2, (int) $this->db->value("SELECT COUNT(*) FROM approval_decisions WHERE decision_level = 'final_decision'"));
        $this->assertSame(2, (int) $this->db->value("SELECT COUNT(*) FROM approval_decisions WHERE decision = 'ready_for_committee'"));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM approval_decisions WHERE differs_from_committee = 1"));
        $this->assertTrue((int) $this->db->value('SELECT COUNT(*) FROM notifications') > 10);
    }

    public function testTopicRevisionCreatesNewVersionAndRejectionAllowsNewTopic(): void
    {
        [, $pid] = $this->registeredGroup();
        TopicService::saveDraft($pid, $this->topicFields(), 201);
        TopicService::submit($pid, 201);
        $this->assertThrows(TransitionDenied::class, fn () => TopicService::advisorDecide($pid, self::ADVISOR, 'revise', ''));   // comments required
        TopicService::advisorDecide($pid, self::ADVISOR, 'revise', 'ขอบเขตกว้างเกินไป');
        $topic = TopicService::current($pid);
        $this->assertSame('revision_required', $topic['status']);
        $draft = TopicService::draftFor($pid, 201);
        $this->assertSame(2, (int) $draft['version_no']);
        $this->assertSame('ระบบตรวจนับผลผลิตมะเขือเทศด้วยภาพ', $draft['title_th']);   // copied from v1
        TopicService::submit($pid, 201);
        TopicService::advisorDecide($pid, self::ADVISOR, 'approve', null);
        TopicService::adminDecide($pid, self::ADMIN, 'reject', 'ซ้ำกับโครงงานปีก่อน');
        $this->assertTrue(TopicService::current($pid) === null);
        $this->assertSame(1, count(TopicService::rejectedTopics($pid)));
        $new = TopicService::draftFor($pid, 201);    // OQ-09: a new topic may be proposed
        $this->assertSame(1, (int) $new['version_no']);
    }

    public function testStudentCannotEditTopicUnderReview(): void
    {
        [, $pid] = $this->registeredGroup();
        TopicService::saveDraft($pid, $this->topicFields(), 201);
        TopicService::submit($pid, 201);
        $this->assertThrows(RuleViolation::class, fn () => TopicService::saveDraft($pid, $this->topicFields(), 201), 'review');
    }

    public function testTopicNeedsActiveGroupWithAdvisorAndCompleteFields(): void
    {
        $gid = GroupService::create(1, 201, 1, 'ทีม');
        GroupService::invite($gid, '6510000002', 201);
        GroupService::respond((int) $this->db->value('SELECT id FROM project_members WHERE group_id = :g AND student_id = 2', ['g' => $gid]), 2, true, 202);
        GroupService::submit($gid, 201);
        GroupService::verify($gid, self::ADMIN);
        $pid = (int) $this->db->value('SELECT id FROM projects WHERE group_id = :g', ['g' => $gid]);
        TopicService::saveDraft($pid, $this->topicFields(), 201);
        $this->assertThrows(RuleViolation::class, fn () => TopicService::submit($pid, 201), 'active');   // verified but no advisor yet
        GroupService::assignAdvisor($gid, self::ADVISOR, self::ADMIN);
        TopicService::saveDraft($pid, ['title_th' => 'ไม่ครบ'] + array_fill_keys(TopicService::FIELDS, ''), 201);
        $this->assertThrows(RuleViolation::class, fn () => TopicService::submit($pid, 201), 'required');
    }

    public function testGroupRules(): void
    {
        $gid = GroupService::create(1, 201, 1, 'ทีม A');
        $this->assertThrows(RuleViolation::class, fn () => GroupService::create(1, 201, 1, 'ทีมซ้ำ'));          // already in a group
        $this->assertThrows(RuleViolation::class, fn () => GroupService::submit($gid, 201));                  // fewer than min members
        GroupService::invite($gid, '6510000002', 201);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::submit($gid, 201));                  // pending invitation
        $this->assertThrows(RuleViolation::class, fn () => GroupService::invite($gid, '9999999999', 201));    // unknown student
        GroupService::invite($gid, '6510000003', 201);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::invite($gid, '6510000004', 201));    // max 3 incl. leader
        $g2 = GroupService::create(5, 205, 1, 'ทีม B');
        GroupService::invite($g2, '6510000002', 205);
        $m = (int) $this->db->value('SELECT id FROM project_members WHERE group_id = :g AND student_id = 2', ['g' => $gid]);
        GroupService::respond($m, 2, true, 202);
        // Accepting one invitation lapses the other.
        $this->assertSame('declined', $this->db->value('SELECT status FROM project_members WHERE group_id = :g AND student_id = 2', ['g' => $g2]));
        GroupService::removeDraftMember($gid, (int) $this->db->value('SELECT id FROM project_members WHERE group_id = :g AND student_id = 3', ['g' => $gid]), 201);
        GroupService::submit($gid, 201);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::invite($gid, '6510000003', 201));    // locked after submission
        GroupService::reject($gid, self::ADMIN, 'ชื่อกลุ่มไม่เหมาะสม');
        $this->assertSame('draft', $this->db->value('SELECT status FROM project_groups WHERE id = :g', ['g' => $gid]));
        GroupService::submit($gid, 201);
        GroupService::verify($gid, self::ADMIN);
        // After registration only the admin changes membership, with a reason, recorded in history.
        $this->assertThrows(RuleViolation::class, fn () => GroupService::adminAddMember($gid, '6510000006', self::ADMIN, ''));
        GroupService::adminAddMember($gid, '6510000006', self::ADMIN, 'ย้ายกลุ่มตามคำร้อง');
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM group_member_histories WHERE group_id = :g AND action = 'added' AND approved_by = 1", ['g' => $gid]));
        // Changing an assigned advisor requires a reason (OQ-04).
        GroupService::assignAdvisor($gid, self::ADVISOR, self::ADMIN);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::assignAdvisor($gid, 102, self::ADMIN, ''));
        GroupService::assignAdvisor($gid, 102, self::ADMIN, 'อาจารย์ลาศึกษาต่อ');
        $this->assertSame(102, GroupService::advisorAssigned($gid));
    }

    public function testRevisionModeAAndMinorRevisions(): void
    {
        [, $pid] = $this->registeredGroup();
        $this->approvedTopic($pid);
        ProposalService::submit($pid, $this->pdf(), null, 'P', ProposalService::SECTIONS, null, 201);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $proposalId = (int) ProposalService::forProject($pid)['id'];
        $a = ProposalService::assignCommittee($proposalId, self::C1, self::C2, date('Y-m-d H:i', strtotime('+7 days')), self::ADMIN);
        EvaluationService::save($this->evaluation($a, 1), self::C1, $this->rateAll(4), 'approved_minor', $this->comments(), [], true);
        EvaluationService::save($this->evaluation($a, 2), self::C2, $this->rateAll(4), 'approved_minor', $this->comments(), [], true);
        DecisionService::decide($proposalId, self::ADMIN, 'approved_minor', 'แก้ไขเล็กน้อย', null, null);   // agrees: no justification
        $this->assertSame('approved_minor', $this->proposalStatus($pid));
        ProposalService::submit($pid, $this->pdf('v2'), $this->pdf('resp'), 'P v2', ProposalService::SECTIONS, 'แก้แล้ว', 201);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        ProposalService::selectRevisionMode($proposalId, 'A', self::ADMIN);
        $this->assertSame('final_review', $this->proposalStatus($pid));
        $this->assertSame('advisor_only', $this->db->value("SELECT revision_mode FROM revision_requests WHERE proposal_id = :p ORDER BY id DESC LIMIT 1", ['p' => $proposalId]));
        DecisionService::decide($proposalId, self::ADMIN, 'approved', 'อนุมัติ', null, null);
        $this->assertSame('approved', $this->proposalStatus($pid));
    }

    public function testAdvisorAndEvaluationGuards(): void
    {
        [, $pid] = $this->registeredGroup();
        $this->approvedTopic($pid);
        ProposalService::submit($pid, $this->pdf(), null, 'P', ProposalService::SECTIONS, null, 201);
        // Another advisor cannot review this proposal.
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::advisorDecide($pid, 102, 'ready', null), 'advisor');
        // Missing checklist sections and non-PDF files are refused.
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::submit($pid, $this->pdf(), null, 'P', ['title'], null, 201));
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $proposalId = (int) ProposalService::forProject($pid)['id'];
        $a = ProposalService::assignCommittee($proposalId, self::C1, self::C2, date('Y-m-d H:i', strtotime('+7 days')), self::ADMIN);
        $e1 = $this->evaluation($a, 1);
        // Incomplete evaluation cannot be submitted; a draft can be saved.
        $partial = array_slice($this->rateAll(4), 0, 3, true);
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::save($e1, self::C1, $partial, 'approved', $this->comments(), [], true), 'rated');
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::save($e1, self::C1, $this->rateAll(4), 'approved', ['strengths' => 'x'], [], true));
        EvaluationService::save($e1, self::C1, $partial, null, [], [], false);
        $this->assertSame('draft', $this->db->value('SELECT status FROM committee_evaluations WHERE id = :e', ['e' => $e1]));
        // Not ready for a final decision while evaluation is incomplete.
        $this->assertThrows(RuleViolation::class, fn () => DecisionService::decide($proposalId, self::ADMIN, 'approved', null, null, 'x'));
        // Reopen requires a reason, then the member can change and resubmit.
        EvaluationService::save($e1, self::C1, $this->rateAll(4), 'approved', $this->comments(), [], true);
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::reopen($e1, self::ADMIN, ''));
        EvaluationService::reopen($e1, self::ADMIN, 'กรอกคะแนนผิด');
        EvaluationService::save($e1, self::C1, $this->rateAll(5), 'approved', $this->comments(), [], false);   // draft save while reopened
        EvaluationService::save($e1, self::C1, $this->rateAll(5), 'approved', $this->comments(), [], true);
        $this->assertSame('100.00', (string) $this->db->value('SELECT total_score FROM committee_evaluations WHERE id = :e', ['e' => $e1]));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'evaluation.reopened'"));
    }

    public function testConflictedAdminCannotDecideWhenAnotherAdminExists(): void
    {
        [, $pid] = $this->registeredGroup();
        $this->approvedTopic($pid);
        ProposalService::submit($pid, $this->pdf(), null, 'P', ProposalService::SECTIONS, null, 201);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $proposalId = (int) ProposalService::forProject($pid)['id'];
        $a = ProposalService::assignCommittee($proposalId, self::C1, self::C2, date('Y-m-d H:i', strtotime('+7 days')), self::ADMIN);
        EvaluationService::save($this->evaluation($a, 1), self::C1, $this->rateAll(4), 'approved', $this->comments(), [], true);
        EvaluationService::save($this->evaluation($a, 2), self::C2, $this->rateAll(4), 'approved', $this->comments(), [], true);
        // committee1 (103) is also made an admin: a conflicted admin while user 1 is available.
        $this->db->query('INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (103, 1, 1)');
        $this->assertThrows(RuleViolation::class, fn () => DecisionService::decide($proposalId, self::C1, 'approved', null, null, null), 'administrator');
        DecisionService::decide($proposalId, self::ADMIN, 'approved', null, null, null);
        $this->assertSame('approved', $this->proposalStatus($pid));
    }

    public function testFileValidationRejectsFakePdf(): void
    {
        [, $pid] = $this->registeredGroup();
        $this->approvedTopic($pid);
        $tmp = tempnam(sys_get_temp_dir(), 'ppms');
        file_put_contents($tmp, '<?php echo "not a pdf";');
        $fake = ['name' => 'evil.pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::submit($pid, $fake, null, 'P', ProposalService::SECTIONS, null, 201));
        $this->assertSame('draft', $this->proposalStatus($pid));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM proposal_versions'));
    }
}
