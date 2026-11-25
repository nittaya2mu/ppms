<?php
/** แบบฟอร์ม 7: แบบอนุมัติขอบเขตโครงงาน. Vars from FormService::context + scope, history, blocker, approvers, ... */
use App\Services\ScopeService;

$this->layout('layouts/app', ['title' => __('scope.title') . ' ' . $project['project_code']]);
$base = '/projects/' . (int) $project['id'];
$canSubmit = $isLeader && $blocker === null;
$prefill = ($scope && $scope['status'] === 'revision_required') ? $scope['items'] : [];
$roleNames = ['advisor' => $advisor ?? null, 'committee1' => $committee[1] ?? null, 'committee2' => $committee[2] ?? null];
?>
<?= $this->partial('projects/_head', ['project' => $project]) ?>
<?= $this->partial('projects/_tabs', ['project' => $project, 'active' => 'scope']) ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h2 class="h5 mb-1"><?= e(__('scope.title')) ?></h2>
        <div class="small text-secondary"><?= e(__('scope.help')) ?></div>
    </div>
    <div class="text-end">
        <?php if ($scope): ?><span class="badge <?= ['approved' => 'text-bg-success', 'pending' => 'text-bg-warning', 'revision_required' => 'text-bg-danger'][$scope['status']] ?>"><?= e(__('scope.status_' . $scope['status'])) ?></span> <span class="small text-secondary">v<?= (int) $scope['version_no'] ?></span><br>
            <a class="btn btn-sm btn-outline-secondary mt-1" href="<?= e(url("$base/scope/print")) ?>"><i class="bi bi-printer"></i> <?= e(__('scope.print')) ?></a><?php endif; ?>
    </div>
</div>

<div class="small mb-3"><?= e(__('scope.prerequisites')) ?>:
    <span class="<?= $proposal && $proposal['status'] === 'approved' ? 'text-success' : 'text-secondary' ?>"><i class="bi bi-<?= $proposal && $proposal['status'] === 'approved' ? 'check-circle' : 'circle' ?>"></i> <?= e(__('scope.pre_proposal')) ?></span> ·
    <span class="<?= $reviewed >= $minReports ? 'text-success' : 'text-secondary' ?>"><i class="bi bi-<?= $reviewed >= $minReports ? 'check-circle' : 'circle' ?>"></i> <?= e(__('scope.pre_progress', ['n' => $reviewed, 'min' => $minReports])) ?></span>
</div>

<?php if ($scope): ?>
    <div class="card mb-3"><div class="card-body">
        <h3 class="h6"><?= e(__('scope.table_title')) ?> (v<?= (int) $scope['version_no'] ?> · <?= e(thai_date($scope['submitted_at'], true)) ?>)</h3>
        <table class="table table-sm table-bordered small mb-3">
            <thead class="table-light"><tr><th style="width:3rem" class="text-center">ที่</th><th><?= e(__('scope.detail')) ?></th><th style="width:8rem" class="text-end"><?= e(__('scope.weight')) ?></th></tr></thead>
            <tbody><?php foreach ($scope['items'] as $i => $it): ?>
                <tr><td class="text-center"><?= $i + 1 ?></td><td><?= nl2br(e($it['detail'])) ?></td><td class="text-end"><?= e(rtrim(rtrim(number_format((float) $it['weight'], 2), '0'), '.')) ?>%</td></tr>
            <?php endforeach; ?></tbody>
            <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end"><?= e(__('scope.total')) ?></td><td class="text-end">100%</td></tr></tfoot>
        </table>
        <h3 class="h6"><?= e(__('scope.approvals')) ?></h3>
        <div class="row g-2">
            <?php foreach (ScopeService::ROLES as $role): $a = $scope['approvals'][$role] ?? null; ?>
                <div class="col-md-4"><div class="border rounded p-2 h-100 small">
                    <div class="text-secondary"><?= e(__('evaluator_role.' . $role)) ?></div>
                    <div class="fw-semibold"><?= !empty($roleNames[$role]) ? e(full_name($roleNames[$role])) : '-' ?></div>
                    <?php if ($a): ?>
                        <div class="<?= $a['decision'] === 'approved' ? 'text-success' : 'text-danger' ?>"><i class="bi bi-<?= $a['decision'] === 'approved' ? 'check-circle' : 'arrow-counterclockwise' ?>"></i> <?= e(__('scope.decision_' . $a['decision'])) ?> · <?= e(thai_date($a['decided_at'], true)) ?></div>
                        <?php if ($a['comments']): ?><div><?= nl2br(e($a['comments'])) ?></div><?php endif; ?>
                    <?php else: ?>
                        <div class="text-secondary"><i class="bi bi-hourglass-split"></i> <?= e($scope['status'] === 'pending' ? __('scope.waiting') : '-') ?></div>
                    <?php endif; ?>
                </div></div>
            <?php endforeach; ?>
        </div>

        <?php if ($pendingRoles): ?>
            <form method="post" action="<?= e(url("$base/scope/decide")) ?>" class="border border-primary rounded p-3 mt-3" data-confirm="<?= e(__('common.decision_confirm')) ?>">
                <?= csrf_field() ?>
                <h3 class="h6"><?= e(__('scope.decide_as', ['role' => __('evaluator_role.' . $pendingRoles[0])])) ?></h3>
                <div class="row g-2">
                    <div class="col-md-4"><select name="decision" class="form-select" required>
                        <option value="approved"><?= e(__('scope.decision_approved')) ?></option>
                        <option value="revision_required"><?= e(__('scope.decision_revision_required')) ?></option>
                    </select></div>
                    <div class="col-md-8"><textarea name="comments" class="form-control" rows="2" placeholder="<?= e(__('common.comments_placeholder')) ?>"></textarea></div>
                </div>
                <button class="btn btn-ppms mt-2"><i class="bi bi-check2-square"></i> <?= e(__('common.record_decision')) ?></button>
                <span class="small text-secondary ms-2"><?= e(__('scope.decide_help')) ?></span>
            </form>
        <?php endif; ?>
    </div></div>
