<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing project_id']);
    exit;
}

$project_id = (int)$input['project_id'];

// Check current status
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
    // Set to completed
    $update_stmt = $conn->prepare("UPDATE projects SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $update_stmt->bind_param('i', $project_id);
    $update_stmt->execute();
    $update_stmt->close();
} else if ($current_status === 'completed') {
    // Set to active
    $update_stmt = $conn->prepare("UPDATE projects SET status = 'active', completed_at = NULL WHERE id = ?");
    $update_stmt->bind_param('i', $project_id);
    $update_stmt->execute();
    $update_stmt->close();
}

echo json_encode(['success' => true]);
