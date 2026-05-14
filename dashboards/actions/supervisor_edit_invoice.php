<?php
/**
 * dashboards/actions/supervisor_edit_invoice.php
 * Supervisor-only: edit description (vendor) and amount of own invoice.
 */
require_once __DIR__ . '/../../Backend/supervisor_session.php';
require_once __DIR__ . '/../../Backend/Database/Database.php';

header('Content-Type: application/json');

$body   = json_decode(file_get_contents('php://input'), true);
$id     = (int) ($body['id'] ?? 0);
$vendor = trim($body['vendor'] ?? '');
$amount = (float) ($body['amount'] ?? 0);
$csrf   = $body['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
    exit;
}

if (!$id || $amount <= 0 || empty($vendor)) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρα δεδομένα.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Only allow editing own invoices
$stmt = $conn->prepare('UPDATE invoices SET description = ?, amount = ? WHERE id = ? AND uploaded_by = ?');
$stmt->bind_param('sdii', $vendor, $amount, $id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Το τιμολόγιο δεν βρέθηκε ή δεν έχετε δικαίωμα επεξεργασίας.']);
}
$stmt->close();
