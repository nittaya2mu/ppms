<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Workflow\TransitionDenied;
use App\Services\Workflow\Workflows;
use Tests\TestCase;

/** Section 12 state machines vs. transition tables in the Phase 1 doc (4.1, 5.1). */
final class WorkflowDefinitionTest extends TestCase
{
    public function testStateListsMatchSection12(): void
    {
        $this->assertSame(['draft', 'submitted', 'verified', 'active', 'inactive'], Workflows::group()->states);
        $this->assertSame(8, count(Workflows::topic()->states));
        $this->assertSame(13, count(Workflows::proposal()->states));
    }

    public function testTopicHappyPathRequiresAdvisorThenAdmin(): void
    {
        $m = Workflows::topic();
        $s = $m->check('draft', 'submit', 'leader');
        $s = $m->check($s, 'start_review', 'advisor');
        $s = $m->check($s, 'advisor_approve', 'advisor');
        $this->assertSame('advisor_approved', $s);
        $s = $m->check($s, 'forward_admin', 'system');
        $this->assertSame('admin_review', $s);
        $this->assertSame('topic_approved', $m->check($s, 'admin_approve', 'admin'));
    }

    public function testAdvisorCannotGiveFinalTopicApproval(): void
    {
        $this->assertThrows(TransitionDenied::class, fn () => Workflows::topic()->check('admin_review', 'admin_approve', 'advisor'));
    }

    public function testAdminCannotSkipAdvisorReview(): void
    {
        $this->assertThrows(TransitionDenied::class, fn () => Workflows::topic()->check('submitted', 'admin_approve', 'admin'));
        $this->assertThrows(TransitionDenied::class, fn () => Workflows::topic()->check('advisor_review', 'admin_approve', 'admin'));
    }

    public function testRevisionAndRejectionRequireComments(): void
    {
        $m = Workflows::topic();
        foreach (['advisor_revise' => ['advisor_review', 'advisor'], 'advisor_reject' => ['advisor_review', 'advisor'], 'admin_revise' => ['admin_review', 'admin'], 'admin_reject' => ['admin_review', 'admin']] as $action => [$from, $role]) {
            $this->assertThrows(TransitionDenied::class, fn () => $m->check($from, $action, $role, '  '), 'requires a reason');
            $m->check($from, $action, $role, 'เหตุผล');
        }
    }

    public function testStudentCanOnlyEditInDraftOrRevisionRequired(): void
    {
        $m = Workflows::topic();
        foreach ($m->states as $state) {
            $leaderActions = $m->available($state, 'leader');
            $expected = in_array($state, ['draft', 'revision_required'], true);
            $this->assertSame($expected, $leaderActions !== [], "leader actions in $state");
        }
    }

    public function testProposalNeverApprovedWithoutFinalReview(): void
    {
        $m = Workflows::proposal();
        foreach ($m->all() as $action => $t) {
            if (in_array($t['to'], ['approved', 'approved_minor', 'rejected'], true)) {
                $this->assertSame(['final_review'], $t['from'], "$action must start from final_review");
                $this->assertSame(['admin'], $t['roles'], "$action must be admin-only");
            }
        }
    }

    public function testProposalRevisionModes(): void
    {
        $m = Workflows::proposal();
        $this->assertSame('final_review', $m->check('advisor_approved', 'mode_advisor_only', 'admin'));
        $this->assertSame('re_evaluation', $m->check('advisor_approved', 'mode_re_evaluation', 'admin'));
        $this->assertSame('committee_evaluation', $m->check('re_evaluation', 'start_re_evaluation', 'admin'));
        $this->assertSame('submitted', $m->check('approved_minor', 'resubmit', 'leader'));
    }

    public function testEvaluationCompletionIsSystemOnly(): void
    {
        $this->assertThrows(TransitionDenied::class, fn () => Workflows::proposal()->check('committee_evaluation', 'complete_evaluation', 'admin'));
        $this->assertThrows(TransitionDenied::class, fn () => Workflows::proposal()->check('committee_evaluation', 'complete_evaluation', 'committee'));
    }
}
