<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Role-specific dashboards (FR-DSH-01). All counts come from the database (Section 23 rule 2).
 * Panels for workflow modules not yet built (Phases 5-12) show live counts of zero until those
 * modules start writing rows.
 */
final class DashboardController
{
    public function index(Request $request): Response
    {
        return match (Auth::activeRole()) {
            'admin'     => $this->admin(),
            'student'   => $this->student(),
            'advisor'   => $this->advisor(),
            'committee' => $this->committee(),
            default     => Response::view('dashboard/no_role'),
        };
    }

    private function admin(): Response
    {
        $db = Database::connection();
        $offering = self::currentOffering();
        $oid = (int) ($offering['id'] ?? 0);
        $count = fn (string $sql) => (int) $db->value($sql, ['o' => $oid]);

        $topicCounts = self::statusCounts(
            'SELECT t.status, COUNT(*) n FROM project_topics t JOIN projects p ON p.id = t.project_id WHERE p.offering_id = :o GROUP BY t.status',
            $oid
        );
        $proposalCounts = self::statusCounts(
            'SELECT ps.status, COUNT(*) n FROM proposal_submissions ps JOIN projects p ON p.id = ps.project_id WHERE p.offering_id = :o GROUP BY ps.status',
            $oid
        );

        $stats = [
            'groups'               => $count('SELECT COUNT(*) FROM project_groups WHERE offering_id = :o AND deleted_at IS NULL'),
            'students'             => $count("SELECT COUNT(*) FROM project_members WHERE offering_id = :o AND status = 'active'"),
            'pending_topics'       => ($topicCounts['submitted'] ?? 0) + ($topicCounts['advisor_review'] ?? 0) + ($topicCounts['admin_review'] ?? 0),
            'approved_topics'      => $topicCounts['topic_approved'] ?? 0,
            'pending_proposals'    => ($proposalCounts['submitted'] ?? 0) + ($proposalCounts['advisor_review'] ?? 0),
            'awaiting_assignment'  => $proposalCounts['committee_assignment'] ?? 0,
            'pending_evaluations'  => $count("SELECT COUNT(*) FROM committee_evaluations ce JOIN committee_assignments ca ON ca.id = ce.assignment_id JOIN proposal_submissions ps ON ps.id = ca.proposal_id JOIN projects p ON p.id = ps.project_id WHERE p.offering_id = :o AND ca.status = 'active' AND ce.status <> 'submitted'"),
            'completed_evaluations'=> $count("SELECT COUNT(*) FROM committee_assignments ca JOIN proposal_submissions ps ON ps.id = ca.proposal_id JOIN projects p ON p.id = ps.project_id WHERE p.offering_id = :o AND ca.status = 'completed'"),
            'revisions'            => ($proposalCounts['revision_required'] ?? 0) + ($proposalCounts['approved_minor'] ?? 0),
            'final_approved'       => $proposalCounts['approved'] ?? 0,
        ];
        $groupsPending = $db->fetchAll(
            "SELECT g.*, u.first_name_th, u.last_name_th, u.title_th FROM project_groups g
               JOIN students s ON s.id = g.leader_student_id JOIN users u ON u.id = s.user_id
              WHERE g.offering_id = :o AND g.status = 'submitted' AND g.deleted_at IS NULL
              ORDER BY g.registered_at LIMIT 10",
            ['o' => $oid]
        );

        return Response::view('dashboard/admin', compact('stats', 'topicCounts', 'proposalCounts', 'offering', 'groupsPending'));
    }

