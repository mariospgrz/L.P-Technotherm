<?php
/**
 * Backend/ProjectDetails/add_budget_adjustment.php
 * POST /Backend/ProjectDetails/add_budget_adjustment.php
 * Admin-only. Adds a (signed) budget adjustment and updates projects.budget.
 * POST fields: project_id, amount (μπορεί να είναι αρνητικό), description
 */
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Μη επιτρεπτή μέθοδος.']);
    exit;
}

$project_id  = (int) ($_POST['project_id'] ?? 0);
$amount_raw  = trim($_POST['amount'] ?? '');
$description = trim($_POST['description'] ?? '');
$created_by  = (int) $_SESSION['user_id'];

if (!$project_id) {
    echo json_encode(['success' => false, 'message' => 'Απαιτείται αναγνωριστικό έργου.']);
    exit;
}
if (!is_numeric($amount_raw) || (float) $amount_raw == 0) {
    echo json_encode(['success' => false, 'message' => 'Το ποσό πρέπει να είναι μη μηδενικός αριθμός.']);
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

// Atomic: INSERT adjustment + UPDATE budget
$conn->begin_transaction();

$stmt = $conn->prepare(
    'INSERT INTO budget_adjustments (project_id, amount, description) VALUES (?, ?, ?)'
);
if (!$stmt) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('ids', $project_id, $amount, $description);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Αποτυχία αποθήκευσης αναπροσαρμογής.']);
    exit;
}
$stmt->close();

$upd = $conn->prepare('UPDATE projects SET budget = budget + ? WHERE id = ?');
if (!$upd) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$upd->bind_param('di', $amount, $project_id);
if (!$upd->execute()) {
    $upd->close();
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Αποτυχία ενημέρωσης προϋπολογισμού.']);
    exit;
}
$upd->close();

$conn->commit();

echo json_encode(['success' => true, 'message' => 'Η αναπροσαρμογή αποθηκεύτηκε επιτυχώς.']);
