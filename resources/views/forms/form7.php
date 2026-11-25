<?php
/** แบบฟอร์ม 7: แบบอนุมัติขอบเขตโครงงาน. Vars from FormService::context + $scope */
$this->layout('layouts/print', ['title' => 'แบบฟอร์ม 7 ' . $project['project_code']]);
$rows = max(count($scope['items']), 10);
$date = fn (string $role) => isset($scope['approvals'][$role]) && $scope['approvals'][$role]['decision'] === 'approved' ? $scope['approvals'][$role]['decided_at'] : null;
?>
<div class="text-center mb-3">
    <div class="fw-bold fs-5">แบบอนุมัติขอบเขตโครงงานวิศวกรรมคอมพิวเตอร์</div>
    <div>ภาค<?= e($termLabel) ?> ปีการศึกษา <?= (int) $project['year_be'] ?></div>
    <div>ภาควิชาวิศวกรรมไฟฟ้าและคอมพิวเตอร์</div>
    <div>คณะวิทยาศาสตร์และวิศวกรรมศาสตร์</div>
</div>
<div class="mb-2"><strong>ชื่อโครงงาน</strong> <?= e($project['title_th']) ?><?= $project['title_en'] ? ' (' . e($project['title_en']) . ')' : '' ?></div>
<div class="mb-1"><strong>นิสิตที่ทำโครงงาน</strong></div>
<?php foreach ($members as $m): ?>
    <div class="ms-3 d-flex"><span class="flex-grow-1">ชื่อ-นามสกุล <?= e(full_name($m)) ?></span><span>รหัสประจำตัวนิสิต <?= e($m['student_code']) ?></span></div>
<?php endforeach; ?>
<div class="text-center fw-semibold mt-3 mb-2">ตารางแสดงขอบเขตโครงงานพร้อมค่าน้ำหนักของแต่ละหัวข้อ</div>
<table class="table table-bordered table-sm">
    <thead><tr class="text-center"><th style="width:3rem">ที่</th><th>รายละเอียดขอบเขตโครงงาน</th><th style="width:7rem">ค่าน้ำหนัก</th></tr></thead>
    <tbody>
    <?php for ($i = 0; $i < $rows; $i++): $it = $scope['items'][$i] ?? null; ?>
        <tr><td class="text-center"><?= $i + 1 ?></td><td><?= $it ? nl2br(e($it['detail'])) : '&nbsp;' ?></td><td class="text-end"><?= $it ? e(rtrim(rtrim(number_format((float) $it['weight'], 2), '0'), '.')) . '%' : '' ?></td></tr>
    <?php endfor; ?>
    <tr class="fw-semibold"><td colspan="2" class="text-center">รวมทั้งหมด</td><td class="text-end">100%</td></tr>
    </tbody>
</table>
<div class="row g-4 mt-3">
    <div class="col-6"><?= $this->partial('forms/_signature', ['role' => 'อาจารย์ที่ปรึกษา', 'person' => $advisor, 'date' => $date('advisor')]) ?></div>
    <div class="col-6"><?= $this->partial('forms/_signature', ['role' => 'อาจารย์ที่ปรึกษาร่วม (ถ้ามี)', 'person' => null, 'date' => null]) ?></div>
    <div class="col-6"><?= $this->partial('forms/_signature', ['role' => 'กรรมการคนที่ 1', 'person' => $committee[1] ?? null, 'date' => $date('committee1')]) ?></div>
    <div class="col-6"><?= $this->partial('forms/_signature', ['role' => 'กรรมการคนที่ 2', 'person' => $committee[2] ?? null, 'date' => $date('committee2')]) ?></div>
</div>
<div class="small text-secondary mt-3">ฉบับที่ <?= (int) $scope['version_no'] ?> · สถานะ: <?= e(__('scope.status_' . $scope['status'])) ?> · ส่งเมื่อ <?= e(thai_date($scope['submitted_at'], true)) ?></div>
