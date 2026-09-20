<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Services\WebSocketAuthService;
use App\WebSocket\NotificationHandler;

// Configuration
$wsPort = (int)(getenv('WS_PORT') ?: 8085);
$internalIpcPort = (int)(getenv('WS_INTERNAL_PORT') ?: 8086);
$secretKey = getenv('WS_SECRET') ?: 'dahira_akr_default_secure_ws_secret_key_2026';
$allowedOrigins = ['dahira_akr_v2.test', 'localhost', '127.0.0.1'];

// Structured Startup Log (backend-patterns)
$logStartup = [
    'timestamp' => date('c'),
    'level' => 'info',
    'event' => 'server_start',
    'ws_port' => $wsPort,
    'ipc_port' => $internalIpcPort,
    'allowed_origins' => $allowedOrigins
];
echo json_encode($logStartup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

$loop = Loop::get();

// 1. Initialize Auth Service and WebSocket Handler
$authService = new WebSocketAuthService($secretKey, $allowedOrigins);
$handler = new NotificationHandler($authService);

// 2. Setup Internal IPC Socket Server strictly on 127.0.0.1 (security-reviewer)
$ipcSocket = new SocketServer('127.0.0.1:' . $internalIpcPort, [], $loop);
$ipcSocket->on('connection', function (\React\Socket\ConnectionInterface $connection) use ($handler) {
    $buffer = '';
    $connection->on('data', function (string $chunk) use (&$buffer, $connection, $handler) {
        $buffer .= $chunk;
        if (str_contains($buffer, "\n")) {
            $lines = explode("\n", $buffer);
            $response = $handler->handleIpcMessage($lines[0]);
            
            $logPush = [
                'timestamp' => date('c'),
                'level' => 'info',
                'event' => 'ipc_notification_processed',
                'response' => $response
            ];
            echo json_encode($logPush, JSON_UNESCAPED_SLASHES) . PHP_EOL;

            $connection->write(json_encode($response) . "\n");
            $connection->end();
        }
    });
});

// 3. Setup Ratchet WebSocket Server
$wsSocket = new SocketServer('0.0.0.0:' . $wsPort, [], $loop);
$wsServer = new WsServer($handler);
$wsServer->enableKeepAlive($loop, 30);
$httpServer = new HttpServer($wsServer);
$ioServer = new IoServer($httpServer, $wsSocket, $loop);

echo "[WebSocket Server] Running and ready on ws://0.0.0.0:{$wsPort} and IPC 127.0.0.1:{$internalIpcPort}\n";

$loop->run();
