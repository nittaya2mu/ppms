<?php
/** @var array $ev @var array $criteria @var array $scores @var array $comments @var ?array $peer */
use App\Services\EvaluationService;
use App\Services\ScoreCalculator;

$this->layout('layouts/app', ['title' => __('menu.evaluation')]);
$locked = $ev['status'] === 'submitted';
$scale = [1 => 'Poor', 2 => 'Needs Improvement', 3 => 'Satisfactory', 4 => 'Good', 5 => 'Excellent'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="small text-secondary"><?= e($ev['project_code']) ?> · <?= e($ev['group_code']) ?> · <?= e(__('evaluations.round', ['n' => (int) $ev['round_no']])) ?></div>
        <h1 class="h4 mb-0"><?= e($ev['title_th']) ?></h1>
        <div class="small"><?= e(__('evaluations.member', ['n' => (int) $ev['member_slot']])) ?> · <?= e(__('evaluations.deadline')) ?>: <?= e(thai_date($ev['evaluation_deadline'], true)) ?></div>
    </div>
    <div class="text-end">
        <?= status_badge($ev['status']) ?><br>
        <a class="btn btn-sm btn-outline-primary mt-2" href="<?= e(url('/files/' . (int) $ev['file_id'])) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Proposal v<?= (int) $ev['version_no'] ?></a>
    </div>
</div>

<?php if ($locked): ?><div class="alert alert-info"><i class="bi bi-lock"></i> <?= e(__('evaluations.locked')) ?></div><?php endif; ?>
<?php if ($ev['status'] === 'reopened'): ?><div class="alert alert-warning"><?= e(__('evaluations.reopened_note')) ?>: <?= e($ev['reopen_reason']) ?></div><?php endif; ?>
<div class="alert alert-light border small"><i class="bi bi-shield-lock"></i> <?= e(__('evaluations.independence')) ?></div>

<form method="post" action="<?= e(url('/evaluations/' . (int) $ev['id'])) ?>" id="evalForm">
    <?= csrf_field() ?>
    <div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>เกณฑ์การประเมิน</th><th class="text-end">น้ำหนัก</th><?php foreach ($scale as $n => $label): ?><th class="text-center small"><?= $n ?><br><span class="fw-normal"><?= e($label) ?></span></th><?php endforeach; ?><th class="text-end">คะแนน</th></tr></thead>
        <tbody>
        <?php foreach ($criteria as $c): $cid = (int) $c['id']; $cur = (int) ($scores[$cid]['rating'] ?? 0); ?>
            <tr>
                <td><?= e($c['name_th']) ?><div class="small text-secondary"><?= e($c['name_en']) ?></div><?php if ($c['description']): ?><div class="small text-secondary"><?= e($c['description']) ?></div><?php endif; ?></td>
                <td class="text-end"><?= e($c['weight']) ?>%</td>
                <?php foreach ($scale as $n => $label): ?>
                    <td class="text-center"><input class="form-check-input js-rating" type="radio" name="rating[<?= $cid ?>]" value="<?= $n ?>" data-weight="<?= e($c['weight']) ?>" aria-label="<?= e($c['name_th'] . ' ' . $n) ?>" <?= $cur === $n ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>></td>
                <?php endforeach; ?>
                <td class="text-end js-criterion-score"><?= $cur ? e(number_format(ScoreCalculator::criterionScore($cur, $c['weight']), 2)) : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="7" class="text-end"><?= e(__('evaluations.total')) ?> (S)</th><th class="text-end fs-5" id="evalTotal"><?= $ev['total_score'] !== null ? e($ev['total_score']) : '-' ?></th></tr></tfoot>
    </table></div></div>

    <div class="card mb-3"><div class="card-body">
        <h2 class="h6"><?= e(__('evaluations.recommendation')) ?> <span class="text-danger">*</span></h2>
        <?php foreach (ScoreCalculator::RECOMMENDATIONS as $rec): ?>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="recommendation" value="<?= e($rec) ?>" id="rec_<?= e($rec) ?>" <?= $ev['recommendation'] === $rec ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                <label class="form-check-label" for="rec_<?= e($rec) ?>"><?= e(__('decision.' . $rec)) ?></label>
            </div>
        <?php endforeach; ?>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h2 class="h6"><?= e(__('evaluations.comments')) ?> <span class="text-danger">*</span></h2>
        <?php foreach (EvaluationService::COMMENT_TYPES as $t): $c = $comments[$t] ?? null; ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between"><label class="form-label fw-semibold small" for="c_<?= e($t) ?>"><?= e(__('comment_type.' . $t)) ?></label>
                    <div class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" name="critical[]" value="<?= e($t) ?>" id="k_<?= e($t) ?>" <?= $c && (int) $c['is_critical'] === 1 ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="k_<?= e($t) ?>"><?= e(__('evaluations.mark_critical')) ?></label></div></div>
                <textarea class="form-control" rows="3" id="c_<?= e($t) ?>" name="comment[<?= e($t) ?>]" <?= $locked ? 'readonly' : '' ?>><?= e($c['content'] ?? '') ?></textarea>
            </div>
        <?php endforeach; ?>
    </div></div>

    <?php if (!$locked): ?>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button class="btn btn-outline-primary" name="do" value="draft"><i class="bi bi-save"></i> <?= e(__('evaluations.save_draft')) ?></button>
            <button class="btn btn-ppms" name="do" value="submit" data-confirm-button="<?= e(__('evaluations.submit_confirm')) ?>"><i class="bi bi-lock"></i> <?= e(__('evaluations.submit')) ?></button>
        </div>
    <?php endif; ?>
</form>

<?php if ($peer): ?>
    <div class="card"><div class="card-body">
        <h2 class="h6"><?= e(__('evaluations.both_results')) ?></h2>
        <?= $this->partial('projects/_evaluation_summary', ['summary' => $peer, 'criteria' => $criteria, 'showNames' => true, 'canReopen' => false, 'base' => '']) ?>
    </div></div>
<?php endif; ?>
