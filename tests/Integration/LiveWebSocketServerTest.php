<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\WebSocketAuthService;
use App\Services\NotificationService;
use PDO;

final class LiveWebSocketServerTest extends TestCase
{
    private WebSocketAuthService $authService;
    private NotificationService $notifService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new WebSocketAuthService(
            'dahira_akr_default_secure_ws_secret_key_2026',
            ['dahira_akr_v2.test', 'localhost', '127.0.0.1']
        );

        $pdo = new PDO('sqlite::memory:');
        $pdo->exec("
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                titre TEXT NOT NULL,
                message TEXT NOT NULL,
                lien TEXT DEFAULT '#',
                lu INTEGER DEFAULT 0,
                type TEXT DEFAULT 'systeme',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->notifService = new NotificationService($pdo, '127.0.0.1', 8086);
    }

    public function testIpcPushToLiveServerReturnsOk(): void
    {
        // Check if server is listening on port 8086
        $connection = @stream_socket_client('tcp://127.0.0.1:8086', $errno, $errstr, 0.5);
        if (!$connection) {
            $this->markTestSkipped('Live WebSocket daemon is not running on port 8086');
        }
        fclose($connection);

        $result = $this->notifService->notify(
            userId: 99,
            titre: 'Test Live IPC',
            message: 'Vérification du flux IPC en direct',
            lien: '/pages/notifications.php'
        );

        $this->assertTrue($result['db_saved']);
        $this->assertTrue($result['ws_delivered'], 'IPC message must be delivered to the live daemon');
    }

    public function testWebSocketHandshakeOnLivePort(): void
    {
        $fp = @stream_socket_client('tcp://127.0.0.1:8085', $errno, $errstr, 0.5);
        if (!$fp) {
            $this->markTestSkipped('Live WebSocket daemon is not running on port 8085');
        }

        $token = $this->authService->generateToken(1, 60);
        $key = base64_encode(random_bytes(16));

        $handshake = "GET /ws?token={$token} HTTP/1.1\r\n" .
                     "Host: 127.0.0.1:8085\r\n" .
                     "Upgrade: websocket\r\n" .
                     "Connection: Upgrade\r\n" .
                     "Sec-WebSocket-Key: {$key}\r\n" .
                     "Sec-WebSocket-Version: 13\r\n" .
                     "Origin: http://127.0.0.1\r\n\r\n";

        fwrite($fp, $handshake);
        $response = fread($fp, 1024);
        fclose($fp);

        $this->assertStringContainsString('101 Switching Protocols', $response, 'WebSocket server must respond with HTTP 101');
    }
}
