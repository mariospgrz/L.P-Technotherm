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

// Include the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

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

    $max_size = 20 * 1024 * 1024; // 20 MB
    if (($file['size'] ?? 0) > $max_size) {
        echo json_encode(['success' => false, 'message' => 'Το αρχείο δεν μπορεί να υπερβαίνει τα 20 MB.']);
        exit;
    }

    $allowed_mimes = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    if (class_exists('finfo')) {
        $finfo     = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime_type = mime_content_type($file['tmp_name']);
    } else {
        $mime_type = $file['type'] ?? '';
    }

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

// ── Save file to disk or S3 (optional) ─────────────────────────────────────────────
if ($file !== null && $safe_ext !== null) {
    // Τυχαίο όνομα αρχείου για αποφυγή συγκρούσεων
    $new_filename = 'inv_' . $uploaded_by . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;
    
    try {
        $s3Client = new S3Client([
            'region'      => $config['aws_region'] ?? 'eu-central-1',
            'version'     => 'latest',
            'credentials' => [
                'key'    => $config['aws_key'] ?? '',
                'secret' => $config['aws_secret'] ?? '',
            ]
        ]);

        $result = $s3Client->putObject([
            'Bucket'     => $config['aws_bucket'] ?? '',
            'Key'        => 'invoices/' . $new_filename,
            'SourceFile' => $file['tmp_name'],
            'ACL'        => 'public-read'
        ]);

        // Full URL to image
        $photo_url = $result->get('ObjectURL');
        $destination = null; // No local file

    } catch (AwsException $e) {
        error_log('S3 Upload Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Αποτυχία αποθήκευσης αρχείου στο S3.']);
        exit;
    }
}

// ── Insert into DB ────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    'INSERT INTO invoices (project_id, uploaded_by, description, amount, photo_url, date, created_at)
     VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('iisds', $project_id, $uploaded_by, $supplier, $amount, $photo_url);

if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Αποτυχία καταχώρησης τιμολογίου.']);
    exit;
}
$stmt->close();

echo json_encode([
    'success'   => true,
    'message'   => 'Το τιμολόγιο ανέβηκε επιτυχώς.',
    'photo_url' => $photo_url,
]);
