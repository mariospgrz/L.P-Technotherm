<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Μη εξουσιοδοτημένος']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$subsFile = __DIR__ . '/subscriptions.json';
$subscriptions = file_exists($subsFile) ? json_decode(file_get_contents($subsFile), true) : [];
if (!is_array($subscriptions)) $subscriptions = [];

// Remove endpoint if it exists anywhere to avoid duplicate pushes
foreach ($subscriptions as $uid => $userSubs) {
    if (is_array($userSubs)) {
        $subscriptions[$uid] = array_values(array_filter($userSubs, function($sub) use ($data) {
            return $sub['endpoint'] !== $data['endpoint'];
        }));
    }
}

if (!isset($subscriptions[$user_id])) {
    $subscriptions[$user_id] = [];
}
$subscriptions[$user_id][] = [
    'endpoint' => $data['endpoint'],
    'keys' => $data['keys'] ?? null
];

file_put_contents($subsFile, json_encode($subscriptions, JSON_PRETTY_PRINT));
echo json_encode(['success' => true]);
