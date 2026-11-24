<?php /** @var array $choices @var ?int $advisorId @var int[] $defaults */ ?>
<div class="row g-2">
    <?php foreach ([1, 2] as $slot): ?>
        <div class="col-md-4"><label class="form-label small"><?= e(__('evaluations.member', ['n' => $slot])) ?></label>
            <select name="member<?= $slot ?>_id" class="form-select form-select-sm" required>
                <option value=""><?= e(__('common.choose')) ?></option>
                <?php foreach ($choices as $c): if ((int) $c['id'] === (int) $advisorId) { continue; } ?>
                    <option value="<?= (int) $c['id'] ?>" <?= ($defaults[$slot - 1] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e(full_name($c)) ?> (<?= e(__('proposals.pending_load', ['n' => (int) $c['pending_count']])) ?>)</option>
                <?php endforeach; ?>
            </select></div>
    <?php endforeach; ?>
    <div class="col-md-4"><label class="form-label small"><?= e(__('evaluations.deadline')) ?></label>
        <input type="datetime-local" class="form-control form-control-sm" name="deadline" required></div>
</div>
<p class="small text-secondary mt-1 mb-0"><?= e(__('proposals.advisor_excluded')) ?></p>
