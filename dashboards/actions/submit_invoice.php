<?php
/**
 * dashboards/actions/submit_invoice.php
 * Fix #3: MIME-type whitelist, safe extension, no client-supplied extension.
 * Fix #4: CSRF validation.
 * Fix #5: DB errors logged server-side only.
 */
require_once __DIR__ . '/../../Backend/supervisor_session.php';
require_once __DIR__ . '/../../Backend/Database/Database.php';

header('Content-Type: application/json');

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
    exit;
}

$user_id     = (int) $_SESSION['user_id'];
$project_id  = (int) ($_POST['project_id'] ?? 0);
$description = trim($_POST['supplier'] ?? '');
$amount      = (float) ($_POST['amount'] ?? 0);

if (!$project_id || !$description || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Συμπληρώστε όλα τα απαραίτητα πεδία.']);
    exit;
}

// ── File upload (optional) ────────────────────────────────────────────────────
$photo_url = null;

if (!empty($_FILES['invoice_photo']['name'])) {

    // Allowed MIME types (verified server-side, not from client)
    $allowed_mimes = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    // Check file size (max 5 MB)
    $max_bytes = 5 * 1024 * 1024;
    if ($_FILES['invoice_photo']['size'] > $max_bytes) {
        echo json_encode(['success' => false, 'message' => 'Το αρχείο δεν πρέπει να υπερβαίνει τα 5 MB.']);
        exit;
    }

    // Detect MIME from actual file content (not client-provided type)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['invoice_photo']['tmp_name']);

    if (!array_key_exists($mime, $allowed_mimes)) {
        echo json_encode(['success' => false, 'message' => 'Επιτρέπονται μόνο εικόνες (JPEG, PNG, WebP) ή PDF.']);
        exit;
    }

    // Use server-determined extension — never trust client filename
    $safe_ext = $allowed_mimes[$mime];
    $filename  = 'inv_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;

    $upload_dir = __DIR__ . '/../../uploads/invoices/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $dest = $upload_dir . $filename;

    if (!move_uploaded_file($_FILES['invoice_photo']['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα ανεβάσματος αρχείου.']);
        exit;
    }

    $photo_url = 'uploads/invoices/' . $filename;
}

// ── Insert into DB ────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    'INSERT INTO invoices (project_id, uploaded_by, description, amount, photo_url, date, created_at)
     VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())'
);
$stmt->bind_param('iisds', $project_id, $user_id, $description, $amount, $photo_url);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log('submit_invoice DB error: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
}
$stmt->close();