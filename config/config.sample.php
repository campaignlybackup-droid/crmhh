<?php
/**
 * Copy this file to config.php and fill in your real database credentials.
 * config.php is excluded from version control and blocked from web access by .htaccess.
 */

return [
    'db' => [
        'host'     => 'localhost',
        'name'     => 'your_database_name',
        'user'     => 'your_database_user',
        'pass'     => 'your_database_password',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'name'      => 'Agency CRM',
        'url'       => 'https://example.com/crm',
        'timezone'  => 'Asia/Kolkata',
        // Change this to a long random string on every install.
        'secret'    => 'change-this-to-a-random-secret-string',
        'debug'     => false,
    ],
];
