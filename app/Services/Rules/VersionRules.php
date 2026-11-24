<?php

declare(strict_types=1);

namespace App\Services\Rules;

use App\Core\Database;

/**
 * Section 3.2 / Section 6: submitted topic versions and all proposal versions are never
 * overwritten. Mirrors trg_ts_immutable and trg_pv_immutable.
 */
final class VersionRules
{
    public static function assertTopicSubmissionEditable(int $submissionId): void
    {
        $isDraft = Database::connection()->value('SELECT is_draft FROM topic_submissions WHERE id = :id', ['id' => $submissionId]);
        if ($isDraft === null) {
            throw new RuleViolation('submission_missing', 'Topic submission not found');
        }
        if ((int) $isDraft === 0) {
            throw new RuleViolation('submission_immutable', 'Submitted topic versions are immutable; create a new version');
        }
    }

    /** Only the workflow status of a proposal version may change. */
    public static function assertProposalVersionUpdate(array $columns): void
    {
        $allowed = ['version_status'];
        $illegal = array_diff(array_keys($columns), $allowed);
        if ($illegal !== []) {
            throw new RuleViolation('proposal_version_immutable', 'Proposal versions are immutable; upload a new version');
        }
    }
}
