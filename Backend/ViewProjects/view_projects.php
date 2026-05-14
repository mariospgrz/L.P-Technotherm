<?php


require_once __DIR__ . '/helper_session.php';
require_once __DIR__ . '/Database/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Μη επιτρεπτή μέθοδος.']);
    exit;
}

$helper_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $helper_id);
$stmt->execute();
$helper = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$helper) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ο χρήστης δεν βρέθηκε.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT
        p.id,
        p.name,
        p.location,
        p.start_date,
        p.status,
        pa.assigned_at
     FROM project_assignments pa
     JOIN projects p ON pa.project_id = p.id
     WHERE pa.user_id = ?
       AND p.status = 'active'
     ORDER BY pa.assigned_at DESC"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $helper_id);
$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = [
        'id'          => (int) $row['id'],
        'name'        => $row['name'],
        'location'    => $row['location'],
        'start_date'  => $row['start_date'],
        'status'      => $row['status'],
        'assigned_at' => $row['assigned_at'],
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'helper'  => [
        'id'   => $helper_id,
        'name' => $helper['name'],
    ],
    'projects' => $projects,
]);