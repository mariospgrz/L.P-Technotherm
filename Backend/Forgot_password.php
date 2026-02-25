<?php
// Backend/Forgot_password.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ─── CONFIG ─────────────────────────────────────────────────────────────────
// Change to production domain when deploying
define('BASE_URL', 'http://localhost/L.P-Technotherm');

// Gmail credentials
// To get an App Password:
// Google Account → Security → 2-Step Verification ON → App Passwords → Create
define('GMAIL_USER', 'mariosand55@gmail.com');       // ← your Gmail address
define('GMAIL_PASS', 'mtwm nczv humj jjxl');        // ← 16-char App Password
define('FROM_EMAIL', 'mariosand55@gmail.com');       // ← same Gmail address
define('FROM_NAME', 'Technotherm');

// true  = show reset link on screen (no email  sent) — use while testing
// false = send real email via Gmail
define('DEBUG_MODE', false);
// ────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/forgot_password.html');
    exit();
}

$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../frontend/forgot_password.html?error=' . urlencode('Please enter a valid email address.'));
    exit();
}

// ── 1. Check email exists in users table ─────────────────────────────────────
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    // Don't reveal whether the email exists
    header('Location: ../frontend/forgot_password.html?success=' . urlencode('If that email is registered, a reset link has been sent.'));
    exit();
}

// ── 2. Generate JWT (valid for 1 hour, no DB storage needed) ─────────────────
$token = JWT::encode(['email' => $email], 3600);

// ── 3. Build the reset link ───────────────────────────────────────────────────
$resetLink = BASE_URL . '/frontend/change_password.html?token=' . urlencode($token);

// ── 4. DEBUG MODE — show link on screen ──────────────────────────────────────
if (DEBUG_MODE) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Debug – Reset Link | Technotherm</title>
        <link rel="stylesheet" href="../frontend/index.css">
        <style>
            .debug-box {
                background: rgba(251, 146, 60, .1);
                border: 1px solid rgba(251, 146, 60, .4);
                border-radius: .5rem;
                padding: 1rem;
                margin: 1.5rem 0;
                word-break: break-all;
            }

            .debug-label {
                font-weight: 600;
                margin-bottom: .5rem;
                color: #fb923c;
                font-size: .75rem;
                text-transform: uppercase;
                letter-spacing: .05em;
            }

            .reset-link {
                color: #60a5fa;
                text-decoration: underline;
                display: block;
                margin-top: .5rem;
                font-size: .85rem;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="card">
                <h2>🛠️ Debug Mode</h2>
                <p class="subtitle">Email not sent. Use the link below to test the reset flow.</p>
                <div class="debug-box">
                    <div class="debug-label">⚠️ Dev Only — Link for:
                        <?= htmlspecialchars($email) ?>
                    </div>
                    <a class="reset-link" href="<?= htmlspecialchars($resetLink) ?>">
                        <?= htmlspecialchars($resetLink) ?>
                    </a>
                </div>
                <div class="links"><a href="../frontend/forgot_password.html">← Try another email</a></div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit();
}

// ── 5. Send real email via PHPMailer + Gmail SMTP ────────────────────────────
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = GMAIL_USER;
    $mail->Password = GMAIL_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Fix for XAMPP localhost: PHP's OpenSSL can't verify Gmail's SSL cert locally.
    // Safe for local dev — remove these lines on a production server with a valid CA bundle.
    /*$mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    */

    // Sender & recipient
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($email);

    // Content
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

    header('Location: ../frontend/forgot_password.html?success=' . urlencode('A password reset link has been sent to your email.'));

} catch (Exception $e) {
    error_log("Mailer Error: " . $mail->ErrorInfo);
    header('Location: ../frontend/forgot_password.html?error=' . urlencode('Failed to send email. Please try again later.'));
}

exit();