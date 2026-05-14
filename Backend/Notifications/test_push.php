<?php
require __DIR__ . '/../../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$subsFile = __DIR__ . '/subscriptions.json';

// Hardcoded VAPID keys
$vapid = [
    'publicKey' => 'BBP41vPUp4vGZA0bRmje_Z2tvby4zutgpaaK4sqCKgZxdMGWYwPrcP_mJirhhwtBx4JmrpRo4d-9svg9DGEpWD0',
    'privateKey' => 'NWMcg8BSqiuSh8POP8GZHOt5VzJgRNNbhddprxe0KKU'
];

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
