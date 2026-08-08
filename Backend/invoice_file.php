<?php
/**
 * Backend/invoice_file.php
 * Auth-gated proxy: redirects to a short-lived S3 presigned URL.
 * GET ?id=<invoice_id>
 */
require_once __DIR__ . '/bootstrap.php';

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
    echo 'Unauthorized';
    exit;
}

$role = $_SESSION['role'] ?? '';
$allowed = ['administrator', 'supervisor', 'helper'];
if (!in_array($role, $allowed, true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$invoiceId = (int) ($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

require_once __DIR__ . '/Database/Database.php';
require_once __DIR__ . '/S3Helper.php';

$stmt = $conn->prepare('SELECT id, photo_url, uploaded_by, project_id FROM invoices WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $invoiceId);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$inv || empty($inv['photo_url'])) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

// Helpers may only view invoices for assigned projects
if ($role === 'helper') {
    $uid = (int) $_SESSION['user_id'];
    if (!user_assigned_to_project($conn, $uid, (int) $inv['project_id'])) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$key = s3_key_from_photo_url($inv['photo_url']);
if ($key === null) {
    // Legacy local / unexpected URL — redirect if absolute http(s)
    if (preg_match('#^https?://#i', $inv['photo_url'])) {
        header('Location: ' . $inv['photo_url']);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}

try {
    $url = s3_presigned_url($key, 300);
    header('Location: ' . $url);
    exit;
} catch (Throwable $e) {
    error_log('invoice_file presign error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Unable to load file';
    exit;
}
