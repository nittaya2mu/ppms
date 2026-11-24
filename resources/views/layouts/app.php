<?php

use App\Core\Auth;
use App\Core\Session;
use App\Services\NotificationService;

/** @var string $content */
/** @var string $title */
$user = Auth::user();
$activeRole = Auth::activeRole();
$unread = $user ? NotificationService::unreadCount((int) $user['id']) : 0;
$flash = Session::pullFlash();
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Role-specific menu (Section 16). Items without a route yet are shown as "coming soon".
$menus = [
    'admin' => [
        ['speedometer2', 'menu.dashboard', '/dashboard'],
        ['people', 'menu.groups', null],
        ['journal-text', 'menu.topics', null],
        ['check2-square', 'menu.topic_approval', null],
        ['file-earmark-pdf', 'menu.proposals', null],
        ['person-check', 'menu.committee_assignment', null],
        ['clipboard-data', 'menu.evaluation', null],
        ['award', 'menu.final_approval', null],
        ['bar-chart', 'menu.reports', null],
        ['person-gear', 'menu.users', '/admin/users'],
        ['gear', 'menu.settings', null],
    ],
    'student' => [
        ['speedometer2', 'menu.dashboard', '/dashboard'],
        ['people', 'menu.groups', null],
        ['journal-text', 'menu.topics', null],
        ['file-earmark-pdf', 'menu.proposals', null],
    ],
    'advisor' => [
        ['speedometer2', 'menu.dashboard', '/dashboard'],
        ['people', 'menu.groups', null],
        ['check2-square', 'menu.topic_approval', null],
        ['file-earmark-check', 'menu.advisor_review', null],
    ],
    'committee' => [
        ['speedometer2', 'menu.dashboard', '/dashboard'],
        ['clipboard-data', 'menu.evaluation', null],
    ],
];
$menu = $menus[$activeRole] ?? [];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?>PPMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<nav class="navbar navbar-dark ppms-topbar fixed-top">
    <div class="container-fluid">
        <button class="btn btn-link text-white d-lg-none me-2 p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="เมนู">
            <i class="bi bi-list fs-3"></i>
        </button>
        <a class="navbar-brand fw-semibold me-auto" href="<?= e(url('/dashboard')) ?>">
            <i class="bi bi-mortarboard-fill text-ppms-green"></i> PPMS
        </a>
        <a class="btn btn-link text-white position-relative me-2" href="<?= e(url('/notifications')) ?>" aria-label="<?= e(__('menu.notifications')) ?>">
            <i class="bi bi-bell fs-5"></i>
            <?php if ($unread > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success"><?= $unread > 99 ? '99+' : (int) $unread ?></span>
            <?php endif; ?>
        </a>
        <div class="dropdown">
            <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline"><?= e(full_name($user, false)) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header"><?= e(__('role.' . $activeRole)) ?></h6></li>
                <?php if (count(Auth::roles()) > 1): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header"><?= e(__('menu.switch_role')) ?></h6></li>
                    <?php foreach (Auth::roles() as $r): if ($r === $activeRole) { continue; } ?>
                        <li>
                            <form method="post" action="<?= e(url('/role')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="role" value="<?= e($r) ?>">
                                <button class="dropdown-item"><i class="bi bi-arrow-left-right"></i> <?= e(__('role.' . $r)) ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e(url('/password')) ?>"><i class="bi bi-key"></i> <?= e(__('menu.change_password')) ?></a></li>
                <li>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> <?= e(__('menu.logout')) ?></button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="offcanvas-lg offcanvas-start ppms-sidebar" tabindex="-1" id="sidebar" aria-label="เมนูหลัก">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title">PPMS</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="ปิด"></button>
    </div>
    <div class="offcanvas-body flex-column p-0">
        <div class="px-3 py-2 small text-white-50"><?= e(__('role.' . $activeRole)) ?></div>
        <ul class="nav flex-column">
            <?php foreach ($menu as [$icon, $label, $href]): ?>
                <li class="nav-item">
                    <?php if ($href === null): ?>
                        <span class="nav-link disabled" title="<?= e(__('menu.coming_soon')) ?>">
                            <i class="bi bi-<?= e($icon) ?>"></i> <?= e(__($label)) ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= e(__('menu.coming_soon')) ?></span>
                        </span>
                    <?php else: ?>
                        <a class="nav-link <?= str_ends_with((string) $current, $href) || str_contains((string) $current, $href . '/') ? 'active' : '' ?>" href="<?= e(url($href)) ?>">
                            <i class="bi bi-<?= e($icon) ?>"></i> <?= e(__($label)) ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<main class="ppms-main">
    <div class="container-fluid py-4">
        <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
            </div>
        <?php endforeach; ?>
        <?= $content ?>
    </div>
</main>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= e(__('common.confirm')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body" id="confirmText"><?= e(__('common.confirm_text')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="button" class="btn btn-ppms" id="confirmOk"><?= e(__('common.yes')) ?></button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
