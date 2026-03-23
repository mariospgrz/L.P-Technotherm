<?php
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Database/Database.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$vapidFile = __DIR__ . '/vapid.json';
$subsFile = __DIR__ . '/subscriptions.json';
$warningsFile = __DIR__ . '/warnings.json';

if (!file_exists($vapidFile)) {
    die("VAPID keys not found.\n");
}
$vapid = json_decode(file_get_contents($vapidFile), true);
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

// Set up the thresholds in minutes
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

// Fetch open timer entries that are >= 240 minutes (4 hours)
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
    
    // Convert old string format to array format for backward compatibility
    if (isset($warnings[$entry_id]) && !is_array($warnings[$entry_id])) {
        $warnings[$entry_id] = [ 450 => $warnings[$entry_id] ];
    }
    
    if (!isset($warnings[$entry_id])) {
        $warnings[$entry_id] = [];
    }
    
    // Check which threshold applies
    foreach ($thresholds as $minutes => $msgData) {
        if ($elapsed >= $minutes) {
            // Did we already warn for this specific threshold?
            if (!empty($warnings[$entry_id][$minutes])) {
                continue; // Already warned for this threshold
            }
            
            // Do we have subscriptions?
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
                // Ensure array structure is valid
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

file_put_contents($warningsFile, json_encode($warnings, JSON_PRETTY_PRINT));
echo "Finished. Sent $sent_count warnings.\n";
