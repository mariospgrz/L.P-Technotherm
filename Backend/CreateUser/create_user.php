<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboards/admin_dashboard.php');
    exit();
}

function redirectError(string $msg): void
{
    header('Location: /dashboards/admin_dashboard.php?error=' . urlencode($msg) . '&tab=users');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$full_name = trim($_POST['full_name'] ?? '');
$role = trim($_POST['role'] ?? '');
$hourly_rate = trim($_POST['hourly_rate'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($username === '') {
    redirectError('Το username είναι υποχρεωτικό.');
}
if (!preg_match('/^[a-zA-Z0-9α-ωΑ-Ωά-ώΆ-Ώ_.\-]{3,50}$/u', $username)) {
    redirectError('Το username επιτρέπει μόνο γράμματα, αριθμούς, _, . και - (3–50 χαρακτήρες).');
}
if ($full_name === '') {
    redirectError('Το ονοματεπώνυμο είναι υποχρεωτικό.');
}

$allowed_roles = ['administrator', 'supervisor', 'helper'];
if (!in_array($role, $allowed_roles, true)) {
    redirectError('Μη έγκυρος ρόλος.');
}

if ($hourly_rate !== '') {
    if (!is_numeric($hourly_rate) || (float) $hourly_rate < 0) {
        redirectError('Το ωρομίσθιο πρέπει να είναι θετικός αριθμός.');
    }
    $hourly_rate = round((float) $hourly_rate, 2);
} else {
    $hourly_rate = null;
}

if ($phone === '') {
    redirectError('Το τηλέφωνο είναι υποχρεωτικό.');
}
if (!preg_match('/^[0-9]{7,20}$/', $phone)) {
    redirectError('Μη έγκυρος αριθμός τηλεφώνου.');
}
if ($email === '') {
    redirectError('Το email είναι υποχρεωτικό.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectError('Μη έγκυρη διεύθυνση email.');
}
if (strlen($password) < 8) {
    redirectError('Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.');

}

$stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων.');
}
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    redirectError('Το username «' . htmlspecialchars($username) . '» χρησιμοποιείται ήδη.');
}
$stmt->close();

$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων.');
}
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    redirectError('Το email «' . htmlspecialchars($email) . '» χρησιμοποιείται ήδη.');
}
$stmt->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    'INSERT INTO users (username, password, name, role, hourly_rate, `Phone number`, email, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
);
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων.');
}
$stmt->bind_param('ssssdss', $username, $password_hash, $full_name, $role, $hourly_rate, $phone, $email);
if (!$stmt->execute()) {
    $stmt->close();
    redirectError('Αποτυχία δημιουργίας χρήστη.');
}
$stmt->close();

header('Location: /dashboards/admin_dashboard.php?success=' . urlencode(
    'Ο χρήστης «' . $username . '» δημιουργήθηκε επιτυχώς!'
) . '&tab=users');
exit();