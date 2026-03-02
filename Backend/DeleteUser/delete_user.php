<?php

session_start();

require_once __DIR__ . '/../Database/Database.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /L.P-Technotherm/frontend/delete_user.html');
    exit();
}

function redirectError(string $msg): void {
    header('Location: /L.P-Technotherm/frontend/delete_user.html?error=' . urlencode($msg));
    exit();
}

// Require active session
if (!isset($_SESSION['user_id'])) {
    header('Location: /L.P-Technotherm/login/login.html');
    exit();
}

// Confirmation checkbox must be checked
if (!isset($_POST['confirm'])) {
    redirectError('Πρέπει να επιβεβαιώσετε τη διαγραφή.');
}

// Target username
$target_username = trim($_POST['username'] ?? '');
if ($target_username === '') {
    redirectError('Το username είναι υποχρεωτικό.');
}

// Verify that the logged-in user is an administrator
$stmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.');
}
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($logged_in_role);
$stmt->fetch();
$stmt->close();

if ($logged_in_role !== 'administrator') {
    redirectError('Δεν έχετε δικαίωμα να διαγράψετε χρήστες.');
}

// Find the target user
$stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.');
}
$stmt->bind_param('s', $target_username);
$stmt->execute();
$stmt->bind_result($target_id);
$stmt->fetch();
$stmt->close();

if (!$target_id) {
    redirectError('Ο χρήστης «' . htmlspecialchars($target_username) . '» δεν βρέθηκε.');
}

// Prevent self-deletion
if ($target_id === (int)$_SESSION['user_id']) {
    redirectError('Δεν μπορείτε να διαγράψετε τον δικό σας λογαριασμό.');
}

// Block deletion if the user has open time entries (clock_in without clock_out)
$stmt = $conn->prepare('SELECT COUNT(*) FROM time_entries WHERE user_id = ? AND clock_out IS NULL');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.');
}
$stmt->bind_param('i', $target_id);
$stmt->execute();
$stmt->bind_result($open_entries);
$stmt->fetch();
$stmt->close();

if ($open_entries > 0) {
    redirectError('Ο χρήστης «' . htmlspecialchars($target_username) . '» έχει ανοιχτές εγγραφές χρόνου και δεν μπορεί να διαγραφεί.');
}

// Delete the user
$stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.');
}
$stmt->bind_param('i', $target_id);
if (!$stmt->execute()) {
    $stmt->close();
    redirectError('Αποτυχία διαγραφής χρήστη. Παρακαλώ δοκιμάστε ξανά.');
}
$stmt->close();

header('Location: /L.P-Technotherm/frontend/delete_user.html?success=' . urlencode(
    'Ο χρήστης «' . $target_username . '» διαγράφηκε επιτυχώς!'
));
exit();
