<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

header('Content-Type: application/json');

$query = "
    SELECT p.id, p.name, p.location, p.budget, p.completed_at,
           COALESCE((SELECT SUM(amount) FROM invoices WHERE project_id = p.id), 0) as material_cost,
           COALESCE((SELECT SUM(TIMESTAMPDIFF(MINUTE, te.clock_in, te.clock_out) / 60.0 * u.hourly_rate) 
                     FROM time_entries te JOIN users u ON te.user_id = u.id 
                     WHERE te.project_id = p.id AND te.clock_out IS NOT NULL), 0) as labor_cost
    FROM projects p
    WHERE p.status = 'completed'
    ORDER BY p.completed_at DESC
";

$result = $conn->query($query);
$completed_projects = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $budget = (float)$row['budget'];
        $material_cost = (float)$row['material_cost'];
        $labor_cost = (float)$row['labor_cost'];
        $total_cost = $material_cost + $labor_cost;
        $profit = $budget - $total_cost;
        
        $completed_projects[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'location' => $row['location'],
            'completed_at' => $row['completed_at'] ? date('d/m/Y', strtotime($row['completed_at'])) : '—',
            'budget' => $budget,
            'total_cost' => $total_cost,
            'profit' => $profit
        ];
    }
}

echo json_encode(['success' => true, 'data' => $completed_projects]);
