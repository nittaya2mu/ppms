<?php

use App\Core\Session;

/** @var string $content */
$flash = Session::pullFlash();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <title><?= e($title ?? __('auth.title')) ?> · PPMS</title>
    <link rel="stylesheet" href="<?= e(asset('vendor/sarabun/sarabun.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="ppms-auth-bg d-flex align-items-center min-vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="text-center text-white mb-4">
                <i class="bi bi-mortarboard-fill display-5 text-ppms-green"></i>
                <h1 class="h4 mt-2 mb-1">PPMS</h1>
                <div class="small opacity-75"><?= e(__('app.name')) ?></div>
                <div class="small opacity-75"><?= e(__('app.department')) ?></div>
            </div>
            <?php foreach ($flash as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4"><?= $content ?></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
