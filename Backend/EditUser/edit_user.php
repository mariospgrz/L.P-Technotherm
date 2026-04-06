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

$user_id = trim($_POST['user_id'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$role = trim($_POST['role'] ?? '');
$hourly_rate = trim($_POST['hourly_rate'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

if (empty($user_id) || !is_numeric($user_id)) {
    redirectError('Δεν επιλέχθηκε έγκυρος χρήστης.');
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

// Check if email is used by another user
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων κατά τον έλεγχο email.');
}
$stmt->bind_param('si', $email, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    redirectError('Το email «' . htmlspecialchars($email) . '» χρησιμοποιείται από άλλον χρήστη.');
}
$stmt->close();

$stmt = $conn->prepare(
    'UPDATE users SET name = ?, role = ?, hourly_rate = ?, `Phone number` = ?, email = ? WHERE id = ?'
);
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων.');
}
$stmt->bind_param('ssdssi', $full_name, $role, $hourly_rate, $phone, $email, $user_id);
if (!$stmt->execute()) {
    $stmt->close();
    redirectError('Αποτυχία ενημέρωσης χρήστη.');
}
$stmt->close();

header('Location: /dashboards/admin_dashboard.php?success=' . urlencode(
    'Τα στοιχεία του χρήστη ενημερώθηκαν επιτυχώς!'
) . '&tab=users');
exit();
