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

// Set PHP and MySQL timezone
date_default_timezone_set('Europe/Nicosia');
$offset = date('P');
$conn->query("SET time_zone = '$offset'");

// --- Poor Man's Cron: Auto Clock-out Fallback ---
// Επειδή ο server δεν επιτρέπει MySQL Events και δεν υπάρχει πρόσβαση στα Cron Jobs,
// εκτελούμε αυτόματα το κλείσιμο των βαρδιών (που ξεπέρασαν τις 8 ώρες) 
// κάθε φορά που κάποιος χρήστης συνδέεται στη βάση δεδομένων.
$conn->query(
    "UPDATE time_entries 
     SET clock_out = DATE_ADD(clock_in, INTERVAL 8 HOUR) 
     WHERE clock_out IS NULL 
     AND clock_in <= DATE_SUB(NOW(), INTERVAL 8 HOUR)"
);
// ------------------------------------------------



