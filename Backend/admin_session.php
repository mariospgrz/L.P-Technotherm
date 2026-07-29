<?php
/**
 * Backend/admin_session.php
 * Fix #7: Secure session cookie flags (HttpOnly, SameSite, Secure).
 * Fix #4: CSRF token generation.
 * Fix #8: Check session_status() before session_start().
 */
require_once __DIR__ . '/bootstrap.php';

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

$timeout = 600; // 10 minutes (aligned with helper/supervisor)

if (!isset($_SESSION['user_id'])) {
    redirect_to('login/login.html');
}

if (
    isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)
) {
    session_unset();
    session_destroy();
    redirect_to('login/login.html?error=' .
        urlencode('Η συνεδρία σας έληξε. Παρακαλώ συνδεθείτε ξανά.'));
}

$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    session_unset();
    session_destroy();
    redirect_to('login/login.html?error=' .
        urlencode('Δεν έχετε δικαίωμα πρόσβασης στον πίνακα διαχείρισης.'));
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
