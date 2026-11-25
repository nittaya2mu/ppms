<?php
/** Proposal chapters 1-3 in the department layout (B_05_Chapter_01). Vars: FormService::context + $content, $label */
use App\Services\ProposalService;

$this->layout('layouts/print', ['title' => 'Proposal ' . $project['project_code']]);
$para = function (string $text): string {
    $out = '';
    foreach (preg_split('/\R{2,}|\R/', trim($text)) as $p) {
        if (trim($p) !== '') {
            $out .= '<p class="ppms-para">' . e(trim($p)) . '</p>';
        }
    }
    return $out;
};
?>
<div class="small text-secondary d-print-none mb-2"><?= e($project['project_code']) ?> · <?= e($label) ?></div>
<div class="ppms-doc">
    <section class="ppms-chapter">
        <div class="ppms-chapter-no">บทที่ 1</div>
        <div class="ppms-chapter-title">บทนำ</div>
        <?php foreach (ProposalService::CHAPTER1 as $i => $k): ?>
            <h3 class="ppms-sec">1.<?= $i + 1 ?> <?= e(__('chapter1.' . $k)) ?></h3>
            <?= $para($content['ch1'][$k] ?? '') ?>
        <?php endforeach; ?>
    </section>
    <?php foreach ([2, 3] as $ch): $c = $content["ch$ch"]; if (!$c['sections']) { continue; } ?>
        <section class="ppms-chapter">
            <div class="ppms-chapter-no">บทที่ <?= $ch ?></div>
            <div class="ppms-chapter-title"><?= e($c['title']) ?></div>
            <?php foreach ($c['sections'] as $i => $s): ?>
                <h3 class="ppms-sec"><?= $ch ?>.<?= $i + 1 ?> <?= e($s['heading']) ?></h3>
                <?= $para($s['content']) ?>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>
