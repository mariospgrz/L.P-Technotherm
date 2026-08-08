<?php
// Backend/Passwordreset/Forgot_password.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$cfg = app_config();

define('BASE_URL', rtrim($cfg['base_url'] ?? '', '/'));
define('GMAIL_USER', $cfg['gmail_user'] ?? '');
define('GMAIL_PASS', $cfg['gmail_pass'] ?? '');
define('FROM_EMAIL', $cfg['from_email'] ?? '');
define('FROM_NAME', $cfg['from_name'] ?? 'L.P Technotherm');
define('DEBUG_MODE', !empty($cfg['debug_mode']));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../frontend/forgot_password.html');
    exit();
}

// Rate limit: max 5 requests per 15 minutes per IP (session-backed)
$ip_key = 'reset_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$window = 15 * 60;
$max_attempts = 5;
$attempts = $_SESSION[$ip_key]['count'] ?? 0;
$first = $_SESSION[$ip_key]['since'] ?? 0;
if ($first && (time() - $first) > $window) {
    $attempts = 0;
    unset($_SESSION[$ip_key]);
}
if ($attempts >= $max_attempts) {
    header('Location: ../../frontend/forgot_password.html?error=' .
        urlencode('Υπερβολικές προσπάθειες. Δοκιμάστε ξανά αργότερα.'));
    exit();
}
$_SESSION[$ip_key]['count'] = $attempts + 1;
if (empty($_SESSION[$ip_key]['since'])) {
    $_SESSION[$ip_key]['since'] = time();
}

$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Παρακαλώ εισάγετε έγκυρη διεύθυνση email.'));
    exit();
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    header('Location: ../../frontend/forgot_password.html?success=' . urlencode('Εάν το email είναι καταχωρημένο, θα σταλεί σύνδεσμος επαναφοράς.'));
    exit();
}

$jti = bin2hex(random_bytes(16));
$token = JWT::encode(['email' => $email, 'jti' => $jti], 3600);

// Store pending jti so only the latest token is valid / one-time use
$pendingFile = __DIR__ . '/pending_resets.json';
$pending = file_exists($pendingFile) ? (json_decode(file_get_contents($pendingFile), true) ?: []) : [];
$pending[$email] = ['jti' => $jti, 'exp' => time() + 3600];
file_put_contents($pendingFile, json_encode($pending, JSON_PRETTY_PRINT), LOCK_EX);

$resetLink = BASE_URL . '/frontend/change_password.html?token=' . urlencode($token);

// DEBUG MODE only when explicitly enabled — never expose in production
if (DEBUG_MODE) {
    ?>
    <!DOCTYPE html>
    <html lang="el">
    <head>
        <meta charset="UTF-8">
        <title>Debug – Reset Link | Technotherm</title>
        <style>
            body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
            .debug-box { background: #fff7ed; border: 1px solid #fb923c; border-radius: 8px; padding: 16px; margin: 16px 0; word-break: break-all; }
            a { color: #2563eb; }
        </style>
    </head>
    <body>
        <h2>Debug Mode — Email not sent</h2>
        <div class="debug-box">
            <strong>Link for:</strong> <?= htmlspecialchars($email) ?><br>
            <a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a>
        </div>
        <a href="../../frontend/forgot_password.html">← Δοκιμάστε άλλο email</a>
    </body>
    </html>
    <?php
    exit();
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = GMAIL_USER;
    $mail->Password = GMAIL_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($email);

    $mail->isHTML(false);
    $mail->Subject = 'Password Reset L.P Technotherm';
    $mail->Body =
        "Hello,\n\n"
        . "You requested a password reset for your Technotherm account.\n\n"
        . "Click the link below to reset your password (expires in 1 hour):\n\n"
        . $resetLink . "\n\n"
        . "If you did not request this, please ignore this email.\n\n"
        . "– The Technotherm Team";

    $mail->send();

    header('Location: ../../frontend/forgot_password.html?success=' . urlencode('Ο σύνδεσμος επαναφοράς κωδικού σάς στάλθηκε στο email σας.'));

} catch (Exception $e) {
    error_log("Mailer Error: " . $mail->ErrorInfo);
    header('Location: ../../frontend/forgot_password.html?error=' . urlencode('Αποτυχία αποστολής email. Παρακαλώ δοκιμάστε ξανά.'));
}

exit();
