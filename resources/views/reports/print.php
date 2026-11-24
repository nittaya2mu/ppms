<?php
/** @var string $title @var array $columns @var array $rows @var array $offering */
$this->layout('layouts/print', ['title' => $title]);
?>
<div class="text-center mb-3">
    <div class="fw-semibold"><?= e(__('app.department')) ?></div>
    <h1 class="h5 mt-1"><?= e($title) ?></h1>
    <div class="small"><?= e($offering['course_code'] . ' ' . $offering['course_name_th']) ?> · ภาคการศึกษาที่ <?= (int) $offering['term_no'] ?>/<?= (int) $offering['year_be'] ?></div>
</div>
<table class="table table-sm table-bordered small">
    <thead><tr><?php foreach ($columns as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
</table>
<p class="small text-secondary"><?= e(__('common.total', ['n' => count($rows)])) ?> · พิมพ์เมื่อ <?= e(thai_date(date('Y-m-d H:i:s'), true)) ?></p>
