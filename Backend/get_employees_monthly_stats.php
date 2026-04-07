<?php
/**
 * Backend/get_employees_monthly_stats.php
 * Fetches available months/years with data and filtered employee stats.
 */
require_once __DIR__ . '/admin_session.php';
require_once __DIR__ . '/Database/Database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get_months'; // 'get_months' or 'get_stats'
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
$year = isset($_GET['year']) ? (int)$_GET['year'] : null;

try {
    if ($action === 'get_months') {
        // Find all unique months/years from both tables
        $query = "
            SELECT DISTINCT YEAR(clock_in) as year, MONTH(clock_in) as month FROM time_entries
            UNION
            SELECT DISTINCT YEAR(date) as year, MONTH(date) as month FROM overtime_requests
            ORDER BY year DESC, month DESC
        ";
        $result = $conn->query($query);
        $available = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['year']) {
                $available[] = [
                    'year' => (int)$row['year'],
                    'month' => (int)$row['month']
                ];
            }
        }
        echo json_encode(['success' => true, 'available' => $available]);
        exit;
    }

    if ($action === 'get_stats' && $month && $year) {
        // 1. Fetch all users (helpers and supervisors)
        $users_res = $conn->query("SELECT id, name, role, hourly_rate FROM users WHERE role IN ('supervisor', 'helper', 'administrator')");
        $stats = [];
        
        while ($u = $users_res->fetch_assoc()) {
            $uid = (int)$u['id'];
            $rate = (float)($u['hourly_rate'] ?? 0);
            
            // 2. Normal Hours for this month
            $stmt = $conn->prepare("
                SELECT SUM(TIMESTAMPDIFF(MINUTE, clock_in, clock_out) / 60.0) as hours 
                FROM time_entries 
                WHERE user_id = ? AND MONTH(clock_in) = ? AND YEAR(clock_in) = ? AND clock_out IS NOT NULL
            ");
            $stmt->bind_param('iii', $uid, $month, $year);
            $stmt->execute();
            $norm = (float)$stmt->get_result()->fetch_assoc()['hours'];
            $stmt->close();

            // 3. Overtime Hours for this month (approved only)
            $stmt = $conn->prepare("
                SELECT SUM(hours) as hours 
                FROM overtime_requests 
                WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND status = 'approved'
            ");
            $stmt->bind_param('iii', $uid, $month, $year);
            $stmt->execute();
            $over = (float)$stmt->get_result()->fetch_assoc()['hours'];
            $stmt->close();

            if ($norm > 0 || $over > 0) {
                $stats[] = [
                    'id' => $uid,
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'rate' => $rate,
                    'normal_hours' => round($norm, 1),
                    'overtime_hours' => round($over, 1),
                    'total_cost' => round(($norm + $over) * $rate, 2)
                ];
            }
        }
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action or parameters.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
