<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use InvalidArgumentException;
use Throwable;

class NotificationService
{
    private PDO $pdo;
    private string $wsHost;
    private int $wsIpcPort;
    private ?string $ipcSecret;

    public function __construct(
        PDO $pdo,
        string $wsHost = '127.0.0.1',
        int $wsIpcPort = 8086,
        ?string $ipcSecret = null
    ) {
        $this->pdo = $pdo;
        $this->wsHost = $wsHost;
        $this->wsIpcPort = $wsIpcPort;
        $this->ipcSecret = $ipcSecret;
    }

    /**
     * Get underlying PDO instance.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Sanitize notification fields to prevent stored XSS attacks.
     *
     * @param array{titre: string, message: string, lien?: string} $data
     * @return array{titre: string, message: string, lien: string}
     */
    public function sanitizePayload(array $data): array
    {
        $titre = trim(strip_tags($data['titre'] ?? ''));
        $message = trim(strip_tags($data['message'] ?? ''));
        $lien = trim($data['lien'] ?? '#');

        // Prevent javascript: or data: URIs in link
        if (preg_match('#^(javascript|data|vbscript):#i', $lien)) {
            $lien = '#';
        }

        return [
            'titre' => $titre,
            'message' => $message,
            'lien' => empty($lien) ? '#' : $lien,
        ];
    }

    /**
     * Format IPC message payload for the WebSocket server daemon.
     */
    public function formatIpcMessage(int $userId, array $data): string
    {
        return json_encode([
            'type' => 'notification',
            'user_id' => $userId,
            'data' => $data,
            'timestamp' => time(),
            'secret' => $this->ipcSecret
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Create notification, persist in DB, and push to WebSocket daemon.
     * Fail-safe: if the WebSocket daemon is offline, DB persistence still succeeds.
     *
     * @return array{db_saved: bool, ws_delivered: bool, notification_id: ?int}
     */
    public function notify(
        int $userId,
        string $titre,
        string $message,
        string $lien = '#',
        string $type = 'systeme'
    ): array {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer.');
        }

        if (trim($titre) === '') {
            throw new InvalidArgumentException('Notification titre cannot be empty.');
        }

        if (trim($message) === '') {
            throw new InvalidArgumentException('Notification message cannot be empty.');
        }

        $sanitized = $this->sanitizePayload([
            'titre' => $titre,
            'message' => $message,
            'lien' => $lien
        ]);

        $allowedTypes = ['commande', 'adhesion', 'systeme'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'systeme';
        }

        // 1. Persist to MySQL database
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, titre, message, lien, type, lu) VALUES (?, ?, ?, ?, ?, 0)"
        );
        $dbSaved = $stmt->execute([
            $userId,
            $sanitized['titre'],
            $sanitized['message'],
            $sanitized['lien'],
            $type
        ]);

        $notifId = $dbSaved ? (int)$this->pdo->lastInsertId() : null;

        // 2. Dispatch real-time push to WebSocket daemon via local IPC socket
        $wsDelivered = false;
        try {
            $ipcPayload = $this->formatIpcMessage($userId, [
                'id' => $notifId,
                'user_id' => $userId,
                'titre' => $sanitized['titre'],
                'message' => $sanitized['message'],
                'lien' => $sanitized['lien'],
                'type' => $type,
                'lu' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $wsDelivered = $this->sendIpcPush($ipcPayload);
        } catch (Throwable $e) {
            // Fail securely and silently: Log error, never crash HTTP request
            error_log('[NotificationService] WS delivery failed: ' . $e->getMessage());
            $wsDelivered = false;
        }

        return [
            'db_saved' => $dbSaved,
            'ws_delivered' => $wsDelivered,
            'notification_id' => $notifId
        ];
    }

    /**
     * Send payload to the local WebSocket push listener.
     * Uses a short timeout (0.5s) to guarantee no blocking on HTTP requests.
     */
    private function sendIpcPush(string $payload): bool
    {
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(
            "tcp://{$this->wsHost}:{$this->wsIpcPort}",
            $errno,
            $errstr,
            0.5,
            STREAM_CLIENT_CONNECT
        );

        if (!$fp) {
            return false;
        }

        stream_set_timeout($fp, 1);
        fwrite($fp, $payload . "\n");
        $response = fgets($fp, 256);
        fclose($fp);

        if ($response !== false) {
            $respData = json_decode(trim($response), true);
            return isset($respData['status']) && $respData['status'] === 'ok';
        }

        return true;
    }
}
