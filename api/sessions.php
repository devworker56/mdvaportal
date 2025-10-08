<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

// CORS headers for mobile app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch($action) {
        case 'start_session':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                startDonationSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'validate_qr':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                validateQRCode($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_module_info':
            if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                getModuleInfo($db, $_GET);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Start a donation session with per-donation charity selection
 * This does NOT update the donors table to avoid trigger conflicts
 */
function startDonationSession($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    $charity_id = $data['charity_id'] ?? '';
    $module_id = $data['module_id'] ?? '';
    
    error_log("Starting donation session: donor_id=$donor_id, charity_id=$charity_id, module_id=$module_id");
    
    if (empty($donor_id) || empty($charity_id) || empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Verify donor exists
    $query = "SELECT id, user_id FROM donors WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found']);
        return;
    }
    
    // Verify charity exists and is approved
    $query = "SELECT id, name FROM charities WHERE id = ? AND approved = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Charity not found or not approved']);
        return;
    }
    
    // Create verifiable donation session record
    $transaction_hash = create_verifiable_donation_session(
        $donor_id,
        $donor['user_id'],
        $charity_id,
        $module_id,
        $db
    );
    
    // Log the activity
    log_activity($db, 'donor', $donor_id, 'donation_session_started', 
        "Donor {$donor['user_id']} started donation session for charity '{$charity['name']}' via module $module_id");
    
    // Notify WebSocket about session start
    notify_websocket('session_started', [
        'donor_id' => $donor_id,
        'donor_user_id' => $donor['user_id'],
        'charity_id' => $charity_id,
        'charity_name' => $charity['name'],
        'module_id' => $module_id,
        'transaction_hash' => $transaction_hash,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Donation session started successfully',
        'session' => [
            'donor_id' => $donor_id,
            'donor_user_id' => $donor['user_id'],
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'module_id' => $module_id,
            'transaction_hash' => $transaction_hash,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}

/**
 * Validate QR code data
 */
function validateQRCode($db, $data) {
    $qr_data = $data['qr_data'] ?? '';
    
    if (empty($qr_data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'QR data required']);
        return;
    }
    
    try {
        $decoded = json_decode($qr_data, true);
        
        if (!$decoded) {
            echo json_encode([
                'success' => true,
                'valid' => false,
                'message' => 'Invalid QR code format - not valid JSON'
            ]);
            return;
        }
        
        // Check required fields for MDVA system
        $valid = true;
        $message = 'Valid MDVA QR code';
        
        if (!isset($decoded['module_id'])) {
            $valid = false;
            $message = 'Missing module_id in QR code';
        } elseif (!isset($decoded['system']) || $decoded['system'] !== 'MDVA') {
            $valid = false;
            $message = 'Not a valid MDVA system QR code';
        } elseif (!isset($decoded['type']) || $decoded['type'] !== 'donation_module') {
            $valid = false;
            $message = 'Not a donation module QR code';
        }
        
        echo json_encode([
            'success' => true,
            'valid' => $valid,
            'message' => $message,
            'qr_data' => $decoded
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => true,
            'valid' => false,
            'message' => 'Invalid QR code format: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get module information
 */
function getModuleInfo($db, $data) {
    $module_id = $data['module_id'] ?? '';
    
    if (empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Module ID required']);
        return;
    }
    
    // For now, return basic module info
    // In a real implementation, you would query a modules table
    $module_info = [
        'module_id' => $module_id,
        'module_name' => 'Module ' . $module_id,
        'status' => 'active',
        'location' => 'Default Location',
        'system' => 'MDVA',
        'type' => 'donation_module'
    ];
    
    echo json_encode([
        'success' => true,
        'module' => $module_info
    ]);
}
?>