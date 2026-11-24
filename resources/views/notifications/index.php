<?php
/** @var array $items */
$this->layout('layouts/app', ['title' => __('notifications.title')]);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e(__('notifications.title')) ?></h1>
    <form method="post" action="<?= e(url('/notifications/read')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-check2-all"></i> <?= e(__('common.mark_all_read')) ?></button>
    </form>
</div>
<div class="list-group">
    <?php if (!$items): ?><div class="list-group-item text-secondary"><?= e(__('notifications.empty')) ?></div><?php endif; ?>
    <?php foreach ($items as $n): ?>
        <div class="list-group-item <?= (int) $n['is_read'] === 0 ? 'list-group-item-light border-start border-4 border-success' : '' ?>">
            <div class="d-flex justify-content-between">
                <strong><?= e($n['title']) ?></strong>
                <small class="text-secondary"><?= e(thai_date($n['created_at'], true)) ?></small>
            </div>
            <div class="small"><?= e($n['message']) ?></div>
            <?php if ($n['link_url']): ?><a class="small" href="<?= e(url($n['link_url'])) ?>">เปิดดู</a><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
