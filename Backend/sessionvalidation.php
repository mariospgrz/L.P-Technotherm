<?php
/**
 * Backend/sessionvalidation.php
 * Generic session guard for non-admin pages.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/login.html');
    exit();
}

$timeout = 600;

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