    private function student(): Response
    {
        $db = Database::connection();
        $student = User::student((int) Auth::id());
        $group = null;
        $members = [];
        $advisor = null;
        if ($student !== null) {
            $group = $db->fetch(
                "SELECT g.* FROM project_groups g JOIN project_members m ON m.group_id = g.id
                  WHERE m.student_id = :s AND m.status = 'active' AND g.deleted_at IS NULL
                  ORDER BY g.id DESC LIMIT 1",
                ['s' => (int) $student['id']]
            );
            if ($group !== null) {
                $members = $db->fetchAll(
                    "SELECT m.member_role, s.student_code, u.title_th, u.first_name_th, u.last_name_th, u.email
                       FROM project_members m JOIN students s ON s.id = m.student_id JOIN users u ON u.id = s.user_id
                      WHERE m.group_id = :g AND m.status = 'active' ORDER BY m.member_role = 'leader' DESC, s.student_code",
                    ['g' => (int) $group['id']]
                );
                $advisor = $db->fetch(
                    "SELECT u.* FROM project_advisors pa JOIN users u ON u.id = pa.advisor_id WHERE pa.group_id = :g AND pa.status = 'assigned'",
                    ['g' => (int) $group['id']]
                );
            }
        }
        $deadlines = $db->fetchAll(
            'SELECT * FROM deadlines WHERE offering_id = :o ORDER BY due_at',
            ['o' => (int) (self::currentOffering()['id'] ?? 0)]
        );
        return Response::view('dashboard/student', compact('student', 'group', 'members', 'advisor', 'deadlines'));
    }

    private function advisor(): Response
    {
        $db = Database::connection();
        $groups = $db->fetchAll(
            "SELECT g.*, p.title_th, t.status AS topic_status, ps.status AS proposal_status
               FROM project_advisors pa
               JOIN project_groups g ON g.id = pa.group_id
               LEFT JOIN projects p ON p.group_id = g.id
               LEFT JOIN project_topics t ON t.project_id = p.id AND t.current_key = 1
               LEFT JOIN proposal_submissions ps ON ps.project_id = p.id
              WHERE pa.advisor_id = :u AND pa.status = 'assigned' AND g.deleted_at IS NULL
              ORDER BY g.group_code",
            ['u' => (int) Auth::id()]
        );
        $pendingTopics = count(array_filter($groups, fn ($g) => in_array($g['topic_status'], ['submitted', 'advisor_review'], true)));
        $pendingProposals = count(array_filter($groups, fn ($g) => in_array($g['proposal_status'], ['submitted', 'advisor_review'], true)));
        return Response::view('dashboard/advisor', compact('groups', 'pendingTopics', 'pendingProposals'));
    }

    private function committee(): Response
    {
        $db = Database::connection();
        $evaluations = $db->fetchAll(
            "SELECT ce.status, ce.member_slot, ca.evaluation_deadline, ca.round_no, ca.status AS assignment_status,
                    p.project_code, p.title_th, pv.version_no
               FROM committee_evaluations ce
               JOIN committee_assignments ca ON ca.id = ce.assignment_id
               JOIN proposal_versions pv ON pv.id = ca.proposal_version_id
               JOIN proposal_submissions ps ON ps.id = ca.proposal_id
               JOIN projects p ON p.id = ps.project_id
              WHERE ce.evaluator_id = :u
              ORDER BY ca.evaluation_deadline",
            ['u' => (int) Auth::id()]
        );
        $pending = count(array_filter($evaluations, fn ($e) => $e['status'] !== 'submitted' && $e['assignment_status'] === 'active'));
        return Response::view('dashboard/committee', compact('evaluations', 'pending'));
    }

    public static function currentOffering(): ?array
    {
        return Database::connection()->fetch(
            "SELECT o.*, c.course_code, c.name_th AS course_name_th, s.term_no, y.year_be
               FROM course_offerings o JOIN courses c ON c.id = o.course_id
               JOIN semesters s ON s.id = o.semester_id JOIN academic_years y ON y.id = s.academic_year_id
              WHERE o.status = 'open' ORDER BY y.year_be DESC, s.term_no DESC LIMIT 1"
        );
    }

    private static function statusCounts(string $sql, int $offeringId): array
    {
        $out = [];
        foreach (Database::connection()->fetchAll($sql, ['o' => $offeringId]) as $row) {
            $out[$row['status']] = (int) $row['n'];
        }
        return $out;
    }
}
