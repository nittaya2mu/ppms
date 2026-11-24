<?php
/** @var array $settings @var string $tab */
$this->layout('layouts/app', ['title' => __('menu.settings')]);
echo $this->partial('admin/settings/_tabs', ['tab' => $tab]);
?>
<form method="post" action="<?= e(url('/admin/settings')) ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th style="width:35%">ค่าตั้ง</th><th>คำอธิบาย</th><th style="width:22%">ค่า</th></tr></thead>
            <tbody>
            <?php foreach ($settings as $s): $field = 'settings[' . str_replace('.', '__', $s['setting_key']) . ']'; ?>
                <tr>
                    <td><code><?= e($s['setting_key']) ?></code></td>
                    <td class="small"><?= e($s['description']) ?></td>
                    <td>
                        <?php if ($s['value_type'] === 'bool'): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="<?= e($field) ?>" value="1" <?= in_array($s['setting_value'], ['true', '1'], true) ? 'checked' : '' ?>>
                            </div>
                        <?php elseif ($s['value_type'] === 'int'): ?>
                            <input type="number" min="0" class="form-control form-control-sm" name="<?= e($field) ?>" value="<?= e($s['setting_value']) ?>">
                        <?php else: ?>
                            <input type="text" class="form-control form-control-sm" name="<?= e($field) ?>" value="<?= e($s['setting_value']) ?>">
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <button class="btn btn-ppms mt-3"><i class="bi bi-save"></i> <?= e(__('common.save')) ?></button>
</form>
