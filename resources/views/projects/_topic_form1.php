<?php
/** Read-only view of a topic submission laid out like form 1. Vars: $sub, $members, $advisor */
$type = $sub['project_type'] ?? null;
?>
<dl class="row small mb-0">
    <dt class="col-md-3"><?= e(__('topic_field.title_th')) ?></dt><dd class="col-md-9"><?= e($sub['title_th']) ?></dd>
    <dt class="col-md-3"><?= e(__('topic_field.title_en')) ?></dt><dd class="col-md-9"><?= e($sub['title_en']) ?></dd>
    <dt class="col-md-3"><?= e(__('topic_field.students')) ?></dt>
    <dd class="col-md-9"><?php foreach ($members as $m): ?><div><?= e(full_name($m)) ?> · <?= e(__('topics.student_code')) ?> <?= e($m['student_code']) ?></div><?php endforeach; ?></dd>
    <dt class="col-md-3"><?= e(__('topic_field.project_type')) ?></dt>
    <dd class="col-md-9"><?= $type ? e(__('project_type.' . $type)) . ($type === 'other' ? ': ' . e($sub['project_type_other']) : '') : '-' ?></dd>
    <dt class="col-md-3"><?= e(__('topic_field.advisor')) ?></dt><dd class="col-md-9"><?= $advisor && $advisor['status'] === 'assigned' ? e(full_name($advisor)) : '-' ?></dd>
    <dt class="col-md-3"><?= e(__('topic_field.co_advisor_name')) ?></dt><dd class="col-md-9"><?= e($sub['co_advisor_name'] ?: '-') ?></dd>
    <?php foreach (['background', 'problem_statement', 'objectives', 'scope', 'expected_outcomes', 'methodology', 'expected_benefits', 'related_work', 'references_text'] as $legacy): ?>
        <?php if (!empty($sub[$legacy])): /* versions submitted before form 1 */ ?>
            <dt class="col-md-3 text-secondary"><?= e(__('topic_field.' . $legacy)) ?></dt><dd class="col-md-9 text-secondary"><?= nl2br(e($sub[$legacy])) ?></dd>
        <?php endif; ?>
    <?php endforeach; ?>
</dl>
