<?php

declare(strict_types=1);

namespace App\Services;

class WebSocketAuthService
{
    private string $secretKey;
    /** @var array<string> */
    private array $allowedOrigins;

    /**
     * @param string $secretKey Secret key used for HMAC signing
     * @param array<string> $allowedOrigins List of whitelisted domain names or hostnames
     */
    public function __construct(string $secretKey, array $allowedOrigins = ['dahira_akr_v2.test', 'localhost', '127.0.0.1'])
    {
        $this->secretKey = $secretKey;
        $this->allowedOrigins = $allowedOrigins;
    }

    /**
     * Generate a signed, time-limited token for WebSocket handshake authentication.
     * Format: userId.expiresAt.signature
     */
    public function generateToken(int $userId, int $ttlSeconds = 60): string
    {
        $expiresAt = time() + $ttlSeconds;
        $dataToSign = $userId . '.' . $expiresAt;
        $signature = hash_hmac('sha256', $dataToSign, $this->secretKey);

        return $dataToSign . '.' . $signature;
    }

    /**
     * Verify a token's structure, expiration, and cryptographic HMAC signature.
     * Uses constant-time hash_equals to prevent timing attacks.
     *
     * @return array{user_id: int, expires_at: int}|null
     */
    public function verifyToken(?string $token): ?array
    {
        if (empty($token) || !is_string($token)) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$userIdStr, $expiresAtStr, $receivedSignature] = $parts;

        if (!ctype_digit($userIdStr) || !ctype_digit($expiresAtStr)) {
            return null;
        }

        $userId = (int)$userIdStr;
        $expiresAt = (int)$expiresAtStr;

        // Check if token has expired
        if ($expiresAt < time()) {
            return null;
        }

        // Verify cryptographic signature
        $expectedSignature = hash_hmac('sha256', $userIdStr . '.' . $expiresAtStr, $this->secretKey);
        if (!hash_equals($expectedSignature, $receivedSignature)) {
            return null;
        }

        return [
            'user_id' => $userId,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Validates the Origin header to prevent Cross-Site WebSocket Hijacking (CSWSH).
     */
    public function isOriginAllowed(?string $origin): bool
    {
        if (empty($origin) || !is_string($origin)) {
            return false;
        }

        $parsed = parse_url($origin);
        $host = $parsed['host'] ?? $origin;

        // Strip port if present in parsed host
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        foreach ($this->allowedOrigins as $allowed) {
            $allowedClean = str_contains($allowed, ':') ? explode(':', $allowed)[0] : $allowed;
            $allowedClean = preg_replace('#^https?://#', '', $allowedClean);

            if (strcasecmp($host, $allowedClean) === 0) {
                return true;
            }
        }

        return false;
    }
}
