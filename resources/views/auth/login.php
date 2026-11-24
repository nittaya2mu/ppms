<?php $this->layout('layouts/auth', ['title' => __('auth.title')]); ?>
<h2 class="h5 mb-3"><?= e(__('auth.title')) ?></h2>
<form method="post" action="<?= e(url('/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="login" class="form-label"><?= e(__('auth.login')) ?></label>
        <input type="text" class="form-control <?= error('login') ? 'is-invalid' : '' ?>" id="login" name="login"
               value="<?= e(old('login')) ?>" autocomplete="username" autofocus required>
        <?php if (error('login')): ?><div class="invalid-feedback"><?= e(error('login')) ?></div><?php endif; ?>
    </div>
    <div class="mb-4">
        <label for="password" class="form-label"><?= e(__('auth.password')) ?></label>
        <input type="password" class="form-control <?= error('password') ? 'is-invalid' : '' ?>" id="password" name="password"
               autocomplete="current-password" required>
        <?php if (error('password')): ?><div class="invalid-feedback"><?= e(error('password')) ?></div><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-ppms w-100"><i class="bi bi-box-arrow-in-right"></i> <?= e(__('auth.submit')) ?></button>
</form>
