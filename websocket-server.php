<?php
// WebSocket Server dla ROP Panel
require_once dirname(__FILE__) . '/../../../wp-load.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/class-rop-websocket-server.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ROP_WebSocket_Server()
        )
    ),
    8080
);

echo "WebSocket server started on port 8080\n";
$server->run();