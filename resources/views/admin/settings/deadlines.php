<?php
/** @var array $offering @var array $deadlines @var string $tab */
$this->layout('layouts/app', ['title' => __('menu.settings')]);
echo $this->partial('admin/settings/_tabs', ['tab' => $tab]);
?>
<p class="text-secondary small"><?= e($offering['course_code'] . ' ' . $offering['course_name_th']) ?> · ภาค <?= (int) $offering['term_no'] ?>/<?= (int) $offering['year_be'] ?></p>
<form method="post" action="<?= e(url('/admin/settings/deadlines')) ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>ขั้นตอน</th><th>กำหนดส่ง</th><th>รับงานหลังกำหนด (ทำเครื่องหมาย late)</th><th>แจ้งเตือนล่วงหน้า (วัน)</th></tr></thead>
        <tbody>
        <?php foreach ($deadlines as $type => $d): ?>
            <tr>
                <td><?= e(__('deadline.' . $type)) ?></td>
                <td><input type="datetime-local" class="form-control form-control-sm" name="due_<?= e($type) ?>" value="<?= $d ? e(date('Y-m-d\TH:i', strtotime($d['due_at']))) : '' ?>"></td>
                <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="late_<?= e($type) ?>" value="1" <?= !$d || (int) $d['allow_late'] === 1 ? 'checked' : '' ?>></div></td>
                <td><input type="number" min="0" max="30" class="form-control form-control-sm" name="remind_<?= e($type) ?>" value="<?= $d ? (int) $d['reminder_days'] : 2 ?>"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
    <p class="small text-secondary mt-2"><?= e(__('settings.deadline_help')) ?></p>
    <button class="btn btn-ppms"><i class="bi bi-save"></i> <?= e(__('common.save')) ?></button>
</form>
