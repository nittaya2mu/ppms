<?php
/** @var ?array $student @var ?array $group @var array $members @var ?array $advisor @var array $deadlines */
$this->layout('layouts/app', ['title' => __('menu.dashboard')]);
$steps = ['group', 'topic', 'proposal', 'evaluation', 'final'];
$currentStep = $group === null ? 0 : (in_array($group['status'], ['active'], true) ? 1 : 0);
?>
<h1 class="h4 mb-3"><?= e(__('menu.dashboard')) ?></h1>

<div class="card mb-4"><div class="card-body">
    <h2 class="h6 mb-3"><?= e(__('dash.progress')) ?></h2>
    <ol class="ppms-stepper">
        <?php foreach ($steps as $i => $s): ?>
            <li class="<?= $i < $currentStep ? 'done' : ($i === $currentStep ? 'current' : '') ?>">
                <span class="dot"><?= $i < $currentStep ? '<i class="bi bi-check"></i>' : $i + 1 ?></span>
                <span class="label"><?= e(__('step.' . $s)) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</div></div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?= e(__('dash.my_group')) ?></h2>
            <?php if ($group === null): ?>
                <p class="text-secondary mb-0"><?= e(__('dash.no_group')) ?></p>
            <?php else: ?>
                <dl class="row small mb-3">
                    <dt class="col-sm-4">Group ID</dt><dd class="col-sm-8"><?= e($group['group_code']) ?></dd>
                    <dt class="col-sm-4">ชื่อกลุ่ม</dt><dd class="col-sm-8"><?= e($group['group_name']) ?></dd>
                    <dt class="col-sm-4"><?= e(__('common.status')) ?></dt><dd class="col-sm-8"><?= status_badge($group['status']) ?></dd>
                    <dt class="col-sm-4"><?= e(__('dash.advisor')) ?></dt>
                    <dd class="col-sm-8"><?= $advisor ? e(full_name($advisor)) : '<span class="text-secondary">' . e(__('dash.no_advisor')) . '</span>' ?></dd>
                </dl>
                <h3 class="h6"><?= e(__('dash.members')) ?></h3>
                <ul class="list-group list-group-flush">
                    <?php foreach ($members as $m): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span><?= e($m['student_code']) ?> · <?= e(full_name($m)) ?></span>
                            <?php if ($m['member_role'] === 'leader'): ?><span class="badge bg-ppms-navy">Group Leader</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?= e(__('dash.deadlines')) ?></h2>
            <?php if (!$deadlines): ?><p class="text-secondary mb-0"><?= e(__('common.none')) ?></p><?php endif; ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($deadlines as $d): $past = strtotime($d['due_at']) < time(); ?>
                    <li class="d-flex justify-content-between py-1 border-bottom <?= $past ? 'text-secondary' : '' ?>">
                        <span><i class="bi bi-calendar-event"></i> <?= e(__('deadline.' . $d['deadline_type'])) ?></span>
                        <span><?= e(thai_date($d['due_at'], true)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div></div>
    </div>
</div>
