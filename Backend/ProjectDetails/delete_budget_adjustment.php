<?php
/**
 * Backend/ProjectDetails/delete_budget_adjustment.php
 * POST /Backend/ProjectDetails/delete_budget_adjustment.php
 * Admin-only. Deletes a budget adjustment and reverts the budget.
 */
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Μη επιτρεπτή μέθοδος.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? ($_POST['id'] ?? 0));

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Απαιτείται αναγνωριστικό αναπροσαρμογής.']);
    exit;
}

$conn->begin_transaction();

// Fetch amount and project_id
$stmt = $conn->prepare('SELECT project_id, amount FROM budget_adjustments WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$adjustment = $res->fetch_assoc();
$stmt->close();

if (!$adjustment) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Η αναπροσαρμογή δεν βρέθηκε.']);
    exit;
}

$project_id = $adjustment['project_id'];
$amount = (float) $adjustment['amount'];

// Revert budget update (subtract amount)
$upd = $conn->prepare('UPDATE projects SET budget = budget - ? WHERE id = ?');
$upd->bind_param('di', $amount, $project_id);
if (!$upd->execute()) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Αποτυχία ενημέρωσης προϋπολογισμού.']);
    exit;
}
$upd->close();

// Delete adjustment
$del = $conn->prepare('DELETE FROM budget_adjustments WHERE id = ?');
$del->bind_param('i', $id);
if (!$del->execute()) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Αποτυχία διαγραφής.']);
    exit;
}
$del->close();

$conn->commit();

echo json_encode(['success' => true, 'message' => 'Η αναπροσαρμογή διαγράφηκε επιτυχώς.']);
