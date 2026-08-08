<?php
// Backend/Passwordreset/Change_password.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/JWT.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../frontend/change_password.html');
    exit();
}

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

try {
    $payload = JWT::decode($token);
    $email = $payload['email'] ?? null;
    $jti = $payload['jti'] ?? null;

    if (!$email || !$jti) {
        throw new Exception('Invalid token payload.');
    }
} catch (Exception $e) {
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Ο σύνδεσμος επαναφοράς είναι άκυρος ή έληξε. Παρακαλώ ζητήστε νέον.'));
    exit();
}

// One-time / latest-token enforcement
$pendingFile = __DIR__ . '/pending_resets.json';
$pending = file_exists($pendingFile) ? (json_decode(file_get_contents($pendingFile), true) ?: []) : [];
$entry = $pending[$email] ?? null;
if (
    !$entry
    || !hash_equals((string) ($entry['jti'] ?? ''), (string) $jti)
    || (isset($entry['exp']) && time() > (int) $entry['exp'])
) {
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Ο σύνδεσμος επαναφοράς είναι άκυρος ή έχει ήδη χρησιμοποιηθεί.'));
    exit();
}

if (strlen($password) < 8) {
    header('Location: ../../frontend/change_password.html?token=' . urlencode($token)
        . '&error=' . urlencode('Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.'));
    exit();
}

if ($password !== $confirm_password) {
    header('Location: ../../frontend/change_password.html?token=' . urlencode($token)
        . '&error=' . urlencode('Οι κωδικοί δεν ταιριάζουν.'));
    exit();
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
if (!$stmt) {
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.'));
    exit();
}
$stmt->bind_param("ss", $hashed, $email);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Ο λογαριασμός δεν βρέθηκε. Παρακαλώ δοκιμάστε ξανά.'));
    exit();
}
$stmt->close();

// Invalidate token (one-time use)
unset($pending[$email]);
file_put_contents($pendingFile, json_encode($pending, JSON_PRETTY_PRINT), LOCK_EX);

header('Location: ../../login/login.html?success=' . urlencode('Ο κωδικός ενημερώθηκε επιτυχώς! Παρακαλώ συνδεθείτε.'));
exit();
