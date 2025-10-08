<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get input data based on method
if ($method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST; // Fallback to form data
    }
} else {
    $input = $_GET;
}

// Enable CORS for React Native app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch($action) {
        case 'record_donation':
            if ($method == 'POST') {
                recordDonation($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'start_session':
            if ($method == 'POST') {
                startDonationSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'stats':
            if ($method == 'GET') {
                getDonationStats($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'history':
            if ($method == 'GET') {
                getDonationHistory($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'tax_receipts':
            if ($method == 'GET') {
                getTaxReceipts($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'charity_donations':
            if ($method == 'GET') {
                getCharityDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'recent_donations':
            if ($method == 'GET') {
                getRecentDonations($db, $input);
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
 * Record a donation from Module MDVA - FIXED VERSION
 */
function recordDonation($db, $data) {
    error_log("Recording donation: " . json_encode($data));
    
    $module_id = $data['module_id'] ?? '';
    $coin_count = $data['coin_count'] ?? 0;
    $amount = $data['amount'] ?? 0;
    
    if (empty($module_id) || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: module_id and amount > 0 required']);
        return;
    }
    
    try {
        // For testing: Use donor 6 and get the last selected charity from sessions
        $donor_id = 6;
        
        // Get the most recent session to find the charity
        $charity_query = "SELECT charity_id FROM verifiable_transactions 
                         WHERE donor_id = ? AND transaction_type = 'donation_session' 
                         ORDER BY id DESC LIMIT 1";
        $charity_stmt = $db->prepare($charity_query);
        $charity_stmt->execute([$donor_id]);
        $session = $charity_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session || !isset($session['charity_id'])) {
            // Fallback to a default charity for testing
            $charity_id = 5; // Animal Rescue League as fallback
            error_log("No active session found, using fallback charity ID: " . $charity_id);
        } else {
            $charity_id = $session['charity_id'];
            error_log("Found active session with charity ID: " . $charity_id);
        }
        
        $query = "INSERT INTO donations (donor_id, charity_id, amount, module_id, coin_count, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        
        error_log("Executing donation insert: donor_id=$donor_id, charity_id=$charity_id, amount=$amount, module_id=$module_id");
        
        if ($stmt->execute([$donor_id, $charity_id, $amount, $module_id, $coin_count])) {
            $donation_id = $db->lastInsertId();
            error_log("Donation recorded successfully with ID: " . $donation_id);
            
            // Get donor and charity info
            $donor_user_id = get_donor_user_id($donor_id, $db);
            $charity_name = get_charity_name($charity_id, $db);
            
            error_log("Donor: $donor_user_id, Charity: $charity_name");
            
            // Create verifiable donation record if function exists
            if (function_exists('create_verifiable_donation')) {
                try {
                    create_verifiable_donation($donor_id, $donor_user_id, $charity_id, $amount, $module_id, $db);
                    error_log("Verifiable donation record created");
                } catch (Exception $e) {
                    error_log("Warning: Could not create verifiable record: " . $e->getMessage());
                }
            } else {
                error_log("Warning: create_verifiable_donation function not found");
            }
            
            $response = [
                'success' => true, 
                'message' => 'Donation recorded successfully',
                'donation_id' => $donation_id,
                'donor_id' => $donor_user_id,
                'charity_id' => $charity_id,
                'charity_name' => $charity_name,
                'amount' => $amount,
                'module_id' => $module_id
            ];
            
            error_log("Sending success response: " . json_encode($response));
            echo json_encode($response);
            
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Donation insert failed: " . print_r($errorInfo, true));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . ($errorInfo[2] ?? 'Unknown error')]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Record donation exception: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
}
/**
 * Start a donation session (when donor scans QR code)
 */
///-----------------------------------------------------------------------------------------------
/**
 * Start a donation session (when donor scans QR code and selects charity)
 */
function startDonationSession($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    $charity_id = $data['charity_id'] ?? '';
    $module_id = $data['module_id'] ?? '';
    
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
    
    // FIXED: Don't update donors table - we're doing per-donation charity selection
    // Just create a session record or return success
    
    try {
        // Create donation session record in a sessions table (if you have one)
        // For now, we'll just create a verifiable transaction record
        
        $transaction_hash = create_verifiable_donation_session(
            $donor_id,
            $donor['user_id'],
            $charity_id,
            $module_id,
            $db
        );
        
        // Notify WebSocket about session start
        notify_websocket('session_started', [
            'donor_id' => $donor_id,
            'charity_id' => $charity_id,
            'module_id' => $module_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Donation session started successfully',
            'session' => [
                'donor_id' => $donor_id,
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'module_id' => $module_id,
                'transaction_hash' => $transaction_hash
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Donation session error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to start donation session',
            'error' => $e->getMessage()
        ]);
    }
}
///-----------------------------------------------------------------------------------------------
/**
 * Get donation statistics for a donor
 */
function getDonationStats($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Donor ID required']);
        return;
    }
    
    $query = "SELECT 
                SUM(amount) as total_donated, 
                COUNT(*) as donation_count,
                AVG(amount) as average_donation,
                MAX(amount) as largest_donation,
                MIN(amount) as smallest_donation,
                MAX(created_at) as last_donation_date
              FROM donations 
              WHERE donor_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get charity distribution
    $query = "SELECT 
                c.name as charity_name,
                SUM(d.amount) as total_donated,
                COUNT(*) as donation_count
              FROM donations d
              JOIN charities c ON d.charity_id = c.id
              WHERE d.donor_id = ?
              GROUP BY d.charity_id
              ORDER BY total_donated DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $charity_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'stats' => $stats ?: [
            'total_donated' => 0,
            'donation_count' => 0,
            'average_donation' => 0,
            'largest_donation' => 0,
            'smallest_donation' => 0,
            'last_donation_date' => null
        ],
        'charity_distribution' => $charity_distribution
    ]);
}

/**
 * Get donation history for a donor
 */
/**
 * Get donation history for a donor
 */
function getDonationHistory($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Donor ID required']);
        return;
    }
    
    try {
        // SIMPLIFIED QUERY WITHOUT PAGINATION PARAMETERS
        $query = "SELECT 
                    d.id,
                    d.amount,
                    d.created_at,
                    d.module_id,
                    c.name as charity_name,
                    c.id as charity_id
                  FROM donations d
                  JOIN charities c ON d.charity_id = c.id
                  WHERE d.donor_id = ?
                  ORDER BY d.created_at DESC
                  LIMIT 50";
        $stmt = $db->prepare($query);
        $stmt->execute([$donor_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'history' => $history,
            'count' => count($history)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Donation history error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load donation history: ' . $e->getMessage()]);
    }
}

/**
 * Get tax receipt data for a donor
 */
function getTaxReceipts($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    $year = $data['year'] ?? date('Y');
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Donor ID required']);
        return;
    }
    
    $receipt_data = generate_tax_receipt_data($donor_id, $year, $db);
    
    echo json_encode([
        'success' => true, 
        'receipt' => $receipt_data,
        'donor_info' => getDonorInfo($donor_id, $db)
    ]);
}

/**
 * Get donations for a charity
 */
function getCharityDonations($db, $data) {
    $charity_id = $data['charity_id'] ?? '';
    $limit = $data['limit'] ?? 50;
    $offset = $data['offset'] ?? 0;
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    $query = "SELECT 
                d.id,
                d.amount,
                d.created_at,
                d.module_id,
                do.user_id as donor_id
              FROM donations d
              JOIN donors do ON d.donor_id = do.id
              WHERE d.charity_id = ?
              ORDER BY d.created_at DESC
              LIMIT ? OFFSET ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id, $limit, $offset]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count and amount for pagination
    $query = "SELECT COUNT(*) as total, SUM(amount) as total_amount 
              FROM donations 
              WHERE charity_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'donations' => $donations,
        'pagination' => [
            'total' => $totals['total'],
            'total_amount' => $totals['total_amount'],
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
}

/**
 * Get recent donations across the system
 */
function getRecentDonations($db, $data) {
    $limit = $data['limit'] ?? 10;
    
    $query = "SELECT 
                d.id,
                d.amount,
                d.created_at,
                do.user_id as donor_id,
                c.name as charity_name
              FROM donations d
              JOIN donors do ON d.donor_id = do.id
              JOIN charities c ON d.charity_id = c.id
              ORDER BY d.created_at DESC
              LIMIT ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'recent_donations' => $donations
    ]);
}

/**
 * Helper function to get donor info
 */
function getDonorInfo($donor_id, $db) {
    $query = "SELECT user_id, email, created_at FROM donors WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Demo function - get first donor ID (for testing)
 */
function getDemoDonorId($db) {
    $query = "SELECT id FROM donors ORDER BY id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}

/**
 * Demo function - get first charity ID (for testing)
 */
function getDemoCharityId($db) {
    $query = "SELECT id FROM charities WHERE approved = 1 ORDER BY id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}
?>