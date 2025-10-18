<?php
// debug_test.php
echo "<h2>MDVA Pusher Debug</h2>";

// Test 1: Check if config file exists
$config_path = __DIR__ . '/config/pusher.php';
echo "1. Config path: " . $config_path . "<br>";
echo "   File exists: " . (file_exists($config_path) ? '✅ YES' : '❌ NO') . "<br>";

// Test 2: Check if vendor autoload exists
$vendor_path = __DIR__ . '/vendor/autoload.php';
echo "2. Vendor path: " . $vendor_path . "<br>";
echo "   File exists: " . (file_exists($vendor_path) ? '✅ YES' : '❌ NO') . "<br>";

// Test 3: Try to include files
echo "3. Including files:<br>";
try {
    require_once $vendor_path;
    echo "   - vendor/autoload.php: ✅ SUCCESS<br>";
} catch (Exception $e) {
    echo "   - vendor/autoload.php: ❌ FAILED - " . $e->getMessage() . "<br>";
}

try {
    require_once $config_path;
    echo "   - config/pusher.php: ✅ SUCCESS<br>";
} catch (Exception $e) {
    echo "   - config/pusher.php: ❌ FAILED - " . $e->getMessage() . "<br>";
}

// Test 4: Check if Pusher class exists
echo "4. Pusher class: " . (class_exists('Pusher\Pusher') ? '✅ LOADED' : '❌ MISSING') . "<br>";

// Test 5: Simple function test
echo "5. Function test: ";
if (function_exists('getPusher')) {
    echo "✅ getPusher() exists<br>";
} else {
    echo "❌ getPusher() missing<br>";
}
?>