<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;

/** Academic years, semesters, courses, offerings, deadlines and the rubric (FR-ACD-01/02, FR-EVL-07). */
final class AcademicService
{
    public const DEADLINE_TYPES = ['group_registration', 'topic_submission', 'proposal_submission', 'committee_evaluation', 'revision_submission'];

    public static function years(): array
    {
        return Database::connection()->fetchAll('SELECT * FROM academic_years ORDER BY year_be DESC');
    }

    public static function semesters(): array
    {
        return Database::connection()->fetchAll(
            'SELECT s.*, y.year_be FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id ORDER BY y.year_be DESC, s.term_no DESC'
        );
    }

    public static function courses(): array
    {
        return Database::connection()->fetchAll('SELECT * FROM courses ORDER BY course_code');
    }

    public static function offerings(): array
    {
        return Database::connection()->fetchAll(
            "SELECT o.*, c.course_code, c.name_th AS course_name_th, s.term_no, y.year_be,
                    u.title_th, u.first_name_th, u.last_name_th,
                    (SELECT COUNT(*) FROM project_groups g WHERE g.offering_id = o.id AND g.deleted_at IS NULL) AS group_count
               FROM course_offerings o JOIN courses c ON c.id = o.course_id
               JOIN semesters s ON s.id = o.semester_id JOIN academic_years y ON y.id = s.academic_year_id
               JOIN users u ON u.id = o.coordinator_id
              ORDER BY y.year_be DESC, s.term_no DESC, c.course_code"
        );
    }

    public static function saveYear(int $yearBe, string $start, string $end, bool $current, int $userId): int
    {
        if ($yearBe < 2500 || $yearBe > 2700) {
            throw new RuleViolation('year_invalid', 'Academic year must be in B.E.');
        }
        if (strtotime($start) === false || strtotime($end) === false || $start >= $end) {
            throw new RuleViolation('dates_invalid', 'Start date must be before end date');
        }
        $db = Database::connection();
        return $db->transaction(function (Database $db) use ($yearBe, $start, $end, $current, $userId) {
            $existing = $db->value('SELECT id FROM academic_years WHERE year_be = :y', ['y' => $yearBe]);
            if ($current) {
                $db->query('UPDATE academic_years SET is_current = 0');
            }
            if ($existing !== null) {
                $db->update('academic_years', ['start_date' => $start, 'end_date' => $end, 'is_current' => $current ? 1 : 0], 'id = :id', ['id' => (int) $existing]);
                $id = (int) $existing;
            } else {
                $id = $db->insert('academic_years', ['year_be' => $yearBe, 'start_date' => $start, 'end_date' => $end, 'is_current' => $current ? 1 : 0]);
            }
            AuditLogger::log('academic_year.saved', 'academic_year', $id, null, compact('yearBe', 'start', 'end', 'current'), userId: $userId);
            return $id;
        });
    }

    public static function saveSemester(int $yearId, int $termNo, string $start, string $end, int $userId): int
    {
        if ($termNo < 1 || $termNo > 3 || $start >= $end) {
            throw new RuleViolation('dates_invalid', 'Invalid semester');
        }
        $db = Database::connection();
        $existing = $db->value('SELECT id FROM semesters WHERE academic_year_id = :y AND term_no = :t', ['y' => $yearId, 't' => $termNo]);
        if ($existing !== null) {
            $db->update('semesters', ['start_date' => $start, 'end_date' => $end], 'id = :id', ['id' => (int) $existing]);
            $id = (int) $existing;
        } else {
            $id = $db->insert('semesters', ['academic_year_id' => $yearId, 'term_no' => $termNo, 'start_date' => $start, 'end_date' => $end]);
        }
        AuditLogger::log('semester.saved', 'semester', $id, null, compact('yearId', 'termNo', 'start', 'end'), userId: $userId);
        return $id;
    }

