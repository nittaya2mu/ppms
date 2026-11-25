<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AcademicService;
use App\Services\DecisionService;
use App\Services\EvaluationService;
use App\Services\FileService;
use App\Services\GroupService;
use App\Services\OfferingService;
use App\Services\PresentationForm;
use App\Services\ProgressService;
use App\Services\ScopeService;
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
        return [
            'title_th' => 'ระบบตรวจนับผลผลิตมะเขือเทศด้วยภาพ',
            'title_en' => 'Image-based Tomato Yield Counting',
            'project_type' => 'ml_ai', 'project_type_other' => '', 'co_advisor_name' => '',
        ];
    }

    /** Chapter 1 sections 1.1 - 1.6 */
    private function chapter1(string $tag = ''): array
    {
        $out = [];
        foreach (ProposalService::CHAPTER1 as $k) {
            $out[$k] = "เนื้อหา $k $tag";
        }
        return $out;
    }

    /** Chapter 1-3 documents. */
    private function chapters(string $label): array
    {
        return [1 => $this->pdf("$label-ch1"), 2 => $this->pdf("$label-ch2"), 3 => $this->pdf("$label-ch3")];
    }

    private function submitProposal(int $pid, string $label = 'v1', ?array $response = null, ?string $notes = null): int
    {
        return ProposalService::submit($pid, $this->chapter1($label), $this->chapters($label), $response, $notes, 201);
    }

    /** Every form-2 item graded with the same letter. */
    private function gradesAll(string $letter): array
    {
        return array_fill_keys(array_keys(PresentationForm::itemMax()), $letter);
    }

    private function checks(string $value = '1'): array
    {
        return array_fill_keys(array_keys(PresentationForm::CHECKS), $value);
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

    private function evaluation(int $assignmentId, string $role): int
    {
        return (int) $this->db->value('SELECT id FROM presentation_evaluations WHERE assignment_id = :a AND evaluator_role = :r', ['a' => $assignmentId, 'r' => $role]);
    }

    /** Submits the form of one evaluator. */
    private function evaluate(int $assignmentId, string $role, int $userId, string $letter, string $result): int
    {
        $id = $this->evaluation($assignmentId, $role);
        EvaluationService::save($id, $userId, $this->gradesAll($letter), $this->checks(), $result, $result === 'pass' ? null : 'ควรปรับปรุงวิธีดำเนินงาน', true);
        return $id;
    }

    /** Topic and proposal approved by agreement of all three evaluators. Returns the assignment id. */
    private function approvedProposal(int $pid): int
    {
        $this->approvedTopic($pid);
        $this->submitProposal($pid);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $proposalId = (int) ProposalService::forProject($pid)['id'];
        $a = ProposalService::assignCommittee($proposalId, self::C1, self::C2, date('Y-m-d H:i', strtotime('+7 days')), self::ADMIN);
        $this->evaluate($a, 'advisor', self::ADVISOR, 'A', 'pass');
        $this->evaluate($a, 'committee1', self::C1, 'B+', 'pass');
        $this->evaluate($a, 'committee2', self::C2, 'B', 'pass');
        DecisionService::decide($proposalId, self::ADMIN, 'approved', 'อนุมัติ', null, null);
        return $a;
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
        AcademicService::createOffering(1, $semId, self::ADMIN, self::ADMIN);

        // 2-4. Group registered, verified, advisor assigned -> active.
        [$gid, $pid] = $this->registeredGroup();
        $this->assertSame('active', $this->db->value('SELECT status FROM project_groups WHERE id = :g', ['g' => $gid]));
        $this->assertSame(2, count(GroupService::members($gid, false)));

        // 5-7. Topic (form 1): advisor then administrator.
        TopicService::saveDraft($pid, $this->topicFields(), 201);
        TopicService::submit($pid, 201);
        $this->assertSame('submitted', TopicService::current($pid)['status']);
        $this->assertThrows(RuleViolation::class, fn () => $this->submitProposal($pid));   // not before official topic approval
        TopicService::advisorDecide($pid, self::ADVISOR, 'approve', null);
        $this->assertSame('admin_review', TopicService::current($pid)['status']);
        TopicService::adminDecide($pid, self::ADMIN, 'approve', 'อนุมัติหัวข้อ');
        $this->assertSame('topic_approved', TopicService::current($pid)['status']);
        $this->assertSame('ระบบตรวจนับผลผลิตมะเขือเทศด้วยภาพ', $this->db->value('SELECT title_th FROM projects WHERE id = :p', ['p' => $pid]));
        $this->assertSame('ml_ai', $this->db->value('SELECT project_type FROM topic_submissions ORDER BY id DESC LIMIT 1'));

        // 8-9. Proposal v1 (chapter 1 + chapter 1-3 files) and advisor readiness.
        $v1 = $this->submitProposal($pid);
        $row = $this->db->fetch('SELECT * FROM proposal_versions WHERE id = :v', ['v' => $v1]);
        $this->assertTrue($row['ch2_file_id'] !== null && $row['ch3_file_id'] !== null, 'chapter 2-3 files stored');
        $this->assertSame('เนื้อหา ch1_scope v1', $row['ch1_scope']);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $this->assertSame('committee_assignment', $this->proposalStatus($pid));
        $proposalId = (int) ProposalService::forProject($pid)['id'];

        // 10. Two committee members (the advisor cannot be one of them); the advisor evaluates too.
        $deadline = date('Y-m-d H:i', strtotime('+14 days'));
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::assignCommittee($proposalId, self::ADVISOR, self::C1, $deadline, self::ADMIN));
        $a1 = ProposalService::assignCommittee($proposalId, self::C1, self::C2, $deadline, self::ADMIN);
        $this->assertSame('committee_evaluation', $this->proposalStatus($pid));
        $this->assertSame(3, (int) $this->db->value('SELECT COUNT(*) FROM presentation_evaluations WHERE assignment_id = :a', ['a' => $a1]));

        // 11-12. Independent presentation evaluation (form 2).
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::ownEvaluation($this->evaluation($a1, 'committee2'), self::C1));
        $eAdv = $this->evaluate($a1, 'advisor', self::ADVISOR, 'B+', 'pass');      // 87.50
        $this->evaluate($a1, 'committee1', self::C1, 'B', 'pass');                 // 75.00
        $this->assertTrue(EvaluationService::summary($a1) === null, 'no summary before all three submit');
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::save($eAdv, self::ADVISOR, $this->gradesAll('A'), $this->checks(), 'pass', null, true), 'locked');
        $this->evaluate($a1, 'committee2', self::C2, 'C', 'revise');               // 50.00

        // 13-14. Scores, average and disagreement flag.
        $assignment = $this->db->fetch('SELECT * FROM committee_assignments WHERE id = :a', ['a' => $a1]);
        $this->assertSame('87.50', (string) $this->db->value('SELECT total_score FROM presentation_evaluations WHERE id = :e', ['e' => $eAdv]));
        $this->assertSame('A', (string) $this->db->value('SELECT letter_grade FROM presentation_evaluations WHERE id = :e', ['e' => $eAdv]));
        $this->assertSame('70.83', (string) $assignment['average_score']);
        $this->assertSame(1, (int) $assignment['recommendations_differ']);
        $this->assertSame('evaluation_completed', $this->proposalStatus($pid));
        $this->assertTrue(EvaluationService::summary($a1) !== null);

        // 15-16. Final decision differs from at least one result -> justification required.
        $this->assertThrows(RuleViolation::class, fn () => DecisionService::decide($proposalId, self::ADMIN, 'major_revisions', 'แก้ไขบทที่ 3', null, ''), 'justification');
        DecisionService::decide($proposalId, self::ADMIN, 'major_revisions', 'แก้ไขบทที่ 3', 'วิธีดำเนินงานยังไม่ชัด', 'เห็นด้วยกับกรรมการคนที่ 2');
        $this->assertSame('revision_required', $this->proposalStatus($pid));

        // 17. Revised proposal: chapter 2-3 files carried over when not re-uploaded; response document required.
        $this->assertThrows(RuleViolation::class, fn () => $this->submitProposal($pid, 'v2', null, 'แก้ไขแล้ว'), 'response');
        $v2 = ProposalService::submit($pid, $this->chapter1('v2'), [1 => $this->pdf('v2-ch1')], $this->pdf('response'), 'แก้ไขบทที่ 3 แล้ว', 201);
        $this->assertSame((int) $row['ch3_file_id'], (int) $this->db->value('SELECT ch3_file_id FROM proposal_versions WHERE id = :v', ['v' => $v2]));
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $this->assertSame('advisor_approved', $this->proposalStatus($pid));
        ProposalService::selectRevisionMode($proposalId, 'B', self::ADMIN, null, null, $deadline);
        $this->assertSame('committee_evaluation', $this->proposalStatus($pid));
        $a2 = (int) $this->db->value('SELECT id FROM committee_assignments WHERE proposal_id = :p AND round_no = 2', ['p' => $proposalId]);
        $this->evaluate($a2, 'advisor', self::ADVISOR, 'A', 'pass');
        $this->evaluate($a2, 'committee1', self::C1, 'A', 'pass');
        $this->evaluate($a2, 'committee2', self::C2, 'B+', 'pass');

        // 18. All versions, evaluations and decisions are kept.
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM proposal_versions WHERE proposal_id = :p', ['p' => $proposalId]));
        $this->assertSame(6, (int) $this->db->value('SELECT COUNT(*) FROM presentation_evaluations'));
        $this->assertSame('superseded', $this->db->value('SELECT version_status FROM proposal_versions WHERE id = :v', ['v' => $v1]));

        // 19. Final approval (agrees with all three -> no justification needed).
        DecisionService::decide($proposalId, self::ADMIN, 'approved', 'อนุมัติ', 'แก้ไขครบถ้วน', null);
        $this->assertSame('approved', $this->proposalStatus($pid));

        // 20. Approval summary.
        $summary = DecisionService::approvalSummary($pid);
        $this->assertSame('approved', $summary['final']['decision']);
        $this->assertSame('95.83', (string) $summary['committee']['assignment']['average_score']);
        $this->assertSame(3, count($summary['committee']['evaluations']));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM approval_decisions WHERE differs_from_committee = 1'));
    }

    public function testProgressReportsAndScopeApproval(): void
    {
        [$gid, $pid] = $this->registeredGroup();
        // Progress reports start only after the topic is approved.
        $this->assertThrows(RuleViolation::class, fn () => ProgressService::submit($pid, 201, date('Y-m-d'), 'งาน'));
        $this->approvedTopic($pid);
        $this->assertThrows(RuleViolation::class, fn () => ProgressService::submit($pid, 205, date('Y-m-d'), 'งาน'), 'member');   // not in the group
        $this->assertThrows(RuleViolation::class, fn () => ProgressService::submit($pid, 201, date('Y-m-d', strtotime('+5 days')), 'งาน'));   // future date
        $r1 = ProgressService::submit($pid, 201, date('Y-m-d', strtotime('-20 days')), 'ออกแบบฐานข้อมูล');
        $r2 = ProgressService::submit($pid, 202, date('Y-m-d', strtotime('-10 days')), 'พัฒนาหน้าจอ');
        ProgressService::update($r2, 202, date('Y-m-d', strtotime('-9 days')), 'พัฒนาหน้าจอหลัก');
        $this->assertThrows(RuleViolation::class, fn () => ProgressService::review($r1, 102, '25', null), 'advisor');   // not this group's advisor
        $this->assertThrows(RuleViolation::class, fn () => ProgressService::review($r1, self::ADVISOR, '12', null));   // not a rubric level
        ProgressService::review($r1, self::ADVISOR, '25', 'ทำต่อ');
        ProgressService::review($r2, self::ADVISOR, '20', null);
        $this->assertThrows(RuleViolation::class, fn () => ProgressService::update($r1, 201, date('Y-m-d'), 'แก้'), 'reviewed');
        $this->assertSame(2, ProgressService::reviewedCount($pid));

        // Scope (form 7) waits for proposal approval and the minimum number of reviewed reports.
        $this->assertSame('scope_after_proposal', ScopeService::blocker($pid));
        $this->submitProposal($pid);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $proposalId = (int) ProposalService::forProject($pid)['id'];
        $a = ProposalService::assignCommittee($proposalId, self::C1, self::C2, date('Y-m-d H:i', strtotime('+7 days')), self::ADMIN);
        $this->evaluate($a, 'advisor', self::ADVISOR, 'A', 'pass');
        $this->evaluate($a, 'committee1', self::C1, 'A', 'pass');
        $this->evaluate($a, 'committee2', self::C2, 'A', 'pass');
        DecisionService::decide($proposalId, self::ADMIN, 'approved', null, null, null);
        $this->assertSame('scope_needs_progress', ScopeService::blocker($pid));
        $r3 = ProgressService::submit($pid, 201, date('Y-m-d'), 'ทดสอบระบบ');
        ProgressService::review($r3, self::ADVISOR, '30', null);
        $this->assertTrue(ScopeService::blocker($pid) === null);

        $items = [['detail' => 'ระบบสมาชิก', 'weight' => '30'], ['detail' => 'ระบบนับผลผลิต', 'weight' => '50'], ['detail' => 'รายงาน', 'weight' => '20']];
        $this->assertThrows(RuleViolation::class, fn () => ScopeService::submit($pid, 201, [['detail' => 'ระบบ', 'weight' => '90']]), 'Weights');
        $this->assertThrows(RuleViolation::class, fn () => ScopeService::submit($pid, 202, $items), 'leader');
        $s1 = ScopeService::submit($pid, 201, $items);
        $this->assertSame('scope_pending', ScopeService::blocker($pid));
        $this->assertThrows(RuleViolation::class, fn () => ScopeService::decide($s1, 102, 'approved', null), 'approver');
        ScopeService::decide($s1, self::C1, 'approved', null);
        $this->assertThrows(RuleViolation::class, fn () => ScopeService::decide($s1, self::C1, 'approved', null));   // already decided
        $this->assertThrows(RuleViolation::class, fn () => ScopeService::decide($s1, self::ADVISOR, 'revision_required', ''));
        ScopeService::decide($s1, self::ADVISOR, 'revision_required', 'แยกหัวข้อรายงานให้ชัดเจน');
        $this->assertSame('revision_required', ScopeService::find($s1)['status']);

        // Resubmission: a new version that all three must approve.
        $items[2] = ['detail' => 'รายงานสรุปผลผลิตรายวัน', 'weight' => '20'];
        $s2 = ScopeService::submit($pid, 201, $items);
        $this->assertSame(2, (int) ScopeService::find($s2)['version_no']);
        ScopeService::decide($s2, self::ADVISOR, 'approved', null);
        ScopeService::decide($s2, self::C1, 'approved', null);
        $this->assertSame('pending', ScopeService::find($s2)['status']);
        ScopeService::decide($s2, self::C2, 'approved', 'เห็นชอบ');
        $this->assertSame('approved', ScopeService::find($s2)['status']);
        $this->assertSame('scope_already_approved', ScopeService::blocker($pid));
        $this->assertSame(3, count(ScopeService::find($s2)['items']));
    }

    public function testTopicRevisionCreatesNewVersionAndRejectionAllowsNewTopic(): void
    {
        [, $pid] = $this->registeredGroup();
        TopicService::saveDraft($pid, $this->topicFields(), 201);
        TopicService::submit($pid, 201);
        $this->assertThrows(TransitionDenied::class, fn () => TopicService::advisorDecide($pid, self::ADVISOR, 'revise', ''));   // comments required
        TopicService::advisorDecide($pid, self::ADVISOR, 'revise', 'ขอบเขตกว้างเกินไป');
        $this->assertSame('revision_required', TopicService::current($pid)['status']);
        $draft = TopicService::draftFor($pid, 201);
        $this->assertSame(2, (int) $draft['version_no']);
        $this->assertSame('ระบบตรวจนับผลผลิตมะเขือเทศด้วยภาพ', $draft['title_th']);   // copied from v1
        $this->assertSame('ml_ai', $draft['project_type']);
        TopicService::submit($pid, 201);
        TopicService::advisorDecide($pid, self::ADVISOR, 'approve', null);
        TopicService::adminDecide($pid, self::ADMIN, 'reject', 'ซ้ำกับโครงงานปีก่อน');
        $this->assertTrue(TopicService::current($pid) === null);
        $this->assertSame(1, count(TopicService::rejectedTopics($pid)));
        $this->assertSame(1, (int) TopicService::draftFor($pid, 201)['version_no']);    // a new topic may be proposed
    }

    public function testStudentCannotEditTopicUnderReview(): void
    {
        [, $pid] = $this->registeredGroup();
        TopicService::saveDraft($pid, $this->topicFields(), 201);
        TopicService::submit($pid, 201);
        $this->assertThrows(RuleViolation::class, fn () => TopicService::saveDraft($pid, $this->topicFields(), 201), 'review');
    }

    public function testTopicForm1Validation(): void
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
        $this->assertThrows(RuleViolation::class, fn () => TopicService::saveDraft($pid, ['project_type' => 'quantum'] + $this->topicFields(), 201));
        TopicService::saveDraft($pid, ['project_type' => 'other', 'project_type_other' => ''] + $this->topicFields(), 201);
        $this->assertThrows(RuleViolation::class, fn () => TopicService::submit($pid, 201), 'required');   // "other" needs a description
        TopicService::saveDraft($pid, ['project_type' => ''] + $this->topicFields(), 201);
        $this->assertThrows(RuleViolation::class, fn () => TopicService::submit($pid, 201), 'required');
        TopicService::saveDraft($pid, ['project_type' => 'other', 'project_type_other' => 'Robotics'] + $this->topicFields(), 201);
        TopicService::submit($pid, 201);
        $this->assertSame('submitted', TopicService::current($pid)['status']);
    }

    public function testGroupRules(): void
    {
        $gid = GroupService::create(1, 201, 1, 'ทีม A');
        $this->assertThrows(RuleViolation::class, fn () => GroupService::create(1, 201, 1, 'ทีมซ้ำ'));
        $this->assertThrows(RuleViolation::class, fn () => GroupService::submit($gid, 201));
        GroupService::invite($gid, '6510000002', 201);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::submit($gid, 201));
        $this->assertThrows(RuleViolation::class, fn () => GroupService::invite($gid, '9999999999', 201));
        GroupService::invite($gid, '6510000003', 201);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::invite($gid, '6510000004', 201));
        $g2 = GroupService::create(5, 205, 1, 'ทีม B');
        GroupService::invite($g2, '6510000002', 205);
        $m = (int) $this->db->value('SELECT id FROM project_members WHERE group_id = :g AND student_id = 2', ['g' => $gid]);
        GroupService::respond($m, 2, true, 202);
        $this->assertSame('declined', $this->db->value('SELECT status FROM project_members WHERE group_id = :g AND student_id = 2', ['g' => $g2]));
        GroupService::removeDraftMember($gid, (int) $this->db->value('SELECT id FROM project_members WHERE group_id = :g AND student_id = 3', ['g' => $gid]), 201);
        GroupService::submit($gid, 201);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::invite($gid, '6510000003', 201));
        GroupService::reject($gid, self::ADMIN, 'ชื่อกลุ่มไม่เหมาะสม');
        $this->assertSame('draft', $this->db->value('SELECT status FROM project_groups WHERE id = :g', ['g' => $gid]));
        GroupService::submit($gid, 201);
        GroupService::verify($gid, self::ADMIN);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::adminAddMember($gid, '6510000006', self::ADMIN, ''));
        GroupService::adminAddMember($gid, '6510000006', self::ADMIN, 'ย้ายกลุ่มตามคำร้อง');
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM group_member_histories WHERE group_id = :g AND action = 'added' AND approved_by = 1", ['g' => $gid]));
        GroupService::assignAdvisor($gid, self::ADVISOR, self::ADMIN);
        $this->assertThrows(RuleViolation::class, fn () => GroupService::assignAdvisor($gid, 102, self::ADMIN, ''));
        GroupService::assignAdvisor($gid, 102, self::ADMIN, 'อาจารย์ลาศึกษาต่อ');
        $this->assertSame(102, GroupService::advisorAssigned($gid));
    }

    public function testRevisionModeAAndMinorRevisions(): void
    {
        [, $pid] = $this->registeredGroup();
        $this->approvedTopic($pid);
        $this->submitProposal($pid);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $proposalId = (int) ProposalService::forProject($pid)['id'];
        $a = ProposalService::assignCommittee($proposalId, self::C1, self::C2, date('Y-m-d H:i', strtotime('+7 days')), self::ADMIN);
        $this->evaluate($a, 'advisor', self::ADVISOR, 'B', 'revise');
        $this->evaluate($a, 'committee1', self::C1, 'B', 'revise');
        $this->evaluate($a, 'committee2', self::C2, 'B', 'revise');
        DecisionService::decide($proposalId, self::ADMIN, 'approved_minor', 'แก้ไขเล็กน้อย', null, null);   // agrees: no justification
        $this->assertSame('approved_minor', $this->proposalStatus($pid));
        $this->submitProposal($pid, 'v2', $this->pdf('resp'), 'แก้แล้ว');
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        ProposalService::selectRevisionMode($proposalId, 'A', self::ADMIN);
        $this->assertSame('final_review', $this->proposalStatus($pid));
        DecisionService::decide($proposalId, self::ADMIN, 'approved', 'อนุมัติ', null, null);
        $this->assertSame('approved', $this->proposalStatus($pid));
    }

    public function testAdvisorAndEvaluationGuards(): void
    {
        [, $pid] = $this->registeredGroup();
        $this->approvedTopic($pid);
        // Chapter 1 sections and all three chapter files are required.
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::submit($pid, ['ch1_background' => 'x'], $this->chapters('p'), null, null, 201), 'chapter');
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::submit($pid, $this->chapter1(), [1 => $this->pdf('only1')], null, null, 201), 'Chapter');
        $this->submitProposal($pid);
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::advisorDecide($pid, 102, 'ready', null), 'advisor');
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $proposalId = (int) ProposalService::forProject($pid)['id'];
        $a = ProposalService::assignCommittee($proposalId, self::C1, self::C2, date('Y-m-d H:i', strtotime('+7 days')), self::ADMIN);
        $e1 = $this->evaluation($a, 'committee1');
        // Incomplete forms cannot be submitted; drafts can be saved.
        $partial = array_slice($this->gradesAll('A'), 0, 3, true);
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::save($e1, self::C1, $partial, $this->checks(), 'pass', null, true), 'required');
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::save($e1, self::C1, $this->gradesAll('A'), [], 'pass', null, true));
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::save($e1, self::C1, $this->gradesAll('C'), $this->checks(), 'revise', '', true), 'Suggestions');
        EvaluationService::save($e1, self::C1, $partial, [], null, null, false);
        $this->assertSame('draft', $this->db->value('SELECT status FROM presentation_evaluations WHERE id = :e', ['e' => $e1]));
        // The advisor's form belongs to the advisor only.
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::ownEvaluation($this->evaluation($a, 'advisor'), self::C1));
        $this->assertThrows(RuleViolation::class, fn () => DecisionService::decide($proposalId, self::ADMIN, 'approved', null, null, 'x'));
        // Reopen requires a reason, then the evaluator can change and resubmit.
        EvaluationService::save($e1, self::C1, $this->gradesAll('B'), $this->checks(), 'pass', null, true);
        $this->assertThrows(RuleViolation::class, fn () => EvaluationService::reopen($e1, self::ADMIN, ''));
        EvaluationService::reopen($e1, self::ADMIN, 'กรอกคะแนนผิด');
        EvaluationService::save($e1, self::C1, $this->gradesAll('A'), $this->checks(), 'pass', null, true);
        $this->assertSame('100.00', (string) $this->db->value('SELECT total_score FROM presentation_evaluations WHERE id = :e', ['e' => $e1]));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'evaluation.reopened'"));
    }

    public function testConflictedAdminCannotDecideWhenAnotherAdminExists(): void
    {
        [, $pid] = $this->registeredGroup();
        $this->approvedTopic($pid);
        $this->submitProposal($pid);
        ProposalService::advisorDecide($pid, self::ADVISOR, 'ready', null);
        $proposalId = (int) ProposalService::forProject($pid)['id'];
        $a = ProposalService::assignCommittee($proposalId, self::C1, self::C2, date('Y-m-d H:i', strtotime('+7 days')), self::ADMIN);
        $this->evaluate($a, 'advisor', self::ADVISOR, 'A', 'pass');
        $this->evaluate($a, 'committee1', self::C1, 'A', 'pass');
        $this->evaluate($a, 'committee2', self::C2, 'A', 'pass');
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
        $this->assertThrows(RuleViolation::class, fn () => ProposalService::submit($pid, $this->chapter1(), [1 => $fake, 2 => $this->pdf('a'), 3 => $this->pdf('b')], null, null, 201));
        $this->assertSame('draft', $this->proposalStatus($pid));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM proposal_versions'));
    }
}
