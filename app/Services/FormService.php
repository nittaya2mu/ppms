<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;

/**
 * Shared data for the department's printable forms (แบบฟอร์ม 1, 2, 7, 8):
 * project, semester, students, advisor and the latest topic version.
 */
final class FormService
{
    public static function context(int $projectId): array
    {
        $db = Database::connection();
        $project = $db->fetch(
            "SELECT p.*, g.group_code, g.group_name, c.course_code, c.name_th AS course_name_th, s.term_no, y.year_be
               FROM projects p JOIN project_groups g ON g.id = p.group_id JOIN course_offerings o ON o.id = p.offering_id
               JOIN courses c ON c.id = o.course_id JOIN semesters s ON s.id = o.semester_id JOIN academic_years y ON y.id = s.academic_year_id
              WHERE p.id = :p AND p.deleted_at IS NULL",
            ['p' => $projectId]
        ) ?? throw new RuleViolation('project_missing', 'Project not found');

        $topic = TopicService::current($projectId);
        $sub = null;
        if ($topic !== null) {
            $sid = $topic['approved_submission_id'] ?? null ?: $topic['current_submission_id'];
            $sub = $sid ? TopicService::submission((int) $sid) : null;
        }
        $assignment = null;
        $proposal = ProposalService::forProject($projectId);
        if ($proposal !== null) {
            $assignment = ProposalService::currentAssignment((int) $proposal['id']);
        }
        return [
            'project'    => $project,
            'termLabel'  => self::termLabel((int) $project['term_no']),
            'members'    => GroupService::members((int) $project['group_id'], false),
            'advisor'    => GroupService::advisor((int) $project['group_id']),
            'topic'      => $sub,
            'proposal'   => $proposal,
            'assignment' => $assignment,
            'committee'  => $assignment ? self::committeeNames($assignment) : [],
        ];
    }

    public static function termLabel(int $termNo): string
    {
        return [1 => 'ต้น', 2 => 'ปลาย', 3 => 'ฤดูร้อน'][$termNo] ?? (string) $termNo;
    }

    /** @return array{1:?array,2:?array} users of committee slots 1 and 2 */
    public static function committeeNames(array $assignment): array
    {
        $db = Database::connection();
        $out = [];
        foreach ([1 => 'member1_id', 2 => 'member2_id'] as $slot => $col) {
            $out[$slot] = $db->fetch('SELECT id, title_th, first_name_th, last_name_th FROM users WHERE id = :u', ['u' => (int) $assignment[$col]]);
        }
        return $out;
    }
}
