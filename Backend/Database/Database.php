<?php
// Backend/Database/Database.php

require_once __DIR__ . '/../bootstrap.php';

$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die(json_encode(["error" => "Configuration file missing. Please copy config.example.php to config.php and configure it."]));
}

$config = app_config();
$GLOBALS['app_config'] = $config;

$servername = $config['db_host'] ?? 'localhost';
$username = $config['db_user'] ?? 'root';
$password = $config['db_pass'] ?? '';
$dbname = $config['db_name'] ?? 'lp_technotherm';
$dbport = (int) ($config['db_port'] ?? 3306);

$conn = new mysqli($servername, $username, $password, $dbname, $dbport);

if ($conn->connect_error) {
    http_response_code(500);
    error_log('DB connection failed: ' . $conn->connect_error);
    die(json_encode(["error" => "Connection failed. Please contact the administrator."]));
}

$conn->set_charset('utf8mb4');

date_default_timezone_set('Europe/Nicosia');
$offset = date('P');
// Offset from date('P') is like +02:00 — safe for SET time_zone
if (preg_match('/^[+-]\d{2}:\d{2}$/', $offset)) {
    $conn->query("SET time_zone = '$offset'");
}

// Poor Man's Cron: auto clock-out after exactly 8 hours from clock_in
$conn->query(
    "UPDATE time_entries 
     SET clock_out = DATE_ADD(clock_in, INTERVAL 8 HOUR) 
     WHERE clock_out IS NULL 
     AND clock_in <= DATE_SUB(NOW(), INTERVAL 8 HOUR)"
);
