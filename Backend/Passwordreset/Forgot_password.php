<?php
// Backend/Passwordreset/Forgot_password.php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ─── Load config ──────────────────────────────────────────────────────────────
$cfg = require __DIR__ . '/../config.php';

define('BASE_URL', $cfg['base_url']);
define('GMAIL_USER', $cfg['gmail_user']);
define('GMAIL_PASS', $cfg['gmail_pass']);
define('FROM_EMAIL', $cfg['from_email']);
define('FROM_NAME', $cfg['from_name']);
define('DEBUG_MODE', $cfg['debug_mode']);

// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /frontend/forgot_password.html');
    exit();
}

$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /frontend/forgot_password.html?error=' . urlencode('Παρακαλώ εισάγετε έγκυρη διεύθυνση email.'));
    exit();
}

// ── 1. Check email exists ─────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    // Don't reveal whether the email exists
    header('Location: /frontend/forgot_password.html?success=' . urlencode('Εάν το email είναι καταχωρημένο, θα σταλεί σύνδεσμος επαναφοράς.'));
    exit();
}

// ── 2. Generate JWT (valid 1 hour) ────────────────────────────────────────────
$token = JWT::encode(['email' => $email], 3600);

// ── 3. Build reset link ───────────────────────────────────────────────────────
$resetLink = BASE_URL . '/frontend/change_password.html?token=' . urlencode($token);

// ── 4. DEBUG MODE — show link on screen ──────────────────────────────────────
if (DEBUG_MODE) {
    ?>
    <!DOCTYPE html>
    <html lang="el">

    <head>
        <meta charset="UTF-8">
        <title>Debug – Reset Link | Technotherm</title>
        <style>
            body {
                font-family: sans-serif;
                max-width: 600px;
                margin: 40px auto;
                padding: 0 20px;
            }

            .debug-box {
                background: #fff7ed;
                border: 1px solid #fb923c;
                border-radius: 8px;
                padding: 16px;
                margin: 16px 0;
                word-break: break-all;
            }

            a {
                color: #2563eb;
            }
        </style>
        <link rel="icon" type="image/jpeg" href="/frontend/images/images.jpg">
    </head>

    <body>
        <h2>🛠️ Debug Mode — Email not sent</h2>
        <div class="debug-box">
            <strong>Link for:</strong> <?= htmlspecialchars($email) ?><br>
            <a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a>
        </div>
        <a href="/frontend/forgot_password.html">← Δοκιμάστε άλλο email</a>
    </body>

    </html>
    <?php
    exit();
}

// ── 5. Send real email ────────────────────────────────────────────────────────
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

    header('Location: /frontend/forgot_password.html?success=' . urlencode('Ο σύνδεσμος επαναφοράς κωδικού σάς στάλθηκε στο email σας.'));

} catch (Exception $e) {
    error_log("Mailer Error: " . $mail->ErrorInfo);
    header('Location: /frontend/forgot_password.html?error=' . urlencode('Αποτυχία αποστολής email. Παρακαλώ δοκιμάστε ξανά.'));
}

exit();
