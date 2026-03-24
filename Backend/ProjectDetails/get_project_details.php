<?php
/**
 * Backend/ProjectDetails/get_project_details.php
 * GET /Backend/ProjectDetails/get_project_details.php?project_id=X
 * Admin-only. Returns full project details as JSON.
 */
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Μη επιτρεπτή μέθοδος.']);
    exit;
}

$project_id = (int) ($_GET['project_id'] ?? 0);
if (!$project_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Απαιτείται αναγνωριστικό έργου.']);
    exit;
}

// 1. Project basic info
$stmt = $conn->prepare('SELECT * FROM projects WHERE id = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Το έργο δεν βρέθηκε.']);
    exit;
}

// 2. Labor cost: SUM( duration_minutes / 60 * hourly_rate ) για ολοκληρωμένες εγγραφές
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, te.clock_in, te.clock_out) / 60.0 * u.hourly_rate), 0) AS labor_cost
     FROM time_entries te
     JOIN users u ON te.user_id = u.id
     WHERE te.project_id = ? AND te.clock_out IS NOT NULL"
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $project_id);
$stmt->execute();
$labor_cost = (float) $stmt->get_result()->fetch_assoc()['labor_cost'];
$stmt->close();

// 2b. Overtime cost: SUM( approved_overtime.hours * hourly_rate )
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(o.hours * u.hourly_rate), 0) AS overtime_cost
     FROM overtime_requests o
     JOIN users u ON o.user_id = u.id
     WHERE o.project_id = ? AND o.status = 'approved'"
);
if ($stmt) {
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $overtime_cost = (float) $stmt->get_result()->fetch_assoc()['overtime_cost'];
    $stmt->close();
    $labor_cost += $overtime_cost;
}

// 3. Material cost: SUM(invoices.amount)
$stmt = $conn->prepare(
    'SELECT COALESCE(SUM(amount), 0) AS material_cost FROM invoices WHERE project_id = ?'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $project_id);
$stmt->execute();
$material_cost = (float) $stmt->get_result()->fetch_assoc()['material_cost'];
$stmt->close();

// 4. Total collected from payments
$stmt = $conn->prepare(
    'SELECT COALESCE(SUM(amount), 0) AS total_collected FROM payments WHERE project_id = ?'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $project_id);
$stmt->execute();
$total_collected = (float) $stmt->get_result()->fetch_assoc()['total_collected'];
$stmt->close();

// 5. Payment history
$stmt = $conn->prepare(
    'SELECT * FROM payments WHERE project_id = ? ORDER BY payment_date DESC'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
$recent_payments = [];
while ($row = $res->fetch_assoc()) {
    $recent_payments[] = $row;
}
$stmt->close();

// 6. Budget adjustments history (χωρίς δημιουργό καθώς δεν υπάρχει column)
$stmt = $conn->prepare(
    'SELECT *
     FROM budget_adjustments ba
     WHERE ba.project_id = ?
     ORDER BY ba.created_at DESC'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
$budget_adjustments = [];
while ($row = $res->fetch_assoc()) {
    $budget_adjustments[] = $row;
}
$stmt->close();

// 7. Sum of adjustments για υπολογισμό original_budget
$stmt = $conn->prepare(
    'SELECT COALESCE(SUM(amount), 0) AS sum_adjustments FROM budget_adjustments WHERE project_id = ?'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων.']);
    exit;
}
$stmt->bind_param('i', $project_id);
$stmt->execute();
$sum_adjustments = (float) $stmt->get_result()->fetch_assoc()['sum_adjustments'];
$stmt->close();

// 8. Time Logs (Ώρες Εργασίας)
$stmt = $conn->prepare(
    'SELECT te.*, u.name as user_name, u.role as user_role 
     FROM time_entries te 
     JOIN users u ON te.user_id = u.id 
     WHERE te.project_id = ? 
     ORDER BY te.date DESC, te.clock_in DESC'
);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
$time_logs = [];
while ($row = $res->fetch_assoc()) {
    $time_logs[] = $row;
}
$stmt->close();

// 9. Invoices (Τιμολόγια)
$stmt = $conn->prepare(
    'SELECT i.*, u.name as uploaded_by_name 
     FROM invoices i 
     LEFT JOIN users u ON i.uploaded_by = u.id 
     WHERE i.project_id = ? 
     ORDER BY i.date DESC, i.created_at DESC'
);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
$invoices = [];
while ($row = $res->fetch_assoc()) {
    $invoices[] = $row;
}
$stmt->close();

// 10. Team (Ομάδα - assigned helpers)
$stmt = $conn->prepare(
    'SELECT pa.*, u.name as helper_name, u.role as helper_role 
     FROM project_assignments pa 
     JOIN users u ON pa.user_id = u.id 
     WHERE pa.project_id = ?'
);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
$team = [];
while ($row = $res->fetch_assoc()) {
    $team[] = $row;
}
$stmt->close();

// 11. Approved Overtime Requests
$stmt = $conn->prepare(
    "SELECT o.id, o.user_id, u.name as user_name, u.role as user_role,
            o.project_id, o.hours, o.date, o.description, o.status
     FROM overtime_requests o
     JOIN users u ON o.user_id = u.id
     WHERE o.project_id = ? AND o.status = 'approved'
     ORDER BY o.date DESC"
);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
$approved_overtime = [];
while ($row = $res->fetch_assoc()) {
    $approved_overtime[] = $row;
}
$stmt->close();

// Υπολογισμοί
$total_budget    = (float) $project['budget'];
$original_budget = $total_budget - $sum_adjustments;
$total_cost      = $labor_cost + $material_cost;
$profit          = $total_budget - $total_cost;
$client_debt     = $total_budget - $total_collected;

echo json_encode([
    'success' => true,
    'project' => $project,
    'financial_overview' => [
        'total_budget'    => round($total_budget, 2),
        'original_budget' => round($original_budget, 2),
        'labor_cost'      => round($labor_cost, 2),
        'material_cost'   => round($material_cost, 2),
        'total_cost'      => round($total_cost, 2),
        'profit'          => round($profit, 2),
        'client_debt'     => round($client_debt, 2),
    ],
    'payments_summary' => [
        'total_invoiced'  => round($total_budget, 2),
        'total_collected' => round($total_collected, 2),
        'remaining'       => round($client_debt, 2),
    ],
    'recent_payments'    => $recent_payments,
    'budget_adjustments' => $budget_adjustments,
    'time_logs'          => $time_logs,
    'invoices'           => $invoices,
    'team'               => $team,
    'approved_overtime'  => $approved_overtime,
]);
