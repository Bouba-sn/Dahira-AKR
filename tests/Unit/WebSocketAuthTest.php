<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\WebSocketAuthService;

final class WebSocketAuthTest extends TestCase
{
    private string $secretKey = 'super_secret_test_key_for_hmac_sha256';
    private WebSocketAuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new WebSocketAuthService(
            $this->secretKey,
            ['dahira_akr_v2.test', 'localhost', '127.0.0.1']
        );
    }

    public function testGenerateTokenReturnsValidFormat(): void
    {
        $userId = 42;
        $token = $this->authService->generateToken($userId, 60);

        $this->assertNotEmpty($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'Token must have 3 parts: userId.expiresAt.signature');
        $this->assertEquals((string)$userId, $parts[0]);
        $this->assertGreaterThan(time(), (int)$parts[1]);
        $this->assertNotEmpty($parts[2]);
    }

    public function testVerifyTokenSucceedsWithValidToken(): void
    {
        $userId = 15;
        $token = $this->authService->generateToken($userId, 120);

        $payload = $this->authService->verifyToken($token);

        $this->assertNotNull($payload);
        $this->assertIsArray($payload);
        $this->assertEquals($userId, $payload['user_id']);
        $this->assertGreaterThanOrEqual(time(), $payload['expires_at']);
    }

    public function testVerifyTokenFailsWhenSignatureIsTampered(): void
    {
        $token = $this->authService->generateToken(10, 60);
        $parts = explode('.', $token);
        // Alter signature
        $tamperedToken = $parts[0] . '.' . $parts[1] . '.' . substr($parts[2], 0, -4) . 'fake';

        $payload = $this->authService->verifyToken($tamperedToken);

        $this->assertNull($payload, 'Tampered token must return null');
    }

    public function testVerifyTokenFailsWhenUserIdIsAltered(): void
    {
        $token = $this->authService->generateToken(10, 60);
        $parts = explode('.', $token);
        // Change user id from 10 to 999 (impersonation attempt)
        $tamperedToken = '999.' . $parts[1] . '.' . $parts[2];

        $payload = $this->authService->verifyToken($tamperedToken);

        $this->assertNull($payload, 'Altered user_id must fail signature verification');
    }

    public function testVerifyTokenFailsWhenExpired(): void
    {
        // Generate a token expired 10 seconds ago
        $userId = 5;
        $expiredAt = time() - 10;
        $signature = hash_hmac('sha256', $userId . '.' . $expiredAt, $this->secretKey);
        $expiredToken = $userId . '.' . $expiredAt . '.' . $signature;

        $payload = $this->authService->verifyToken($expiredToken);

        $this->assertNull($payload, 'Expired token must return null');
    }

    public function testVerifyTokenFailsWithMalformedInput(): void
    {
        $this->assertNull($this->authService->verifyToken(''));
        $this->assertNull($this->authService->verifyToken('invalid-token-without-dots'));
        $this->assertNull($this->authService->verifyToken('1.2'));
        $this->assertNull($this->authService->verifyToken('notanumber.timestamp.sig'));
    }

    public function testValidateOriginAllowsWhitelistedDomains(): void
    {
        $this->assertTrue($this->authService->isOriginAllowed('http://dahira_akr_v2.test'));
        $this->assertTrue($this->authService->isOriginAllowed('http://localhost'));
        $this->assertTrue($this->authService->isOriginAllowed('http://localhost:8080'));
        $this->assertTrue($this->authService->isOriginAllowed('http://127.0.0.1'));
        $this->assertTrue($this->authService->isOriginAllowed('http://127.0.0.1:8085'));
    }

    public function testValidateOriginRejectsUntrustedDomains(): void
    {
        // Cross-Site WebSocket Hijacking (CSWSH) attack prevention
        $this->assertFalse($this->authService->isOriginAllowed('http://malicious-attacker.com'));
        $this->assertFalse($this->authService->isOriginAllowed('https://evil-phishing.org'));
        $this->assertFalse($this->authService->isOriginAllowed(''));
    }
}
