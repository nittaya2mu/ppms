<?php

declare(strict_types=1);

namespace App\Services\Rules;

use App\Core\Database;

/**
 * Section 3.4 / FR-EVL-04: a submitted evaluation is locked; only an administrator can
 * reopen it, with a reason. Mirrors trg_eval_locked and trg_scores_* / trg_comments_*.
 */
final class EvaluationRules
{
    /** Call before changing an evaluation, its scores or its comments. */
    public static function assertEditable(int $evaluationId): void
    {
        $status = Database::connection()->value(
            'SELECT status FROM committee_evaluations WHERE id = :id',
            ['id' => $evaluationId]
        );
        if ($status === null) {
            throw new RuleViolation('evaluation_missing', 'Evaluation not found');
        }
        if ($status === 'submitted') {
            throw new RuleViolation('evaluation_locked', 'Evaluation is locked after submission');
        }
    }

    public static function assertReopenable(int $evaluationId, ?int $adminId, ?string $reason): void
    {
        $status = Database::connection()->value(
            'SELECT status FROM committee_evaluations WHERE id = :id',
            ['id' => $evaluationId]
        );
        if ($status !== 'submitted') {
            throw new RuleViolation('evaluation_not_submitted', 'Only a submitted evaluation can be reopened');
        }
        if ($adminId === null || trim((string) $reason) === '') {
            throw new RuleViolation('reopen_reason', 'Reopening requires an administrator and a reason');
        }
    }
}
