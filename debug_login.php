<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Admin Login Detailed Debug</h2>";

// Test the exact login process
$test_email = 'admin@mdva.org';
$test_password = 'password';

echo "<h3>1. Checking Database Connection:</h3>";
if ($db) {
    echo "✓ Database connected successfully<br>";
} else {
    echo "✗ Database connection failed<br>";
    exit;
}

echo "<h3>2. Checking Admin Record:</h3>";
$query = "SELECT * FROM admins WHERE email = ?";
$stmt = $db->prepare($query);
$stmt->execute([$test_email]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin) {
    echo "✓ Admin found:<br>";
    echo "<pre>";
    print_r($admin);
    echo "</pre>";
    
    echo "<h3>3. Password Verification:</h3>";
    $password_match = password_verify($test_password, $admin['password']);
    echo "Testing password: '$test_password'<br>";
    echo "Password matches: " . ($password_match ? '✓ YES' : '✗ NO') . "<br>";
    echo "Stored hash: " . $admin['password'] . "<br>";
    
    echo "<h3>4. Account Status:</h3>";
    echo "Active status: " . ($admin['active'] ? '✓ ACTIVE' : '✗ INACTIVE') . "<br>";
    
} else {
    echo "✗ No admin found with email: $test_email<br>";
}

echo "<h3>5. Session Test:</h3>";
$_SESSION['test'] = 'session_works';
echo "Session test: " . ($_SESSION['test'] === 'session_works' ? '✓ Sessions working' : '✗ Sessions not working') . "<br>";

echo "<h3>6. Form Simulation:</h3>";
echo "If you're submitting from form, check that:<br>";
echo "- user_type = 'admin'<br>";
echo "- email = '$test_email'<br>";
echo "- password = '$test_password'<br>";
?>