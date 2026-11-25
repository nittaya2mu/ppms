<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccessPolicy;
use App\Services\EvaluationService;
use App\Services\ProgressService;
use App\Services\ScopeService;
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
    private function studentProject(string $suffix = ''): ?Response
    {
        if ($this->role() !== 'student') {
            return null;
        }
        $sid = AccessPolicy::studentId() ?? throw HttpException::forbidden();
        $group = GroupService::forStudent($sid, (int) $this->offering()['id']);
        if ($group === null || !$group['project_id']) {
            return $this->redirect('/groups');
        }
        return $this->redirect('/projects/' . (int) $group['project_id'] . $suffix);
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
        $chapters = \App\Services\ProposalDraftService::pendingForAdvisor((int) $this->offering()['id'], $this->userId());
        foreach ($chapters as &$c) {
            $c['title_th'] = $c['chapter_label'] . ' · ' . ($c['title_th'] ?? '');
        }
        unset($c);
        return $this->view('queue/list', [
            'titleKey' => 'menu.advisor_review', 'kind' => 'proposal', 'rows' => $rows, 'anchor' => '#proposal', 'help' => __('queue.advisor_review_help'),
            'secondary' => $chapters, 'secondaryTitle' => __('chapters.queue_title'), 'secondaryAnchor' => '#chapters',
        ]);
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
        if (in_array($this->role(), ['committee', 'advisor'], true)) {
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

    /** แบบฟอร์ม 8: progress reports (students go to their own page). */
    public function progress(Request $r): Response
    {
        if ($redirect = $this->studentProject('/progress')) {
            return $redirect;
        }
        $this->allow(['admin', 'advisor']);
        return $this->view('queue/progress', ['rows' => ProgressService::queue((int) $this->offering()['id'], $this->role(), $this->userId()), 'minReports' => ProgressService::minReports()]);
    }

    /** แบบฟอร์ม 7: scope approvals. */
    public function scope(Request $r): Response
    {
        if ($redirect = $this->studentProject('/scope')) {
            return $redirect;
        }
        $this->allow(['admin', 'advisor', 'committee']);
        return $this->view('queue/scope', ['rows' => ScopeService::queue((int) $this->offering()['id'], $this->role(), $this->userId())]);
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
