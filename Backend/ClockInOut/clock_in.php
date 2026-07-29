<?php
/**
 * Backend/ClockInOut/clock_in.php
 * POST – Clock the current user IN for a given project.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένος']);
    exit;
}

require_once __DIR__ . '/../Database/Database.php';

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$project_id = (int) ($_POST['project_id'] ?? $input['project_id'] ?? 0);
$csrf_in = $_POST['csrf_token'] ?? $input['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_in)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο CSRF token']);
    exit;
}

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Απαιτείται επιλογή έργου']);
    exit;
}

// Authorization: helpers must be assigned to the project
if ($role === 'helper' && !user_assigned_to_project($conn, $user_id, $project_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Δεν είστε ανατεθειμένος σε αυτό το έργο.']);
    exit;
}

// Verify project exists and is active
$pchk = $conn->prepare("SELECT id FROM projects WHERE id = ? AND status = 'active' LIMIT 1");
$pchk->bind_param('i', $project_id);
$pchk->execute();
$pchk->store_result();
if ($pchk->num_rows === 0) {
    $pchk->close();
    echo json_encode(['success' => false, 'message' => 'Το έργο δεν βρέθηκε ή δεν είναι ενεργό.']);
    exit;
}
$pchk->close();

$conn->begin_transaction();

try {
    $chk = $conn->prepare(
        'SELECT id FROM time_entries WHERE user_id = ? AND clock_out IS NULL LIMIT 1 FOR UPDATE'
    );
    $chk->bind_param('i', $user_id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Είστε ήδη σε εκκίνηση εργασίας. Κάντε Clock Out πρώτα.']);
        exit;
    }
    $chk->close();

    $today = date('Y-m-d');
    $stmt = $conn->prepare(
        'INSERT INTO time_entries (user_id, project_id, clock_in, date)
         VALUES (?, ?, NOW(), ?)'
    );
    $stmt->bind_param('iis', $user_id, $project_id, $today);

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Σφάλμα καταχώρησης.']);
        exit;
    }
    $stmt->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('clock_in error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Σφάλμα καταχώρησης.']);
    exit;
}

$ts = $conn->query('SELECT NOW() AS t')->fetch_assoc()['t'];

echo json_encode([
    'success' => true,
    'clock_in_time' => $ts,
]);
