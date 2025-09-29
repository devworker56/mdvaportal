<?php
session_start();
require_once '../config/database.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $name = $_POST['name'];
    $description = $_POST['description'];
    $email = $_POST['email'];
    $website = $_POST['website'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match";
        header("Location: register.php");
        exit();
    }

    // Check if email already exists
    $query = "SELECT id FROM charities WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    
    if($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Email already registered";
        header("Location: register.php");
        exit();
    }

    // Insert charity
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $query = "INSERT INTO charities (name, description, email, password, website) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$name, $description, $email, $hashed_password, $website])) {
        $_SESSION['success'] = "Registration successful! Waiting for admin approval.";
        header("Location: login.php");
    } else {
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: register.php");
    }
}
?>