<?php
/**
 * Backend/ClockInOut/clock_in.php
 * POST – Clock the current user IN for a given project.
 * Works for both supervisors and helpers (session-agnostic).
 *
 * Request (JSON or form-data):
 *   { project_id: int, csrf_token: string }
 *
 * Response JSON:
 *   { success: true,  clock_in_time: "2026-03-12T08:00:00" }
 *   { success: false, message: "..." }
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένος']);
    exit;
}

require_once __DIR__ . '/../Database/Database.php';

$user_id = (int) $_SESSION['user_id'];

// ── Parse input ──────────────────────────────────────────────────────────────
$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$project_id = (int) ($_POST['project_id'] ?? $input['project_id'] ?? 0);
$csrf_in    = $_POST['csrf_token']  ?? $input['csrf_token']  ?? '';

// CSRF check
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_in)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο CSRF token']);
    exit;
}

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Απαιτείται επιλογή έργου']);
    exit;
}

// ── Check no open entry already exists ──────────────────────────────────────
$chk = $conn->prepare('SELECT id FROM time_entries WHERE user_id = ? AND clock_out IS NULL LIMIT 1');
$chk->bind_param('i', $user_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
    $chk->close();
    echo json_encode(['success' => false, 'message' => 'Είστε ήδη σε εκκίνηση εργασίας. Κάντε Clock Out πρώτα.']);
    exit;
}
$chk->close();

// ── Insert the new time entry ────────────────────────────────────────────────
$today = date('Y-m-d');
$stmt  = $conn->prepare(
    'INSERT INTO time_entries (user_id, project_id, clock_in, date)
     VALUES (?, ?, NOW(), ?)'
);
$stmt->bind_param('iis', $user_id, $project_id, $today);

if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Σφάλμα καταχώρησης: ' . $conn->error]);
    exit;
}
$stmt->close();

// ── Return the server clock_in time ─────────────────────────────────────────
$ts = $conn->query('SELECT NOW() AS t')->fetch_assoc()['t'];

echo json_encode([
    'success'       => true,
    'clock_in_time' => $ts,   // "2026-03-12 08:00:00"  — JS will parse this
]);
