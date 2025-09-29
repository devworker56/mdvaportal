<?php
session_start();
require_once '../config/database.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $email = $_POST['email'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    if($user_type == 'charity') {
        $query = "SELECT * FROM charities WHERE email = ? AND approved = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'charity';
            $_SESSION['charity_name'] = $user['name'];
            header("Location: ../charity/dashboard.php");
            exit();
        }
    } elseif($user_type == 'admin') {
        $query = "SELECT * FROM admins WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'admin';
            header("Location: ../admin/dashboard.php");
            exit();
        }
    }

    $_SESSION['error'] = "Invalid credentials or account not approved";
    header("Location: login.php");
    exit();
}
?>