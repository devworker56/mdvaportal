<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

// Enable CORS for mobile app
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
        case 'get_approved':
            getApprovedCharities($db);
            break;
            
        case 'approve':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                approveCharity($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'reject':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                rejectCharity($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'revoke':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                revokeCharity($db, $input);
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
 * Get all approved charities for mobile app
 */
function getApprovedCharities($db) {
    $query = "SELECT id, name, description, created_at, logo_url, website 
              FROM charities 
              WHERE approved = 1 
              ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $charities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'charities' => $charities
    ]);
}

/**
 * Approve a charity and notify mobile apps
 */
function approveCharity($db, $data) {
    $charity_id = $data['id'] ?? $data['charity_id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    $query = "UPDATE charities SET approved = 1 WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Get charity info for notification
        $query = "SELECT id, name, description FROM charities WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$charity_id]);
        $charity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify WebSocket server about new approved charity
        notify_websocket('new_charity', [
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'charity_description' => $charity['description'],
            'action' => 'approved',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Log the activity
        log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_approved', 
            "Charity '{$charity['name']}' (ID: $charity_id) approved");
        
        echo json_encode(['success' => true, 'message' => 'Charity approved successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to approve charity']);
    }
}

/**
 * Reject a charity application
 */
function rejectCharity($db, $data) {
    $charity_id = $data['id'] ?? $data['charity_id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    // Get charity name before deletion for logging
    $query = "SELECT name FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $query = "DELETE FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Log the activity
        log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_rejected', 
            "Charity '{$charity['name']}' (ID: $charity_id) rejected and deleted");
            
        echo json_encode(['success' => true, 'message' => 'Charity rejected successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to reject charity']);
    }
}

/**
 * Revoke charity approval
 */
function revokeCharity($db, $data) {
    $charity_id = $data['id'] ?? $data['charity_id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    $query = "UPDATE charities SET approved = 0 WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Get charity info for notification
        $query = "SELECT name FROM charities WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$charity_id]);
        $charity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify WebSocket server about charity revocation
        notify_websocket('charity_update', [
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'action' => 'revoked',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Log the activity
        log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_revoked', 
            "Charity '{$charity['name']}' (ID: $charity_id) approval revoked");
            
        echo json_encode(['success' => true, 'message' => 'Charity approval revoked']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to revoke charity']);
    }
}
?>