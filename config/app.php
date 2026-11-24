<?php

declare(strict_types=1);

/**
 * Application configuration.
 * Override any value in config/local.php (not committed), which must return an array.
 */
$config = [
    'name'      => 'PPMS',
    'full_name' => 'Project Preparation Management System',
    'env'       => 'development',          // development | production
    'debug'     => true,
    'url'       => 'http://localhost:8000', // base URL without trailing slash
    'base_path' => '',                      // e.g. '/ppms/public' when served from a sub-folder in XAMPP
    'timezone'  => 'Asia/Bangkok',
    'locale'    => 'th',

    'session' => [
        'name'            => 'PPMS_SESSID',
        'idle_timeout'    => 60 * 60,  // seconds; overridden by system_settings session.timeout_min
        'secure_cookie'   => false,    // set true when served over HTTPS
    ],

    'security' => [
        'max_failed_logins' => 5,
        'lockout_minutes'   => 15,
        'min_password_len'  => 8,
    ],

    'storage' => [
        'uploads' => dirname(__DIR__) . '/storage/uploads',
        'logs'    => dirname(__DIR__) . '/storage/logs',
    ],
];

$local = __DIR__ . '/local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override['app'] ?? []);
    }
}

return $config;
