<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** The course offering the system currently works in (the latest 'open' offering). */
final class OfferingService
{
    private static ?array $current = null;
    private static bool $loaded = false;

    public static function current(): ?array
    {
        if (!self::$loaded) {
            self::$current = Database::connection()->fetch(
                "SELECT o.*, c.course_code, c.name_th AS course_name_th, c.name_en AS course_name_en,
                        s.term_no, y.year_be
                   FROM course_offerings o JOIN courses c ON c.id = o.course_id
                   JOIN semesters s ON s.id = o.semester_id JOIN academic_years y ON y.id = s.academic_year_id
                  WHERE o.status = 'open' ORDER BY y.year_be DESC, s.term_no DESC, o.id DESC LIMIT 1"
            );
            self::$loaded = true;
        }
        return self::$current;
    }

    public static function flush(): void
    {
        self::$loaded = false;
        self::$current = null;
    }

    public static function deadline(int $offeringId, string $type): ?array
    {
        return Database::connection()->fetch(
            'SELECT * FROM deadlines WHERE offering_id = :o AND deadline_type = :t',
            ['o' => $offeringId, 't' => $type]
        );
    }

    /**
     * Late-submission rule (OQ-05): returns true when the submission is late.
     * Throws when the deadline has passed and late submissions are not allowed.
     */
    public static function checkDeadline(int $offeringId, string $type): bool
    {
        $deadline = self::deadline($offeringId, $type);
        if ($deadline === null || strtotime($deadline['due_at']) >= time()) {
            return false;
        }
        if ((int) $deadline['allow_late'] !== 1) {
            throw new Rules\RuleViolation('deadline_passed', 'The deadline has passed');
        }
        return true;
    }
}
