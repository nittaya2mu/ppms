<?php
/** Presentation evaluation results (แบบฟอร์ม 2) of one completed round.
 *  @var array $summary @var bool $showNames @var bool $canReopen @var string $base */
use App\Services\PresentationForm;

$a = $summary['assignment'];
$evals = $summary['evaluations'];
?>
<div class="row g-2 mb-2 text-center">
    <?php foreach ($evals as $ev): ?>
        <div class="col-md-3 col-6"><div class="border rounded p-2 h-100">
            <div class="small text-secondary"><?= e(__('evaluator_role.' . $ev['evaluator_role'])) ?><?= $showNames ? ' · ' . e(full_name($ev)) : '' ?></div>
            <div class="fs-5 fw-semibold"><?= e($ev['total_score']) ?> <span class="badge text-bg-light border"><?= e($ev['letter_grade']) ?></span></div>
            <div class="small"><?= e(__('presentation_result.' . $ev['result'])) ?></div>
        </div></div>
    <?php endforeach; ?>
    <div class="col-md-3 col-6"><div class="border border-success rounded p-2 h-100">
        <div class="small text-secondary"><?= e(__('final.average')) ?></div>
        <div class="fs-5 fw-semibold"><?= e($a['average_score']) ?> <span class="badge text-bg-light border"><?= e(PresentationForm::letter((float) $a['average_score'])) ?></span></div>
        <?php if ((int) $a['recommendations_differ'] === 1): ?><div class="small text-danger"><?= e(__('evaluations.results_differ')) ?></div><?php endif; ?>
    </div></div>
</div>

<div class="table-responsive"><table class="table table-sm small align-middle mb-2">
    <thead><tr><th><?= e(__('evaluations.item')) ?></th><th class="text-end"><?= e(__('evaluations.max_points')) ?></th>
        <?php foreach ($evals as $ev): ?><th class="text-end"><?= e(__('evaluator_role.' . $ev['evaluator_role'])) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php $max = PresentationForm::itemMax(); foreach (PresentationForm::GROUPS as [$glabel, $gmax, $items]): ?>
        <tr class="table-light"><td colspan="<?= 2 + count($evals) ?>" class="fw-semibold"><?= e($glabel) ?> (<?= (int) $gmax ?> คะแนน)</td></tr>
        <?php foreach ($items as $key => $label): ?>
            <tr><td class="ps-3"><?= e($label) ?></td><td class="text-end"><?= e(rtrim(rtrim(number_format($max[$key], 2), '0'), '.')) ?></td>
                <?php foreach ($evals as $ev): $g = $ev['grades'][$key] ?? null; ?>
                    <td class="text-end"><?= $g !== null ? e($g) . ' → ' . e(number_format(PresentationForm::GRADES[$g] / 4 * $max[$key], 2)) : '-' ?></td>
                <?php endforeach; ?></tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <tr class="fw-semibold"><td><?= e(__('evaluations.total')) ?></td><td class="text-end">100</td>
        <?php foreach ($evals as $ev): ?><td class="text-end"><?= e($ev['total_score']) ?> (<?= e($ev['letter_grade']) ?>)</td><?php endforeach; ?></tr>
    <?php foreach (PresentationForm::CHECKS as $ck => $clabel): ?>
        <tr><td><?= e($clabel) ?></td><td></td>
            <?php foreach ($evals as $ev): ?><td class="text-end"><?= $ev[$ck] === null ? '-' : ((int) $ev[$ck] === 1 ? e(__('evaluations.suitable')) : '<span class="text-danger">' . e(__('evaluations.not_suitable')) . '</span>') ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
</table></div>

<?php foreach ($evals as $ev): ?>
    <?php if ($ev['suggestions']): ?>
        <div class="small mb-1"><strong><?= e(__('evaluations.suggestions_of', ['who' => __('evaluator_role.' . $ev['evaluator_role'])])) ?>:</strong> <?= nl2br(e($ev['suggestions'])) ?></div>
    <?php endif; ?>
    <?php if ($canReopen && $ev['status'] === 'submitted'): ?>
        <details class="small mb-2"><summary class="text-secondary"><?= e(__('evaluations.reopen_title', ['who' => __('evaluator_role.' . $ev['evaluator_role'])])) ?></summary>
            <form method="post" action="<?= e(url("$base/evaluations/reopen")) ?>" class="row g-2 mt-1" data-confirm="<?= e(__('evaluations.reopen_confirm')) ?>">
                <?= csrf_field() ?><input type="hidden" name="evaluation_id" value="<?= (int) $ev['id'] ?>">
                <div class="col-md-9"><input class="form-control form-control-sm" name="reason" required placeholder="<?= e(__('groups.reason')) ?>"></div>
                <div class="col-md-3"><button class="btn btn-sm btn-outline-danger w-100"><?= e(__('evaluations.reopen')) ?></button></div>
            </form></details>
    <?php endif; ?>
<?php endforeach; ?>
