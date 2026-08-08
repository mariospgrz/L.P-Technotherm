<?php
/**
 * Backend/Overtime/update_overtime.php
 * Admin-only endpoint to approve or reject overtime requests.
 */
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
    exit;
}

$id     = (int) ($data['id'] ?? 0);
$status = in_array($data['status'] ?? '', ['approved', 'rejected', 'pending'], true)
    ? $data['status'] : null;

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Άκυρα δεδομένα.']);
    exit;
}

$stmt = $conn->prepare("UPDATE overtime_requests SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log('update_overtime DB error: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
}
$stmt->close();