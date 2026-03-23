<?php
// Backend/Database/Database.php

// Attempt to load configuration
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    die(json_encode(["error" => "Configuration file missing. Please copy config.example.php to config.php and configure it."]));
}

$config = require $configFile;

$servername = $config['db_host'] ?? 'localhost';
$username = $config['db_user'] ?? 'root';
$password = $config['db_pass'] ?? '';
$dbname = $config['db_name'] ?? 'l.p technotherm';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Enforce UTF-8 so Greek characters are stored/retrieved correctly
$conn->set_charset('utf8mb4');
