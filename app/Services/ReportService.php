<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * The 12 reports of Section 18, all computed from live data.
 * {scope} is replaced by a group filter: all groups for admins, the advisor's own groups
 * for advisors (OQ-21: advisors get reports 1, 3, 5, 8, 10 only).
 */
final class ReportService
{
    public const ADVISOR_REPORTS = ['groups', 'topic_submissions', 'proposal_submissions', 'average_scores', 'revision_history', 'progress_reports', 'scope_approvals'];

    public static function definitions(): array
    {
        $name = "CONCAT(COALESCE(%s.title_th,''), %s.first_name_th, ' ', %s.last_name_th)";
        $n = fn (string $a) => sprintf($name, $a, $a, $a);
        return [
            'groups' => [
                'columns' => ['Group ID', 'ชื่อกลุ่ม', 'สถานะ', 'จำนวนสมาชิก', 'Group Leader', 'อาจารย์ที่ปรึกษา', 'วันที่ลงทะเบียน'],
                'sql' => "SELECT g.group_code, g.group_name, g.status,
                                 (SELECT COUNT(*) FROM project_members m WHERE m.group_id = g.id AND m.status = 'active'),
                                 {$n('lu')}, (SELECT {$n('au')} FROM project_advisors pa JOIN users au ON au.id = pa.advisor_id WHERE pa.group_id = g.id AND pa.status = 'assigned' LIMIT 1),
                                 g.registered_at
                            FROM project_groups g JOIN students ls ON ls.id = g.leader_student_id JOIN users lu ON lu.id = ls.user_id
                           WHERE g.offering_id = :o AND g.deleted_at IS NULL AND {scope} ORDER BY g.group_code",
            ],
            'memberships' => [
                'columns' => ['Group ID', 'ชื่อกลุ่ม', 'รหัสนิสิต', 'ชื่อ-สกุล', 'บทบาท', 'หลักสูตร', 'อีเมล', 'เข้าร่วมเมื่อ'],
                'sql' => "SELECT g.group_code, g.group_name, s.student_code, {$n('u')}, m.member_role, s.program, u.email, m.joined_at
                            FROM project_members m JOIN project_groups g ON g.id = m.group_id JOIN students s ON s.id = m.student_id JOIN users u ON u.id = s.user_id
                           WHERE g.offering_id = :o AND m.status = 'active' AND g.deleted_at IS NULL AND {scope} ORDER BY g.group_code, m.member_role = 'leader' DESC, s.student_code",
            ],
            'topic_submissions' => [
                'columns' => ['Project', 'Group ID', 'Version', 'ชื่อหัวข้อ (ไทย)', 'Title (English)', 'ส่งเมื่อ', 'ส่งช้า', 'สถานะหัวข้อปัจจุบัน'],
                'sql' => "SELECT p.project_code, g.group_code, ts.version_no, ts.title_th, ts.title_en, ts.submitted_at, IF(ts.is_late = 1, 'late', ''), t.status
                            FROM topic_submissions ts JOIN project_topics t ON t.id = ts.topic_id JOIN projects p ON p.id = t.project_id JOIN project_groups g ON g.id = p.group_id
                           WHERE p.offering_id = :o AND ts.is_draft = 0 AND {scope} ORDER BY p.project_code, ts.version_no",
            ],
            'topic_approvals' => [
                'columns' => ['Project', 'Version', 'ระดับ', 'ผลการพิจารณา', 'ผู้พิจารณา', 'วันที่', 'ความเห็น'],
                'sql' => "SELECT p.project_code, ts.version_no, a.level, a.decision, {$n('u')}, a.decided_at, a.comments
                            FROM topic_approvals a JOIN topic_submissions ts ON ts.id = a.submission_id JOIN project_topics t ON t.id = a.topic_id
                            JOIN projects p ON p.id = t.project_id JOIN project_groups g ON g.id = p.group_id JOIN users u ON u.id = a.decided_by
                           WHERE p.offering_id = :o AND {scope} ORDER BY p.project_code, a.id",
            ],
            'proposal_submissions' => [
                'columns' => ['Project', 'Group ID', 'Version', 'ชื่อเรื่อง', 'ไฟล์', 'ส่งเมื่อ', 'ส่งช้า', 'สถานะของ Version', 'สถานะ Proposal'],
                'sql' => "SELECT p.project_code, g.group_code, pv.version_no, pv.title, f.original_name, pv.submitted_at, IF(pv.is_late = 1, 'late', ''), pv.version_status, ps.status
                            FROM proposal_versions pv JOIN proposal_submissions ps ON ps.id = pv.proposal_id JOIN projects p ON p.id = ps.project_id
                            JOIN project_groups g ON g.id = p.group_id JOIN document_files f ON f.id = pv.file_id
                           WHERE p.offering_id = :o AND {scope} ORDER BY p.project_code, pv.version_no",
            ],
            'committee_assignments' => [
                'columns' => ['Project', 'รอบ', 'Version', 'อาจารย์ที่ปรึกษา', 'กรรมการคนที่ 1', 'กรรมการคนที่ 2', 'วันที่มอบหมาย', 'กำหนดส่ง', 'สถานะ'],
                'sql' => "SELECT p.project_code, ca.round_no, pv.version_no, {$n('ua')}, {$n('u1')}, {$n('u2')}, ca.assigned_at, ca.evaluation_deadline, ca.status
                            FROM committee_assignments ca JOIN proposal_submissions ps ON ps.id = ca.proposal_id JOIN projects p ON p.id = ps.project_id
                            JOIN project_groups g ON g.id = p.group_id JOIN proposal_versions pv ON pv.id = ca.proposal_version_id
                            JOIN users ua ON ua.id = ca.advisor_id JOIN users u1 ON u1.id = ca.member1_id JOIN users u2 ON u2.id = ca.member2_id
                           WHERE p.offering_id = :o AND {scope} ORDER BY p.project_code, ca.round_no",
            ],
            'individual_evaluations' => [
                'columns' => ['Project', 'รอบ', 'ผู้ประเมิน (บทบาท)', 'ชื่อผู้ประเมิน', 'การนำเสนอ (40)', 'ทฤษฎี (30)', 'เอกสาร (30)', 'คะแนนรวม', 'เกรด', 'สรุปผล', 'ส่งเมื่อ'],
                'sql' => "SELECT p.project_code, ca.round_no, ce.evaluator_role, {$n('u')}, ce.grades_json, ce.grades_json, ce.grades_json,
                                 ce.total_score, ce.letter_grade, ce.result, ce.submitted_at
                            FROM presentation_evaluations ce JOIN committee_assignments ca ON ca.id = ce.assignment_id AND ca.status = 'completed'
                            JOIN proposal_submissions ps ON ps.id = ca.proposal_id JOIN projects p ON p.id = ps.project_id
                            JOIN project_groups g ON g.id = p.group_id JOIN users u ON u.id = ce.evaluator_id
                           WHERE p.offering_id = :o AND {scope} ORDER BY p.project_code, ca.round_no, FIELD(ce.evaluator_role, 'advisor', 'committee1', 'committee2')",
                'transform' => 'groupScores',
            ],
            'average_scores' => [
                'columns' => ['Project', 'ชื่อโครงงาน', 'รอบ', 'อาจารย์ที่ปรึกษา', 'กรรมการคนที่ 1', 'กรรมการคนที่ 2', 'คะแนนเฉลี่ย', 'ผลไม่ตรงกัน'],
                'sql' => "SELECT p.project_code, p.title_th, ca.round_no,
                                 (SELECT total_score FROM presentation_evaluations WHERE assignment_id = ca.id AND evaluator_role = 'advisor'),
                                 (SELECT total_score FROM presentation_evaluations WHERE assignment_id = ca.id AND evaluator_role = 'committee1'),
                                 (SELECT total_score FROM presentation_evaluations WHERE assignment_id = ca.id AND evaluator_role = 'committee2'),
                                 ca.average_score, IF(ca.recommendations_differ = 1, 'ใช่', '')
                            FROM committee_assignments ca JOIN proposal_submissions ps ON ps.id = ca.proposal_id JOIN projects p ON p.id = ps.project_id
                            JOIN project_groups g ON g.id = p.group_id
                           WHERE p.offering_id = :o AND ca.status = 'completed' AND {scope} ORDER BY p.project_code, ca.round_no",
            ],
            'recommendations' => [
                'columns' => ['Project', 'รอบ', 'ผล: อาจารย์ที่ปรึกษา', 'ผล: กรรมการคนที่ 1', 'ผล: กรรมการคนที่ 2', 'ไม่ตรงกัน', 'คะแนนเฉลี่ย', 'คำตัดสินขั้นสุดท้าย', 'ต่างจากผู้ประเมิน'],
                'sql' => "SELECT p.project_code, ca.round_no,
                                 (SELECT result FROM presentation_evaluations WHERE assignment_id = ca.id AND evaluator_role = 'advisor'),
                                 (SELECT result FROM presentation_evaluations WHERE assignment_id = ca.id AND evaluator_role = 'committee1'),
                                 (SELECT result FROM presentation_evaluations WHERE assignment_id = ca.id AND evaluator_role = 'committee2'),
                                 IF(ca.recommendations_differ = 1, 'ใช่', ''), ca.average_score,
                                 (SELECT d.decision FROM approval_decisions d WHERE d.assignment_id = ca.id AND d.decision_level = 'final_decision' ORDER BY d.id DESC LIMIT 1),
                                 (SELECT IF(d.differs_from_committee = 1, 'ใช่', '') FROM approval_decisions d WHERE d.assignment_id = ca.id AND d.decision_level = 'final_decision' ORDER BY d.id DESC LIMIT 1)
                            FROM committee_assignments ca JOIN proposal_submissions ps ON ps.id = ca.proposal_id JOIN projects p ON p.id = ps.project_id
                            JOIN project_groups g ON g.id = p.group_id
                           WHERE p.offering_id = :o AND ca.status = 'completed' AND {scope} ORDER BY p.project_code, ca.round_no",
            ],
            'revision_history' => [
                'columns' => ['Project', 'ขอแก้ไขโดย', 'คำตัดสิน', 'จาก Version', 'โหมดการแก้ไข', 'สถานะ', 'ส่งฉบับแก้ (Version)', 'วันที่ขอแก้ไข', 'กำหนดส่ง'],
                'sql' => "SELECT p.project_code, rr.requested_by_level, d.decision, sv.version_no, rr.revision_mode, rr.status,
                                 (SELECT GROUP_CONCAT(nv.version_no) FROM revision_responses rs JOIN proposal_versions nv ON nv.id = rs.new_version_id WHERE rs.revision_request_id = rr.id),
                                 rr.created_at, rr.due_at
                            FROM revision_requests rr JOIN approval_decisions d ON d.id = rr.decision_id JOIN proposal_versions sv ON sv.id = rr.source_version_id
                            JOIN proposal_submissions ps ON ps.id = rr.proposal_id JOIN projects p ON p.id = ps.project_id JOIN project_groups g ON g.id = p.group_id
                           WHERE p.offering_id = :o AND {scope} ORDER BY p.project_code, rr.id",
            ],
            'final_approvals' => [
                'columns' => ['Project', 'ชื่อโครงงาน', 'Version', 'คำตัดสิน', 'S_avg', 'ต่างจากกรรมการ', 'เหตุผล (Justification)', 'ผู้บันทึก', 'วันที่'],
                'sql' => "SELECT p.project_code, p.title_th, pv.version_no, d.decision, d.average_score_snapshot, IF(d.differs_from_committee = 1, 'ใช่', ''),
                                 d.justification, {$n('u')}, d.decided_at
                            FROM approval_decisions d JOIN proposal_submissions ps ON ps.id = d.proposal_id JOIN projects p ON p.id = ps.project_id
                            JOIN project_groups g ON g.id = p.group_id JOIN proposal_versions pv ON pv.id = d.proposal_version_id JOIN users u ON u.id = d.decided_by
                           WHERE p.offering_id = :o AND d.decision_level = 'final_decision' AND {scope} ORDER BY p.project_code, d.id",
            ],
            'progress_reports' => [
                'columns' => ['Project', 'Group ID', 'ครั้งที่', 'วันที่เข้าพบ', 'สรุปผลการทำโครงงาน', 'ข้อเสนอแนะของอาจารย์', 'คะแนน', 'ผู้ประเมิน', 'ประเมินเมื่อ'],
                'sql' => "SELECT p.project_code, g.group_code, r.report_no, r.meeting_date, r.work_summary, r.suggestions, r.score, {$n('u')}, r.reviewed_at
                            FROM progress_reports r JOIN projects p ON p.id = r.project_id JOIN project_groups g ON g.id = p.group_id
                            LEFT JOIN users u ON u.id = r.reviewed_by
                           WHERE p.offering_id = :o AND {scope} ORDER BY p.project_code, r.report_no",
            ],
            'scope_approvals' => [
                'columns' => ['Project', 'ชื่อโครงงาน', 'Version', 'จำนวนหัวข้อขอบเขต', 'สถานะ', 'อาจารย์ที่ปรึกษา', 'กรรมการคนที่ 1', 'กรรมการคนที่ 2', 'ส่งเมื่อ', 'อนุมัติครบเมื่อ'],
                'sql' => "SELECT p.project_code, p.title_th, s.version_no, JSON_LENGTH(s.items_json), s.status,
                                 (SELECT decision FROM scope_approvals WHERE scope_id = s.id AND approver_role = 'advisor'),
                                 (SELECT decision FROM scope_approvals WHERE scope_id = s.id AND approver_role = 'committee1'),
                                 (SELECT decision FROM scope_approvals WHERE scope_id = s.id AND approver_role = 'committee2'),
                                 s.submitted_at, IF(s.status = 'approved', s.decided_at, NULL)
                            FROM scope_submissions s JOIN projects p ON p.id = s.project_id JOIN project_groups g ON g.id = p.group_id
                           WHERE p.offering_id = :o AND {scope} ORDER BY p.project_code, s.version_no",
            ],
            'course_progress' => [
                'columns' => ['ขั้นตอน', 'สถานะ', 'จำนวน'],
                'sql' => "SELECT 'กลุ่มโครงงาน', g.status, COUNT(*) FROM project_groups g WHERE g.offering_id = :o AND g.deleted_at IS NULL AND {scope} GROUP BY g.status
                          UNION ALL
                          SELECT 'หัวข้อโครงงาน', t.status, COUNT(*) FROM project_topics t JOIN projects p ON p.id = t.project_id JOIN project_groups g ON g.id = p.group_id WHERE p.offering_id = :o2 AND t.current_key = 1 AND {scope} GROUP BY t.status
                          UNION ALL
                          SELECT 'Proposal', ps.status, COUNT(*) FROM proposal_submissions ps JOIN projects p ON p.id = ps.project_id JOIN project_groups g ON g.id = p.group_id WHERE p.offering_id = :o3 AND {scope} GROUP BY ps.status
                          UNION ALL
                          SELECT 'แบบอนุมัติขอบเขต', s.status, COUNT(*) FROM scope_submissions s JOIN projects p ON p.id = s.project_id JOIN project_groups g ON g.id = p.group_id
                           WHERE p.offering_id = :o4 AND {scope} AND s.version_no = (SELECT MAX(x.version_no) FROM scope_submissions x WHERE x.project_id = s.project_id) GROUP BY s.status",
            ],
        ];
    }

