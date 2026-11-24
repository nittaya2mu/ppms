<?php
/** @var array $stats @var array $topicCounts @var array $proposalCounts @var ?array $offering @var array $groupsPending */
$this->layout('layouts/app', ['title' => __('menu.dashboard')]);
$cards = [
    ['groups', 'people', 'navy'], ['students', 'person-badge', 'navy'],
    ['pending_topics', 'hourglass-split', 'amber'], ['approved_topics', 'check2-circle', 'green'],
    ['pending_proposals', 'file-earmark-pdf', 'amber'], ['awaiting_assignment', 'person-plus', 'amber'],
    ['pending_evaluations', 'clipboard', 'amber'], ['completed_evaluations', 'clipboard-check', 'green'],
    ['revisions', 'arrow-repeat', 'red'], ['final_approved', 'award', 'green'],
];
$topicStates = ['draft', 'submitted', 'advisor_review', 'revision_required', 'admin_review', 'topic_approved', 'topic_rejected'];
$proposalStates = ['submitted', 'advisor_review', 'committee_assignment', 'committee_evaluation', 'final_review', 'revision_required', 'approved_minor', 'approved', 'rejected'];
$chartData = [
    'topic' => ['labels' => array_map(fn ($s) => __('status.' . $s), $topicStates), 'values' => array_map(fn ($s) => $topicCounts[$s] ?? 0, $topicStates)],
    'proposal' => ['labels' => array_map(fn ($s) => __('status.' . $s), $proposalStates), 'values' => array_map(fn ($s) => $proposalCounts[$s] ?? 0, $proposalStates)],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">
    <div>
        <h1 class="h4 mb-0"><?= e(__('menu.dashboard')) ?></h1>
        <div class="text-secondary small">
            <?php if ($offering): ?>
                <?= e(__('dash.offering')) ?>: <?= e($offering['course_code'] . ' ' . $offering['course_name_th']) ?>
                · ภาค <?= (int) $offering['term_no'] ?>/<?= (int) $offering['year_be'] ?>
            <?php else: ?><?= e(__('dash.no_offering')) ?><?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$key, $icon, $tone]): ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="card stat-card stat-<?= e($tone) ?> h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="small text-secondary"><?= e(__('dash.' . $key)) ?></span>
                        <i class="bi bi-<?= e($icon) ?>"></i>
                    </div>
                    <div class="fs-3 fw-semibold"><?= (int) $stats[$key] ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h6"><?= e(__('dash.topic_chart')) ?></h2>
        <canvas class="js-bar-chart" height="220" data-chart="<?= e(json_encode($chartData['topic'], JSON_UNESCAPED_UNICODE)) ?>"></canvas>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h6"><?= e(__('dash.proposal_chart')) ?></h2>
        <canvas class="js-bar-chart" height="220" data-chart="<?= e(json_encode($chartData['proposal'], JSON_UNESCAPED_UNICODE)) ?>"></canvas>
    </div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <h2 class="h6"><?= e(__('dash.groups_pending')) ?></h2>
        <?php if (!$groupsPending): ?>
            <p class="text-secondary mb-0"><?= e(__('common.none')) ?></p>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Group ID</th><th>ชื่อกลุ่ม</th><th>Group Leader</th><th>วันที่ลงทะเบียน</th><th><?= e(__('common.status')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($groupsPending as $g): ?>
                    <tr><td><?= e($g['group_code']) ?></td><td><?= e($g['group_name']) ?></td><td><?= e(full_name($g)) ?></td>
                        <td><?= e(thai_date($g['registered_at'], true)) ?></td><td><?= status_badge($g['status']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>
