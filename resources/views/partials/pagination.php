<?php /** @var int $page @var int $pages @var int $total @var array $params */ ?>
<div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-secondary"><?= e(__('common.total', ['n' => $total])) ?></small>
    <?php if ($pages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query($params + ['page' => $p])) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
</div>
