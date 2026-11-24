<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ScoreCalculator;
use Tests\TestCase;

/** Section 9.2 / 9.5 arithmetic (FR-EVL-06, FR-EVL-07, FR-DEC-04). */
final class ScoreCalculatorTest extends TestCase
{
    private const DEFAULT_WEIGHTS = [1 => 15, 2 => 15, 3 => 15, 4 => 25, 5 => 15, 6 => 15];

    public function testDefaultRubricWeightsSumTo100(): void
    {
        $this->assertTrue(ScoreCalculator::weightsValid(self::DEFAULT_WEIGHTS));
    }

    public function testWeightsNotSummingTo100AreRejected(): void
    {
        $this->assertFalse(ScoreCalculator::weightsValid([1 => 15, 2 => 15, 3 => 15, 4 => 25, 5 => 15, 6 => 14.99]));
        $this->assertFalse(ScoreCalculator::weightsValid([1 => 60, 2 => 50]));
        $this->assertFalse(ScoreCalculator::weightsValid([1 => 100, 2 => 0]));
        $this->assertFalse(ScoreCalculator::weightsValid([]));
    }

    public function testDecimalWeightsSummingTo100AreAccepted(): void
    {
        $this->assertTrue(ScoreCalculator::weightsValid([1 => 33.33, 2 => 33.33, 3 => 33.34]));
    }

    public function testCriterionScoreFormula(): void
    {
        $this->assertSame(15.0, ScoreCalculator::criterionScore(5, 15));
        $this->assertSame(12.0, ScoreCalculator::criterionScore(4, 15));
        $this->assertSame(5.0, ScoreCalculator::criterionScore(1, 25));
        $this->assertSame(6.67, ScoreCalculator::criterionScore(1, 33.33)); // 6.666 -> 6.67 half-up
    }

    public function testRatingOutsideOneToFiveIsRejected(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, fn () => ScoreCalculator::criterionScore(0, 15));
        $this->assertThrows(\InvalidArgumentException::class, fn () => ScoreCalculator::criterionScore(6, 15));
    }

    public function testWorkedExampleFromDesignDocIs79(): void
    {
        $r = ScoreCalculator::total([1 => 4, 2 => 4, 3 => 3, 4 => 5, 5 => 4, 6 => 3], self::DEFAULT_WEIGHTS);
        $this->assertSame(79.0, $r['total']);
        $this->assertSame(25.0, $r['per_criterion'][4]);
    }

    public function testTotalRangeIs20To100(): void
    {
        $this->assertSame(100.0, ScoreCalculator::total(array_fill_keys(range(1, 6), 5), self::DEFAULT_WEIGHTS)['total']);
        $this->assertSame(20.0, ScoreCalculator::total(array_fill_keys(range(1, 6), 1), self::DEFAULT_WEIGHTS)['total']);
    }

    public function testMissingRatingIsIncompleteEvaluation(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, fn () => ScoreCalculator::total([1 => 5, 2 => 5], self::DEFAULT_WEIGHTS), 'Missing rating');
    }

    public function testAverageOfTwoEvaluations(): void
    {
        $this->assertSame(81.5, ScoreCalculator::average(79.0, 84.0));
        $this->assertSame(80.01, ScoreCalculator::average(80.01, 80.0)); // 80.005 -> 80.01 half-up
        $this->assertThrows(\InvalidArgumentException::class, fn () => ScoreCalculator::average(101, 50));
    }

    public function testRecommendationDisagreementIsFlagged(): void
    {
        $this->assertTrue(ScoreCalculator::recommendationsDiffer('approved', 'major_revisions'));
        $this->assertFalse(ScoreCalculator::recommendationsDiffer('approved_minor', 'approved_minor'));
        $this->assertThrows(\InvalidArgumentException::class, fn () => ScoreCalculator::recommendationsDiffer('approved', 'maybe'));
    }

    public function testJustificationRequiredWhenAdminDiffersFromCommittee(): void
    {
        // Default OQ-15 'any': differs from at least one member.
        $this->assertFalse(ScoreCalculator::requiresJustification('approved', 'approved', 'approved'));
        $this->assertTrue(ScoreCalculator::requiresJustification('approved', 'approved', 'major_revisions'));
        $this->assertTrue(ScoreCalculator::requiresJustification('not_approved', 'approved', 'approved'));
        // Alternative 'both'.
        $this->assertFalse(ScoreCalculator::requiresJustification('approved', 'approved', 'major_revisions', 'both'));
        $this->assertTrue(ScoreCalculator::requiresJustification('not_approved', 'approved', 'major_revisions', 'both'));
    }
}
