<?php
session_start();

require_once __DIR__ . '/Database/Database.php';

// If user is already logged in, redirect by role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'administrator') {
        header('Location: /L.P-Technotherm/frontend/admin_dashboard.php');
    } else {
        header('Location: /L.P-Technotherm/frontend/dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /L.P-Technotherm/login/login.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: /L.P-Technotherm/login/login.html?error=' . urlencode('Παρακαλώ συμπληρώστε και τα δύο πεδία.'));
    exit;
}

// Use prepared statements to prevent SQL injection
$stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    header('Location: /L.P-Technotherm/login/login.html?error=' . urlencode('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.'));
    exit;
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user && password_verify($password, $user['password'])) {
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['LAST_ACTIVITY'] = time();

    // Redirect based on role
    if ($user['role'] === 'administrator') {
        header('Location: /L.P-Technotherm/frontend/admin_dashboard.php');
    } else {
        header('Location: /L.P-Technotherm/frontend/dashboard.php');
    }
    exit;
} else {
    header('Location: /L.P-Technotherm/login/login.html?error=' . urlencode('Λάθος username ή κωδικός.'));
    exit;
}
