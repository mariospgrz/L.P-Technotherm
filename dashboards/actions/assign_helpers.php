<?php
/**
 * dashboards/actions/assign_helpers.php
 * Schema: project_assignments(project_id, user_id, assigned_at)
 * Saves helper assignments to a project for this supervisor.
 */
require_once __DIR__ . '/../../Backend/supervisor_session.php';
require_once __DIR__ . '/../../Backend/Database/Database.php';

header('Content-Type: application/json');

$user_id = (int) $_SESSION['user_id'];
$project_id = (int) ($_POST['project_id'] ?? 0);
$helper_ids = $_POST['helper_ids'] ?? [];

if (!$project_id) {
    echo json_encode(['success' => false, 'message' => 'Επιλέξτε έργο.']);
    exit;
}

// Verify the project exists
$check = $conn->prepare('SELECT id FROM projects WHERE id = ?');
$check->bind_param('i', $project_id);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Το έργο δεν βρέθηκε.']);
    exit;
}
$check->close();

// Remove existing HELPER assignments for this project (keep supervisor assignment)
$del = $conn->prepare(
    "DELETE pa FROM project_assignments pa
       JOIN users u ON u.id = pa.user_id
      WHERE pa.project_id = ? AND u.role = 'helper'"
);
$del->bind_param('i', $project_id);
$del->execute();
$del->close();

// Insert new helper assignments
if (!empty($helper_ids)) {
    $ins = $conn->prepare(
        'INSERT INTO project_assignments (project_id, user_id, assigned_at) VALUES (?, ?, NOW())'
    );
    foreach ($helper_ids as $hid) {
        $hid = (int) $hid;
        if ($hid > 0) {
            $ins->bind_param('ii', $project_id, $hid);
            $ins->execute();
        }
    }
    $ins->close();
}

echo json_encode(['success' => true]);
