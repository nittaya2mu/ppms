<?php

// Copy this file to config/local.php and adjust for your machine. local.php is not committed.
return [
    'app' => [
        'env'       => 'development',
        'debug'     => true,
        'url'       => 'http://localhost/ppms/public',
        'base_path' => '/ppms/public',   // XAMPP: http://localhost/ppms/public
    ],
    'database' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'ppms',
        'username' => 'root',
        'password' => '',
    ],
];