<?php endif; ?>

<?php if ($canSubmit): ?>
    <div class="card mb-3 border-primary"><div class="card-body">
        <h3 class="h6"><?= e($scope ? __('scope.resubmit') : __('scope.submit_title')) ?></h3>
        <p class="small text-secondary"><?= e(__('scope.form_help', ['max' => ScopeService::MAX_ITEMS])) ?></p>
        <form method="post" action="<?= e(url("$base/scope")) ?>" data-confirm="<?= e(__('scope.submit_confirm')) ?>">
            <?= csrf_field() ?>
            <table class="table table-sm align-middle">
                <thead><tr><th style="width:3rem" class="text-center">ที่</th><th><?= e(__('scope.detail')) ?></th><th style="width:9rem"><?= e(__('scope.weight')) ?> (%)</th></tr></thead>
                <tbody>
                <?php $oldD = (array) old('detail', []); $oldW = (array) old('weight', []);
                for ($i = 0; $i < ScopeService::MAX_ITEMS; $i++): $d = $oldD[$i] ?? ($prefill[$i]['detail'] ?? ''); $w = $oldW[$i] ?? (isset($prefill[$i]) ? (string) $prefill[$i]['weight'] : ''); ?>
                    <tr><td class="text-center"><?= $i + 1 ?></td>
                        <td><input class="form-control form-control-sm" name="detail[]" maxlength="1000" value="<?= e($d) ?>"></td>
                        <td><input class="form-control form-control-sm text-end js-scope-weight" name="weight[]" type="number" min="0" max="100" step="0.01" value="<?= e($w) ?>"></td></tr>
                <?php endfor; ?>
                </tbody>
                <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end"><?= e(__('scope.total')) ?></td><td class="text-end" id="scopeTotal">0%</td></tr></tfoot>
            </table>
            <button class="btn btn-ppms"><i class="bi bi-send"></i> <?= e(__('scope.submit')) ?></button>
        </form>
    </div></div>
<?php elseif ($isLeader && $blocker !== null && $blocker !== 'scope_pending' && $blocker !== 'scope_already_approved'): ?>
    <div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('rule.' . $blocker, ['min' => $minReports])) ?></div>
<?php elseif (!$scope): ?>
    <p class="text-secondary"><?= e(__('scope.none')) ?></p>
<?php endif; ?>

<?php if (count($history) > 1): ?>
    <details class="small"><summary><?= e(__('scope.history')) ?> (<?= count($history) ?>)</summary>
        <ul><?php foreach ($history as $h): ?><li>v<?= (int) $h['version_no'] ?> · <?= e(thai_date($h['submitted_at'], true)) ?> · <?= e(__('scope.status_' . $h['status'])) ?>
            <?php foreach ($h['approvals'] as $a): if ($a['comments']): ?><div class="text-secondary"><?= e(__('evaluator_role.' . $a['approver_role'])) ?>: <?= e($a['comments']) ?></div><?php endif; endforeach; ?></li><?php endforeach; ?></ul>
    </details>
<?php endif; ?>
