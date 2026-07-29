<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? [];

require_csrf($input['csrf_token'] ?? '', true);

if (!isset($input['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing project_id']);
    exit;
}

$project_id = (int) $input['project_id'];

$stmt = $conn->prepare("SELECT status FROM projects WHERE id = ?");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Project not found']);
    exit;
}

$row = $res->fetch_assoc();
$current_status = $row['status'];
$stmt->close();

if ($current_status === 'active') {
    $update_stmt = $conn->prepare("UPDATE projects SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $update_stmt->bind_param('i', $project_id);
    $update_stmt->execute();
    $update_stmt->close();
} elseif ($current_status === 'completed') {
    $update_stmt = $conn->prepare("UPDATE projects SET status = 'active', completed_at = NULL WHERE id = ?");
    $update_stmt->bind_param('i', $project_id);
    $update_stmt->execute();
    $update_stmt->close();
}

echo json_encode(['success' => true]);
