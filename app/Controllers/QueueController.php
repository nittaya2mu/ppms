<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccessPolicy;
use App\Services\EvaluationService;
use App\Services\GroupService;
use App\Services\ProposalService;
use App\Services\TopicService;

/**
 * Work lists behind the main menu (Section 16): each lists the projects that need the
 * current role's attention and links to the project page where the action happens.
 */
final class QueueController extends Controller
{
    /** Students go straight to their own project page. */
    private function studentProject(): ?Response
    {
        if ($this->role() !== 'student') {
            return null;
        }
        $sid = AccessPolicy::studentId() ?? throw HttpException::forbidden();
        $group = GroupService::forStudent($sid, (int) $this->offering()['id']);
        if ($group === null || !$group['project_id']) {
            return $this->redirect('/groups');
        }
        return $this->redirect('/projects/' . (int) $group['project_id']);
    }

    public function topics(Request $r): Response
    {
        if ($resp = $this->studentProject()) {
            return $resp;
        }
        $this->allow(['admin', 'advisor']);
        $rows = TopicService::queue((int) $this->offering()['id'], $this->role(), $this->userId(), 'all');
        return $this->list('menu.topics', 'topic', $rows, '#topic');
    }

    public function topicApproval(Request $r): Response
    {
        $this->allow(['admin', 'advisor']);
        $rows = TopicService::queue((int) $this->offering()['id'], $this->role(), $this->userId(), 'pending');
        return $this->list('menu.topic_approval', 'topic', $rows, '#topic', __('queue.topic_approval_help_' . $this->role()));
    }

    public function proposals(Request $r): Response
    {
        if ($resp = $this->studentProject()) {
            return $resp;
        }
        $this->allow(['admin', 'advisor']);
        $rows = ProposalService::queue((int) $this->offering()['id'], $this->role(), $this->userId(), 'all');
        return $this->list('menu.proposals', 'proposal', $rows, '#proposal');
    }

    public function advisorReview(Request $r): Response
    {
        $this->allow(['advisor']);
        $rows = ProposalService::queue((int) $this->offering()['id'], 'advisor', $this->userId(), 'pending_advisor');
        return $this->list('menu.advisor_review', 'proposal', $rows, '#proposal', __('queue.advisor_review_help'));
    }

    public function committeeAssignment(Request $r): Response
    {
        $this->allow(['admin']);
        $oid = (int) $this->offering()['id'];
        $rows = array_merge(
            ProposalService::queue($oid, 'admin', $this->userId(), 'awaiting_assignment'),
            ProposalService::queue($oid, 'admin', $this->userId(), 'awaiting_mode')
        );
        return $this->list('menu.committee_assignment', 'proposal', $rows, '#proposal', __('queue.committee_help'));
    }

    public function evaluations(Request $r): Response
    {
        if ($this->role() === 'committee') {
            return $this->view('evaluations/index', ['rows' => EvaluationService::mine($this->userId())]);
        }
        $this->allow(['admin']);
        $rows = ProposalService::queue((int) $this->offering()['id'], 'admin', $this->userId(), 'evaluation');
        return $this->list('menu.evaluation', 'proposal', $rows, '#proposal', __('queue.evaluation_help'));
    }

    public function finalApproval(Request $r): Response
    {
        $this->allow(['admin']);
        $oid = (int) $this->offering()['id'];
        $rows = ProposalService::queue($oid, 'admin', $this->userId(), 'final');
        $revisions = ProposalService::queue($oid, 'admin', $this->userId(), 'revision');
        return $this->view('queue/list', [
            'titleKey' => 'menu.final_approval', 'kind' => 'proposal', 'rows' => $rows, 'anchor' => '#proposal',
            'help' => __('queue.final_help'), 'secondary' => $revisions, 'secondaryTitle' => __('queue.waiting_revision'),
        ]);
    }

    private function list(string $titleKey, string $kind, array $rows, string $anchor, ?string $help = null): Response
    {
        return $this->view('queue/list', compact('titleKey', 'kind', 'rows', 'anchor', 'help') + ['secondary' => null, 'secondaryTitle' => null]);
    }

    private function allow(array $roles): void
    {
        if (!in_array($this->role(), $roles, true)) {
            throw HttpException::forbidden();
        }
    }
}
