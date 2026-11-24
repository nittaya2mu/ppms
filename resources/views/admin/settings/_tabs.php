<?php /** @var string $tab */ ?>
<h1 class="h4 mb-3"><?= e(__('menu.settings')) ?></h1>
<ul class="nav nav-tabs mb-3">
    <?php foreach (['system' => '/admin/settings', 'academic' => '/admin/settings/academic', 'deadlines' => '/admin/settings/deadlines', 'rubric' => '/admin/settings/rubric'] as $key => $href): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>"><?= e(__('settings.tab_' . $key)) ?></a></li>
    <?php endforeach; ?>
</ul>
