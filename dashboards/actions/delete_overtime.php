<?php
/**
 * dashboards/actions/delete_overtime.php
 */
require_once __DIR__ . '/../../Backend/admin_session.php';
require_once __DIR__ . '/../../Backend/Database/Database.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);

$id = (int) ($body['id'] ?? 0);
$csrf = $body['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
    exit;
}

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο αναγνωριστικό.']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM overtime_requests WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Η αίτηση δεν βρέθηκε ή δεν διαγράφηκε.']);
}
$stmt->close();
