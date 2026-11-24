<?php
/** @var array $offering @var array $criteria @var bool $locked @var string $tab */
$this->layout('layouts/app', ['title' => __('menu.settings')]);
echo $this->partial('admin/settings/_tabs', ['tab' => $tab]);
$rows = $criteria;
if (!$locked) {
    $rows[] = ['id' => '', 'name_th' => '', 'name_en' => '', 'description' => '', 'weight' => '', 'is_active' => 1];
}
$sum = array_sum(array_map(fn ($c) => (int) $c['is_active'] === 1 ? (float) $c['weight'] : 0, $criteria));
?>
<p class="text-secondary small"><?= e(__('settings.rubric_help')) ?></p>
<?php if ($locked): ?><div class="alert alert-info small"><i class="bi bi-lock"></i> <?= e(__('settings.rubric_locked')) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/settings/rubric')) ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>#</th><th>เกณฑ์ (ไทย)</th><th>Criterion (English)</th><th>คำอธิบายระดับคะแนน</th><th style="width:110px">น้ำหนัก (%)</th><th>ใช้งาน</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $c): ?>
            <tr>
                <td><?= $i + 1 ?><input type="hidden" name="criteria[<?= $i ?>][id]" value="<?= e($c['id']) ?>"></td>
                <td><input class="form-control form-control-sm" name="criteria[<?= $i ?>][name_th]" value="<?= e($c['name_th']) ?>" <?= $locked ? 'disabled' : '' ?>></td>
                <td><input class="form-control form-control-sm" name="criteria[<?= $i ?>][name_en]" value="<?= e($c['name_en']) ?>" <?= $locked ? 'disabled' : '' ?>></td>
                <td><input class="form-control form-control-sm" name="criteria[<?= $i ?>][description]" value="<?= e($c['description']) ?>" <?= $locked ? 'disabled' : '' ?>></td>
                <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm js-weight" name="criteria[<?= $i ?>][weight]" value="<?= e($c['weight']) ?>" <?= $locked ? 'disabled' : '' ?>></td>
                <td><input class="form-check-input js-weight-active" type="checkbox" name="criteria[<?= $i ?>][is_active]" value="1" <?= (int) $c['is_active'] === 1 ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="4" class="text-end">รวม (ต้องเท่ากับ 100)</th><th><span id="weightSum" class="fw-semibold"><?= e(number_format($sum, 2)) ?></span></th><th></th></tr></tfoot>
    </table></div></div>
    <?php if (!$locked): ?><button class="btn btn-ppms mt-3"><i class="bi bi-save"></i> <?= e(__('common.save')) ?></button><?php endif; ?>
</form>
