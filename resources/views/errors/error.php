<?php
/** @var int $status @var string $key */
$this->layout(\App\Core\Auth::check() ? 'layouts/app' : 'layouts/auth', ['title' => (string) $status]);
?>
<div class="text-center py-4">
    <div class="display-5 fw-semibold text-ppms-navy"><?= (int) $status ?></div>
    <p class="lead mt-2"><?= e(__('error.' . $key)) ?></p>
    <a class="btn btn-ppms" href="<?= e(url('/dashboard')) ?>"><?= e(__('common.back')) ?></a>
</div>
