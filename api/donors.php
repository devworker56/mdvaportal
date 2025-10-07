<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

// IMPROVED INPUT HANDLING
$input = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

// ADD CORS HEADERS FOR MOBILE APP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ADD ERROR HANDLING FOR DATABASE
try {
    switch($action) {
        case 'register':
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email and password are required']);
                break;
            }
            
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
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Registration failed']);
            }
            break;
            
        case 'login':
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email and password are required']);
                break;
            }
            
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
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
            break;
            
        case 'select_charity':
            // ADD PROPER VALIDATION AND ERROR HANDLING
            $donor_id = $input['donor_id'] ?? '';
            $charity_id = $input['charity_id'] ?? '';
            
            error_log("select_charity called with donor_id: $donor_id, charity_id: $charity_id");
            
            if (empty($donor_id) || empty($charity_id)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Missing required fields',
                    'received_data' => $input
                ]);
                break;
            }
            
            // Verify donor exists
            $query = "SELECT id FROM donors WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$donor_id]);
            
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Donor not found']);
                break;
            }
            
            // Verify charity exists and is approved
            $query = "SELECT id FROM charities WHERE id = ? AND approved = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$charity_id]);
            
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Charity not found or not approved']);
                break;
            }
            
            $query = "UPDATE donors SET selected_charity_id = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if($stmt->execute([$charity_id, $donor_id])) {
                error_log("Charity selection updated successfully for donor $donor_id");
                echo json_encode([
                    'success' => true, 
                    'message' => 'Charity selection updated',
                    'donor_id' => $donor_id,
                    'charity_id' => $charity_id
                ]);
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Database error: " . print_r($errorInfo, true));
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to update charity selection',
                    'database_error' => $errorInfo[2] ?? 'Unknown database error'
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("PHP Exception in donors.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]);
}
?>