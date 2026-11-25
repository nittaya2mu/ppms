<?php /** Signature block. Vars: $role (label), ?$person (user row), ?$date (electronic approval time) */ ?>
<div class="ppms-signature text-center small">
    <div class="mb-1">ลงชื่อ ..................................................</div>
    <div>(<?= !empty($person) ? e(full_name($person)) : '..................................................' ?>)</div>
    <div><?= e($role) ?></div>
    <div>วันที่ <?= !empty($date) ? e(thai_date($date)) : '......../............../............' ?></div>
    <?php if (!empty($date)): ?><div class="text-secondary" style="font-size:.75rem">บันทึกในระบบ PPMS เมื่อ <?= e(thai_date($date, true)) ?></div><?php endif; ?>
</div>
