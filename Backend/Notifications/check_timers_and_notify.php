<?php
require_once __DIR__ . '/../bootstrap.php';
require_cron_access();

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/vapid_keys.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$subsFile = __DIR__ . '/subscriptions.json';
$warningsFile = __DIR__ . '/warnings.json';

try {
    $vapid = notification_vapid_keys();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$subscriptions = file_exists($subsFile) ? json_decode(file_get_contents($subsFile), true) : [];
$warnings = file_exists($warningsFile) ? json_decode(file_get_contents($warningsFile), true) : [];

if (empty($subscriptions)) {
    die("No active subscriptions.\n");
}

$auth = [
    'VAPID' => [
        'subject' => 'mailto:admin@lptechnotherm.com',
        'publicKey' => $vapid['publicKey'],
        'privateKey' => $vapid['privateKey'],
    ],
];
$webPush = new WebPush($auth);

$thresholds = [
    240 => [
        'title' => 'Ενημέρωση: 4 Ώρες Εργασίας',
        'body' => "Εργάζεστε στο έργο %PROJECT% για 4 ώρες."
    ],
    450 => [
        'title' => 'Προσοχή: 7,5 Ώρες Εργασίας',
        'body' => "Εργάζεστε στο έργο %PROJECT% πάνω από 7,5 ώρες. Θα γίνει αυτόματη αποσύνδεση σε 30 λεπτά."
    ]
];

$query = "SELECT te.id, te.user_id, te.clock_in, p.name as project_name, 
                 TIMESTAMPDIFF(MINUTE, te.clock_in, NOW()) as elapsed
          FROM time_entries te
          JOIN projects p ON p.id = te.project_id
          WHERE te.clock_out IS NULL 
          AND TIMESTAMPDIFF(MINUTE, te.clock_in, NOW()) >= 240
          AND TIMESTAMPDIFF(MINUTE, te.clock_in, NOW()) < 480";

$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("No timers exceeding 4 hours found.\n");
}

$sent_count = 0;
while ($row = $res->fetch_assoc()) {
    $entry_id = $row['id'];
    $user_id = $row['user_id'];
    $elapsed = (int)$row['elapsed'];

    if (isset($warnings[$entry_id]) && !is_array($warnings[$entry_id])) {
        $warnings[$entry_id] = [ 450 => $warnings[$entry_id] ];
    }

    if (!isset($warnings[$entry_id])) {
        $warnings[$entry_id] = [];
    }

    foreach ($thresholds as $minutes => $msgData) {
        if ($elapsed >= $minutes) {
            if (!empty($warnings[$entry_id][$minutes])) {
                continue;
            }

            if (empty($subscriptions[$user_id])) {
                continue;
            }

            $payload = json_encode([
                'title' => $msgData['title'],
                'body' => str_replace('%PROJECT%', $row['project_name'], $msgData['body']),
                'icon' => '/frontend/images/images.jpg',
                'url' => '/'
            ]);

            $success_for_user = false;
            foreach ($subscriptions[$user_id] as $subData) {
                if (empty($subData['endpoint'])) continue;

                $subscription = Subscription::create([
                    'endpoint' => $subData['endpoint'],
                    'publicKey' => $subData['keys']['p256dh'] ?? '',
                    'authToken' => $subData['keys']['auth'] ?? '',
                ]);

                $report = $webPush->sendOneNotification($subscription, $payload);
                if ($report->isSuccess()) {
                    $success_for_user = true;
                } else {
                    echo "Push failed for endpoint {$subData['endpoint']}: " . $report->getReason() . "\n";
                }
            }

            if ($success_for_user) {
                $warnings[$entry_id][$minutes] = date('Y-m-d H:i:s');
                $sent_count++;
            }
        }
    }
}

$fp = fopen($warningsFile, 'c+');
if ($fp) {
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($warnings, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

echo "Finished. Sent $sent_count warnings.\n";
