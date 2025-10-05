<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Manual admin login
$query = "SELECT * FROM admins WHERE email = 'admin@mdva.org'";
$stmt = $db->prepare($query);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin && password_verify('password', $admin['password'])) {
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['user_type'] = 'admin';
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name'] = $admin['full_name'];
    
    echo "<h2>✓ Manual Admin Login Successful!</h2>";
    echo "<p>Session data:</p>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    echo "<p><a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";
} else {
    echo "<h2>✗ Manual Login Failed</h2>";
}
?>