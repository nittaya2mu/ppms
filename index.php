<?php

declare(strict_types=1);

/*
 * This file only runs when the web server did NOT apply the rewrite rules in .htaccess
 * (with .htaccess working, every request is sent to public/ instead).
 * It shows an installation check in Thai instead of a bare "403 Forbidden".
 * It never prints secrets.
 */

$root = __DIR__;
$checks = [
    ['.htaccess (ที่ราก htdocs)', is_file($root . '/.htaccess'), 'สร้างไฟล์ชื่อ .htaccess ที่ราก htdocs (ดู docs/deploy-infinityfree.md หรือคัดลอกจาก GitHub)'],
    ['public/.htaccess', is_file($root . '/public/.htaccess'), 'สร้างไฟล์ public/.htaccess'],
    ['public/index.php', is_file($root . '/public/index.php'), 'อัปโหลดโฟลเดอร์ public ให้ครบ'],
    ['config/local.php', is_file($root . '/config/local.php'), 'เปลี่ยนชื่อ config/local.infinityfree.example.php เป็น local.php แล้วแก้ค่าฐานข้อมูล'],
    ['โฟลเดอร์ storage เขียนได้', is_dir($root . '/storage') && is_writable($root . '/storage'), 'สร้างโฟลเดอร์ storage (ระบบจะสร้าง uploads, logs, cache ให้เอง)'],
];
$rewriteNote = is_file($root . '/.htaccess')
    ? 'มีไฟล์ .htaccess แล้ว แต่เซิร์ฟเวอร์ยังไม่ใช้กฎ rewrite: ตรวจว่าไฟล์ชื่อ .htaccess ตรงทุกตัวอักษร (ไม่มี .txt ต่อท้าย) และอยู่ในโฟลเดอร์ htdocs'
    : 'ไม่พบไฟล์ .htaccess: โปรแกรมอัปโหลดบางตัวข้ามไฟล์ที่ขึ้นต้นด้วยจุด ให้สร้างไฟล์เองด้วยเมนู New File';

http_response_code(500);
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PPMS: ตรวจการติดตั้ง</title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#1f2937}h1{color:#12305a;font-size:1.4rem}li{margin:.5rem 0}.ok{color:#1f9d55}.bad{color:#c0392b}code{background:#f3f5f8;padding:1px 4px}</style></head>
<body>
<h1>PPMS: การติดตั้งยังไม่สมบูรณ์</h1>
<p><?= htmlspecialchars($rewriteNote, ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<?php foreach ($checks as [$label, $ok, $fix]): ?>
    <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✔' : '✘' ?> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        <?php if (!$ok): ?><br><small><?= htmlspecialchars($fix, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></li>
<?php endforeach; ?>
</ul>
<p>คู่มือ: <code>docs/deploy-infinityfree.md</code></p>
</body>
</html>
