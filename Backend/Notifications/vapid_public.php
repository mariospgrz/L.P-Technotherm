<?php
// Backend/Notifications/vapid_public.php
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $vapid = load_vapid_keys();
    echo json_encode(['publicKey' => $vapid['publicKey']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'VAPID public key unavailable']);
}
