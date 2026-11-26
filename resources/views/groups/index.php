<?php
/** @var array $rows @var int $total @var int $page @var int $per_page @var string $q @var string $status */
$this->layout('layouts/app', ['title' => __('menu.groups')]);
$pages = max(1, (int) ceil($total / $per_page));
?>
<h1 class="h4 mb-3"><?= e(__('menu.groups')) ?></h1>
<?php if (!empty($advisorRequests)): $isAdv = \App\Core\Auth::activeRole() === 'advisor'; ?>
    <div class="card border-primary mb-3"><div class="card-body">
        <h2 class="h6"><i class="bi bi-person-raised-hand"></i> <?= e($isAdv ? __('groups.requests_for_advisor') : __('groups.requests_for_admin')) ?> (<?= count($advisorRequests) ?>)</h2>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Group ID</th><th><?= e(__('groups.name')) ?></th><th><?= e(__('dash.members')) ?></th><?php if (!$isAdv): ?><th><?= e(__('dash.advisor')) ?></th><?php endif; ?><th><?= e(__('groups.requested_at')) ?></th><th></th></tr></thead>
            <tbody><?php foreach ($advisorRequests as $req): ?>
                <tr><td><?= e($req['group_code']) ?></td><td><?= e($req['group_name']) ?></td><td><?= (int) $req['member_count'] ?></td>
                    <?php if (!$isAdv): ?><td><?= e(full_name($req)) ?></td><?php endif; ?>
                    <td class="small"><?= e(thai_date($req['created_at'], true)) ?></td>
                    <td><a class="btn btn-sm btn-ppms" href="<?= e(url('/groups/' . (int) $req['group_id'])) ?>"><?= e($isAdv ? __('groups.answer_request') : __('groups.approve_request')) ?></a></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div></div>
<?php endif; ?>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-6"><input type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('groups.search_placeholder')) ?>"></div>
    <div class="col-md-3"><select name="status" class="form-select">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach (['draft', 'submitted', 'verified', 'active', 'inactive'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(__('status.' . $s)) ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-3"><button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> <?= e(__('common.search')) ?></button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Group ID</th><th>ชื่อกลุ่ม / หัวข้อ</th><th>สมาชิก</th><th>Advisor</th><th>กลุ่ม</th><th>Topic</th><th>Proposal</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary"><?= e(__('common.none')) ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $g): ?>
        <tr>
            <td><a href="<?= e(url('/groups/' . (int) $g['id'])) ?>"><?= e($g['group_code']) ?></a></td>
            <td><?= e($g['group_name']) ?><?php if ($g['title_th']): ?><div class="small text-secondary"><?= e($g['title_th']) ?></div><?php endif; ?></td>
            <td><?= (int) $g['member_count'] ?></td>
            <td class="small"><?= e($g['advisor_name'] ?? '') ?><?php if (!$g['advisor_name'] && $g['requested_advisor']): ?><span class="text-warning-emphasis">ขอ: <?= e($g['requested_advisor']) ?></span><?php endif; ?></td>
            <td><?= status_badge($g['status']) ?></td>
            <td><?= $g['topic_status'] ? status_badge($g['topic_status']) : '-' ?></td>
            <td><?= $g['proposal_status'] ? status_badge($g['proposal_status']) : '-' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?= $this->partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'params' => ['q' => $q, 'status' => $status]]) ?>
