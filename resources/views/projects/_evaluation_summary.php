<?php
/** @var array $summary @var array $criteria @var bool $showNames @var bool $canReopen @var string $base */
$a = $summary['assignment'];
$evals = $summary['evaluations'];
$byCriterion = [];
foreach ($evals as $i => $ev) {
    foreach ($ev['scores'] as $s) {
        $byCriterion[(int) $s['criterion_id']][$i] = $s;
    }
}
?>
<div class="row g-2 mb-2 text-center">
    <?php foreach ($evals as $i => $ev): ?>
        <div class="col-4"><div class="border rounded p-2"><div class="small text-secondary"><?= e(__('evaluations.member', ['n' => (int) $ev['member_slot']])) ?><?= $showNames ? ' · ' . e(full_name($ev)) : '' ?></div>
            <div class="fs-5 fw-semibold">S<?= (int) $ev['member_slot'] ?> = <?= e($ev['total_score']) ?></div>
            <div class="small"><?= e(__('decision.' . $ev['recommendation'])) ?></div></div></div>
    <?php endforeach; ?>
    <div class="col-4"><div class="border rounded p-2 border-success"><div class="small text-secondary"><?= e(__('final.average')) ?></div>
        <div class="fs-5 fw-semibold">S<sub>avg</sub> = <?= e($a['average_score']) ?></div>
        <div class="small"><?= (int) $a['recommendations_differ'] === 1 ? '<span class="text-danger">' . e(__('final.disagreement')) . '</span>' : e(__('final.agreement')) ?></div></div></div>
</div>
<div class="table-responsive"><table class="table table-sm small mb-2">
    <thead><tr><th>เกณฑ์</th><th class="text-end">น้ำหนัก</th><?php foreach ($evals as $ev): ?><th class="text-end">คนที่ <?= (int) $ev['member_slot'] ?> (1-5 → คะแนน)</th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($evals[0]['scores'] as $s): $cid = (int) $s['criterion_id']; ?>
        <tr><td><?= e($s['name_th']) ?></td><td class="text-end"><?= e($s['weight_snapshot']) ?></td>
            <?php foreach ($evals as $i => $ev): $x = $byCriterion[$cid][$i] ?? null; ?><td class="text-end"><?= $x ? (int) $x['rating'] . ' → ' . e($x['weighted_score']) : '-' ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php foreach ($evals as $ev): if (!$ev['comments']) { continue; } ?>
    <details class="small mb-1" open><summary><?= e(__('evaluations.comments_of', ['n' => (int) $ev['member_slot']])) ?></summary>
        <dl class="row mb-0 mt-1">
            <?php foreach (\App\Services\EvaluationService::COMMENT_TYPES as $t): $c = $ev['comments'][$t] ?? null; if (!$c) { continue; } ?>
                <dt class="col-md-3"><?= e(__('comment_type.' . $t)) ?><?= (int) $c['is_critical'] === 1 ? ' <span class="badge text-bg-danger">' . e(__('evaluations.critical')) . '</span>' : '' ?></dt>
                <dd class="col-md-9 <?= (int) $c['is_critical'] === 1 ? 'fw-semibold' : '' ?>"><?= nl2br(e($c['content'])) ?></dd>
            <?php endforeach; ?>
        </dl>
    </details>
    <?php if ($canReopen): ?>
        <details class="small mb-2"><summary class="text-secondary"><?= e(__('evaluations.reopen_title', ['n' => (int) $ev['member_slot']])) ?></summary>
            <form method="post" action="<?= e(url("$base/evaluations/reopen")) ?>" class="row g-2 mt-1" data-confirm="<?= e(__('evaluations.reopen_confirm')) ?>">
                <?= csrf_field() ?><input type="hidden" name="evaluation_id" value="<?= (int) $ev['id'] ?>">
                <div class="col-md-9"><input class="form-control form-control-sm" name="reason" required placeholder="<?= e(__('groups.reason')) ?>"></div>
                <div class="col-md-3"><button class="btn btn-sm btn-outline-danger w-100"><?= e(__('evaluations.reopen')) ?></button></div>
            </form></details>
    <?php endif; ?>
<?php endforeach; ?>
