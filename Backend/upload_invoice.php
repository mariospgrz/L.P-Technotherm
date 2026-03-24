<?php
/**
 * Backend/ProjectDetails/upload_invoice.php
 * POST – Καταχώρηση τιμολογίου για συγκεκριμένο έργο.
 *
 * POST fields:
 *   project_id     (int)    – υποχρεωτικό
 *   supplier       (string) – υποχρεωτικό (περιγραφή/προμηθευτής)
 *   amount         (float)  – ποσό τιμολογίου, υποχρεωτικό
 *   invoice_photo  (file)   – προαιρετικό (εικόνα ή PDF)
 *   invoice_file   (file)   – προαιρετικό (legacy εναλλακτικό όνομα πεδίου)
 *
 * Response JSON:
 *   { success: true,  message: "...", photo_url: "..." }
 *   { success: false, message: "..." }
 */

require_once __DIR__ . '/supervisor_session.php';
require_once __DIR__ . '/Database/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Μη επιτρεπτή μέθοδος.']);
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
    exit;
}

// ── Validate inputs ───────────────────────────────────────────────────────────
$project_id  = (int) ($_POST['project_id'] ?? 0);
$supplier    = trim($_POST['supplier'] ?? '');
$amount_raw  = trim($_POST['amount'] ?? '');
$uploaded_by = (int) $_SESSION['user_id'];

if (!$project_id) {
    echo json_encode(['success' => false, 'message' => 'Απαιτείται αναγνωριστικό έργου.']);
    exit;
}

if ($supplier === '') {
    echo json_encode(['success' => false, 'message' => 'Ο προμηθευτής είναι υποχρεωτικός.']);
    exit;
}

if (!is_numeric($amount_raw) || (float) $amount_raw <= 0) {
    echo json_encode(['success' => false, 'message' => 'Το ποσό πρέπει να είναι θετικός αριθμός.']);
    exit;
}
$amount = round((float) $amount_raw, 2);

// ── Validate uploaded file (optional) ─────────────────────────────────────────
// Support both `invoice_photo` (current UI) and legacy `invoice_file`.
$file = null;
$safe_ext = null;
$destination = null;
$photo_url = null;

$possibleFile = null;
if (isset($_FILES['invoice_photo']) && (($_FILES['invoice_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
    $possibleFile = $_FILES['invoice_photo'];
} elseif (isset($_FILES['invoice_file']) && (($_FILES['invoice_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
    $possibleFile = $_FILES['invoice_file'];
}

if ($possibleFile !== null) {
    // Normalize variable for the rest of the flow.
    $file = $possibleFile;

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'Το αρχείο υπερβαίνει το μέγιστο μέγεθος του server.',
            UPLOAD_ERR_FORM_SIZE  => 'Το αρχείο υπερβαίνει το μέγιστο μέγεθος της φόρμας.',
            UPLOAD_ERR_PARTIAL    => 'Το αρχείο ανέβηκε μερικώς.',
            UPLOAD_ERR_NO_FILE    => 'Δεν επιλέχθηκε αρχείο.',
            UPLOAD_ERR_NO_TMP_DIR => 'Λείπει ο προσωρινός φάκελος.',
            UPLOAD_ERR_CANT_WRITE => 'Αποτυχία εγγραφής στον δίσκο.',
            UPLOAD_ERR_EXTENSION  => 'Η μεταφόρτωση διακόπηκε από επέκταση PHP.',
        ];
        $err_code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $err_msg  = $upload_errors[$err_code] ?? 'Άγνωστο σφάλμα μεταφόρτωσης.';
        echo json_encode(['success' => false, 'message' => $err_msg]);
        exit;
    }

    $max_size = 5 * 1024 * 1024; // 5 MB
    if (($file['size'] ?? 0) > $max_size) {
        echo json_encode(['success' => false, 'message' => 'Το αρχείο δεν μπορεί να υπερβαίνει τα 5 MB.']);
        exit;
    }

    $allowed_mimes = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime_type, $allowed_mimes)) {
        echo json_encode(['success' => false, 'message' => 'Επιτρέπονται μόνο εικόνες (JPEG, PNG, WebP) ή PDF.']);
        exit;
    }

    $safe_ext = $allowed_mimes[$mime_type];
}

// ── Verify project exists ─────────────────────────────────────────────────────
$check = $conn->prepare('SELECT id FROM projects WHERE id = ? LIMIT 1');
if (!$check) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$check->bind_param('i', $project_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    echo json_encode(['success' => false, 'message' => 'Το έργο δεν βρέθηκε.']);
    exit;
}
$check->close();

// ── Save file to disk (optional) ─────────────────────────────────────────────
if ($file !== null && $safe_ext !== null) {
    $upload_dir = __DIR__ . '/../uploads/invoices/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Τυχαίο όνομα αρχείου για αποφυγή συγκρούσεων
    $new_filename = 'inv_' . $uploaded_by . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;
    $destination  = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode(['success' => false, 'message' => 'Αποτυχία αποθήκευσης αρχείου.']);
        exit;
    }

    // Relative path για αποθήκευση στη βάση (no leading slash)
    $photo_url = 'uploads/invoices/' . $new_filename;
}

// ── Insert into DB ────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    'INSERT INTO invoices (project_id, uploaded_by, description, amount, photo_url, date, created_at)
     VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())'
);
if (!$stmt) {
    // Διαγραφή αρχείου αν αποτύχει η βάση
    if ($destination && is_file($destination)) {
        unlink($destination);
    }
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('iisds', $project_id, $uploaded_by, $supplier, $amount, $photo_url);

if (!$stmt->execute()) {
    $stmt->close();
    if ($destination && is_file($destination)) {
        unlink($destination);
    }
    echo json_encode(['success' => false, 'message' => 'Αποτυχία καταχώρησης τιμολογίου.']);
    exit;
}
$stmt->close();

echo json_encode([
    'success'   => true,
    'message'   => 'Το τιμολόγιο ανέβηκε επιτυχώς.',
    'photo_url' => $photo_url,
]);
