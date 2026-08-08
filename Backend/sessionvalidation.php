<?php
/**
 * Backend/sessionvalidation.php
 * Generic session guard (any authenticated role).
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

if (!isset($_SESSION['user_id'])) {
    redirect_to('login/login.html');
}

$timeout = 600;

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
