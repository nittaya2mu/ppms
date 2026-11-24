<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;

/**
 * Builds rows for integration tests on top of seed_demo.sql:
 *   users 101/102 advisor+committee, 103/104 committee, students.id 1..6 (users 201..206), offering 1.
 */
final class Fixtures
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param int[] $memberStudentIds */
    public function group(string $code, int $leaderStudentId, array $memberStudentIds = [], string $status = 'active'): int
    {
        $gid = $this->db->insert('project_groups', [
            'offering_id' => 1, 'group_code' => $code, 'group_name' => "Group $code",
            'leader_student_id' => $leaderStudentId, 'status' => $status, 'registered_at' => date('Y-m-d H:i:s'),
        ]);
        $this->member($gid, $leaderStudentId, 'leader');
        foreach ($memberStudentIds as $sid) {
            $this->member($gid, $sid, 'member');
        }
        return $gid;
    }

    public function member(int $groupId, int $studentId, string $role = 'member', string $status = 'active'): int
    {
        return $this->db->insert('project_members', [
            'group_id' => $groupId, 'student_id' => $studentId, 'offering_id' => 1,
            'member_role' => $role, 'status' => $status, 'joined_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function advisor(int $groupId, int $advisorUserId): int
    {
        return $this->db->insert('project_advisors', [
            'group_id' => $groupId, 'advisor_id' => $advisorUserId, 'status' => 'assigned',
            'assigned_by' => 1, 'assigned_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function project(int $groupId): int
    {
        return $this->db->insert('projects', ['group_id' => $groupId, 'offering_id' => 1, 'project_code' => 'P-' . $groupId]);
    }

    /** @return array{topic:int, submission:int} */
    public function topicWithSubmission(int $projectId, string $status = 'submitted'): array
    {
        $topicId = $this->db->insert('project_topics', ['project_id' => $projectId, 'status' => $status]);
        $subId = $this->db->insert('topic_submissions', [
            'topic_id' => $topicId, 'version_no' => 1, 'title_th' => 'หัวข้อทดสอบ', 'title_en' => 'Test topic',
            'is_draft' => 0, 'submitted_by' => 201, 'submitted_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->update('project_topics', ['current_submission_id' => $subId], 'id = :id', ['id' => $topicId]);
        return ['topic' => $topicId, 'submission' => $subId];
    }

    public function file(string $ownerType = 'proposal_version'): int
    {
        return $this->db->insert('document_files', [
            'owner_type' => $ownerType, 'original_name' => 'proposal.pdf', 'stored_name' => bin2hex(random_bytes(16)) . '.pdf',
            'storage_path' => 'test/', 'mime_type' => 'application/pdf', 'file_size' => 1024,
            'sha256' => hash('sha256', random_bytes(8)), 'uploaded_by' => 201,
        ]);
    }

    /** @return array{proposal:int, version:int} */
    public function proposalWithVersion(int $projectId, string $status = 'committee_assignment'): array
    {
        $pid = $this->db->insert('proposal_submissions', ['project_id' => $projectId, 'status' => $status]);
        $vid = $this->db->insert('proposal_versions', [
            'proposal_id' => $pid, 'version_no' => 1, 'file_id' => $this->file(), 'title' => 'Proposal', 'submitted_by' => 201,
        ]);
        $this->db->update('proposal_submissions', ['current_version_id' => $vid], 'id = :id', ['id' => $pid]);
        return ['proposal' => $pid, 'version' => $vid];
    }

    public function assignment(int $proposalId, int $versionId, int $advisor, int $m1, int $m2, int $round = 1): int
    {
        return $this->db->insert('committee_assignments', [
            'proposal_id' => $proposalId, 'proposal_version_id' => $versionId, 'round_no' => $round,
            'advisor_id' => $advisor, 'member1_id' => $m1, 'member2_id' => $m2, 'assigned_by' => 1,
            'evaluation_deadline' => date('Y-m-d H:i:s', strtotime('+14 days')),
        ]);
    }

    /** A complete project ready for committee evaluation: returns ids. */
    public function evaluationReady(): array
    {
        $g = $this->group('G1', 1, [2]);
        $this->advisor($g, 101);
        $p = $this->project($g);
        $pp = $this->proposalWithVersion($p);
        $a = $this->assignment($pp['proposal'], $pp['version'], 101, 103, 104);
        $e1 = $this->db->insert('committee_evaluations', ['assignment_id' => $a, 'evaluator_id' => 103, 'member_slot' => 1]);
        $e2 = $this->db->insert('committee_evaluations', ['assignment_id' => $a, 'evaluator_id' => 104, 'member_slot' => 2]);
        return ['group' => $g, 'project' => $p, 'proposal' => $pp['proposal'], 'version' => $pp['version'], 'assignment' => $a, 'eval1' => $e1, 'eval2' => $e2];
    }
}
