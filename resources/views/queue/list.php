<?php
/** @var string $titleKey @var string $kind @var array $rows @var string $anchor @var ?string $help @var ?array $secondary @var ?string $secondaryTitle */
$this->layout('layouts/app', ['title' => __($titleKey)]);
$table = function (array $rows, ?string $anchor = null, ?string $kindOverride = null) use ($kind) {
    $kind = $kindOverride ?? $kind;
    ob_start(); ?>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Project</th><th>กลุ่ม</th><th>หัวข้อ</th><th>Version</th><th>ส่งเมื่อ</th><?php if ($kind === 'proposal'): ?><th>S<sub>avg</sub></th><?php endif; ?><th><?= e(__('common.status')) ?></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-secondary"><?= e(__('queue.empty')) ?></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><a href="<?= e(url('/projects/' . (int) $r['project_id'] . $anchor)) ?>"><?= e($r['project_code']) ?></a></td>
                <td class="small"><?= e($r['group_code']) ?><br><?= e($r['group_name']) ?></td>
                <td><?= e($r['title_th'] ?? '') ?></td>
                <td><?= $r['version_no'] ? 'v' . (int) $r['version_no'] : '-' ?></td>
                <td class="small"><?= e(thai_date($r['submitted_at'] ?? null, true)) ?><?= !empty($r['is_late']) ? ' <span class="badge text-bg-warning">late</span>' : '' ?></td>
                <?php if ($kind === 'proposal'): ?><td><?= $r['average_score'] !== null ? e($r['average_score']) : '-' ?></td><?php endif; ?>
                <td><?= status_badge($r['status']) ?><?php if (!empty($r['needs_admin_attention'])): ?> <span class="badge text-bg-danger"><?= e(__('final.disagreement')) ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="h4 mb-1"><?= e(__($titleKey)) ?></h1>
<?php if ($help): ?><p class="text-secondary small"><?= e($help) ?></p><?php endif; ?>
<?= $table($rows, $anchor) ?>
<?php if ($secondary !== null): ?>
    <h2 class="h6 mt-4"><?= e($secondaryTitle) ?></h2>
    <?= $table($secondary, $secondaryAnchor ?? $anchor, 'chapter') ?>
<?php endif; ?>
