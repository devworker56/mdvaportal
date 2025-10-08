<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php'; // ADD THIS LINE

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
                // Get the newly created donor ID
                $new_donor_id = $db->lastInsertId();
                
                // Create initial verifiable transaction for this donor
                create_initial_verifiable_transaction($new_donor_id, $user_id, $db);
                
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
            // VERIFIABLE CHARITY SELECTION WITH BLOCKCHAIN-ESQUE RECORDING
            $donor_id = $input['donor_id'] ?? '';
            $charity_id = $input['charity_id'] ?? '';
            
            error_log("Verifiable charity selection called with donor_id: $donor_id, charity_id: $charity_id");
            
            if (empty($donor_id) || empty($charity_id)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Missing required fields',
                    'received_data' => $input
                ]);
                break;
            }
            
            try {
                // Start transaction for atomic operation
                $db->beginTransaction();
                
                // Get current charity selection for the audit trail
                $query = "SELECT selected_charity_id, user_id FROM donors WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$donor_id]);
                $current_donor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_donor) {
                    throw new Exception("Donor not found");
                }
                
                $old_charity_id = $current_donor['selected_charity_id'];
                $donor_user_id = $current_donor['user_id'];
                
                // Verify charity exists and is approved
                $query = "SELECT id, name FROM charities WHERE id = ? AND approved = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$charity_id]);
                $charity = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$charity) {
                    throw new Exception("Charity not found or not approved");
                }
                
                // Update charity selection
                $query = "UPDATE donors SET selected_charity_id = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if (!$stmt->execute([$charity_id, $donor_id])) {
                    throw new Exception("Failed to update charity selection in database");
                }
                
                // USE THE FUNCTION FROM functions.php INSTEAD OF DUPLICATED CODE
                $transaction_hash = create_verifiable_charity_selection(
                    $donor_id, 
                    $donor_user_id, 
                    $old_charity_id, 
                    $charity_id, 
                    $charity['name'], 
                    $db
                );
                
                $db->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Charity selection updated and recorded to FairGive verifiable ledger',
                    'transaction_hash' => $transaction_hash,
                    'charity_name' => $charity['name'],
                    'verified' => true,
                    'donor_id' => $donor_user_id,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                error_log("FairGive Verifiable Charity Selection Failed: " . $e->getMessage());
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to update verifiable charity selection',
                    'error' => $e->getMessage()
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