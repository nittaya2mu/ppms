<?php /** Project sub-navigation. Vars: $project, $active (main|progress|scope) */ $b = '/projects/' . (int) $project['id']; ?>
<ul class="nav nav-tabs mb-3 small">
    <li class="nav-item"><a class="nav-link <?= $active === 'main' ? 'active' : '' ?>" href="<?= e(url($b)) ?>"><i class="bi bi-journal-text"></i> <?= e(__('project_tab.main')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $active === 'progress' ? 'active' : '' ?>" href="<?= e(url("$b/progress")) ?>"><i class="bi bi-calendar-check"></i> <?= e(__('project_tab.progress')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $active === 'scope' ? 'active' : '' ?>" href="<?= e(url("$b/scope")) ?>"><i class="bi bi-list-check"></i> <?= e(__('project_tab.scope')) ?></a></li>
</ul>
