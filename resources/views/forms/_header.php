<?php /** Department form header. Vars: $heading, $project, $termLabel, ?$showTerm */ ?>
<div class="ppms-form-header text-center mb-3">
    <div class="fw-bold fs-5"><?= e($heading) ?></div>
    <div>หลักสูตรวิศวกรรมศาสตรบัณฑิต สาขาวิชาวิศวกรรมคอมพิวเตอร์</div>
    <div>ภาควิชาวิศวกรรมไฟฟ้าและคอมพิวเตอร์ คณะวิทยาศาสตร์และวิศวกรรมศาสตร์</div>
    <div>มหาวิทยาลัยเกษตรศาสตร์ วิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนคร</div>
    <?php if ($showTerm ?? true): ?><div>ประจำภาค<?= e($termLabel) ?> ปีการศึกษา <?= (int) $project['year_be'] ?></div><?php endif; ?>
    <hr class="border-dark opacity-75">
</div>
