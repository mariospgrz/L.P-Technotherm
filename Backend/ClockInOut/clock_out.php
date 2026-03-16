<?php
/**
 * Backend/ClockInOut/clock_out.php
 * POST – Clock the current user OUT (close their open time_entries row).
 *
 * Request (JSON or form-data):
 *   { csrf_token: string }
 *
 * Response JSON:
 *   { success: true,  total_minutes: int, clock_out_time: "..." }
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
$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf_in = $_POST['csrf_token'] ?? $input['csrf_token'] ?? '';

// CSRF check
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_in)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο CSRF token']);
    exit;
}

// ── Find the open entry ──────────────────────────────────────────────────────
$find = $conn->prepare(
    'SELECT id, clock_in FROM time_entries WHERE user_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1'
);
$find->bind_param('i', $user_id);
$find->execute();
$row = $find->get_result()->fetch_assoc();
$find->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Δεν βρέθηκε ανοιχτή καταγραφή εργασίας.']);
    exit;
}

$entry_id = (int) $row['id'];

// ── Set clock_out = NOW() ───────────────────────────────────────────────────
$upd = $conn->prepare(
    'UPDATE time_entries SET clock_out = NOW() WHERE id = ?'
);
$upd->bind_param('i', $entry_id);

if (!$upd->execute()) {
    $upd->close();
    echo json_encode(['success' => false, 'message' => 'Σφάλμα αποθήκευσης: ' . $conn->error]);
    exit;
}
$upd->close();

// ── Fetch final times for response ──────────────────────────────────────────
$res = $conn->prepare(
    'SELECT clock_in, clock_out,
            TIMESTAMPDIFF(MINUTE, clock_in, clock_out) AS total_minutes
       FROM time_entries WHERE id = ?'
);
$res->bind_param('i', $entry_id);
$res->execute();
$final = $res->get_result()->fetch_assoc();
$res->close();

echo json_encode([
    'success'        => true,
    'total_minutes'  => (int) ($final['total_minutes'] ?? 0),
    'clock_out_time' => $final['clock_out'] ?? '',
]);
