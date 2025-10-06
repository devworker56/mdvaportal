<?php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class MDVAWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $subscriptions;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->subscriptions = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!isset($data['type'])) {
            return;
        }
        
        switch($data['type']) {
            case 'subscribe':
                $this->subscriptions[$from->resourceId] = [
                    'user_type' => $data['user_type'],
                    'user_id' => $data['user_id']
                ];
                echo "Client {$from->resourceId} subscribed as {$data['user_type']} ID {$data['user_id']}\n";
                break;
                
            case 'subscribe_charities':
                // Mobile app subscribing to charity updates
                $this->subscriptions[$from->resourceId] = [
                    'user_type' => 'mobile_app',
                    'channel' => 'charity_updates'
                ];
                echo "Mobile app {$from->resourceId} subscribed to charity updates\n";
                break;
                
            case 'new_donation':
                // Broadcast donation to relevant charity subscribers
                $charity_id = $data['charity_id'] ?? null;
                if ($charity_id) {
                    foreach ($this->clients as $client) {
                        if(isset($this->subscriptions[$client->resourceId])) {
                            $sub = $this->subscriptions[$client->resourceId];
                            if($sub['user_type'] == 'charity' && $sub['user_id'] == $charity_id) {
                                $client->send(json_encode([
                                    'type' => 'new_donation',
                                    'charity_id' => $charity_id,
                                    'amount' => $data['amount'],
                                    'donor_id' => $data['donor_id'],
                                    'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s')
                                ]));
                            }
                        }
                    }
                }
                break;
                
            case 'new_charity':
            case 'charity_update':
                // Broadcast to all mobile apps subscribed to charity updates
                $message_data = [
                    'type' => 'charity_list_updated',
                    'message' => 'Charity list has been updated',
                    'charity_id' => $data['charity_id'] ?? null,
                    'charity_name' => $data['charity_name'] ?? null,
                    'action' => $data['action'] ?? 'updated',
                    'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s')
                ];
                
                $this->broadcastToMobileApps($message_data);
                echo "Broadcasted charity update: {$data['action']} for charity {$data['charity_id']}\n";
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->subscriptions[$conn->resourceId]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Broadcast message to all mobile apps subscribed to charity updates
     */
    private function broadcastToMobileApps($message) {
        foreach ($this->clients as $client) {
            if(isset($this->subscriptions[$client->resourceId])) {
                $sub = $this->subscriptions[$client->resourceId];
                if($sub['user_type'] == 'mobile_app' && $sub['channel'] == 'charity_updates') {
                    $client->send(json_encode($message));
                }
            }
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new MDVAWebSocket()
        )
    ),
    8080
);

echo "MDVA WebSocket server running on port 8080\n";
echo "Waiting for connections...\n";
$server->run();
?>