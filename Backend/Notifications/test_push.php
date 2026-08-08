<?php
require_once __DIR__ . '/../bootstrap.php';
require_cron_access();

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/vapid_keys.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$subsFile = __DIR__ . '/subscriptions.json';

try {
    $vapid = notification_vapid_keys();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if (!file_exists($subsFile)) {
    die("No subscriptions file found.\n");
}

$subscriptions = json_decode(file_get_contents($subsFile), true);
if (empty($subscriptions)) {
    die("No active subscriptions found in subscriptions.json.\n");
}

$auth = [
    'VAPID' => [
        'subject' => 'mailto:admin@lptechnotherm.com',
        'publicKey' => $vapid['publicKey'],
        'privateKey' => $vapid['privateKey'],
    ],
];
$webPush = new WebPush($auth);

$payload = json_encode([
    'title' => 'Test Notification (Τεστ)',
    'body' => 'Αυτό είναι ένα δοκιμαστικό μήνυμα για να επιβεβαιώσετε ότι οι ειδοποιήσεις δουλεύουν!',
    'icon' => '/frontend/images/images.jpg',
    'url' => '/'
]);

$count = 0;
foreach ($subscriptions as $userId => $userSubs) {
    if (!is_array($userSubs)) continue;

    foreach ($userSubs as $subData) {
        if (empty($subData['endpoint'])) continue;

        $subscription = Subscription::create([
            'endpoint' => $subData['endpoint'],
            'publicKey' => $subData['keys']['p256dh'] ?? '',
            'authToken' => $subData['keys']['auth'] ?? '',
        ]);

        $report = $webPush->sendOneNotification($subscription, $payload);
        if ($report->isSuccess()) {
            echo "Success! Sent push notification to User ID {$userId}.\n";
            $count++;
        } else {
            echo "Failed for User ID {$userId}: " . $report->getReason() . "\n";
        }
    }
}

echo "\nFinished simulation. Sent {$count} notifications total.\n";
