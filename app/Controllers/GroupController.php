<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccessPolicy;
use App\Services\GroupService;
use App\Services\Rules\RuleViolation;
use App\Services\SettingsService;
use App\Services\TopicService;

/** Project Groups menu (Section 4). */
final class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $offering = $this->offering();
        if ($this->role() === 'student') {
            $studentId = AccessPolicy::studentId() ?? throw HttpException::forbidden();
            $group = GroupService::forStudent($studentId, (int) $offering['id']);
            if ($group !== null) {
                return $this->show($request, (int) $group['id']);
            }
            return $this->view('groups/start', [
                'invitations' => GroupService::invitations($studentId, (int) $offering['id']),
                'offering' => $offering,
            ]);
        }
        $advisorFilter = $this->role() === 'advisor' ? $this->userId() : null;
        if ($this->role() === 'committee') {
            throw HttpException::forbidden();
        }
        $result = GroupService::search(
            (int) $offering['id'],
            (string) $request->query('q', ''),
            (string) $request->query('status', ''),
            $advisorFilter,
            (int) $request->query('page', 1)
        );
        $requests = GroupService::advisorRequests((int) $offering['id'], $this->role() === 'advisor' ? $this->userId() : null);
        return $this->view('groups/index', $result + ['q' => (string) $request->query('q', ''), 'status' => (string) $request->query('status', ''), 'offering' => $offering, 'advisorRequests' => $requests]);
    }

    public function showPage(Request $request): Response
    {
        return $this->show($request, $this->id($request));
    }

    private function show(Request $request, int $groupId): Response
    {
        AccessPolicy::authorizeGroup($groupId);
        $group = GroupService::find($groupId) ?? throw HttpException::notFound();
        return $this->view('groups/show', [
            'group'      => $group,
            'members'    => GroupService::members($groupId),
            'advisor'    => GroupService::advisor($groupId),
            'history'    => $this->role() === 'admin' ? GroupService::history($groupId) : [],
            'isLeader'   => $this->role() === 'student' && AccessPolicy::isLeader($groupId),
            'advisors'   => in_array($this->role(), ['student', 'admin'], true) ? GroupService::advisorChoices() : [],
            'myRequest'  => $this->role() === 'advisor' ? \App\Core\Database::connection()->fetch(
                "SELECT * FROM project_advisors WHERE group_id = :g AND advisor_id = :u AND status = 'requested' ORDER BY id DESC LIMIT 1",
                ['g' => $groupId, 'u' => $this->userId()]
            ) : null,
            'topic'      => $group['project_id'] ? TopicService::current((int) $group['project_id']) : null,
            'minMembers' => SettingsService::int('group.min_members', 2),
            'maxMembers' => SettingsService::int('group.max_members', 3),
        ]);
    }

    // ----- Student -----------------------------------------------------------

    public function store(Request $r): Response
    {
        $studentId = AccessPolicy::studentId() ?? throw HttpException::forbidden();
        return $this->attempt($r, '/groups', fn () => GroupService::create($studentId, $this->userId(), (int) $this->offering()['id'], (string) $r->input('group_name')), __('groups.created'));
    }

    public function rename(Request $r): Response
    {
        $gid = $this->leaderGroup($r);
        return $this->attempt($r, '/groups', fn () => GroupService::rename($gid, (string) $r->input('group_name'), $this->userId()), __('common.saved'));
    }

    public function invite(Request $r): Response
    {
        $gid = $this->leaderGroup($r);
        return $this->attempt($r, '/groups', fn () => GroupService::invite($gid, (string) $r->input('student_code'), $this->userId()), __('groups.invited'));
    }

    public function removeMember(Request $r): Response
    {
        $gid = $this->leaderGroup($r);
        return $this->attempt($r, '/groups', fn () => GroupService::removeDraftMember($gid, $this->id($r, 'member'), $this->userId()), __('common.saved'));
    }

    public function respond(Request $r): Response
    {
        $studentId = AccessPolicy::studentId() ?? throw HttpException::forbidden();
        $accept = $r->input('answer') === 'accept';
        return $this->attempt($r, '/groups', fn () => GroupService::respond($this->id($r, 'member'), $studentId, $accept, $this->userId()),
            $accept ? __('groups.joined') : __('groups.declined'));
    }

    public function submit(Request $r): Response
    {
        $gid = $this->leaderGroup($r);
        return $this->attempt($r, '/groups', fn () => GroupService::submit($gid, $this->userId()), __('groups.submitted'));
    }

    public function requestAdvisor(Request $r): Response
    {
        $gid = $this->leaderGroup($r);
        return $this->attempt($r, '/groups', fn () => GroupService::requestAdvisor($gid, (int) $r->input('advisor_id'), $this->userId()), __('groups.advisor_requested'));
    }

    /** The requested advisor accepts or declines (step 2 of 3). */
    public function respondAdvisor(Request $r): Response
    {
        $gid = $this->id($r);
        $accept = $r->input('answer') === 'accept';
        return $this->attempt($r, "/groups/$gid", fn () => GroupService::respondAdvisorRequest($gid, $this->userId(), $accept, (string) $r->input('note')),
            $accept ? __('groups.advisor_accepted') : __('groups.advisor_declined'));
    }

    // ----- Administrator ----------------------------------------------------

    public function verify(Request $r): Response
    {
        $gid = $this->id($r);
        return $this->attempt($r, "/groups/$gid", fn () => GroupService::verify($gid, $this->userId()), __('groups.verified'));
    }

    public function reject(Request $r): Response
    {
        $gid = $this->id($r);
        return $this->attempt($r, "/groups/$gid", function () use ($gid, $r) {
            $reason = trim((string) $r->input('reason'));
            if ($reason === '') {
                throw new RuleViolation('reason_required', 'Reason required');
            }
            GroupService::reject($gid, $this->userId(), $reason);
        }, __('groups.rejected'));
    }

    public function assignAdvisor(Request $r): Response
    {
        $gid = $this->id($r);
        return $this->attempt($r, "/groups/$gid", fn () => GroupService::assignAdvisor($gid, (int) $r->input('advisor_id'), $this->userId(), (string) $r->input('reason')), __('groups.advisor_assigned'));
    }

    public function adminAddMember(Request $r): Response
    {
        $gid = $this->id($r);
        return $this->attempt($r, "/groups/$gid", fn () => GroupService::adminAddMember($gid, (string) $r->input('student_code'), $this->userId(), (string) $r->input('reason')), __('common.saved'));
    }

    /** Admin membership change on one member: action = remove | leader. */
    public function adminMember(Request $r): Response
    {
        $gid = $this->id($r);
        $mid = $this->id($r, 'member');
        $reason = (string) $r->input('reason');
        return $this->attempt($r, "/groups/$gid", fn () => $r->input('action') === 'leader'
            ? GroupService::adminChangeLeader($gid, $mid, $this->userId(), $reason)
            : GroupService::adminRemoveMember($gid, $mid, $this->userId(), $reason), __('common.saved'));
    }

    /** Only the Group Leader can act on the group (Section 3.2). */
    private function leaderGroup(Request $r): int
    {
        $gid = $this->id($r);
        if (!AccessPolicy::isLeader($gid, (int) Auth::id())) {
            throw HttpException::forbidden();
        }
        return $gid;
    }
}
