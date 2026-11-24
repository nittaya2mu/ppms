<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Evaluation arithmetic of Sections 9.2 and 9.5 (FR-EVL-06).
 * Uses integer hundredths internally so results are exact to 2 decimals (DD-09).
 */
final class ScoreCalculator
{
    public const RECOMMENDATIONS = ['approved', 'approved_minor', 'major_revisions', 'not_approved'];

    /**
     * Validates that active criterion weights sum to exactly 100.00 (FR-EVL-07).
     * @param array<int|string,float|int|string> $weights
     */
    public static function weightsValid(array $weights): bool
    {
        if ($weights === []) {
            return false;
        }
        $sum = 0;
        foreach ($weights as $w) {
            if (!is_numeric($w) || (float) $w <= 0) {
                return false;
            }
            $sum += self::toHundredths($w);
        }
        return $sum === 10000;
    }

    /** Criterion Score = (Rating / 5) x Weight, rounded half-up to 2 decimals. */
    public static function criterionScore(int $rating, float|int|string $weight): float
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Rating must be 1-5');
        }
        // (rating * weight_hundredths) / 5, half-up
        $hundredths = intdiv($rating * self::toHundredths($weight) * 2 + 5, 10);
        return (float) ($hundredths / 100);
    }

    /**
     * Total S = sum of criterion scores, 0-100.
     * @param array<int|string,int> $ratings criterion_id => rating
     * @param array<int|string,float|int|string> $weights criterion_id => weight
     * @return array{total:float, per_criterion:array<int|string,float>}
     */
    public static function total(array $ratings, array $weights): array
    {
        if (!self::weightsValid($weights)) {
            throw new InvalidArgumentException('Criterion weights must sum to 100');
        }
        $missing = array_diff_key($weights, $ratings);
        if ($missing !== []) {
            throw new InvalidArgumentException('Missing rating for criteria: ' . implode(', ', array_keys($missing)));
        }
        $per = [];
        $sum = 0;
        foreach ($weights as $id => $weight) {
            $score = self::criterionScore((int) $ratings[$id], $weight);
            $per[$id] = $score;
            $sum += (int) round($score * 100);
        }
        return ['total' => (float) ($sum / 100), 'per_criterion' => $per];
    }

    /** S_avg = (S1 + S2) / 2, half-up to 2 decimals. */
    public static function average(float $s1, float $s2): float
    {
        foreach ([$s1, $s2] as $s) {
            if ($s < 0 || $s > 100) {
                throw new InvalidArgumentException('Scores must be within 0-100');
            }
        }
        $sum = self::toHundredths($s1) + self::toHundredths($s2);
        return (float) (intdiv($sum + 1, 2) / 100);
    }

    public static function recommendationsDiffer(string $r1, string $r2): bool
    {
        foreach ([$r1, $r2] as $r) {
            if (!in_array($r, self::RECOMMENDATIONS, true)) {
                throw new InvalidArgumentException("Unknown recommendation $r");
            }
        }
        return $r1 !== $r2;
    }

    /**
     * Does the admin's final decision require a justification? (FR-DEC-04, OQ-15)
     * $mode 'any'  = decision differs from at least one recommendation (default)
     * $mode 'both' = decision differs from both recommendations
     */
    public static function requiresJustification(string $decision, string $r1, string $r2, string $mode = 'any'): bool
    {
        return $mode === 'both'
            ? ($decision !== $r1 && $decision !== $r2)
            : ($decision !== $r1 || $decision !== $r2);
    }

    private static function toHundredths(float|int|string $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
