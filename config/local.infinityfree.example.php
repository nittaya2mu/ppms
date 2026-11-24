<?php

// InfinityFree (shared hosting) settings.
// 1. Rename this file to local.php (in the same config/ folder) on the server.
// 2. Replace every value marked CHANGE with the values from the InfinityFree
//    control panel: "MySQL Databases" page and your site's address.
return [
    'app' => [
        'env'       => 'production',
        'debug'     => false,                                   // never true on a public site
        'url'       => 'https://CHANGE.infinityfreeapp.com',    // CHANGE: your site address
        'base_path' => '',                                      // files are uploaded to htdocs/ directly
        'session'   => [
            'secure_cookie' => true,                            // set false only if SSL is not installed yet
        ],
    ],
    'database' => [
        'host'     => 'sqlXXX.infinityfree.com',                // CHANGE: "MySQL Hostname" (not localhost)
        'port'     => 3306,
        'database' => 'if0_XXXXXXXX_ppms',                      // CHANGE: "MySQL DB Name"
        'username' => 'if0_XXXXXXXX',                           // CHANGE: "MySQL Username"
        'password' => 'CHANGE',                                 // CHANGE: your hosting account password
    ],
];
