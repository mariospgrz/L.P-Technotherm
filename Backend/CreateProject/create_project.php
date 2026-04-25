<?php


require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

function redirectError(string $msg): void
{
    header('Location: /dashboards/admin_dashboard.php?error=' . urlencode($msg) . '&tab=projects');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboards/admin_dashboard.php?tab=projects');
    exit();
}

$name = trim($_POST['project_name'] ?? '');
$location = trim($_POST['location'] ?? '');
$budget_raw = trim($_POST['budget'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');


if ($name === '') {
    redirectError('Το όνομα έργου είναι υποχρεωτικό.');
}
if (mb_strlen($name) < 3) {
    redirectError('Το όνομα έργου πρέπει να έχει τουλάχιστον 3 χαρακτήρες.');
}
if (mb_strlen($name) > 150) {
    redirectError('Το όνομα έργου δεν μπορεί να υπερβαίνει τους 150 χαρακτήρες.');
}

if ($location === '') {
    redirectError('Η τοποθεσία είναι υποχρεωτική.');
}
if (mb_strlen($location) > 150) {
    redirectError('Η τοποθεσία δεν μπορεί να υπερβαίνει τους 150 χαρακτήρες.');
}

if ($budget_raw === '') {
    redirectError('Ο προϋπολογισμός είναι υποχρεωτικός.');
}
if (!is_numeric($budget_raw)) {
    redirectError('Ο προϋπολογισμός πρέπει να είναι αριθμός.');
}
$budget = round((float) $budget_raw, 2);
if ($budget <= 0) {
    redirectError('Ο προϋπολογισμός πρέπει να είναι μεγαλύτερος από €0.');
}
if ($budget > 99_999_999.99) {
    redirectError('Ο προϋπολογισμός υπερβαίνει το μέγιστο επιτρεπτό όριο.');
}

if ($start_date === '') {
    redirectError('Η ημερομηνία έναρξης είναι υποχρεωτική.');
}
$date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
if (!$date_obj || $date_obj->format('Y-m-d') !== $start_date) {
    redirectError('Μη έγκυρη μορφή ημερομηνίας.');
}

$stmt = $conn->prepare(
    "SELECT id FROM projects WHERE name = ? AND location = ? AND status = 'active' LIMIT 1"
);
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων.');
}
$stmt->bind_param('ss', $name, $location);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    redirectError(
        'Υπάρχει ήδη ενεργό έργο με το όνομα «' . htmlspecialchars($name) .
        '» στην τοποθεσία «' . htmlspecialchars($location) . '».'
    );
}
$stmt->close();


$stmt = $conn->prepare(
    'INSERT INTO projects (name, location, budget, start_date, status)
     VALUES (?, ?, ?, ?, \'active\')'
);
if (!$stmt) {
    redirectError('Σφάλμα βάσης δεδομένων κατά την αποθήκευση.');
}
$stmt->bind_param('ssds', $name, $location, $budget, $start_date);

if (!$stmt->execute()) {
    $stmt->close();
    redirectError('Αποτυχία δημιουργίας έργου. Παρακαλώ δοκιμάστε ξανά.');
}
$stmt->close();

header('Location: /dashboards/admin_dashboard.php?success=' . urlencode(
    'Το έργο «' . $name . '» δημιουργήθηκε επιτυχώς!'
) . '&tab=projects');
exit();