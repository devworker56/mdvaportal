<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';
$charity_id = $_GET['id'] ?? '';

switch($action) {
    case 'approve':
        $query = "UPDATE charities SET approved = 1 WHERE id = ?";
        $stmt = $db->prepare($query);
        if($stmt->execute([$charity_id])) {
            // Notify WebSocket server about new approved charity
            notifyWebSocket('new_charity', ['charity_id' => $charity_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to approve charity']);
        }
        break;
        
    case 'reject':
        $query = "DELETE FROM charities WHERE id = ?";
        $stmt = $db->prepare($query);
        if($stmt->execute([$charity_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject charity']);
        }
        break;
        
    case 'revoke':
        $query = "UPDATE charities SET approved = 0 WHERE id = ?";
        $stmt = $db->prepare($query);
        if($stmt->execute([$charity_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to revoke charity']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function notifyWebSocket($type, $data) {
    $context = new ZMQContext();
    $socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'my pusher');
    $socket->connect("tcp://localhost:5555");
    
    $message = json_encode([
        'type' => $type,
        'data' => $data
    ]);
    
    $socket->send($message);
}
?>