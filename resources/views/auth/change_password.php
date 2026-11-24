<?php
/** @var bool $forced */
$this->layout($forced ? 'layouts/auth' : 'layouts/app', ['title' => __('auth.change_password')]);
?>
<?php if (!$forced): ?><div class="row"><div class="col-lg-6"><div class="card"><div class="card-body"><?php endif; ?>
<h2 class="h5 mb-3"><?= e(__('auth.change_password')) ?></h2>
<?php if ($forced): ?><div class="alert alert-warning small"><?= e(__('auth.must_change')) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/password')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php foreach ([['current_password', 'auth.current_password', 'current-password'], ['password', 'auth.new_password', 'new-password'], ['password_confirmation', 'auth.confirm_password', 'new-password']] as [$name, $label, $ac]): ?>
        <div class="mb-3">
            <label for="<?= e($name) ?>" class="form-label"><?= e(__($label)) ?></label>
            <input type="password" class="form-control <?= error($name) ? 'is-invalid' : '' ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" autocomplete="<?= e($ac) ?>" required>
            <?php if (error($name)): ?><div class="invalid-feedback"><?= e(error($name)) ?></div><?php endif; ?>
            <?php if ($name === 'password'): ?><div class="form-text"><?= e(__('auth.password_hint')) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-ppms w-100"><?= e(__('common.save')) ?></button>
</form>
<?php if ($forced): ?>
    <form method="post" action="<?= e(url('/logout')) ?>" class="mt-3 text-center">
        <?= csrf_field() ?>
        <button class="btn btn-link btn-sm text-secondary"><?= e(__('menu.logout')) ?></button>
    </form>
<?php else: ?></div></div></div></div><?php endif; ?>
