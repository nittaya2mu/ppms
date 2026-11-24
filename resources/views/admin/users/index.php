<?php
/** @var array $rows @var int $total @var int $page @var int $per_page @var string $search @var string $role */
$this->layout('layouts/app', ['title' => __('users.title')]);
$pages = max(1, (int) ceil($total / $per_page));
$qs = fn (int $p) => '?' . http_build_query(['q' => $search, 'role' => $role, 'page' => $p]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0"><?= e(__('users.title')) ?></h1>
    <a href="<?= e(url('/admin/users/create')) ?>" class="btn btn-ppms"><i class="bi bi-person-plus"></i> <?= e(__('users.add')) ?></a>
</div>

<form class="row g-2 mb-3" method="get" action="<?= e(url('/admin/users')) ?>">
    <div class="col-md-6"><input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="ชื่อ, username, อีเมล, รหัสนิสิต"></div>
    <div class="col-md-3">
        <select name="role" class="form-select">
            <option value=""><?= e(__('common.all')) ?></option>
            <?php foreach (['admin', 'student', 'advisor', 'committee'] as $r): ?>
                <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(__('role.' . $r)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> <?= e(__('common.search')) ?></button></div>
</form>

<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
        <th>Username</th><th>ชื่อ-สกุล</th><th>อีเมล</th><th><?= e(__('users.roles')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('users.last_login')) ?></th><th class="text-end"><?= e(__('common.actions')) ?></th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary"><?= e(__('common.none')) ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $u): ?>
        <tr>
            <td><?= e($u['username']) ?><?php if ($u['student_code']): ?><div class="small text-secondary"><?= e($u['student_code']) ?></div><?php endif; ?></td>
            <td><?= e(full_name($u)) ?></td>
            <td class="small"><?= e($u['email']) ?></td>
            <td><?php foreach (array_filter(explode(',', (string) $u['role_codes'])) as $rc): ?>
                <span class="badge bg-ppms-navy-subtle text-ppms-navy me-1"><?= e(ucfirst($rc)) ?></span>
            <?php endforeach; ?></td>
            <td><?= (int) $u['is_active'] === 1 ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>' : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?></td>
            <td class="small"><?= e(thai_date($u['last_login_at'], true)) ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/users/' . (int) $u['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i></a>
                <form class="d-inline" method="post" action="<?= e(url('/admin/users/' . (int) $u['id'] . '/reset-password')) ?>" data-confirm="รีเซ็ตรหัสผ่านของ <?= e($u['username']) ?>?">
                    <?= csrf_field() ?><button class="btn btn-sm btn-outline-warning" title="<?= e(__('users.reset_password')) ?>"><i class="bi bi-key"></i></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-secondary"><?= e(__('common.total', ['n' => $total])) ?></small>
    <?php if ($pages > 1): ?>
    <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($qs($p)) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
