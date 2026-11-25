<?php
/** แบบฟอร์ม 2: แบบประเมินหัวข้อโครงงาน (การนำเสนอ). @var array $ev @var array $grades @var array $version @var array $files @var ?array $peer */
use App\Services\PresentationForm;

$this->layout('layouts/app', ['title' => __('menu.evaluation')]);
$locked = $ev['status'] === 'submitted';
$dis = $locked ? 'disabled' : '';
$max = PresentationForm::itemMax();
$fmt = fn (float $n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="small text-secondary"><?= e($ev['project_code']) ?> · <?= e($ev['group_code']) ?> · <?= e(__('evaluations.round', ['n' => (int) $ev['round_no']])) ?></div>
        <h1 class="h4 mb-0"><?= e($ev['title_th']) ?></h1>
        <?php if ($ev['title_en']): ?><div class="text-secondary"><?= e($ev['title_en']) ?></div><?php endif; ?>
        <div class="small"><?= e(__('evaluations.form2_title')) ?> · <?= e(__('evaluator_role.' . $ev['evaluator_role'])) ?> · <?= e(__('evaluations.deadline')) ?>: <?= e(thai_date($ev['evaluation_deadline'], true)) ?></div>
    </div>
    <div class="text-end"><?= status_badge($ev['status']) ?></div>
</div>

<div class="card mb-3"><div class="card-body">
    <h2 class="h6"><i class="bi bi-folder2-open"></i> <?= e(__('evaluations.documents')) ?> (Proposal v<?= (int) $ev['version_no'] ?>)</h2>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($files as $ch => $f): if (!$f) { continue; } ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/files/' . (int) $f['id'])) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> <?= e(__('proposals.chapter_n', ['n' => $ch])) ?> <span class="small text-secondary">(<?= e($f['original_name']) ?>)</span></a>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($version['ch1_background'])): ?>
        <details class="mt-2"><summary class="small fw-semibold"><?= e(__('chapters.content_web')) ?></summary><?= $this->partial('projects/_chapters', ['content' => \App\Services\ProposalDraftService::fromVersion($version)]) ?></details>
        <a class="small" href="<?= e(url('/projects/' . (int) $ev['project_id'] . '/proposal/print?version=' . (int) $ev['version_no'])) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> <?= e(__('chapters.print')) ?></a>
    <?php endif; ?>
</div></div>

<?php if ($locked): ?><div class="alert alert-info"><i class="bi bi-lock"></i> <?= e(__('evaluations.locked')) ?></div><?php endif; ?>
<?php if ($ev['status'] === 'reopened'): ?><div class="alert alert-warning"><?= e(__('evaluations.reopened_note')) ?>: <?= e($ev['reopen_reason']) ?></div><?php endif; ?>
<div class="alert alert-light border small"><i class="bi bi-shield-lock"></i> <?= e(__('evaluations.independence')) ?></div>

<form method="post" action="<?= e(url('/evaluations/' . (int) $ev['id'])) ?>" id="evalForm">
    <?= csrf_field() ?>
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6"><?= e(__('evaluations.proposal_checks')) ?> <span class="text-danger">*</span></h2>
        <div class="row g-2 small">
            <?php $i = 1; foreach (PresentationForm::CHECKS as $ck => $clabel): $val = old("check.$ck", $ev[$ck] === null ? null : (string) $ev[$ck]); ?>
                <div class="col-md-6 d-flex justify-content-between border-bottom py-1"><span><?= $i++ ?>. <?= e($clabel) ?></span><span>
                    <span class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="check[<?= e($ck) ?>]" id="<?= e($ck) ?>1" value="1" <?= $val === '1' ? 'checked' : '' ?> <?= $dis ?>><label class="form-check-label" for="<?= e($ck) ?>1"><?= e(__('evaluations.suitable')) ?></label></span>
                    <span class="form-check form-check-inline mb-0 me-0"><input class="form-check-input" type="radio" name="check[<?= e($ck) ?>]" id="<?= e($ck) ?>0" value="0" <?= $val === '0' ? 'checked' : '' ?> <?= $dis ?>><label class="form-check-label" for="<?= e($ck) ?>0"><?= e(__('evaluations.not_suitable')) ?></label></span>
                </span></div>
            <?php endforeach; ?>
        </div>
    </div></div>

    <div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th><?= e(__('evaluations.item')) ?></th><th class="text-end"><?= e(__('evaluations.max_points')) ?></th><th style="width:9rem"><?= e(__('evaluations.grade')) ?></th><th class="text-end"><?= e(__('evaluations.points')) ?></th></tr></thead>
        <tbody>
        <?php foreach (PresentationForm::GROUPS as [$glabel, $gmax, $items]): ?>
            <tr class="table-light"><td colspan="4" class="fw-semibold"><?= e($glabel) ?> (<?= (int) $gmax ?> คะแนน)</td></tr>
            <?php $n = 1; foreach ($items as $key => $label): $g = old("grade.$key", $grades[$key] ?? ''); ?>
                <tr><td class="ps-3"><?= $n++ ?>. <?= e($label) ?></td><td class="text-end"><?= e($fmt($max[$key])) ?></td>
                    <td><select class="form-select form-select-sm js-grade" name="grade[<?= e($key) ?>]" data-max="<?= e((string) $max[$key]) ?>" <?= $dis ?>>
                        <option value="">-</option>
                        <?php foreach (PresentationForm::GRADES as $letter => $point): ?><option value="<?= e($letter) ?>" data-point="<?= e((string) $point) ?>" <?= $g === $letter ? 'selected' : '' ?>><?= e($letter) ?> (<?= e((string) $point) ?>)</option><?php endforeach; ?>
                    </select></td>
                    <td class="text-end js-grade-points">-</td></tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="fw-semibold"><td><?= e(__('evaluations.total')) ?></td><td class="text-end">100</td><td><?= e(__('evaluations.letter_grade')) ?>: <span id="presLetter">-</span></td><td class="text-end fs-5" id="presTotal">0.00</td></tr></tfoot>
    </table></div>
    <div class="card-footer small text-secondary"><?= e(__('evaluations.grade_note')) ?></div></div>

    <div class="card mb-3"><div class="card-body">
        <h2 class="h6"><?= e(__('evaluations.result')) ?> <span class="text-danger">*</span></h2>
        <?php $res = old('result', $ev['result'] ?? ''); foreach (PresentationForm::RESULTS as $rv): ?>
            <div class="form-check"><input class="form-check-input" type="radio" name="result" id="res_<?= e($rv) ?>" value="<?= e($rv) ?>" <?= $res === $rv ? 'checked' : '' ?> <?= $dis ?>>
                <label class="form-check-label" for="res_<?= e($rv) ?>"><strong><?= e(__('presentation_result.' . $rv)) ?></strong> <span class="small text-secondary"><?= e(__('presentation_result_desc.' . $rv)) ?></span></label></div>
        <?php endforeach; ?>
        <label class="form-label small fw-semibold mt-3" for="suggestions"><?= e(__('evaluations.suggestions')) ?></label>
        <textarea class="form-control" rows="4" id="suggestions" name="suggestions" <?= $dis ?>><?= e(old('suggestions', $ev['suggestions'] ?? '')) ?></textarea>
        <div class="form-text"><?= e(__('evaluations.suggestions_help')) ?></div>
    </div></div>

    <?php if (!$locked): ?>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button class="btn btn-outline-primary" name="do" value="draft"><i class="bi bi-save"></i> <?= e(__('evaluations.save_draft')) ?></button>
            <button class="btn btn-ppms" name="do" value="submit" data-confirm-button="<?= e(__('evaluations.submit_confirm')) ?>"><i class="bi bi-lock"></i> <?= e(__('evaluations.submit')) ?></button>
        </div>
    <?php endif; ?>
</form>

<?php if ($peer): ?>
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6"><?= e(__('evaluations.all_results')) ?></h2>
        <?= $this->partial('projects/_evaluation_summary', ['summary' => $peer, 'showNames' => true, 'canReopen' => false, 'base' => '']) ?>
    </div></div>
<?php endif; ?>
