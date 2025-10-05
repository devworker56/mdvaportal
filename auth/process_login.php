<?php
session_start();
require_once '../config/database.php';

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $user_type = $_POST['user_type'];

    echo "<h3>Debug Login Process:</h3>";
    echo "User Type: " . htmlspecialchars($user_type) . "<br>";
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password Length: " . strlen($password) . "<br>";

    if($user_type == 'charity') {
        echo "Processing as Charity...<br>";
        $query = "SELECT * FROM charities WHERE email = ? AND approved = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user) {
            echo "Charity found: " . $user['name'] . "<br>";
            if(password_verify($password, $user['password'])) {
                echo "✓ Charity password verified!<br>";
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = 'charity';
                $_SESSION['charity_name'] = $user['name'];
                echo "Redirecting to charity dashboard...<br>";
                header("Location: ../charity/dashboard.php");
                exit();
            } else {
                echo "✗ Charity password incorrect<br>";
            }
        } else {
            echo "✗ Charity not found or not approved<br>";
        }

    } elseif($user_type == 'admin') {
        echo "Processing as Admin...<br>";
        $query = "SELECT * FROM admins WHERE email = ? AND active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user) {
            echo "Admin found: " . $user['email'] . "<br>";
            $password_valid = password_verify($password, $user['password']);
            echo "Password valid: " . ($password_valid ? 'YES' : 'NO') . "<br>";
            
            if($password_valid) {
                echo "✓ Admin login successful!<br>";
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_name'] = $user['full_name'];
                
                // Debug session data
                echo "Session data set:<br>";
                echo "- user_id: " . $_SESSION['user_id'] . "<br>";
                echo "- user_type: " . $_SESSION['user_type'] . "<br>";
                echo "- admin_email: " . $_SESSION['admin_email'] . "<br>";
                
                echo "Redirecting to admin dashboard...<br>";
                header("Location: ../admin/dashboard.php");
                exit();
            } else {
                echo "✗ Admin password incorrect<br>";
            }
        } else {
            echo "✗ Admin not found or inactive<br>";
        }
    } else {
        echo "✗ Invalid user type: " . htmlspecialchars($user_type) . "<br>";
    }

    $_SESSION['error'] = "Invalid credentials or account not approved";
    echo "Setting error message and redirecting to login...<br>";
    header("Location: login.php");
    exit();
} else {
    echo "✗ Not a POST request<br>";
    header("Location: login.php");
    exit();
}
?>