<?php
/**
 * Backend/upload_invoice.php
 * S3 VERSION - private objects + proxy URL for clients.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/supervisor_session.php';
require_once __DIR__ . '/Database/Database.php';
require_once __DIR__ . '/S3Helper.php';

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Μη επιτρεπτή μέθοδος.']);
        exit;
    }

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
        exit;
    }

    $project_id = (int) ($_POST['project_id'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');
    $amount_raw = trim($_POST['amount'] ?? '');
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

    $file = null;
    $safe_ext = null;
    $photo_key = null;

    $possibleFile = null;
    if (isset($_FILES['invoice_photo']) && (($_FILES['invoice_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $possibleFile = $_FILES['invoice_photo'];
    } elseif (isset($_FILES['invoice_file']) && (($_FILES['invoice_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $possibleFile = $_FILES['invoice_file'];
    }

    if ($possibleFile !== null) {
        $file = $possibleFile;
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα μεταφόρτωσης.']);
            exit;
        }

        if (($file['size'] ?? 0) > 20 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Το αρχείο υπερβαίνει το όριο των 20MB.']);
            exit;
        }

        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file['tmp_name']);
        } elseif (function_exists('mime_content_type')) {
            $mime_type = mime_content_type($file['tmp_name']);
        } else {
            $mime_type = $file['type'] ?? '';
        }

        if (!array_key_exists($mime_type, $allowed_mimes)) {
            echo json_encode(['success' => false, 'message' => 'Επιτρέπονται μόνο εικόνες ή PDF.']);
            exit;
        }
        $safe_ext = $allowed_mimes[$mime_type];
    }

    $check = $conn->prepare('SELECT id FROM projects WHERE id = ? LIMIT 1');
    $check->bind_param('i', $project_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        $check->close();
        echo json_encode(['success' => false, 'message' => 'Το έργο δεν βρέθηκε.']);
        exit;
    }
    $check->close();

    if ($file !== null && $safe_ext !== null) {
        $new_filename = 'inv_' . $uploaded_by . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;
        $photo_key = 'invoices/' . $new_filename;

        s3_client()->putObject([
            'Bucket' => s3_bucket(),
            'Key' => $photo_key,
            'SourceFile' => $file['tmp_name'],
            // Private object — access via Backend/invoice_file.php
        ]);
    }

    $stmt = $conn->prepare(
        'INSERT INTO invoices (project_id, uploaded_by, description, amount, photo_url, date, created_at)
         VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())'
    );
    $stmt->bind_param('iisds', $project_id, $uploaded_by, $supplier, $amount, $photo_key);
    $stmt->execute();
    $new_id = (int) $conn->insert_id;
    $stmt->close();

    $project_name = '';
    $pstmt = $conn->prepare('SELECT name FROM projects WHERE id = ? LIMIT 1');
    if ($pstmt) {
        $pstmt->bind_param('i', $project_id);
        $pstmt->execute();
        $pstmt->bind_result($project_name);
        $pstmt->fetch();
        $pstmt->close();
    }

    $clientPhotoUrl = $photo_key ? invoice_proxy_url($new_id) : null;

    echo json_encode([
        'success' => true,
        'message' => 'Το τιμολόγιο ανέβηκε επιτυχώς.',
        'invoice' => [
            'id' => $new_id,
            'description' => $supplier,
            'project' => $project_name,
            'amount' => $amount,
            'date' => date('Y-m-d'),
            'photo_url' => $clientPhotoUrl,
        ],
    ]);

} catch (Throwable $e) {
    error_log('upload_invoice error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Αποτυχία μεταφόρτωσης. Παρακαλώ δοκιμάστε ξανά.']);
}
