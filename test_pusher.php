<?php
require_once 'config/pusher.php';

try {
    $pusher = getPusher();
    $result = $pusher->trigger('test-channel', 'test-event', [
        'message' => 'MDVA Pusher test successful!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ Pusher integration: SUCCESS\n";
    echo "Your real-time system is ready!\n";
} catch (Exception $e) {
    echo "❌ Pusher test failed: " . $e->getMessage() . "\n";
}
?>