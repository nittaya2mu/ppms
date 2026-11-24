<?php

declare(strict_types=1);

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
