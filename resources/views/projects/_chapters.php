<?php
/** Chapters 1-3 typed on the web. Var: $content (ProposalDraftService structure), ?$only (chapter number) */
$only = $only ?? null;
?>
<div class="ppms-chapter1 small border rounded p-3 mt-2">
    <?php if ($only === null || $only === 1): ?>
        <div class="fw-bold text-center">บทที่ 1 บทนำ</div>
        <?php foreach (\App\Services\ProposalService::CHAPTER1 as $i => $k): ?>
            <div class="fw-semibold mt-2">1.<?= $i + 1 ?> <?= e(__('chapter1.' . $k)) ?></div>
            <div class="ms-3"><?= nl2br(e($content['ch1'][$k] ?? '')) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php foreach ([2, 3] as $ch): if ($only !== null && $only !== $ch) { continue; } $c = $content["ch$ch"] ?? null; if (!$c || !$c['sections']) { continue; } ?>
        <div class="fw-bold text-center mt-3">บทที่ <?= $ch ?> <?= e($c['title']) ?></div>
        <?php foreach ($c['sections'] as $i => $s): ?>
            <div class="fw-semibold mt-2"><?= $ch ?>.<?= $i + 1 ?> <?= e($s['heading']) ?></div>
            <div class="ms-3"><?= nl2br(e($s['content'])) ?></div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
