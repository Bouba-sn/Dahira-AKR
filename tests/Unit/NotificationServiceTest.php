<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\NotificationService;
use InvalidArgumentException;
use PDO;

final class NotificationServiceTest extends TestCase
{
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an in-memory SQLite PDO or mock PDO for testing
        $sqlite = new PDO('sqlite::memory:');
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec("
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

        $this->service = new NotificationService(
            $sqlite,
            '127.0.0.1',
            9999 // port where nothing is listening to test fail-safe
        );
    }

    public function testSanitizePayloadStripsHarmfulXssContent(): void
    {
        $dirtyTitre = '<script>alert("XSS")</script>Nouvelle adhésion';
        $dirtyMessage = 'Votre demande <img src=x onerror=alert(1)> est validée';

        $sanitized = $this->service->sanitizePayload([
            'titre' => $dirtyTitre,
            'message' => $dirtyMessage,
            'lien' => 'javascript:alert(1)'
        ]);

        $this->assertStringNotContainsString('<script>', $sanitized['titre']);
        $this->assertStringNotContainsString('<img', $sanitized['message']);
        $this->assertEquals('#', $sanitized['lien'], 'Javascript URI should be replaced with #');
    }

    public function testValidatePayloadThrowsExceptionOnInvalidUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->notify(0, 'Titre', 'Message');
    }

    public function testValidatePayloadThrowsExceptionOnEmptyTitre(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->notify(1, '   ', 'Message');
    }

    public function testValidatePayloadThrowsExceptionOnEmptyMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->notify(1, 'Titre', '   ');
    }

    public function testFormatIpcMessageProducesCorrectJson(): void
    {
        $ipcMessage = $this->service->formatIpcMessage(42, [
            'id' => 101,
            'titre' => 'Cotisation reçue',
            'message' => 'Merci pour votre versement',
            'lien' => '/pages/cotisations.php',
            'type' => 'commande',
            'created_at' => '2026-09-18 20:00:00'
        ]);

        $decoded = json_decode($ipcMessage, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('notification', $decoded['type']);
        $this->assertEquals(42, $decoded['user_id']);
        $this->assertEquals('Cotisation reçue', $decoded['data']['titre']);
        $this->assertEquals('commande', $decoded['data']['type']);
    }

    public function testNotifySucceedsAndPersistsToDbEvenWhenWebSocketServerIsOffline(): void
    {
        // WebSocket server is offline on port 9999
        $result = $this->service->notify(
            userId: 7,
            titre: 'Rappel réunion',
            message: 'Réunion générale dimanche à 17h',
            lien: '/pages/reunions.php',
            type: 'systeme'
        );

        $this->assertTrue($result['db_saved'], 'Notification must be stored in database even if WS server is down');
        $this->assertFalse($result['ws_delivered'], 'WS delivery must indicate false without crashing execution');

        // Verify DB content
        $count = (int)$this->service->getPdo()->query("SELECT COUNT(*) FROM notifications WHERE user_id = 7")->fetchColumn();
        $this->assertEquals(1, $count);
    }
}
