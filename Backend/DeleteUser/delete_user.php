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

if (!isset($_POST['confirm'])) {
    redirectError('Πρέπει να επιβεβαιώσετε τη διαγραφή.');
}

$target_username = trim($_POST['username'] ?? '');
if ($target_username === '') {
    redirectError('Το username είναι υποχρεωτικό.');
}

$stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων.');
}
$stmt->bind_param('s', $target_username);
$stmt->execute();
$stmt->bind_result($target_id);
$stmt->fetch();
$stmt->close();

if (!$target_id) {
    redirectError('Ο χρήστης «' . htmlspecialchars($target_username) . '» δεν βρέθηκε.');
}
if ($target_id === (int) $_SESSION['user_id']) {
    redirectError('Δεν μπορείτε να διαγράψετε τον δικό σας λογαριασμό.');
}

$stmt = $conn->prepare('SELECT COUNT(*) FROM time_entries WHERE user_id = ? AND clock_out IS NULL');
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων.');
}
$stmt->bind_param('i', $target_id);
$stmt->execute();
$stmt->bind_result($open);
$stmt->fetch();
$stmt->close();
if ($open > 0) {
    redirectError('Ο χρήστης έχει ανοιχτές εγγραφές χρόνου και δεν μπορεί να διαγραφεί.');
}

try {
    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    if (!$stmt) {
        redirectError('Σφάλμα βάσης δεδομένων.');
    }
    $stmt->bind_param('i', $target_id);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // Check if it's a foreign key constraint error (1451)
    if (strpos($e->getMessage(), 'foreign key constraint fails') !== false || $e->getCode() === 1451) {
        redirectError('Αδύνατη η διαγραφή! Ο χρήστης έχει καταγεγραμμένο ιστορικό (βάρδιες ή υπερωρίες). Η διαγραφή θα κατέστρεφε τα δεδομένα μισθοδοσίας και αναφορών έργων.');
    }
    redirectError('Αποτυχία διαγραφής: Σφάλμα βάσης δεδομένων.');
}

header('Location: /dashboards/admin_dashboard.php?success=' . urlencode(
    'Ο χρήστης «' . $target_username . '» διαγράφηκε επιτυχώς!'
) . '&tab=users');
exit();
