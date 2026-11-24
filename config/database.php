<?php

declare(strict_types=1);

/**
 * Database configuration (MySQL 8+ / MariaDB 10.6+).
 * Override in config/local.php under the 'database' key.
 * Environment variables PPMS_DB_* take precedence (used by the test suite).
 */
$config = [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'ppms',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
];

$local = __DIR__ . '/local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace($config, $override['database'] ?? []);
    }
}

foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
    $env = getenv('PPMS_DB_' . strtoupper($key));
    if ($env !== false) {
        $config[$key] = $key === 'port' ? (int) $env : $env;
    }
}

return $config;
