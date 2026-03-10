<?php
/**
 * Backend/supervisor_session.php
 * Include at the TOP of every supervisor-only PHP page.
 */
session_start();

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
