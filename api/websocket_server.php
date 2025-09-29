<?php
require __DIR__ . '/vendor/autoload.php';

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
        
        switch($data['type']) {
            case 'subscribe':
                $this->subscriptions[$from->resourceId] = [
                    'user_type' => $data['user_type'],
                    'user_id' => $data['user_id']
                ];
                echo "Client {$from->resourceId} subscribed as {$data['user_type']} ID {$data['user_id']}\n";
                break;
                
            case 'new_donation':
                // Broadcast to relevant subscribers
                foreach ($this->clients as $client) {
                    if(isset($this->subscriptions[$client->resourceId])) {
                        $sub = $this->subscriptions[$client->resourceId];
                        if($sub['user_type'] == 'charity' && $sub['user_id'] == $data['charity_id']) {
                            $client->send(json_encode([
                                'type' => 'new_donation',
                                'charity_id' => $data['charity_id'],
                                'amount' => $data['amount'],
                                'donor_id' => $data['donor_id']
                            ]));
                        }
                    }
                }
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
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new MDVAWebSocket()
        )
    ),
    8080
);

echo "WebSocket server running on port 8080\n";
$server->run();
?>