    public static function saveCourse(?int $id, string $code, string $nameTh, string $nameEn, ?string $credits, int $userId): int
    {
        $db = Database::connection();
        $data = ['course_code' => $code, 'name_th' => $nameTh, 'name_en' => $nameEn, 'credits' => $credits ?: null];
        if ($id) {
            $db->update('courses', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $db->insert('courses', $data);
        }
        AuditLogger::log('course.saved', 'course', $id, null, $data, userId: $userId);
        return $id;
    }

    /** Creates an offering and copies the default rubric into it. */
    public static function createOffering(int $courseId, int $semesterId, int $coordinatorId, int $userId): int
    {
        $db = Database::connection();
        if ($db->value('SELECT id FROM course_offerings WHERE course_id = :c AND semester_id = :s', ['c' => $courseId, 's' => $semesterId])) {
            throw new RuleViolation('offering_exists', 'Offering already exists');
        }
        return $db->transaction(function (Database $db) use ($courseId, $semesterId, $coordinatorId, $userId) {
            $id = $db->insert('course_offerings', ['course_id' => $courseId, 'semester_id' => $semesterId, 'coordinator_id' => $coordinatorId, 'status' => 'draft']);
            // Copy the rubric of the most recent offering, or the Section 9.1 default.
            $source = $db->value('SELECT offering_id FROM evaluation_criteria WHERE offering_id <> :o ORDER BY offering_id DESC LIMIT 1', ['o' => $id]);
            $rows = $source !== null
                ? $db->fetchAll('SELECT seq_no, name_th, name_en, description, weight FROM evaluation_criteria WHERE offering_id = :s AND is_active = 1 ORDER BY seq_no', ['s' => (int) $source])
                : self::defaultRubric();
            foreach ($rows as $r) {
                $db->insert('evaluation_criteria', $r + ['offering_id' => $id]);
            }
            AuditLogger::log('offering.created', 'offering', $id, null, compact('courseId', 'semesterId', 'coordinatorId'), userId: $userId);
            return $id;
        });
    }

    public static function setOfferingStatus(int $offeringId, string $status, int $userId): void
    {
        if (!in_array($status, ['draft', 'open', 'closed'], true)) {
            throw new RuleViolation('invalid', 'Invalid status');
        }
        Database::connection()->update('course_offerings', ['status' => $status], 'id = :id', ['id' => $offeringId]);
        AuditLogger::log('offering.status', 'offering', $offeringId, null, ['status' => $status], userId: $userId);
        OfferingService::flush();
    }

    public static function deadlines(int $offeringId): array
    {
        $rows = Database::connection()->fetchAll('SELECT * FROM deadlines WHERE offering_id = :o', ['o' => $offeringId]);
        $out = array_fill_keys(self::DEADLINE_TYPES, null);
        foreach ($rows as $r) {
            $out[$r['deadline_type']] = $r;
        }
        return $out;
    }

    /** @param array<string,array{due_at:?string,allow_late:bool,reminder_days:int}> $values */
    public static function saveDeadlines(int $offeringId, array $values, int $userId): void
    {
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($offeringId, $values, $userId) {
            foreach (self::DEADLINE_TYPES as $type) {
                $v = $values[$type] ?? null;
                if ($v === null || trim((string) $v['due_at']) === '') {
                    $db->query('DELETE FROM deadlines WHERE offering_id = :o AND deadline_type = :t', ['o' => $offeringId, 't' => $type]);
                    continue;
                }
                $due = date('Y-m-d H:i:s', strtotime((string) $v['due_at']));
                $db->query(
                    'INSERT INTO deadlines (offering_id, deadline_type, due_at, allow_late, reminder_days) VALUES (:o, :t, :d, :l, :r)
                     ON DUPLICATE KEY UPDATE due_at = VALUES(due_at), allow_late = VALUES(allow_late), reminder_days = VALUES(reminder_days)',
                    ['o' => $offeringId, 't' => $type, 'd' => $due, 'l' => $v['allow_late'] ? 1 : 0, 'r' => max(0, min(30, (int) $v['reminder_days']))]
                );
            }
            AuditLogger::log('deadlines.saved', 'offering', $offeringId, null, $values, userId: $userId);
        });
    }

