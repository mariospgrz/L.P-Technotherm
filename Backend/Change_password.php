<?php
// Backend/Change_password.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/change_password.html');
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
    // Token invalid or expired
    header('Location: ../frontend/forgot_password.html?error=' . urlencode('Your reset link has expired or is invalid. Please request a new one.'));
    exit();
}

// ── 2. Validate password inputs ─────────────────────────────────────────────
if (strlen($password) < 8) {
    header('Location: ../frontend/change_password.html?token=' . urlencode($token)
        . '&error=' . urlencode('Password must be at least 8 characters.'));
    exit();
}

if ($password !== $confirm_password) {
    header('Location: ../frontend/change_password.html?token=' . urlencode($token)
        . '&error=' . urlencode('Passwords do not match.'));
    exit();
}

// ── 3. Hash and update the password in users table ──────────────────────────
$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed, $email);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    // Email from token not found in DB
    header('Location: ../frontend/forgot_password.html?error=' . urlencode('Account not found. Please try again.'));
    exit();
}

$stmt->close();

// ── 4. Redirect to login with success message ────────────────────────────────
header('Location: ../frontend/login.html?success=' . urlencode('Password updated successfully! Please log in.'));
exit();