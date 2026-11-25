<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The department's presentation evaluation form (แบบฟอร์ม 2: แบบประเมินหัวข้อโครงงาน).
 * Each item is graded A-F (A=4 ... F=0); an item's points = grade / 4 x its maximum.
 * Presentation 40 + Theory and related work 30 + Document completeness 30 = 100.
 */
final class PresentationForm
{
    /** group => [label, max points, items => label] */
    public const GROUPS = [
        'presentation' => ['การนำเสนอ', 40, [
            'pres_completeness' => 'ความครบถ้วนของ Presentation',
            'pres_delivery'     => 'การนำเสนอของนิสิต',
            'pres_punctuality'  => 'การตรงต่อเวลา',
            'pres_dress'        => 'การแต่งกาย',
            'pres_qa'           => 'การตอบคำถาม',
        ]],
        'theory' => ['ทฤษฎีและงานวิจัยที่เกี่ยวข้อง', 30, [
            'theory_completeness'  => 'ความสมบูรณ์ ครบถ้วน ของทฤษฎี',
            'theory_understanding' => 'ความเข้าใจของทฤษฎีและงานวิจัยที่เกี่ยวข้อง',
        ]],
        'document' => ['ความครบถ้วนของเนื้อหาในเอกสาร', 30, [
            'doc_format'  => 'ความถูกต้องตามรูปแบบที่กำหนด',
            'doc_content' => 'ความครบถ้วนเนื้อหาของเอกสาร (บทที่ 3)',
        ]],
    ];

    public const GRADES = ['A' => 4.0, 'B+' => 3.5, 'B' => 3.0, 'C+' => 2.5, 'C' => 2.0, 'D+' => 1.5, 'D' => 1.0, 'F' => 0.0];

    /** Suitability checks at the top of the form (เหมาะสม / ไม่เหมาะสม). */
    public const CHECKS = [
        'objectives_ok'  => 'วัตถุประสงค์',
        'scope_ok'       => 'ขอบเขตของโครงงาน',
        'methodology_ok' => 'วิธีการดำเนินงาน',
        'literature_ok'  => 'เอกสารและงานวิจัยที่เกี่ยวข้อง',
    ];

    public const RESULTS = ['pass', 'revise', 'fail'];

    /** Total score >= threshold => letter grade (KU scale). */
    public const LETTER_SCALE = [80 => 'A', 75 => 'B+', 70 => 'B', 65 => 'C+', 60 => 'C', 55 => 'D+', 50 => 'D', 0 => 'F'];

    /** @return array<string,float> item key => maximum points */
    public static function itemMax(): array
    {
        $out = [];
        foreach (self::GROUPS as [$label, $max, $items]) {
            foreach ($items as $key => $_) {
                $out[$key] = $max / count($items);
            }
        }
        return $out;
    }

    public static function itemLabel(string $key): string
    {
        foreach (self::GROUPS as [$label, $max, $items]) {
            if (isset($items[$key])) {
                return $items[$key];
            }
        }
        return $key;
    }

    /**
     * @param array<string,string> $grades item key => letter grade
     * @return array{items: array<string,float>, total: float, letter: string, complete: bool}
     */
    public static function score(array $grades): array
    {
        $items = [];
        $raw = 0.0;
        foreach (self::itemMax() as $key => $max) {
            $g = $grades[$key] ?? null;
            if ($g !== null && isset(self::GRADES[$g])) {
                $points = self::GRADES[$g] / 4 * $max;
                $raw += $points;
                $items[$key] = round($points, 2);   // display only; the total is rounded once
            }
        }
        $total = round($raw + 1e-9, 2);
        return ['items' => $items, 'total' => $total, 'letter' => self::letter($total), 'complete' => count($items) === count(self::itemMax())];
    }

    public static function letter(float $total): string
    {
        foreach (self::LETTER_SCALE as $min => $letter) {
            if ($total >= $min) {
                return $letter;
            }
        }
        return 'F';
    }

    /** Category of a final decision, to compare with evaluator results. */
    public static function decisionResult(string $decision): string
    {
        return ['approved' => 'pass', 'approved_minor' => 'revise', 'major_revisions' => 'revise', 'not_approved' => 'fail'][$decision] ?? 'revise';
    }
}
