<?php
/**
 * Backend/supervisor_session.php
 * Fix #7: Secure session cookie flags.
 * Fix #4: CSRF token generation.
 * Fix #8: Check session_status() before session_start().
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

$timeout = 600; // 10 minutes

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/login.html');
    exit();
}

if (
    isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)
) {
    session_unset();
    session_destroy();
    header('Location: /login/login.html?error=' .
        urlencode('Η συνεδρία σας έληξε. Παρακαλώ συνδεθείτε ξανά.'));
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supervisor') {
    session_unset();
    session_destroy();
    header('Location: /login/login.html?error=' .
        urlencode('Δεν έχετε δικαίωμα πρόσβασης στην σελίδα επιβλέποντα.'));
    exit();
}

// ── CSRF token (generate once per session) ────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
