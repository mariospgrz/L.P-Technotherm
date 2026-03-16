<?php
/**
 * dashboards/actions/delete_invoice.php
 * Fix #4: CSRF validation.
 */
require_once __DIR__ . '/../../Backend/supervisor_session.php';
require_once __DIR__ . '/../../Backend/Database/Database.php';

header('Content-Type: application/json');

$body   = json_decode(file_get_contents('php://input'), true);
$inv_id = (int) ($body['id'] ?? 0);

// ── CSRF — read from JSON body ────────────────────────────────────────────────
$csrf = $body['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (!$inv_id) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο αναγνωριστικό.']);
    exit;
}

// Only delete invoices belonging to this user
$stmt = $conn->prepare('DELETE FROM invoices WHERE id = ? AND uploaded_by = ?');
$stmt->bind_param('ii', $inv_id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Το τιμολόγιο δεν βρέθηκε ή δεν έχετε δικαίωμα διαγραφής.']);
}
$stmt->close();