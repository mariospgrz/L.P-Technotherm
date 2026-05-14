<?php
require __DIR__ . '/../../vendor/autoload.php';
use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();
file_put_contents(__DIR__ . '/vapid.json', json_encode($keys, JSON_PRETTY_PRINT));
echo "VAPID files created securely in Backend/Notifications/vapid.json\n";
