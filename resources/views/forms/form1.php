<?php
/** แบบฟอร์ม 1: แบบขอเสนออนุมัติโครงงาน. Vars from FormService::context + $approvals */
$this->layout('layouts/print', ['title' => 'แบบฟอร์ม 1 ' . $project['project_code']]);
$sub = $topic;
$type = $sub['project_type'] ?? null;
$advisorDate = $approvals['advisor'] ?? null;
$adminDate = $approvals['admin'] ?? null;
?>
<?= $this->partial('forms/_header', ['heading' => 'แบบขอเสนออนุมัติโครงงานวิศวกรรมคอมพิวเตอร์', 'project' => $project, 'termLabel' => $termLabel]) ?>
<div class="mb-2"><strong>ชื่อโครงงาน</strong></div>
<div class="ms-3 mb-1">(ภาษาไทย) <?= e($sub['title_th'] ?? '') ?></div>
<div class="ms-3 mb-3">(ภาษาอังกฤษ) <?= e($sub['title_en'] ?? '') ?></div>
<div class="mb-2"><strong>นิสิตที่ทำโครงงาน</strong></div>
<?php foreach ($members as $m): ?>
    <div class="ms-3 mb-1 d-flex"><span class="flex-grow-1">ชื่อ-นามสกุล <?= e(full_name($m)) ?></span><span>รหัสประจำตัว <?= e($m['student_code']) ?></span></div>
<?php endforeach; ?>
<div class="mt-3 mb-2"><strong>ประเภทโครงงาน</strong></div>
<div class="row row-cols-2 ms-2 mb-3">
    <?php foreach (\App\Services\TopicService::PROJECT_TYPES as $t): ?>
        <div class="col"><?= $type === $t ? '☑' : '☐' ?> <?= e(__('project_type.' . $t)) ?><?= $t === 'other' && $type === 'other' ? ' ' . e($sub['project_type_other']) : '' ?></div>
    <?php endforeach; ?>
</div>
<div class="mb-1"><strong>อาจารย์ที่ปรึกษา</strong> <?= $advisor ? e(full_name($advisor)) : '' ?></div>
<div class="mb-4"><strong>อาจารย์ที่ปรึกษาร่วม (ถ้ามี)</strong> <?= e($sub['co_advisor_name'] ?? '') ?></div>
<div class="row g-4 mb-4">
    <?php foreach ($members as $m): ?>
        <div class="col-4"><?= $this->partial('forms/_signature', ['role' => 'นิสิตผู้เสนอโครงงาน', 'person' => $m, 'date' => $sub['submitted_at'] ?? null]) ?></div>
    <?php endforeach; ?>
</div>
<div class="row g-4">
    <div class="col-4"><?= $this->partial('forms/_signature', ['role' => 'อาจารย์ที่ปรึกษา', 'person' => $advisor, 'date' => $advisorDate]) ?></div>
    <div class="col-4"><?= $this->partial('forms/_signature', ['role' => 'อาจารย์ที่ปรึกษาร่วม', 'person' => null, 'date' => null]) ?></div>
    <div class="col-4"><?= $this->partial('forms/_signature', ['role' => 'อาจารย์ประจำวิชา', 'person' => $adminUser, 'date' => $adminDate]) ?></div>
</div>
