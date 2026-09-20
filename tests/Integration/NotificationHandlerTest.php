<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\WebSocket\NotificationHandler;
use App\Services\WebSocketAuthService;
use Ratchet\ConnectionInterface;
use GuzzleHttp\Psr7\Request;

class FakeConnection implements ConnectionInterface
{
    public int $resourceId;
    public ?Request $httpRequest = null;
    public array $sentMessages = [];
    public bool $closed = false;

    public function __construct(int $resourceId, ?Request $request = null)
    {
        $this->resourceId = $resourceId;
        $this->httpRequest = $request;
    }

    public function send($data): ConnectionInterface
    {
        $this->sentMessages[] = (string)$data;
        return $this;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

final class NotificationHandlerTest extends TestCase
{
    private WebSocketAuthService $authService;
    private NotificationHandler $handler;
    private string $secret = 'integration_test_secret_32_bytes_len';

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new WebSocketAuthService(
            $this->secret,
            ['dahira_akr_v2.test', 'localhost', '127.0.0.1']
        );
        $this->handler = new NotificationHandler($this->authService);
    }

    private function createFakeConnection(int $resourceId, ?string $queryString = null, string $origin = 'http://dahira_akr_v2.test'): FakeConnection
    {
        $uri = '/ws' . ($queryString ? '?' . $queryString : '');
        $request = new Request('GET', $uri, [
            'Origin' => [$origin],
            'Host' => ['dahira_akr_v2.test']
        ]);

        return new FakeConnection($resourceId, $request);
    }

    public function testOnOpenRejectsConnectionWithoutToken(): void
    {
        $conn = $this->createFakeConnection(1, null);

        $this->handler->onOpen($conn);
        $this->assertTrue($conn->closed);
        $this->assertEquals(0, $this->handler->getConnectedClientsCount());
    }

    public function testOnOpenRejectsConnectionWithInvalidOrigin(): void
    {
        $validToken = $this->authService->generateToken(10, 60);
        $conn = $this->createFakeConnection(1, 'token=' . $validToken, 'http://malicious-site.com');

        $this->handler->onOpen($conn);
        $this->assertTrue($conn->closed);
        $this->assertEquals(0, $this->handler->getConnectedClientsCount());
    }

    public function testOnOpenAcceptsConnectionWithValidTokenAndAllowedOrigin(): void
    {
        $validToken = $this->authService->generateToken(10, 60);
        $conn = $this->createFakeConnection(1, 'token=' . $validToken, 'http://dahira_akr_v2.test');

        $this->handler->onOpen($conn);
        $this->assertFalse($conn->closed);
        $this->assertEquals(1, $this->handler->getConnectedClientsCount());
        $this->assertTrue($this->handler->isUserConnected(10));
    }

    public function testSendToUserDeliversOnlyToTargetedUser(): void
    {
        // Connect User 1 (resourceId 1)
        $token1 = $this->authService->generateToken(1, 60);
        $conn1 = $this->createFakeConnection(1, 'token=' . $token1);

        // Connect User 2 (resourceId 2)
        $token2 = $this->authService->generateToken(2, 60);
        $conn2 = $this->createFakeConnection(2, 'token=' . $token2);

        $this->handler->onOpen($conn1);
        $this->handler->onOpen($conn2);

        $payload = [
            'type' => 'notification',
            'titre' => 'Message pour User 1',
            'message' => 'Contenu secret',
            'lien' => '#'
        ];

        $delivered = $this->handler->sendToUser(1, $payload);
        $this->assertTrue($delivered);

        $this->assertCount(1, $conn1->sentMessages);
        $data = json_decode($conn1->sentMessages[0], true);
        $this->assertEquals('Message pour User 1', $data['titre']);

        // User 2 received nothing
        $this->assertCount(0, $conn2->sentMessages);
    }

    public function testBroadcastDeliversToAllConnectedUsers(): void
    {
        $conn1 = $this->createFakeConnection(1, 'token=' . $this->authService->generateToken(1, 60));
        $conn2 = $this->createFakeConnection(2, 'token=' . $this->authService->generateToken(2, 60));

        $this->handler->onOpen($conn1);
        $this->handler->onOpen($conn2);

        $payload = [
            'type' => 'notification',
            'titre' => 'Annonce générale',
            'message' => 'Magal dans 3 jours',
            'lien' => '#'
        ];

        $count = $this->handler->broadcast($payload);
        $this->assertEquals(2, $count);
        $this->assertCount(1, $conn1->sentMessages);
        $this->assertCount(1, $conn2->sentMessages);
    }

    public function testOnCloseCleansUpConnectionFromMemory(): void
    {
        $token = $this->authService->generateToken(5, 60);
        $conn = $this->createFakeConnection(1, 'token=' . $token);

        $this->handler->onOpen($conn);
        $this->assertTrue($this->handler->isUserConnected(5));

        $this->handler->onClose($conn);
        $this->assertFalse($this->handler->isUserConnected(5));
        $this->assertEquals(0, $this->handler->getConnectedClientsCount());
    }
}
