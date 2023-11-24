<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require __DIR__ . '/vendor/autoload.php';

class WebSocketServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Nuevo cliente conectado ({$conn->resourceId})\n";
        $this->notifyStatus();
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Mensaje recibido de {$from->resourceId}: $msg\n";
        $data = json_decode($msg, true);
        $messageWithResource = [
            'resourceId' => $from->resourceId,
            'message' => $data['message']
        ];
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send(json_encode($messageWithResource));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Cliente {$conn->resourceId} desconectado\n";
        $this->notifyStatus();
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    public function notifyStatus() {
        $onlineStatus = (count($this->clients) > 1) ? 'En línea' : 'Fuera de línea';
        foreach ($this->clients as $client) {
            $client->send(json_encode(['status' => $onlineStatus]));
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new WebSocketServer()
        )
    ),
    9090
);

echo "Servidor WebSocket iniciado en el puerto 9090...\n";
$server->run();
?>
