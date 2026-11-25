<?php

declare(strict_types=1);

// Requires PHP 8.2+. This check uses only old syntax so it runs (and explains) on older PHP.
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>PPMS: ต้องใช้ PHP 8.2 ขึ้นไป</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px">'
        . '<h1 style="color:#12305a;font-size:1.4rem">PPMS ต้องใช้ PHP 8.2 ขึ้นไป</h1>'
        . '<p>เครื่องนี้ใช้ PHP ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>XAMPP: ดาวน์โหลดและติดตั้ง XAMPP รุ่นที่มี PHP 8.2 จาก https://www.apachefriends.org แล้วคัดลอกโฟลเดอร์ ppms ไปไว้ใน htdocs ใหม่และ import ฐานข้อมูลอีกครั้ง</p>'
        . '</body></html>';
    exit;
}

// Front controller. Point the web server's document root at this folder.
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    // Fallback autoloader so the app runs without Composer (shared hosting / XAMPP).
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    require dirname(__DIR__) . '/app/Helpers/functions.php';
}

// Built-in PHP server: serve static assets directly.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

App\Core\App::run();
