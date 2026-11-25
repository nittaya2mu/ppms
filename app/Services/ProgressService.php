<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;

/**
 * Advisor meeting / progress reports (แบบฟอร์ม 8: สมุดบันทึกการเข้าพบอาจารย์ที่ปรึกษาโครงงาน).
 * Part 1 is written by the students, part 2 (suggestions + score) by the advisor.
 */
final class ProgressService
{
    public static function forProject(int $projectId): array
    {
        return Database::connection()->fetchAll(
            'SELECT r.*, s.title_th AS s_title, s.first_name_th AS s_first, s.last_name_th AS s_last,
                    a.title_th, a.first_name_th, a.last_name_th
               FROM progress_reports r JOIN users s ON s.id = r.submitted_by LEFT JOIN users a ON a.id = r.reviewed_by
              WHERE r.project_id = :p ORDER BY r.report_no',
            ['p' => $projectId]
        );
    }

    public static function find(int $reportId): ?array
    {
        return Database::connection()->fetch('SELECT * FROM progress_reports WHERE id = :id', ['id' => $reportId]);
    }

    /** @return array<int,array{score:float,label:string}> rubric levels, highest first */
    public static function rubric(): array
    {
        $raw = SettingsService::get('progress.rubric', []);
        $levels = [];
        foreach (is_array($raw) ? $raw : [] as $l) {
            if (isset($l['score'], $l['label']) && is_numeric($l['score'])) {
                $levels[] = ['score' => (float) $l['score'], 'label' => (string) $l['label']];
            }
        }
        usort($levels, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $levels;
    }

    public static function maxScore(): float
    {
        $r = self::rubric();
        return $r ? $r[0]['score'] : 0.0;
    }

    public static function minReports(): int
    {
        return max(0, SettingsService::int('progress.min_reports', 3));
    }

    public static function maxReports(): int
    {
        return max(self::minReports(), SettingsService::int('progress.max_reports', 5));
    }

    public static function reviewedCount(int $projectId): int
    {
        return (int) Database::connection()->value("SELECT COUNT(*) FROM progress_reports WHERE project_id = :p AND status = 'reviewed'", ['p' => $projectId]);
    }

    /** Reports may start once the topic has final approval. */
    public static function open(int $projectId): bool
    {
        $topic = TopicService::current($projectId);
        return $topic !== null && $topic['status'] === 'topic_approved';
    }

    public static function submit(int $projectId, int $userId, string $meetingDate, string $summary): int
    {
        $project = self::projectForMember($projectId, $userId);
        if (!self::open($projectId)) {
            throw new RuleViolation('progress_not_open', 'Progress reports start after the topic is approved');
        }
        [$date, $summary] = self::validate($meetingDate, $summary);
        $db = Database::connection();
        return $db->transaction(function (Database $db) use ($project, $projectId, $userId, $date, $summary) {
            $next = 1 + (int) $db->value('SELECT COALESCE(MAX(report_no), 0) FROM progress_reports WHERE project_id = :p', ['p' => $projectId]);
            if ($next > self::maxReports()) {
                throw new RuleViolation('progress_max', 'Maximum number of reports reached');
            }
            $id = $db->insert('progress_reports', [
                'project_id' => $projectId, 'report_no' => $next, 'meeting_date' => $date, 'work_summary' => $summary, 'submitted_by' => $userId,
            ]);
            AuditLogger::log('progress.submitted', 'progress_report', $id, null, ['report_no' => $next], userId: $userId);
            $advisor = GroupService::advisorAssigned((int) $project['group_id']);
            if ($advisor) {
                NotificationService::notify([$advisor], 'progress.submitted', 'มีบันทึกการเข้าพบรอประเมิน',
                    "โครงงาน {$project['project_code']} ส่งบันทึกการเข้าพบครั้งที่ $next", '/projects/' . $projectId . '/progress', 'progress_report', $id);
            }
            return $id;
        });
    }

    /** Students may edit their part until the advisor has scored it. */
    public static function update(int $reportId, int $userId, string $meetingDate, string $summary): void
    {
        $report = self::find($reportId) ?? throw new RuleViolation('progress_missing', 'Report not found');
        self::projectForMember((int) $report['project_id'], $userId);
        if ($report['status'] !== 'submitted') {
            throw new RuleViolation('progress_locked', 'The advisor has already reviewed this report');
        }
        [$date, $summary] = self::validate($meetingDate, $summary);
        Database::connection()->update('progress_reports', ['meeting_date' => $date, 'work_summary' => $summary], 'id = :id', ['id' => $reportId]);
    }

    /** Part 2: the project advisor's suggestions and score (may be revised later). */
    public static function review(int $reportId, int $advisorId, string $score, ?string $suggestions): void
    {
        $report = self::find($reportId) ?? throw new RuleViolation('progress_missing', 'Report not found');
        $project = Database::connection()->fetch('SELECT * FROM projects WHERE id = :p', ['p' => (int) $report['project_id']]);
        if (GroupService::advisorAssigned((int) $project['group_id']) !== $advisorId) {
            throw new RuleViolation('not_advisor', 'Only the project advisor can score progress reports');
        }
        $allowed = array_map(fn ($l) => $l['score'], self::rubric());
        if (!is_numeric($score) || !in_array((float) $score, $allowed, true)) {
            throw new RuleViolation('progress_score', 'Choose a score level');
        }
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($report, $reportId, $advisorId, $score, $suggestions, $project) {
            $db->update('progress_reports', [
                'status' => 'reviewed', 'reviewed_by' => $advisorId, 'score' => number_format((float) $score, 2, '.', ''),
                'suggestions' => trim((string) $suggestions) ?: null, 'reviewed_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $reportId]);
            AuditLogger::log('progress.reviewed', 'progress_report', $reportId, ['score' => $report['score']], ['score' => (float) $score], userId: $advisorId);
            NotificationService::notify(GroupService::memberUserIds((int) $project['group_id']), 'progress.reviewed', 'อาจารย์ที่ปรึกษาประเมินบันทึกการเข้าพบแล้ว',
                "ครั้งที่ {$report['report_no']}: " . number_format((float) $score, 0) . ' คะแนน', '/projects/' . $project['id'] . '/progress', 'progress_report', $reportId);
        });
    }

    /** Projects with reports waiting for the advisor (or all, for the admin). */
    public static function queue(int $offeringId, string $role, int $userId): array
    {
        $where = ['p.offering_id = :o', 'p.deleted_at IS NULL'];
        $params = ['o' => $offeringId];
        if ($role === 'advisor') {
            $where[] = "EXISTS (SELECT 1 FROM project_advisors pa WHERE pa.group_id = p.group_id AND pa.advisor_id = :u AND pa.status = 'assigned')";
            $params['u'] = $userId;
        }
        return Database::connection()->fetchAll(
            "SELECT p.id AS project_id, p.project_code, p.title_th, g.group_code, g.group_name,
                    COUNT(r.id) AS reports, SUM(r.status = 'reviewed') AS reviewed, SUM(r.status = 'submitted') AS pending,
                    MAX(r.submitted_at) AS last_submitted
               FROM projects p JOIN project_groups g ON g.id = p.group_id
               JOIN project_topics t ON t.project_id = p.id AND t.current_key = 1 AND t.status = 'topic_approved'
               LEFT JOIN progress_reports r ON r.project_id = p.id
              WHERE " . implode(' AND ', $where) . '
              GROUP BY p.id, p.project_code, p.title_th, g.group_code, g.group_name
              ORDER BY SUM(r.status = \'submitted\') DESC, g.group_code',
            $params
        );
    }

    private static function projectForMember(int $projectId, int $userId): array
    {
        $project = Database::connection()->fetch('SELECT * FROM projects WHERE id = :p AND deleted_at IS NULL', ['p' => $projectId])
            ?? throw new RuleViolation('project_missing', 'Project not found');
        if (!in_array($userId, GroupService::memberUserIds((int) $project['group_id']), true)) {
            throw new RuleViolation('not_member', 'Only group members can write progress reports');
        }
        return $project;
    }

    /** @return array{0:string,1:string} */
    private static function validate(string $meetingDate, string $summary): array
    {
        $ts = strtotime($meetingDate);
        if ($ts === false || $ts > strtotime('tomorrow')) {
            throw new RuleViolation('progress_date', 'Invalid meeting date');
        }
        $summary = trim($summary);
        if ($summary === '') {
            throw new RuleViolation('required_fields', 'Summary is required');
        }
        return [date('Y-m-d', $ts), $summary];
    }
}
