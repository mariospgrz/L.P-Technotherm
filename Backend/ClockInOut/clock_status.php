<?php
/**
 * Backend/ClockInOut/clock_status.php
 * GET – Returns the current clock-in state for the logged-in user.
 *
 * Also enforces the 8-hour limit entirely in MySQL (TIMESTAMPDIFF)
 * to avoid PHP/MySQL timezone mismatches.  Called on every page
 * load AND every 30 seconds by the JS poll.
 *
 * Response JSON (clocked in, within 8h):
 *   { clocked_in: true, clock_in_time: "YYYY-MM-DD HH:MM:SS",
 *     entry_id: int, project_id: int, project_name: string,
 *     elapsed_seconds: int }
 *
 * Response JSON (not clocked in / auto-clocked out):
 *   { clocked_in: false, clock_in_time: null, project_id: null,
 *     project_name: null, auto_clocked_out: bool }
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Μη εξουσιοδοτημένος']);
    exit;
}

require_once __DIR__ . '/../Database/Database.php';

$user_id = (int) $_SESSION['user_id'];

// ── Fetch the open entry AND elapsed seconds in one MySQL query ───────────────
// Using TIMESTAMPDIFF(SECOND, clock_in, NOW()) keeps everything inside MySQL,
// completely eliminating PHP/MySQL timezone mismatch issues.
$stmt = $conn->prepare(
    'SELECT te.id,
            te.clock_in,
            te.project_id,
            p.name AS project_name,
            TIMESTAMPDIFF(SECOND, te.clock_in, NOW()) AS elapsed_seconds
       FROM time_entries te
       JOIN projects p ON p.id = te.project_id
      WHERE te.user_id = ?
        AND te.clock_out IS NULL
      ORDER BY te.clock_in DESC
      LIMIT 1'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Not clocked in at all ─────────────────────────────────────────────────────
if (!$row) {
    echo json_encode([
        'clocked_in'       => false,
        'clock_in_time'    => null,
        'project_id'       => null,
        'project_name'     => null,
        'auto_clocked_out' => false,
    ]);
    exit;
}

$entry_id      = (int) $row['id'];
$elapsed_secs  = (int) $row['elapsed_seconds'];
$limit_8h_secs = 8 * 3600;   // 28800 seconds

// ── 8-hour limit: auto clock-out using MySQL NOW() ────────────────────────────
if ($elapsed_secs >= $limit_8h_secs) {
    $upd = $conn->prepare(
        'UPDATE time_entries SET clock_out = NOW()
          WHERE id = ? AND clock_out IS NULL'
    );
    $upd->bind_param('i', $entry_id);
    $upd->execute();
    $upd->close();

    echo json_encode([
        'clocked_in'       => false,
        'clock_in_time'    => null,
        'project_id'       => null,
        'project_name'     => null,
        'auto_clocked_out' => true,
    ]);
    exit;
}

// ── Still clocked in, within 8 hours ─────────────────────────────────────────
echo json_encode([
    'clocked_in'       => true,
    'entry_id'         => $entry_id,
    'clock_in_time'    => $row['clock_in'],         // "YYYY-MM-DD HH:MM:SS"
    'project_id'       => (int) $row['project_id'],
    'project_name'     => $row['project_name'],
    'elapsed_seconds'  => $elapsed_secs,
]);