    public static function rubric(int $offeringId, bool $activeOnly = false): array
    {
        return Database::connection()->fetchAll(
            'SELECT * FROM evaluation_criteria WHERE offering_id = :o' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY seq_no',
            ['o' => $offeringId]
        );
    }

    public static function rubricLocked(int $offeringId): bool
    {
        return Database::connection()->value('SELECT rubric_locked_at FROM course_offerings WHERE id = :o', ['o' => $offeringId]) !== null;
    }

    /**
     * Replaces the rubric (FR-EVL-07). Weights of active criteria must sum to exactly 100.
     * Refused once any evaluation has been submitted in the offering (DD-08).
     * @param array<int,array{id:?int,name_th:string,name_en:string,description:?string,weight:string,is_active:bool}> $rows
     */
    public static function saveRubric(int $offeringId, array $rows, int $userId): void
    {
        if (self::rubricLocked($offeringId)) {
            throw new RuleViolation('rubric_locked', 'Rubric is locked');
        }
        $active = array_filter($rows, fn ($r) => $r['is_active'] && trim($r['name_th']) !== '');
        $weights = array_map(fn ($r) => $r['weight'], $active);
        if (!ScoreCalculator::weightsValid($weights)) {
            throw new RuleViolation('rubric_weights', 'Weights must sum to 100');
        }
        $db = Database::connection();
        $db->transaction(function (Database $db) use ($offeringId, $rows, $userId) {
            $old = self::rubric($offeringId);
            $seq = 1;
            // Two-phase renumbering avoids unique (offering_id, seq_no) collisions.
            $db->query('UPDATE evaluation_criteria SET seq_no = seq_no + 100 WHERE offering_id = :o', ['o' => $offeringId]);
            foreach ($rows as $r) {
                if (trim($r['name_th']) === '') {
                    continue;
                }
                $data = [
                    'seq_no' => $seq++, 'name_th' => trim($r['name_th']), 'name_en' => trim($r['name_en']) ?: trim($r['name_th']),
                    'description' => trim((string) $r['description']) ?: null, 'weight' => number_format((float) $r['weight'], 2, '.', ''),
                    'is_active' => $r['is_active'] ? 1 : 0,
                ];
                if (!empty($r['id'])) {
                    $db->update('evaluation_criteria', $data, 'id = :id AND offering_id = :o', ['id' => (int) $r['id'], 'o' => $offeringId]);
                } else {
                    $db->insert('evaluation_criteria', $data + ['offering_id' => $offeringId]);
                }
            }
            AuditLogger::log('rubric.saved', 'offering', $offeringId, ['criteria' => $old], ['criteria' => $rows], userId: $userId);
        });
    }

    public static function lockRubric(int $offeringId): void
    {
        Database::connection()->query('UPDATE course_offerings SET rubric_locked_at = NOW() WHERE id = :o AND rubric_locked_at IS NULL', ['o' => $offeringId]);
    }

    private static function defaultRubric(): array
    {
        $d = [
            ['ที่มาและความสำคัญ', 'Background and Significance', 15],
            ['การกำหนดปัญหาและวัตถุประสงค์', 'Problem Definition and Objectives', 15],
            ['การทบทวนวรรณกรรมและงานที่เกี่ยวข้อง', 'Literature Review and Related Work', 15],
            ['วิธีดำเนินงานและความเป็นไปได้ทางเทคนิค', 'Methodology and Technical Feasibility', 25],
            ['ขอบเขตโครงงานและผลที่คาดว่าจะได้รับ', 'Project Scope and Expected Outcomes', 15],
            ['การวางแผนโครงงานและการนำเสนอ', 'Project Planning and Presentation', 15],
        ];
        $out = [];
        foreach ($d as $i => [$th, $en, $w]) {
            $out[] = ['seq_no' => $i + 1, 'name_th' => $th, 'name_en' => $en, 'description' => null, 'weight' => $w];
        }
        return $out;
    }
}
