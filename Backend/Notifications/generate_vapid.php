<?php
require_once __DIR__ . '/../bootstrap.php';

// Only CLI or cron_secret — never regenerate keys via open web without auth
require_cron_access();

require __DIR__ . '/../../vendor/autoload.php';
use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();
file_put_contents(__DIR__ . '/vapid.json', json_encode($keys, JSON_PRETTY_PRINT));
echo "VAPID keys written to Backend/Notifications/vapid.json (gitignored).\n";
echo "Public key: " . $keys['publicKey'] . "\n";
