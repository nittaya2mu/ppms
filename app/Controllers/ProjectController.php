<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AcademicService;
use App\Services\AccessPolicy;
use App\Services\DecisionService;
use App\Services\EvaluationService;
use App\Services\FileService;
use App\Services\FormService;
use App\Services\GroupService;
use App\Services\ProposalService;
use App\Services\Rules\RuleViolation;
use App\Services\SettingsService;
use App\Services\TopicService;

/** Project page: topic and proposal workflow with role-specific actions. */
final class ProjectController extends Controller
{
    public function show(Request $request): Response
    {
        $pid = $this->id($request);
        $groupId = AccessPolicy::groupOfProject($pid);
        AccessPolicy::authorizeGroup($groupId);
        $role = $this->role();
        $uid = $this->userId();
        $group = GroupService::find($groupId);

        $topic = TopicService::current($pid);
        $topicView = null;
        if ($topic !== null) {
            $sub = $topic['current_submission_id'] ? TopicService::submission((int) $topic['current_submission_id']) : null;
            $topicView = [
                'topic' => $topic, 'submission' => $sub,
                'versions' => TopicService::versions((int) $topic['id']),
                'decisions' => TopicService::decisions((int) $topic['id']),
                'documents' => $sub ? FileService::forOwner('topic_submission', (int) $sub['id']) : [],
            ];
        }

        $proposal = ProposalService::forProject($pid);
        $proposalView = null;
        if ($proposal !== null) {
            $assignments = ProposalService::assignments((int) $proposal['id']);
            $summaries = [];
            $progress = [];
            $released = $this->releasedAssignments((int) $proposal['id']);
            foreach ($assignments as $a) {
                $aid = (int) $a['id'];
                $visible = match ($role) {
                    'admin', 'advisor' => $a['status'] === 'completed',
                    'student'          => in_array($aid, $released, true),
                    'committee'        => EvaluationService::peerVisible($aid) && AccessPolicy::isCommittee((int) $proposal['id']),
                    default            => false,
                };
                if ($visible) {
                    $summaries[$aid] = EvaluationService::summary($aid);
                } elseif (in_array($role, ['admin', 'advisor'], true)) {
                    $progress[$aid] = EvaluationService::progress($aid);   // status only, never scores
                }
            }
            $proposalView = [
                'proposal' => $proposal,
                'versions' => ProposalService::versions((int) $proposal['id']),
                'latest' => ProposalService::latestVersion((int) $proposal['id']),
                'decisions' => ProposalService::decisions((int) $proposal['id']),
                'assignments' => $assignments,
                'summaries' => $summaries,
                'progress' => $progress,
                'revision' => ProposalService::openRevision((int) $proposal['id']),
                'committeeView' => DecisionService::committeeView((int) $proposal['id']),
            ];
        }

        return $this->view('projects/show', [
            'project' => Database::connection()->fetch('SELECT * FROM projects WHERE id = :p', ['p' => $pid]),
            'group' => $group,
            'members' => GroupService::members($groupId, false),
            'advisor' => GroupService::advisor($groupId),
            'topicView' => $topicView,
            'rejectedTopics' => TopicService::rejectedTopics($pid),
            'proposalView' => $proposalView,
            'isLeader' => $role === 'student' && AccessPolicy::isLeader($groupId),
            'isAdvisor' => $role === 'advisor' && AccessPolicy::isAdvisor($groupId),
            'committeeChoices' => $role === 'admin' ? ProposalService::committeeChoices() : [],
            'chapterMaxMb' => FileService::maxMb('upload.chapter_max_mb'),
            'topicMaxMb' => FileService::maxMb('upload.topic_max_mb'),
            'justifyWhen' => (string) SettingsService::get('decision.justify_when', 'any'),
            'scopeStatus' => \App\Services\ScopeService::current($pid)['status'] ?? null,
        ]);
    }

    /** Assignments whose results were released to students together with a final decision. */
    private function releasedAssignments(int $proposalId): array
    {
        $released = Database::connection()->value('SELECT released_at FROM proposal_submissions WHERE id = :p', ['p' => $proposalId]);
        if ($released === null) {
            return [];
        }
        return array_map('intval', array_column(Database::connection()->fetchAll(
            "SELECT DISTINCT assignment_id FROM approval_decisions WHERE proposal_id = :p AND decision_level = 'final_decision' AND assignment_id IS NOT NULL",
            ['p' => $proposalId]
        ), 'assignment_id'));
    }

    // ----- Topic --------------------------------------------------------------

    public function saveTopic(Request $r): Response
    {
        $pid = $this->leaderProject($r);
        $submit = $r->input('do') === 'submit';
        return $this->attempt($r, "/projects/$pid", function () use ($pid, $r, $submit) {
            TopicService::saveDraft($pid, $r->all(), $this->userId());
            if ($submit) {
                TopicService::submit($pid, $this->userId());
            }
        }, $submit ? __('topics.submitted') : __('common.saved'));
    }

    public function uploadTopicDocument(Request $r): Response
    {
        $pid = $this->leaderProject($r);
        return $this->attempt($r, "/projects/$pid", function () use ($pid, $r) {
            $file = $r->file('document') ?? throw new RuleViolation('file_upload_failed', 'No file');
            TopicService::attachDocument($pid, $file, $this->userId());
        }, __('topics.document_uploaded'));
    }

    public function topicAdvisorDecision(Request $r): Response
    {
        $pid = $this->id($r);
        if (!AccessPolicy::isAdvisor(AccessPolicy::groupOfProject($pid))) {
            throw HttpException::forbidden();
        }
        return $this->attempt($r, "/projects/$pid", fn () => TopicService::advisorDecide($pid, $this->userId(), (string) $r->input('decision'), (string) $r->input('comments')), __('common.decision_saved'));
    }

