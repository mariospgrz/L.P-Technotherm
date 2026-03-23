<?php
/**
 * Backend/config.example.php
 * Template for config.php — safe to commit.
 * Copy this to config.php and fill in real values.
 */

return [
    // Database Configuration
    'db_host'     => 'localhost',
    'db_user'     => 'root',
    'db_pass'     => '',
    'db_name'     => 'l.p technotherm',

    'gmail_user' => 'your-gmail@gmail.com',
    'gmail_pass' => 'xxxx xxxx xxxx xxxx',   // 16-char Google App Password
    'from_email'  => 'your-gmail@gmail.com',
    'from_name'   => 'Technotherm',

    // Generate with: php -r "echo bin2hex(random_bytes(32));"
    'jwt_secret'  => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',

    'base_url'    => 'https://yourdomain.com',
    'debug_mode'  => false,
];
