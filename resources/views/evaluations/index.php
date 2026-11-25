<?php
/** @var array $rows */
$this->layout('layouts/app', ['title' => __('menu.evaluation')]);
?>
<h1 class="h4 mb-1"><?= e(__('menu.evaluation')) ?></h1>
<p class="text-secondary small"><?= e(__('evaluations.independence')) ?></p>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Project</th><th>หัวข้อ</th><th>รอบ / Version</th><th>เอกสาร</th><th><?= e(__('evaluations.deadline')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary"><?= e(__('queue.empty')) ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $overdue = $r['status'] !== 'submitted' && strtotime($r['evaluation_deadline']) < time(); ?>
        <tr>
            <td><?= e($r['project_code']) ?><div class="small text-secondary"><?= e($r['group_code']) ?></div></td>
            <td><?= e($r['title_th']) ?></td>
            <td><?= (int) $r['round_no'] ?> / v<?= (int) $r['version_no'] ?></td>
            <td><a href="<?= e(url('/files/' . (int) $r['file_id'])) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> <?= e(__('proposals.chapter_n', ['n' => 1])) ?></a></td>
            <td class="<?= $overdue ? 'text-danger fw-semibold' : '' ?>"><?= e(thai_date($r['evaluation_deadline'], true)) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td><a class="btn btn-sm <?= $r['status'] === 'submitted' ? 'btn-outline-secondary' : 'btn-ppms' ?>" href="<?= e(url('/evaluations/' . (int) $r['id'])) ?>"><?= e($r['status'] === 'submitted' ? __('evaluations.view') : __('evaluations.evaluate')) ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
