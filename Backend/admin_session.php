<?php
/**
 * Backend/admin_session.php
 * Include this at the TOP of every admin-only PHP page.
 * - Starts the session
 * - Redirects to login if the user is not authenticated
 * - Redirects to login (with error) if the user is not an administrator
 * - Kills timed-out sessions (10-minute inactivity)
 */

session_start();

$timeout = 600; // 10 minutes

// ── 1. Must be logged in ──────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: /L.P-Technotherm/login/login.html');
    exit();
}

// ── 2. Session timeout ────────────────────────────────────────────────────
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

// ── 3. Must be administrator ──────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    session_unset();
    session_destroy();
    header('Location: /L.P-Technotherm/login/login.html?error=' .
        urlencode('Δεν έχετε δικαίωμα πρόσβασης στον πίνακα διαχείρισης.'));
    exit();
}
