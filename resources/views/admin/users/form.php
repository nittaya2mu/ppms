<?php
/** @var ?array $user @var string[] $roles @var ?array $student */
$isEdit = $user !== null;
$this->layout('layouts/app', ['title' => $isEdit ? __('users.edit') : __('users.add')]);
$val = fn (string $k) => old($k, $user[$k] ?? '');
$checkedRoles = has_errors() ? (array) old('roles', []) : $roles;
$field = function (string $name, string $type = 'text', bool $required = false) use ($val) {
    $err = error($name);
    return '<div class="mb-3"><label class="form-label" for="' . e($name) . '">' . e(__('field.' . $name)) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
        . '<input type="' . e($type) . '" class="form-control' . ($err ? ' is-invalid' : '') . '" id="' . e($name) . '" name="' . e($name) . '" value="' . e($val($name)) . '"' . ($required ? ' required' : '') . '>'
        . ($err ? '<div class="invalid-feedback">' . e($err) . '</div>' : '') . '</div>';
};
?>
<h1 class="h4 mb-3"><?= e($isEdit ? __('users.edit') : __('users.add')) ?></h1>
<form method="post" action="<?= e(url($isEdit ? '/admin/users/' . (int) $user['id'] : '/admin/users')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-7"><div class="card"><div class="card-body">
            <div class="row">
                <div class="col-md-6"><?= $field('username', 'text', true) ?></div>
                <div class="col-md-6"><?= $field('email', 'email', true) ?></div>
                <div class="col-md-4"><?= $field('title_th') ?></div>
                <div class="col-md-4"><?= $field('first_name_th', 'text', true) ?></div>
                <div class="col-md-4"><?= $field('last_name_th', 'text', true) ?></div>
                <div class="col-md-6"><?= $field('first_name_en') ?></div>
                <div class="col-md-6"><?= $field('last_name_en') ?></div>
            </div>
            <?php if ($isEdit): ?>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $user['is_active'] === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active"><?= e(__('field.is_active')) ?></label>
                </div>
            <?php endif; ?>
        </div></div></div>
        <div class="col-lg-5">
            <div class="card mb-3"><div class="card-body">
                <h2 class="h6"><?= e(__('field.roles')) ?> <span class="text-danger">*</span></h2>
                <?php foreach (['admin', 'student', 'advisor', 'committee'] as $r): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="<?= e($r) ?>" id="role_<?= e($r) ?>" <?= in_array($r, $checkedRoles, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="role_<?= e($r) ?>"><?= e(__('role.' . $r)) ?></label>
                    </div>
                <?php endforeach; ?>
                <?php if (error('roles')): ?><div class="text-danger small mt-1"><?= e(error('roles')) ?></div><?php endif; ?>
            </div></div>
            <?php if (!$isEdit): ?>
            <div class="card"><div class="card-body">
                <h2 class="h6"><?= e(__('users.student_info')) ?></h2>
                <?= $field('student_code') ?><?= $field('program') ?><?= $field('department') ?>
            </div></div>
            <?php elseif ($student): ?>
            <div class="card"><div class="card-body small">
                <h2 class="h6"><?= e(__('users.student_info')) ?></h2>
                <div><?= e(__('field.student_code')) ?>: <?= e($student['student_code']) ?></div>
                <div><?= e(__('field.program')) ?>: <?= e($student['program']) ?></div>
            </div></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-ppms"><i class="bi bi-save"></i> <?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/users')) ?>"><?= e(__('common.cancel')) ?></a>
    </div>
</form>
<?php if ($isEdit && $user['id'] != \App\Core\Auth::id()): ?>
<form method="post" class="mt-4" action="<?= e(url('/admin/users/' . (int) $user['id'] . '/delete')) ?>" data-confirm="ปิดใช้งานผู้ใช้นี้? ข้อมูลประวัติยังถูกเก็บไว้">
    <?= csrf_field() ?>
    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-person-x"></i> <?= e(__('common.delete')) ?></button>
</form>
<?php endif; ?>
