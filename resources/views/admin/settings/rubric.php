<?php
/** @var array $progressRubric @var int $minReports @var int $maxReports @var string $tab */
use App\Services\PresentationForm;

$this->layout('layouts/app', ['title' => __('menu.settings')]);
echo $this->partial('admin/settings/_tabs', ['tab' => $tab]);
$max = PresentationForm::itemMax();
$rows = $progressRubric;
while (count($rows) < 8) {
    $rows[] = ['score' => '', 'label' => ''];
}
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?= e(__('settings.form2_title')) ?></h2>
            <p class="small text-secondary"><?= e(__('settings.form2_help')) ?></p>
            <table class="table table-sm small mb-2">
                <thead><tr><th><?= e(__('evaluations.item')) ?></th><th class="text-end"><?= e(__('evaluations.max_points')) ?></th></tr></thead>
                <tbody>
                <?php foreach (PresentationForm::GROUPS as [$glabel, $gmax, $items]): ?>
                    <tr class="table-light fw-semibold"><td><?= e($glabel) ?></td><td class="text-end"><?= (int) $gmax ?></td></tr>
                    <?php foreach ($items as $key => $label): ?><tr><td class="ps-3"><?= e($label) ?></td><td class="text-end"><?= e(rtrim(rtrim(number_format($max[$key], 2), '0'), '.')) ?></td></tr><?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="small"><?= e(__('settings.form2_grades')) ?>: <?php foreach (PresentationForm::GRADES as $l => $p): ?><span class="badge text-bg-light border"><?= e($l) ?>=<?= e((string) $p) ?></span> <?php endforeach; ?></div>
            <div class="small mt-1"><?= e(__('settings.form2_letters')) ?>: <?php foreach (PresentationForm::LETTER_SCALE as $min => $l): ?><span class="badge text-bg-light border"><?= e($l) ?> ≥ <?= (int) $min ?></span> <?php endforeach; ?></div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <form method="post" action="<?= e(url('/admin/settings/rubric')) ?>" class="card h-100"><div class="card-body">
            <?= csrf_field() ?>
            <h2 class="h6"><?= e(__('settings.progress_title')) ?></h2>
            <p class="small text-secondary"><?= e(__('settings.progress_help')) ?></p>
            <div class="row g-2 mb-3">
                <div class="col-6"><label class="form-label small"><?= e(__('settings.progress_min')) ?></label><input type="number" min="1" max="20" class="form-control form-control-sm" name="min_reports" value="<?= $minReports ?>"></div>
                <div class="col-6"><label class="form-label small"><?= e(__('settings.progress_max')) ?></label><input type="number" min="1" max="20" class="form-control form-control-sm" name="max_reports" value="<?= $maxReports ?>"></div>
            </div>
            <table class="table table-sm small align-middle">
                <thead><tr><th style="width:6rem"><?= e(__('progress.score')) ?></th><th><?= e(__('settings.progress_level')) ?></th></tr></thead>
                <tbody><?php foreach ($rows as $lvl): ?>
                    <tr><td><input type="number" step="0.5" min="0" class="form-control form-control-sm" name="level_score[]" value="<?= e($lvl['score'] === '' ? '' : (string) ($lvl['score'] + 0)) ?>"></td>
                        <td><input class="form-control form-control-sm" name="level_label[]" maxlength="300" value="<?= e($lvl['label']) ?>"></td></tr>
                <?php endforeach; ?></tbody>
            </table>
            <button class="btn btn-ppms"><i class="bi bi-save"></i> <?= e(__('common.save')) ?></button>
        </div></form>
    </div>
</div>
