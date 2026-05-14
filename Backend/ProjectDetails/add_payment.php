<?php
/**
 * Backend/ProjectDetails/add_payment.php
 * POST /Backend/ProjectDetails/add_payment.php
 * Admin-only. Records a payment for a project.
 * POST fields: project_id, invoice_number, amount
 */
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Μη επιτρεπτή μέθοδος.']);
    exit;
}

$project_id     = (int) ($_POST['project_id'] ?? 0);
$invoice_number = trim($_POST['invoice_number'] ?? '');
$amount_raw     = trim($_POST['amount'] ?? '');

if (!$project_id) {
    echo json_encode(['success' => false, 'message' => 'Απαιτείται αναγνωριστικό έργου.']);
    exit;
}
if ($invoice_number === '') {
    echo json_encode(['success' => false, 'message' => 'Ο αριθμός τιμολογίου είναι υποχρεωτικός.']);
    exit;
}
if (!is_numeric($amount_raw) || (float) $amount_raw <= 0) {
    echo json_encode(['success' => false, 'message' => 'Το ποσό πρέπει να είναι θετικός αριθμός.']);
    exit;
}

$amount = round((float) $amount_raw, 2);

// Verify project exists
$check = $conn->prepare('SELECT id FROM projects WHERE id = ? LIMIT 1');
if (!$check) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$check->bind_param('i', $project_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    echo json_encode(['success' => false, 'message' => 'Το έργο δεν βρέθηκε.']);
    exit;
}
$check->close();

$stmt = $conn->prepare(
    'INSERT INTO payments (project_id, invoice_number, amount, payment_date) VALUES (?, ?, ?, CURDATE())'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('isd', $project_id, $invoice_number, $amount);
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Αποτυχία αποθήκευσης πληρωμής.']);
    exit;
}
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Η πληρωμή καταχωρήθηκε επιτυχώς.']);
