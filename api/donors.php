<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

switch($action) {
    case 'register':
        $email = $input['email'];
        $password = $input['password'];
        
        // Generate unique user ID
        $user_id = 'DONOR_' . uniqid();
        
        // Check if email exists
        $query = "SELECT id FROM donors WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        
        if($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            break;
        }
        
        // Insert donor
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO donors (user_id, email, password) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if($stmt->execute([$user_id, $email, $hashed_password])) {
            echo json_encode(['success' => true, 'message' => 'Registration successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
        }
        break;
        
    case 'login':
        $email = $input['email'];
        $password = $input['password'];
        
        $query = "SELECT * FROM donors WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($donor && password_verify($password, $donor['password'])) {
            echo json_encode([
                'success' => true,
                'token' => bin2hex(random_bytes(32)),
                'user' => [
                    'id' => $donor['id'],
                    'user_id' => $donor['user_id'],
                    'email' => $donor['email'],
                    'selected_charity_id' => $donor['selected_charity_id']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
        break;
        
    case 'select_charity':
        $donor_id = $input['donor_id'];
        $charity_id = $input['charity_id'];
        
        $query = "UPDATE donors SET selected_charity_id = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if($stmt->execute([$charity_id, $donor_id])) {
            echo json_encode(['success' => true, 'message' => 'Charity selection updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update charity selection']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>