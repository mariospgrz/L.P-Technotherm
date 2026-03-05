<?php
session_start();

require_once __DIR__ . '/Database/Database.php';

// If user is already logged in, redirect by role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'administrator') {
        header('Location: /frontend/admin_dashboard.php');
    } else {
        header('Location: /frontend/dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login/login.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: /login/login.html?error=' . urlencode('Παρακαλώ συμπληρώστε και τα δύο πεδία.'));
    exit;
}

$stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    header('Location: /login/login.html?error=' . urlencode('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.'));
    exit;
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['LAST_ACTIVITY'] = time();

    if ($user['role'] === 'administrator') {
        header('Location: /frontend/admin_dashboard.php');
    } else {
        header('Location: /frontend/dashboard.php');
    }
    exit;
} else {
    header('Location: /login/login.html?error=' . urlencode('Λάθος username ή κωδικός.'));
    exit;
}
