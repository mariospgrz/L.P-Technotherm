<?php
/**
 * dashboards/actions/admin_edit_invoice.php
 * Admin-only action to edit an invoice description (vendor) and amount.
 */
require_once __DIR__ . '/../../Backend/admin_session.php';
require_once __DIR__ . '/../../Backend/Database/Database.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id = (int) ($body['id'] ?? 0);
$vendor = trim($body['vendor'] ?? '');
$amount = (float) ($body['amount'] ?? 0);
$csrf = $body['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
    exit;
}

if (!$id || $amount <= 0 || empty($vendor)) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρα δεδομένα (συμπληρώστε προμηθευτή και ποσό).']);
    exit;
}

$stmt = $conn->prepare('UPDATE invoices SET description = ?, amount = ? WHERE id = ?');
$stmt->bind_param('sdi', $vendor, $amount, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ενημέρωση του τιμολογίου.']);
}
$stmt->close();
