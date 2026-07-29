<?php
/**
 * Backend/login.php
 * Fix #6: Rate limiting (IP-based, session-backed with countdown).
 * Fix #7: session_regenerate_id() after successful login.
 */
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/Database/Database.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'administrator') {
        redirect_to('dashboards/admin_dashboard.php');
    } elseif ($_SESSION['role'] === 'supervisor') {
        redirect_to('dashboards/supervisor_dashboard.php');
    } elseif ($_SESSION['role'] === 'helper') {
        redirect_to('dashboards/helper_dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('login/login.html');
}

$ip_key = 'login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$window = 15 * 60;
$max_attempts = 5;

$attempts = $_SESSION[$ip_key]['count'] ?? 0;
$first_fail = $_SESSION[$ip_key]['since'] ?? 0;

if ($first_fail && (time() - $first_fail) > $window) {
    $attempts = 0;
    $first_fail = 0;
    unset($_SESSION[$ip_key]);
}

if ($attempts >= $max_attempts) {
    $wait = $window - (time() - $first_fail);
    redirect_to('login/login.html?error=' .
        urlencode("Υπερβολικές αποτυχημένες προσπάθειες. Δοκιμάστε ξανά σε " . ceil($wait / 60) . " λεπτά."));
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    redirect_to('login/login.html?error=' . urlencode('Παρακαλώ συμπληρώστε και τα δύο πεδία.'));
}

$stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    redirect_to('login/login.html?error=' . urlencode('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.'));
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user && password_verify($password, $user['password'])) {
    unset($_SESSION[$ip_key]);
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['LAST_ACTIVITY'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    if ($user['role'] === 'administrator') {
        redirect_to('dashboards/admin_dashboard.php');
    } elseif ($user['role'] === 'supervisor') {
        redirect_to('dashboards/supervisor_dashboard.php');
    } elseif ($user['role'] === 'helper') {
        redirect_to('dashboards/helper_dashboard.php');
    }
    exit;
}

sleep(1);

$_SESSION[$ip_key]['count'] = $attempts + 1;
if (!$first_fail) {
    $_SESSION[$ip_key]['since'] = time();
}

$remaining = $max_attempts - ($_SESSION[$ip_key]['count']);
$msg = 'Λάθος username ή κωδικός.';
if ($remaining <= 2 && $remaining > 0) {
    $msg .= ' (' . $remaining . ' απόπειρες απομένουν)';
}

redirect_to('login/login.html?error=' . urlencode($msg));
