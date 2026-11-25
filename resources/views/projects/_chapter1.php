<?php /** Chapter 1 text of a proposal version. Var: $v */ ?>
<div class="ppms-chapter1 small border rounded p-3 mt-2">
    <div class="fw-bold text-center">บทที่ 1 บทนำ</div>
    <?php foreach (\App\Services\ProposalService::CHAPTER1 as $i => $k): ?>
        <div class="fw-semibold mt-2">1.<?= $i + 1 ?> <?= e(__('chapter1.' . $k)) ?></div>
        <div class="ms-3"><?= nl2br(e($v[$k] ?? '')) ?></div>
    <?php endforeach; ?>
</div>
