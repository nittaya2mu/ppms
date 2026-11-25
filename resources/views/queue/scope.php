<?php
/** @var array $rows */
$this->layout('layouts/app', ['title' => __('menu.scope')]);
?>
<h1 class="h4 mb-1"><?= e(__('menu.scope')) ?></h1>
<p class="text-secondary small"><?= e(__('queue.scope_help')) ?></p>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Project</th><th>กลุ่ม</th><th>หัวข้อ</th><th>Version</th><th>ส่งเมื่อ</th><th><?= e(__('common.status')) ?></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-secondary"><?= e(__('queue.empty')) ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('/projects/' . (int) $r['project_id'] . '/scope')) ?>"><?= e($r['project_code']) ?></a></td>
            <td class="small"><?= e($r['group_code']) ?><br><?= e($r['group_name']) ?></td>
            <td><?= e($r['title_th'] ?? '') ?></td>
            <td>v<?= (int) $r['version_no'] ?></td>
            <td class="small"><?= e(thai_date($r['submitted_at'], true)) ?></td>
            <td><span class="badge <?= ['approved' => 'text-bg-success', 'pending' => 'text-bg-warning', 'revision_required' => 'text-bg-danger'][$r['status']] ?>"><?= e(__('scope.status_' . $r['status'])) ?></span>
                <?php if (!empty($r['awaiting_me'])): ?><span class="badge text-bg-primary"><?= e(__('scope.awaiting_me')) ?></span><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