    public static function allowed(string $key, string $role): bool
    {
        if (!array_key_exists($key, self::definitions())) {
            return false;
        }
        return $role === 'admin' || ($role === 'advisor' && in_array($key, self::ADVISOR_REPORTS, true));
    }

    /** @return array{columns:string[], rows:array<int,array<int,mixed>>} */
    public static function run(string $key, int $offeringId, string $role, int $userId): array
    {
        $def = self::definitions()[$key];
        $params = ['o' => $offeringId];
        if (str_contains($def['sql'], ':o2')) {
            $params += ['o2' => $offeringId, 'o3' => $offeringId, 'o4' => $offeringId];
        }
        $scope = '1 = 1';
        if ($role === 'advisor') {
            $count = substr_count($def['sql'], '{scope}');
            $parts = [];
            for ($i = 0; $i < $count; $i++) {
                $parts[] = "EXISTS (SELECT 1 FROM project_advisors rpa WHERE rpa.group_id = g.id AND rpa.advisor_id = :adv$i AND rpa.status = 'assigned')";
                $params["adv$i"] = $userId;
            }
            $sql = $def['sql'];
            foreach ($parts as $p) {
                $sql = preg_replace('/\{scope\}/', $p, $sql, 1);
            }
        } else {
            $sql = str_replace('{scope}', $scope, $def['sql']);
        }
        $stmt = Database::connection()->query($sql, $params);
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
        if (($def['transform'] ?? null) === 'groupScores') {
            $rows = array_map([self::class, 'groupScores'], $rows);
        }
        return ['columns' => $def['columns'], 'rows' => array_map(fn ($r) => array_map(fn ($v) => self::label($v), $r), $rows)];
    }

    /** Columns 4-6 hold the grades JSON: replace with the points of each form-2 group. */
    private static function groupScores(array $row): array
    {
        $grades = json_decode((string) $row[4], true) ?: [];
        $points = PresentationForm::score($grades)['items'];
        $i = 4;
        foreach (PresentationForm::GROUPS as [$label, $max, $items]) {
            $row[$i++] = number_format(array_sum(array_intersect_key($points, $items)), 2);
        }
        return $row;
    }

    /** Translate stored status codes into the UI labels. */
    private static function label(mixed $value): mixed
    {
        if (!is_string($value) || !preg_match('/^[a-z_]+$/', $value)) {
            return $value;
        }
        foreach (['status.', 'decision.', 'level.', 'version_status.', 'assignment.', 'revision_mode.', 'member_role.', 'evaluator_role.', 'presentation_result.', 'scope.status_', 'scope.decision_'] as $prefix) {
            $t = __($prefix . $value);
            if ($t !== $prefix . $value) {
                return $t;
            }
        }
        return $value;
    }
}
