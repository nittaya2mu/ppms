<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccessPolicy;
use App\Services\FormService;
use App\Services\ProgressService;
use App\Services\ScopeService;

/** Progress reports (แบบฟอร์ม 8) and scope approval (แบบฟอร์ม 7) of a project. */
final class ProjectFormsController extends Controller
{
    // ----- Progress reports ---------------------------------------------------

    public function progress(Request $r): Response
    {
        $pid = $this->project($r);
        $groupId = AccessPolicy::groupOfProject($pid);
        $reports = ProgressService::forProject($pid);
        return $this->view('projects/progress', FormService::context($pid) + [
            'reports' => $reports,
            'rubric' => ProgressService::rubric(),
            'maxScore' => ProgressService::maxScore(),
            'minReports' => ProgressService::minReports(),
            'maxReports' => ProgressService::maxReports(),
            'open' => ProgressService::open($pid),
            'isStudent' => $this->role() === 'student' && AccessPolicy::isMember($groupId),
            'isAdvisor' => $this->role() === 'advisor' && AccessPolicy::isAdvisor($groupId),
        ]);
    }

    public function progressStore(Request $r): Response
    {
        $pid = $this->project($r);
        return $this->attempt($r, "/projects/$pid/progress", fn () => ProgressService::submit($pid, $this->userId(), (string) $r->input('meeting_date'), (string) $r->input('work_summary')), __('progress.submitted'));
    }

    public function progressUpdate(Request $r): Response
    {
        $pid = $this->project($r);
        return $this->attempt($r, "/projects/$pid/progress", fn () => ProgressService::update($this->reportOf($r, $pid), $this->userId(), (string) $r->input('meeting_date'), (string) $r->input('work_summary')), __('common.saved'));
    }

    public function progressReview(Request $r): Response
    {
        $pid = $this->project($r);
        return $this->attempt($r, "/projects/$pid/progress", fn () => ProgressService::review($this->reportOf($r, $pid), $this->userId(), (string) $r->input('score'), (string) $r->input('suggestions')), __('progress.reviewed'));
    }

    public function progressPrint(Request $r): Response
    {
        $pid = $this->project($r);
        return $this->view('forms/form8', FormService::context($pid) + ['reports' => ProgressService::forProject($pid), 'rubric' => ProgressService::rubric(), 'minReports' => ProgressService::minReports()]);
    }

    // ----- Scope approval -----------------------------------------------------

    public function scope(Request $r): Response
    {
        $pid = $this->project($r);
        $groupId = AccessPolicy::groupOfProject($pid);
        $current = ScopeService::current($pid);
        $myRoles = in_array($this->role(), ['advisor', 'committee'], true) ? ScopeService::rolesOf($pid, $this->userId()) : [];
        return $this->view('projects/scope', FormService::context($pid) + [
            'scope' => $current,
            'history' => ScopeService::history($pid),
            'blocker' => ScopeService::blocker($pid),
            'approvers' => ScopeService::approvers($pid),
            'reviewed' => ProgressService::reviewedCount($pid),
            'minReports' => ProgressService::minReports(),
            'isLeader' => $this->role() === 'student' && AccessPolicy::isLeader($groupId),
            'pendingRoles' => $current && $current['status'] === 'pending' ? array_values(array_diff($myRoles, array_keys($current['approvals']))) : [],
        ]);
    }

    public function scopeStore(Request $r): Response
    {
        $pid = $this->project($r);
        $rows = [];
        $all = $r->all();
        foreach ((array) ($all['detail'] ?? []) as $i => $detail) {
            $rows[] = ['detail' => (string) $detail, 'weight' => (string) ($all['weight'][$i] ?? '')];
        }
        return $this->attempt($r, "/projects/$pid/scope", fn () => ScopeService::submit($pid, $this->userId(), $rows), __('scope.submitted'));
    }

    public function scopeDecide(Request $r): Response
    {
        $pid = $this->project($r);
        $scope = ScopeService::current($pid) ?? throw HttpException::notFound();
        return $this->attempt($r, "/projects/$pid/scope", fn () => ScopeService::decide((int) $scope['id'], $this->userId(), (string) $r->input('decision'), (string) $r->input('comments')), __('common.decision_saved'));
    }

    public function scopePrint(Request $r): Response
    {
        $pid = $this->project($r);
        $scope = ScopeService::current($pid) ?? throw HttpException::notFound();
        return $this->view('forms/form7', FormService::context($pid) + ['scope' => $scope]);
    }

    private function project(Request $r): int
    {
        $pid = $this->id($r);
        AccessPolicy::authorizeGroup(AccessPolicy::groupOfProject($pid));
        return $pid;
    }

    private function reportOf(Request $r, int $pid): int
    {
        $rid = $this->id($r, 'report');
        $report = ProgressService::find($rid);
        if ($report === null || (int) $report['project_id'] !== $pid) {
            throw HttpException::notFound();
        }
        return $rid;
    }
}
