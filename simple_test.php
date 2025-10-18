<?php
// simple_test.php
echo "<h2>Fresh Vendor Test</h2>";

// Simple direct include
require_once __DIR__ . '/vendor/autoload.php';

echo "✅ Vendor loaded<br>";

// Direct Pusher creation (no config file)
$pusher = new Pusher\Pusher(
    'fe6f264f2fba2f7bc4a2',
    '7cf64dce7ff9a89e0450', 
    '2065620',
    ['cluster' => 'us2', 'useTLS' => true]
);

echo "✅ Pusher object created<br>";

try {
    $result = $pusher->trigger('test-channel', 'test-event', [
        'message' => 'Fresh vendor test!'
    ]);
    echo "✅ <strong>SUCCESS! Pusher is working!</strong><br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>