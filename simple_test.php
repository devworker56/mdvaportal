<?php
// minimal_test.php
echo "Step 1: Starting test...<br>";
flush();

require_once __DIR__ . '/vendor/autoload.php';
echo "Step 2: Vendor loaded<br>";
flush();

$pusher = new Pusher\Pusher(
    'fe6f264f2fba2f7bc4a2',
    '7cf64dce7ff9a89e0450', 
    '2065620',
    ['cluster' => 'us2', 'useTLS' => true]
);
echo "Step 3: Pusher object created<br>";
flush();

$result = $pusher->trigger('mdva-test', 'donation-event', [
    'message' => 'Test from MDVA system',
    'status' => 'working'
]);
echo "Step 4: ✅ PUSHER SUCCESS! Real-time ready!<br>";
?>