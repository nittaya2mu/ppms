<?php
/** @var array $project @var array $members @var ?array $advisor @var ?array $proposal @var ?array $final @var ?array $committee */
$this->layout('layouts/print', ['title' => __('final.summary') . ' ' . $project['project_code']]);
$evals = $committee['evaluations'] ?? [];
?>
<div class="text-center mb-4">
    <div class="fw-semibold"><?= e(__('app.department')) ?></div>
    <h1 class="h4 mt-2"><?= e(__('final.summary_heading')) ?></h1>
    <div><?= e($project['course_code'] . ' ' . $project['course_name_th']) ?> · ภาคการศึกษาที่ <?= (int) $project['term_no'] ?>/<?= (int) $project['year_be'] ?></div>
</div>
<table class="table table-bordered align-middle">
    <tbody>
        <tr><th style="width:30%">ชื่อโครงงาน (ภาษาไทย)</th><td><?= e($project['title_th']) ?></td></tr>
        <tr><th>Project Title (English)</th><td><?= e($project['title_en']) ?></td></tr>
        <tr><th>รหัสโครงงาน / กลุ่ม</th><td><?= e($project['project_code']) ?> · <?= e($project['group_code']) ?> <?= e($project['group_name']) ?></td></tr>
        <tr><th>สมาชิกกลุ่ม</th><td><?php foreach ($members as $m): ?><div><?= e($m['student_code']) ?> <?= e(full_name($m)) ?><?= $m['member_role'] === 'leader' ? ' (Group Leader)' : '' ?></div><?php endforeach; ?></td></tr>
        <tr><th>อาจารย์ที่ปรึกษา</th><td><?= $advisor ? e(full_name($advisor)) : '-' ?></td></tr>
        <?php foreach ($evals as $ev): ?>
            <tr><th>ผลประเมินการนำเสนอ: <?= e(__('evaluator_role.' . $ev['evaluator_role'])) ?></th><td><?= $showNames || $ev['evaluator_role'] === 'advisor' ? e(full_name($ev)) . ' · ' : '' ?>คะแนน <?= e($ev['total_score']) ?> (<?= e($ev['letter_grade']) ?>) · <?= e(__('presentation_result.' . $ev['result'])) ?></td></tr>
        <?php endforeach; ?>
        <tr><th>คะแนนเฉลี่ย (S<sub>avg</sub>)</th><td><?= $committee ? e($committee['assignment']['average_score']) . ' (รอบที่ ' . (int) $committee['assignment']['round_no'] . ')' : '-' ?></td></tr>
        <tr><th>ผลการพิจารณาขั้นสุดท้าย</th><td class="fw-semibold"><?= e(__('decision.' . $final['decision'])) ?></td></tr>
        <tr><th>ความเห็น / เหตุผลประกอบ</th><td><?= nl2br(e(trim(($final['comments'] ?? '') . "\n" . ($final['supporting_reasons'] ?? '')))) ?></td></tr>
        <?php if ($final['justification']): ?><tr><th>เหตุผลที่ต่างจากข้อเสนอแนะของกรรมการ</th><td><?= nl2br(e($final['justification'])) ?></td></tr><?php endif; ?>
        <tr><th>วันที่อนุมัติ / บันทึกผล</th><td><?= e(thai_date($project['final_approved_at'] ?? $final['decided_at'], true)) ?></td></tr>
        <tr><th>ผู้บันทึกผล</th><td><?= e(full_name($final)) ?> (ผู้ประสานงานรายวิชา)</td></tr>
    </tbody>
</table>
<div class="row mt-5 text-center small">
    <div class="col">.......................................................<br>(<?= e(full_name($final)) ?>)<br>ผู้ประสานงานรายวิชา</div>
    <div class="col">.......................................................<br>(<?= $advisor ? e(full_name($advisor)) : '' ?>)<br>อาจารย์ที่ปรึกษา</div>
</div>
<p class="small text-secondary mt-4">ออกจากระบบ PPMS เมื่อ <?= e(thai_date(date('Y-m-d H:i:s'), true)) ?> · เอกสารนี้สร้างจากข้อมูลในระบบ (Proposal v<?= (int) ($proposal['version_no'] ?? 0) ?>)</p>
