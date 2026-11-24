<?php
/** @var array $groups @var int $pendingTopics @var int $pendingProposals */
$this->layout('layouts/app', ['title' => __('menu.dashboard')]);
?>
<h1 class="h4 mb-3"><?= e(__('menu.dashboard')) ?></h1>
<div class="row g-3 mb-4">
    <?php foreach ([['assigned_groups', count($groups), 'navy'], ['pending_topic_reviews', $pendingTopics, 'amber'], ['pending_proposal_reviews', $pendingProposals, 'amber']] as [$k, $n, $tone]): ?>
        <div class="col-md-4"><div class="card stat-card stat-<?= e($tone) ?>"><div class="card-body">
            <div class="small text-secondary"><?= e(__('dash.' . $k)) ?></div>
            <div class="fs-3 fw-semibold"><?= (int) $n ?></div>
        </div></div></div>
    <?php endforeach; ?>
</div>
<div class="card"><div class="card-body">
    <h2 class="h6"><?= e(__('dash.assigned_groups')) ?></h2>
    <?php if (!$groups): ?><p class="text-secondary mb-0"><?= e(__('common.none')) ?></p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Group ID</th><th>ชื่อกลุ่ม</th><th>หัวข้อ</th><th>Topic</th><th>Proposal</th></tr></thead>
        <tbody><?php foreach ($groups as $g): ?>
            <tr><td><?= e($g['group_code']) ?></td><td><?= e($g['group_name']) ?></td><td><?= e($g['title_th'] ?? '-') ?></td>
                <td><?= $g['topic_status'] ? status_badge($g['topic_status']) : '-' ?></td>
                <td><?= $g['proposal_status'] ? status_badge($g['proposal_status']) : '-' ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div></div>
