<?php
/**
 * dashboards/actions/submit_overtime.php
 * Fix #2: Accept both supervisor AND helper roles.
 * Fix #5: Do not expose DB errors to client.
 */
require_once __DIR__ . '/../../Backend/sessionvalidation.php';
require_once __DIR__ . '/../../Backend/Database/Database.php';

header('Content-Type: application/json');

// ── Role check: only supervisor or helper may submit overtime ─────────────────
$allowed_roles = ['supervisor', 'helper'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένος.']);
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
    exit;
}

$user_id    = (int) $_SESSION['user_id'];
$project_id = (int) ($_POST['project_id'] ?? 0);
$hours      = (float) ($_POST['hours'] ?? 0);
$date       = trim($_POST['request_date'] ?? date('Y-m-d'));
$reason     = trim($_POST['reason'] ?? '');

if (!$project_id || $hours <= 0) {
    echo json_encode(['success' => false, 'message' => 'Συμπληρώστε όλα τα απαραίτητα πεδία.']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$stmt = $conn->prepare(
    "INSERT INTO overtime_requests (user_id, project_id, hours, date, description, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
);
$stmt->bind_param('iidss', $user_id, $project_id, $hours, $date, $reason);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log('submit_overtime DB error: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
}
$stmt->close();
