<?php
/** @var string[] $keys @var array $offering */
$this->layout('layouts/app', ['title' => __('menu.reports')]);
?>
<h1 class="h4 mb-1"><?= e(__('menu.reports')) ?></h1>
<p class="text-secondary small"><?= e($offering['course_code'] . ' ' . $offering['course_name_th']) ?> · ภาค <?= (int) $offering['term_no'] ?>/<?= (int) $offering['year_be'] ?> · <?= e(__('report.help')) ?></p>
<div class="row g-3">
    <?php foreach ($keys as $i => $k): ?>
        <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body d-flex flex-column">
            <h2 class="h6"><?= $i + 1 ?>. <?= e(__('report.' . $k)) ?></h2>
            <p class="small text-secondary flex-grow-1"><?= e(__('report_desc.' . $k)) ?></p>
            <div class="d-flex flex-wrap gap-1">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/reports/' . $k)) ?>"><i class="bi bi-table"></i> <?= e(__('report.view')) ?></a>
                <a class="btn btn-sm btn-outline-success" href="<?= e(url('/reports/' . $k . '?format=xlsx')) ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports/' . $k . '?format=csv')) ?>">CSV</a>
                <a class="btn btn-sm btn-outline-danger" href="<?= e(url('/reports/' . $k . '?format=print')) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            </div>
        </div></div></div>
    <?php endforeach; ?>
</div>
