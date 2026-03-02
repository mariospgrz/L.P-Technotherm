<?php

require_once __DIR__ . '/../Database/Database.php';

//Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /L.P-Technotherm/frontend/create_user.html');
    exit();
}

function redirectError(string $msg): void {
    header('Location: /L.P-Technotherm/frontend/create_user.html?error=' . urlencode($msg));
    exit();
}

//Inputs from html
$username    = trim($_POST['username']    ?? '');
$password    = $_POST['password']         ?? '';
$full_name   = trim($_POST['full_name']   ?? '');
$role        = trim($_POST['role']        ?? '');
$hourly_rate = trim($_POST['hourly_rate'] ?? '');
$phone       = trim($_POST['phone']       ?? '');
$email       = trim($_POST['email']       ?? '');

//Validation
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
    if (!is_numeric($hourly_rate) || (float)$hourly_rate < 0) {
        redirectError('Το ωρομίσθιο πρέπει να είναι θετικός αριθμός.');
    }
    // needs to be checked
    $hourly_rate = round((float)$hourly_rate, 2);
} else {
    $hourly_rate = null;
}

if ($phone === '') {
    redirectError('Το τηλέφωνο είναι υποχρεωτικό.');
}

if (!preg_match('/^[0-9]{7,20}$/', $phone)) {
    redirectError('Μη έγκυρος αριθμός τηλεφώνου. Χρησιμοποιήστε μόνο αριθμούς.');
}

if ($email === '') {
    redirectError('Το email είναι υποχρεωτικό.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectError('Μη έγκυρη διεύθυνση email.');
}

if (strlen($password) < 6) {
    redirectError('Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες.');
}

//Check if username is unique 
$stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.');
}
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    redirectError('Το username «' . htmlspecialchars($username) . '» χρησιμοποιείται ήδη.');
}
$stmt->close();

//password hash
$password_hash = password_hash($password, PASSWORD_DEFAULT);

//Insert user
$stmt = $conn->prepare(
    'INSERT INTO users (username, password, name, role, hourly_rate, `Phone number`, email, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
);

if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.');
}

$stmt->bind_param('ssssdss', $username, $password_hash, $full_name, $role, $hourly_rate, $phone, $email);

if (!$stmt->execute()) {
    $stmt->close();
    redirectError('Αποτυχία δημιουργίας χρήστη. Παρακαλώ δοκιμάστε ξανά.');
}

$stmt->close();

header('Location: /L.P-Technotherm/frontend/create_user.html?success=' . urlencode(
    'Ο χρήστης «' . $username . '» δημιουργήθηκε επιτυχώς!'
));
exit();