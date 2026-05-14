<?php
// Backend/Passwordreset/Change_password.php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/JWT.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../frontend/change_password.html');
    exit();
}

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// ── 1. Verify the JWT ────────────────────────────────────────────────────────
try {
    $payload = JWT::decode($token);
    $email = $payload['email'] ?? null;

    if (!$email) {
        throw new Exception('Email not found in token.');
    }
} catch (Exception $e) {
    // Token invalid or expired — send back to forgot-password
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Ο σύνδεσμος επαναφοράς είναι άκυρος ή έληξε. Παρακαλώ ζητήστε νέον.'));
    exit();
}

// ── 2. Validate password inputs ─────────────────────────────────────────────
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

// ── 3. Hash and update the password in users table ──────────────────────────
$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
if (!$stmt) {
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.'));
    exit();
}
$stmt->bind_param("ss", $hashed, $email);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    // Email from token not found in DB
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Ο λογαριασμός δεν βρέθηκε. Παρακαλώ δοκιμάστε ξανά.'));
    exit();
}

$stmt->close();

// ── 4. Redirect to login with success message ────────────────────────────────
header('Location: ../../login/login.html?success=' . urlencode('Ο κωδικός ενημερώθηκε επιτυχώς! Παρακαλώ συνδεθείτε.'));
exit();
