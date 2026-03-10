<?php
/**
 * Backend/admin_session.php
 * Include at the TOP of every admin-only PHP page.
 */
session_start();

$timeout = 300; // 5 minutes

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

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    session_unset();
    session_destroy();
    header('Location: /login/login.html?error=' .
        urlencode('Δεν έχετε δικαίωμα πρόσβασης στον πίνακα διαχείρισης.'));
    exit();
}
