<?php
/**
 * Backend/sessionvalidation.php
 * Generic session guard for NON-admin pages.
 * Only checks that the user is logged in and session hasn't timed out.
 * For admin-only pages, use Backend/admin_session.php instead.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /L.P-Technotherm/login/login.html');
    exit();
}

// Session timeout: 10 minutes of inactivity
$timeout = 600;

if (
    isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)
) {
    session_unset();
    session_destroy();
    header('Location: /L.P-Technotherm/login/login.html?error=' .
        urlencode('Η συνεδρία σας έληξε. Παρακαλώ συνδεθείτε ξανά.'));
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time();
