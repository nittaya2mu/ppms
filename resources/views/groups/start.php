<?php
/** @var array $invitations @var array $offering */
$this->layout('layouts/app', ['title' => __('menu.groups')]);
?>
<h1 class="h4 mb-3"><?= e(__('menu.groups')) ?></h1>
<?php if ($invitations): ?>
    <div class="card mb-3 border-success"><div class="card-body">
        <h2 class="h6"><i class="bi bi-envelope-open"></i> <?= e(__('groups.invitations')) ?></h2>
        <?php foreach ($invitations as $inv): ?>
            <div class="d-flex flex-wrap align-items-center justify-content-between border-top py-2 gap-2">
                <div><strong><?= e($inv['group_code']) ?></strong> <?= e($inv['group_name']) ?>
                    <div class="small text-secondary">Group Leader: <?= e(full_name($inv)) ?></div></div>
                <div class="d-flex gap-2">
                    <?php foreach (['accept' => ['btn-ppms', 'groups.accept'], 'decline' => ['btn-outline-secondary', 'groups.decline']] as $ans => [$cls, $label]): ?>
                        <form method="post" action="<?= e(url('/groups/invitations/' . (int) $inv['member_id'])) ?>">
                            <?= csrf_field() ?><input type="hidden" name="answer" value="<?= $ans ?>">
                            <button class="btn btn-sm <?= $cls ?>"><?= e(__($label)) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div></div>
<?php endif; ?>
<div class="card"><div class="card-body">
    <h2 class="h6"><?= e(__('groups.create')) ?></h2>
    <p class="small text-secondary"><?= e(__('groups.create_help')) ?></p>
    <form method="post" action="<?= e(url('/groups')) ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-8"><input class="form-control" name="group_name" maxlength="200" required placeholder="<?= e(__('groups.name')) ?>" value="<?= e(old('group_name')) ?>"></div>
        <div class="col-md-4"><button class="btn btn-ppms w-100"><i class="bi bi-people"></i> <?= e(__('groups.create')) ?></button></div>
    </form>
</div></div>
