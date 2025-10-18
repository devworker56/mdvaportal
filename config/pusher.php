<?php
// config/pusher.php
require_once __DIR__ . '/../vendor/autoload.php';

// Pusher Configuration - you'll get these from pusher.com
define('PUSHER_APP_ID', '2065620');
define('PUSHER_KEY', 'fe6f264f2fba2f7bc4a2'); 
define('PUSHER_SECRET', '7cf64dce7ff9a89e0450');
define('PUSHER_CLUSTER', 'us2'); // Change to your cluster

function getPusher() {
    return new Pusher\Pusher(
        PUSHER_KEY,
        PUSHER_SECRET, 
        PUSHER_APP_ID,
        [
            'cluster' => PUSHER_CLUSTER,
            'useTLS' => true,
            'encrypted' => true
        ]
    );
}
?>