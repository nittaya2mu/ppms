<?php
/** @var array $evaluations @var int $pending */
$this->layout('layouts/app', ['title' => __('menu.dashboard')]);
?>
<h1 class="h4 mb-3"><?= e(__('menu.dashboard')) ?></h1>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card stat-card stat-navy"><div class="card-body">
        <div class="small text-secondary"><?= e(__('dash.assigned_projects')) ?></div><div class="fs-3 fw-semibold"><?= count($evaluations) ?></div>
    </div></div></div>
    <div class="col-md-4"><div class="card stat-card stat-amber"><div class="card-body">
        <div class="small text-secondary"><?= e(__('dash.pending_count')) ?></div><div class="fs-3 fw-semibold"><?= (int) $pending ?></div>
    </div></div></div>
</div>
<div class="card"><div class="card-body">
    <h2 class="h6"><?= e(__('dash.assigned_projects')) ?></h2>
    <?php if (!$evaluations): ?><p class="text-secondary mb-0"><?= e(__('common.none')) ?></p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Project ID</th><th>หัวข้อ</th><th>Version</th><th>รอบ</th><th>กำหนดส่ง</th><th><?= e(__('common.status')) ?></th></tr></thead>
        <tbody><?php foreach ($evaluations as $ev): ?>
            <tr><td><?= e($ev['project_code']) ?></td><td><?= e($ev['title_th']) ?></td><td>v<?= (int) $ev['version_no'] ?></td>
                <td><?= (int) $ev['round_no'] ?></td><td><?= e(thai_date($ev['evaluation_deadline'], true)) ?></td>
                <td><?= status_badge($ev['status']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div></div>
