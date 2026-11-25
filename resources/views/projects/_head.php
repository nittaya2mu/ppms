<?php /** Project heading for sub-pages. Vars: $project */ ?>
<div class="mb-2">
    <div class="small text-secondary"><?= e($project['project_code']) ?> · <?= e($project['group_code']) ?> <?= e($project['group_name']) ?></div>
    <h1 class="h4 mb-0"><?= e($project['title_th'] ?: __('topics.untitled')) ?></h1>
    <?php if ($project['title_en']): ?><div class="text-secondary"><?= e($project['title_en']) ?></div><?php endif; ?>
</div>
