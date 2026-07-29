<?php
/**
 * Backend/ViewProjects/view_projects.php
 * Helper: list assigned active projects.
 */
require_once __DIR__ . '/../helper_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Μη επιτρεπτή μέθοδος.']);
    exit;
}

$helper_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT p.id, p.name, p.location, p.status, p.start_date
       FROM projects p
       JOIN project_assignments pa ON pa.project_id = p.id
      WHERE pa.user_id = ?
        AND p.status = 'active'
      ORDER BY p.start_date DESC"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $helper_id);
$stmt->execute();
$res = $stmt->get_result();
$projects = [];
while ($row = $res->fetch_assoc()) {
    $projects[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'projects' => $projects]);
