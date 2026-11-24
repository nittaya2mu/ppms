<?php
/** @var string $key @var string $title @var array $columns @var array $rows @var array $offering */
$this->layout('layouts/app', ['title' => $title]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><a href="<?= e(url('/reports')) ?>" class="small">&larr; <?= e(__('menu.reports')) ?></a>
        <h1 class="h4 mb-0"><?= e($title) ?></h1></div>
    <div class="d-flex gap-1">
        <a class="btn btn-sm btn-outline-success" href="?format=xlsx"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a class="btn btn-sm btn-outline-secondary" href="?format=csv">CSV</a>
        <a class="btn btn-sm btn-outline-danger" href="?format=print" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    </div>
</div>
<input type="search" class="form-control mb-2 js-table-filter" data-target="#reportTable" placeholder="<?= e(__('common.filter_table')) ?>">
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" id="reportTable">
    <thead class="table-light"><tr><?php foreach ($columns as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="<?= count($columns) ?>" class="text-secondary"><?= e(__('common.none')) ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody>
</table></div></div>
<p class="small text-secondary mt-2"><?= e(__('common.total', ['n' => count($rows)])) ?></p>
