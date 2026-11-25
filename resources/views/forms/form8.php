<?php
/** แบบฟอร์ม 8: สมุดบันทึกการเข้าพบอาจารย์ที่ปรึกษาโครงงาน. Vars from FormService::context + reports, rubric */
$this->layout('layouts/print', ['title' => 'แบบฟอร์ม 8 ' . $project['project_code']]);
$topicCo = $topic['co_advisor_name'] ?? '';
?>
<div class="text-center mb-3">
    <div>หลักสูตรวิศวกรรมคอมพิวเตอร์</div>
    <div>ภาควิชาวิศวกรรมไฟฟ้าและคอมพิวเตอร์ คณะวิทยาศาสตร์และวิศวกรรมศาสตร์</div>
    <div class="fw-bold fs-5 mt-2">สมุดบันทึกการเข้าพบอาจารย์ที่ปรึกษาโครงงาน</div>
    <div>ปีการศึกษา <?= (int) $project['year_be'] ?></div>
</div>
<div class="mb-1"><strong>ชื่อโครงงาน</strong></div>
<div class="ms-3">(ภาษาไทย) <?= e($project['title_th']) ?></div>
<div class="ms-3 mb-2">(ภาษาอังกฤษ) <?= e($project['title_en']) ?></div>
<div class="mb-1"><strong>นิสิตที่ทำโครงงาน</strong></div>
<?php foreach ($members as $m): ?>
    <div class="ms-3 d-flex"><span class="flex-grow-1">ชื่อ-นามสกุล <?= e(full_name($m)) ?></span><span>รหัสประจำตัว <?= e($m['student_code']) ?></span></div>
<?php endforeach; ?>
<div class="mt-2"><strong>อาจารย์ที่ปรึกษาหลัก</strong> <?= $advisor ? e(full_name($advisor)) : '' ?></div>
<div class="mb-3"><strong>อาจารย์ที่ปรึกษาร่วม</strong> <?= e($topicCo) ?></div>

<?php if (!$reports): ?><p class="text-secondary">ยังไม่มีบันทึกการเข้าพบ</p><?php endif; ?>
<?php foreach ($reports as $rep): ?>
    <div class="ppms-page-block border border-dark p-3 mb-4">
        <div class="fw-bold text-center mb-2">สรุปบันทึกการเข้าพบอาจารย์ที่ปรึกษา ครั้งที่ <?= (int) $rep['report_no'] ?> (วันที่ <?= e(thai_date($rep['meeting_date'])) ?>)</div>
        <div class="fw-semibold">ตอนที่ 1 ส่วนของนิสิต</div>
        <div class="ms-2 small">สรุปผลการทำโครงงาน</div>
        <div class="ms-3 mb-2"><?= nl2br(e($rep['work_summary'])) ?></div>
        <div class="fw-semibold">ตอนที่ 2 ส่วนของอาจารย์ที่ปรึกษา</div>
        <div class="ms-2 small">ข้อเสนอแนะที่ควรปรับปรุง</div>
        <div class="ms-3 mb-2"><?= $rep['suggestions'] ? nl2br(e($rep['suggestions'])) : '-' ?></div>
        <div class="small fw-semibold">คะแนนการรายงานความก้าวหน้า (เฉพาะอาจารย์ที่ปรึกษา)</div>
        <table class="table table-bordered table-sm small mb-1">
            <?php foreach ($rubric as $lvl): $on = $rep['score'] !== null && (float) $rep['score'] === $lvl['score']; ?>
                <tr><td style="width:2rem" class="text-center"><?= $on ? '☑' : '☐' ?></td><td style="width:6rem"><?= e(number_format($lvl['score'], 0)) ?> คะแนน</td><td><?= e($lvl['label']) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($rep['score'] !== null && !in_array((float) $rep['score'], array_column($rubric, 'score'), true)): ?>
                <tr><td class="text-center">☑</td><td><?= e(number_format((float) $rep['score'], 0)) ?> คะแนน</td><td>(ให้คะแนนตามเกณฑ์ที่ใช้ขณะประเมิน)</td></tr>
            <?php endif; ?>
        </table>
        <div class="small text-secondary mb-3">* ของงานที่ได้รับมอบหมายในแต่ละครั้ง</div>
        <div class="row g-3">
            <div class="col-4"><?= $this->partial('forms/_signature', ['role' => 'อาจารย์ที่ปรึกษา', 'person' => $advisor, 'date' => $rep['reviewed_at']]) ?></div>
            <div class="col-4"><?= $this->partial('forms/_signature', ['role' => 'อาจารย์ที่ปรึกษาร่วม', 'person' => null, 'date' => null]) ?></div>
            <div class="col-4"><?= $this->partial('forms/_signature', ['role' => 'อาจารย์ประจำวิชา', 'person' => null, 'date' => null]) ?></div>
        </div>
    </div>
<?php endforeach; ?>
