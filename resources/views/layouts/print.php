<?php /** @var string $content */ ?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <title><?= e($title ?? 'PPMS') ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/sarabun/sarabun.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="ppms-print">
<div class="container py-4">
    <div class="d-print-none d-flex gap-2 mb-3">
        <button type="button" class="btn btn-ppms js-print"><i class="bi bi-printer"></i> <?= e(__('common.print_pdf')) ?></button>
        <a href="#" class="btn btn-outline-secondary js-back"><?= e(__('common.back')) ?></a>
    </div>
    <?= $content ?>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
