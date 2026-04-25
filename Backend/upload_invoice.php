<?php
/**
 * Backend/upload_invoice.php
 * S3 VERSION - Primary upload method.
 */

require_once __DIR__ . '/supervisor_session.php';
require_once __DIR__ . '/Database/Database.php';

// Include the Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die(json_encode(['success' => false, 'message' => 'Λείπει ο φάκελος "vendor" στον server.']));
}
require_once $autoloadPath;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header('Content-Type: application/json');

// Enable error reporting for debugging
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
    $photo_url = null;

    $possibleFile = null;
    if (isset($_FILES['invoice_photo']) && (($_FILES['invoice_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $possibleFile = $_FILES['invoice_photo'];
    } elseif (isset($_FILES['invoice_file']) && (($_FILES['invoice_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $possibleFile = $_FILES['invoice_file'];
    }

    if ($possibleFile !== null) {
        $file = $possibleFile;
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα μεταφόρτωσης (Error code: ' . $file['error'] . ')']);
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
            echo json_encode(['success' => false, 'message' => 'Επιτρέπονται μόνο εικόνες ή PDF. (Mime: ' . $mime_type . ')']);
            exit;
        }
        $safe_ext = $allowed_mimes[$mime_type];
    }

    // ── Verify project exists ─────────────────────────────────────────────────────
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

    // ── Save file to S3 ───────────────────────────────────────────────────────────
    if ($file !== null && $safe_ext !== null) {
        $new_filename = 'inv_' . $uploaded_by . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;

        $s3Client = new S3Client([
            'region' => $config['aws_region'] ?? 'eu-central-1',
            'version' => 'latest',
            'credentials' => [
                'key' => $config['aws_key'] ?? '',
                'secret' => $config['aws_secret'] ?? '',
            ]
        ]);

        $result = $s3Client->putObject([
            'Bucket' => $config['aws_bucket'] ?? '',
            'Key' => 'invoices/' . $new_filename,
            'SourceFile' => $file['tmp_name'],
            'ACL' => 'public-read'
        ]);

        $photo_url = $result->get('ObjectURL');
    }

    // ── Insert into DB ────────────────────────────────────────────────────────────
    $stmt = $conn->prepare(
        'INSERT INTO invoices (project_id, uploaded_by, description, amount, photo_url, date, created_at)
         VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())'
    );
    $stmt->bind_param('iisds', $project_id, $uploaded_by, $supplier, $amount, $photo_url);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();

    // Fetch project name
    $project_name = '';
    $pstmt = $conn->prepare('SELECT name FROM projects WHERE id = ? LIMIT 1');
    if ($pstmt) {
        $pstmt->bind_param('i', $project_id);
        $pstmt->execute();
        $pstmt->bind_result($project_name);
        $pstmt->fetch();
        $pstmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Το τιμολόγιο ανέβηκε επιτυχώς στο S3.',
        'invoice' => [
            'id' => $new_id,
            'description' => $supplier,
            'project' => $project_name,
            'amount' => $amount,
            'date' => date('Y-m-d'),
            'photo_url' => $photo_url,
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα S3: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'PHP Fatal Error: ' . $e->getMessage()]);
}
