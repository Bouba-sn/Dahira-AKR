<?php

declare(strict_types=1);

namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Services\WebSocketAuthService;
use SplObjectStorage;
use Exception;
use Throwable;

class NotificationHandler implements MessageComponentInterface
{
    private WebSocketAuthService $authService;
    private SplObjectStorage $clients;

    /** @var array<int, array<int, ConnectionInterface>> Map: userId => [resourceId => ConnectionInterface] */
    private array $userConnections = [];

    /** @var array<int, int> Map: resourceId => userId */
    private array $connUserMap = [];

    public function __construct(WebSocketAuthService $authService)
    {
        $this->authService = $authService;
        $this->clients = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $request = $conn->httpRequest ?? null;

        if (!$request) {
            $conn->close();
            return;
        }

        // 1. Origin Header validation (CSWSH Prevention)
        $origins = $request->getHeader('Origin');
        $origin = !empty($origins) ? $origins[0] : null;

        if (!$this->authService->isOriginAllowed($origin)) {
            error_log(sprintf('[WS Security] Rejected connection from unauthorized origin: %s', $origin ?? 'NONE'));
            $conn->close();
            return;
        }

        // 2. Token Authentication & Signature Verification
        $queryString = $request->getUri()->getQuery();
        parse_str($queryString, $queryParams);
        $token = $queryParams['token'] ?? null;

        $authPayload = $this->authService->verifyToken($token);
        if (!$authPayload) {
            error_log('[WS Security] Rejected connection with invalid or expired auth token');
            $conn->close();
            return;
        }

        $userId = $authPayload['user_id'];

        // 3. Register client connection
        $this->clients->attach($conn);
        $this->userConnections[$userId][$conn->resourceId] = $conn;
        $this->connUserMap[$conn->resourceId] = $userId;
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Client-to-server messages (e.g. heartbeat ping/pong)
        $data = json_decode((string)$msg, true);
        if (isset($data['type']) && $data['type'] === 'ping') {
            $from->send(json_encode(['type' => 'pong', 'timestamp' => time()]));
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);

        $resourceId = $conn->resourceId;
        if (isset($this->connUserMap[$resourceId])) {
            $userId = $this->connUserMap[$resourceId];
            unset($this->userConnections[$userId][$resourceId]);

            if (empty($this->userConnections[$userId])) {
                unset($this->userConnections[$userId]);
            }

            unset($this->connUserMap[$resourceId]);
        }
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        error_log(sprintf('[WS Error] Resource %d: %s', $conn->resourceId, $e->getMessage()));
        $conn->close();
    }

    /**
     * Deliver real-time notification to all active connections belonging to a specific user.
     */
    public function sendToUser(int $userId, array $data): bool
    {
        if (empty($this->userConnections[$userId])) {
            return false;
        }

        $payload = json_encode($data);
        $delivered = false;

        foreach ($this->userConnections[$userId] as $conn) {
            try {
                $conn->send($payload);
                $delivered = true;
            } catch (Throwable $e) {
                error_log(sprintf('[WS Delivery Error] Failed to send to conn %d: %s', $conn->resourceId, $e->getMessage()));
            }
        }

        return $delivered;
    }

    /**
     * Broadcast message to all currently connected clients.
     */
    public function broadcast(array $data): int
    {
        $payload = json_encode($data);
        $count = 0;

        foreach ($this->clients as $conn) {
            try {
                $conn->send($payload);
                $count++;
            } catch (Throwable) {
                // Ignore broken client during broadcast
            }
        }

        return $count;
    }

    /**
     * Process internal IPC push message received from PHP web processes.
     */
    public function handleIpcMessage(string $rawMessage): array
    {
        $payload = json_decode(trim($rawMessage), true);
        if (!$payload || !isset($payload['type'])) {
            return ['status' => 'error', 'message' => 'Invalid JSON payload'];
        }

        if ($payload['type'] === 'notification') {
            $userId = (int)($payload['user_id'] ?? 0);
            $notifData = $payload['data'] ?? [];

            if ($userId > 0) {
                $delivered = $this->sendToUser($userId, $notifData);
                return ['status' => 'ok', 'delivered' => $delivered, 'recipients' => $delivered ? 1 : 0];
            }

            // Public broadcast if user_id is 0
            $count = $this->broadcast($notifData);
            return ['status' => 'ok', 'delivered' => $count > 0, 'recipients' => $count];
        }

        return ['status' => 'error', 'message' => 'Unknown message type'];
    }

    public function getConnectedClientsCount(): int
    {
        return count($this->clients);
    }

    public function isUserConnected(int $userId): bool
    {
        return !empty($this->userConnections[$userId]);
    }
}
