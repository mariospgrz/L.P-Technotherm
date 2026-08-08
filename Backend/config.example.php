<?php
/**
 * Copy this file to config.php and fill in real values.
 * config.php is gitignored and must never be committed.
 */
return [
    // ── App ──────────────────────────────────────────────────────────────────
    // Leave empty if the app is at the web root, or e.g. '/L.P-Technotherm'
    'base_path' => '',
    'base_url' => 'http://localhost',
    'debug_mode' => false,

    // Shared secret for HTTP access to notification/cron scripts (?key=... or X-Cron-Key)
    'cron_secret' => 'change-me-to-a-long-random-string',

    // ── Database ─────────────────────────────────────────────────────────────
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'lp_technotherm',
    'db_port' => 3306,

    // ── Auth / mail ──────────────────────────────────────────────────────────
    'jwt_secret' => 'change-me-to-a-long-random-string',
    'gmail_user' => '',
    'gmail_pass' => '',
    'from_email' => 'noreply@example.com',
    'from_name' => 'L.P Technotherm',

    // Optional fallback if vapid.json is missing (prefer generate_vapid.php)
    'vapid_public_key' => '',
    'vapid_private_key' => '',

    // ── AWS S3 (private invoices) ────────────────────────────────────────────
    'aws_region' => 'eu-central-1',
    'aws_key' => '',
    'aws_secret' => '',
    'aws_bucket' => '',
];
