<?php
// Backend/Notifications/vapid_public.php
header('Content-Type: application/json; charset=utf-8');

$vapidFile = __DIR__ . '/vapid.json';
if (!file_exists($vapidFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'VAPID keys not generated yet']);
    exit;
}

$vapid = json_decode(file_get_contents($vapidFile), true);
echo json_encode(['publicKey' => $vapid['publicKey']]);