    public function topicAdminDecision(Request $r): Response
    {
        $pid = $this->id($r);
        return $this->attempt($r, "/projects/$pid", fn () => TopicService::adminDecide($pid, $this->userId(), (string) $r->input('decision'), (string) $r->input('comments')), __('common.decision_saved'));
    }

    // ----- Proposal -----------------------------------------------------------

    public function submitProposal(Request $r): Response
    {
        $pid = $this->leaderProject($r);
        return $this->attempt($r, "/projects/$pid", function () use ($pid, $r) {
            $files = [1 => $r->file('chapter1'), 2 => $r->file('chapter2'), 3 => $r->file('chapter3')];
            ProposalService::submit($pid, $r->all(), $files, $r->file('response_doc'), (string) $r->input('revision_notes'), $this->userId());
        }, __('proposals.submitted'));
    }

    public function proposalAdvisorDecision(Request $r): Response
    {
        $pid = $this->id($r);
        if (!AccessPolicy::isAdvisor(AccessPolicy::groupOfProject($pid))) {
            throw HttpException::forbidden();
        }
        return $this->attempt($r, "/projects/$pid", fn () => ProposalService::advisorDecide($pid, $this->userId(), (string) $r->input('decision'), (string) $r->input('comments')), __('common.decision_saved'));
    }

    public function assignCommittee(Request $r): Response
    {
        $pid = $this->id($r);
        $proposal = ProposalService::forProject($pid) ?? throw HttpException::notFound();
        return $this->attempt($r, "/projects/$pid", fn () => ProposalService::assignCommittee((int) $proposal['id'], (int) $r->input('member1_id'), (int) $r->input('member2_id'), (string) $r->input('deadline'), $this->userId()), __('proposals.committee_assigned'));
    }

    public function revisionMode(Request $r): Response
    {
        $pid = $this->id($r);
        $proposal = ProposalService::forProject($pid) ?? throw HttpException::notFound();
        return $this->attempt($r, "/projects/$pid", fn () => ProposalService::selectRevisionMode(
            (int) $proposal['id'], (string) $r->input('mode'), $this->userId(),
            $r->input('member1_id') ? (int) $r->input('member1_id') : null,
            $r->input('member2_id') ? (int) $r->input('member2_id') : null,
            (string) $r->input('deadline')
        ), __('proposals.mode_selected'));
    }

    public function replaceMember(Request $r): Response
    {
        $pid = $this->id($r);
        return $this->attempt($r, "/projects/$pid", fn () => ProposalService::replaceCommitteeMember(
            (int) $r->input('assignment_id'), (int) $r->input('slot'), (int) $r->input('member_id'), $this->userId(), (string) $r->input('reason')
        ), __('common.saved'));
    }

    public function finalDecision(Request $r): Response
    {
        $pid = $this->id($r);
        $proposal = ProposalService::forProject($pid) ?? throw HttpException::notFound();
        return $this->attempt($r, "/projects/$pid", fn () => DecisionService::decide(
            (int) $proposal['id'], $this->userId(), (string) $r->input('decision'),
            (string) $r->input('comments'), (string) $r->input('reasons'), (string) $r->input('justification'), (string) $r->input('revision_due') ?: null
        ), __('common.decision_saved'));
    }

    public function reopenEvaluation(Request $r): Response
    {
        $pid = $this->id($r);
        return $this->attempt($r, "/projects/$pid", fn () => EvaluationService::reopen((int) $r->input('evaluation_id'), $this->userId(), (string) $r->input('reason')), __('evaluations.reopened'));
    }

    public function summary(Request $r): Response
    {
        $pid = $this->id($r);
        AccessPolicy::authorizeGroup(AccessPolicy::groupOfProject($pid));
        $data = DecisionService::approvalSummary($pid) ?? throw HttpException::notFound();
        if ($data['final'] === null) {
            throw new HttpException(409);
        }
        $data['showNames'] = $this->role() !== 'student' || \App\Services\SettingsService::bool('evaluation.show_committee_names');
        return $this->view('projects/summary', $data);
    }

    /** แบบฟอร์ม 1 (printable). */
    public function topicPrint(Request $r): Response
    {
        $pid = $this->id($r);
        AccessPolicy::authorizeGroup(AccessPolicy::groupOfProject($pid));
        $ctx = FormService::context($pid);
        if ($ctx['topic'] === null) {
            throw HttpException::notFound();
        }
        $approvals = [];
        $adminUser = null;
        foreach (Database::connection()->fetchAll(
            "SELECT a.level, a.decided_at, a.decided_by FROM topic_approvals a WHERE a.submission_id = :s AND a.decision = 'approved' ORDER BY a.id",
            ['s' => (int) $ctx['topic']['id']]
        ) as $a) {
            $approvals[$a['level']] = $a['decided_at'];
            if ($a['level'] === 'admin') {
                $adminUser = Database::connection()->fetch('SELECT title_th, first_name_th, last_name_th FROM users WHERE id = :u', ['u' => (int) $a['decided_by']]);
            }
        }
        return $this->view('forms/form1', $ctx + ['approvals' => $approvals, 'adminUser' => $adminUser]);
    }

    private function leaderProject(Request $r): int
    {
        $pid = $this->id($r);
        if ($this->role() !== 'student' || !AccessPolicy::isLeader(AccessPolicy::groupOfProject($pid))) {
            throw HttpException::forbidden();
        }
        return $pid;
    }
}
