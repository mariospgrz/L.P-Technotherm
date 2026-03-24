<?php
// Backend/Notifications/vapid_public.php
header('Content-Type: application/json; charset=utf-8');

$vapid = [
    'publicKey' => 'BBP41vPUp4vGZA0bRmje_Z2tvby4zutgpaaK4sqCKgZxdMGWYwPrcP_mJirhhwtBx4JmrpRo4d-9svg9DGEpWD0'
];

echo json_encode(['publicKey' => $vapid['publicKey']]);
