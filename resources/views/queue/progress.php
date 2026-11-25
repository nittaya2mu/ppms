<?php
/** @var array $rows @var int $minReports */
$this->layout('layouts/app', ['title' => __('menu.progress')]);
?>
<h1 class="h4 mb-1"><?= e(__('menu.progress')) ?></h1>
<p class="text-secondary small"><?= e(__('queue.progress_help', ['min' => $minReports])) ?></p>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Project</th><th>กลุ่ม</th><th>หัวข้อ</th><th class="text-center"><?= e(__('progress.reports')) ?></th><th class="text-center"><?= e(__('progress.reviewed_count')) ?></th><th class="text-center"><?= e(__('progress.pending')) ?></th><th><?= e(__('progress.last')) ?></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary"><?= e(__('queue.empty')) ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('/projects/' . (int) $r['project_id'] . '/progress')) ?>"><?= e($r['project_code']) ?></a></td>
            <td class="small"><?= e($r['group_code']) ?><br><?= e($r['group_name']) ?></td>
            <td><?= e($r['title_th'] ?? '') ?></td>
            <td class="text-center"><?= (int) $r['reports'] ?></td>
            <td class="text-center <?= (int) $r['reviewed'] >= $minReports ? 'text-success fw-semibold' : '' ?>"><?= (int) $r['reviewed'] ?> / <?= $minReports ?></td>
            <td class="text-center"><?= (int) $r['pending'] > 0 ? '<span class="badge text-bg-warning">' . (int) $r['pending'] . '</span>' : '-' ?></td>
            <td class="small"><?= e(thai_date($r['last_submitted'], true)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
