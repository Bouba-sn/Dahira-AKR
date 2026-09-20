<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Services\PasswordResetService;
use App\Services\EmailService;

class PasswordResetServiceTest extends TestCase
{
    private PDO $pdo;
    private EmailService $mockEmailService;
    private PasswordResetService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Schéma utilisateurs
        $this->pdo->exec("
            CREATE TABLE utilisateurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'user'
            );
        ");

        // Schéma password_resets
        $this->pdo->exec("
            CREATE TABLE password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                code TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Utilisateur de test
        $initialHash = password_hash('AncienPass123', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->pdo->exec("INSERT INTO utilisateurs (id, nom, email, password) VALUES (1, 'Moustapha Fall', 'moustapha@test.sn', '{$initialHash}')");

        // Mock email service
        $this->mockEmailService = new EmailService('noreply@dahira.sn', 'Dahira AKR', true);
        $this->service = new PasswordResetService($this->pdo, $this->mockEmailService);
    }

    public function testCreateResetCodeGeneratesValid6DigitCodeAndSendsEmail(): void
    {
        $code = $this->service->createResetCode('moustapha@test.sn');

        $this->assertNotNull($code);
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);

        // Vérifier l'email envoyé
        $sentEmails = $this->mockEmailService->getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertEquals('moustapha@test.sn', $sentEmails[0]['to']);
        $this->assertStringContainsString($code, $sentEmails[0]['subject']);
        $this->assertStringContainsString($code, $sentEmails[0]['body']);
        $this->assertStringContainsString('Moustapha Fall', $sentEmails[0]['body']);

        // Vérifier en base
        $stmt = $this->pdo->query("SELECT * FROM password_resets WHERE email = 'moustapha@test.sn'");
        $record = $stmt->fetch();
        $this->assertNotEmpty($record);
        $this->assertEquals($code, $record['code']);
    }

    public function testCreateResetCodeReturnsNullForUnknownEmail(): void
    {
        $code = $this->service->createResetCode('inconnu@test.sn');

        $this->assertNull($code);
        $this->assertCount(0, $this->mockEmailService->getSentEmails());
    }

    public function testVerifyCodeAcceptsValidCode(): void
    {
        $code = $this->service->createResetCode('moustapha@test.sn');
        $this->assertTrue($this->service->verifyCode('moustapha@test.sn', $code));
    }

    public function testVerifyCodeRejectsWrongCode(): void
    {
        $this->service->createResetCode('moustapha@test.sn');
        $this->assertFalse($this->service->verifyCode('moustapha@test.sn', '000000'));
    }

    public function testVerifyCodeRejectsExpiredCode(): void
    {
        // Insérer un code expiré il y a 5 minutes
        $pastDate = date('Y-m-d H:i:s', time() - 300);
        $this->pdo->exec("INSERT INTO password_resets (email, code, expires_at) VALUES ('moustapha@test.sn', '123456', '{$pastDate}')");

        $this->assertFalse($this->service->verifyCode('moustapha@test.sn', '123456'));
    }

    public function testResetPasswordUpdatesPasswordHashAndCleansCode(): void
    {
        $code = $this->service->createResetCode('moustapha@test.sn');

        $success = $this->service->resetPassword('moustapha@test.sn', $code, 'NouveauSuperPass2026!');
        $this->assertTrue($success);

        // Vérifier le hash en base
        $stmt = $this->pdo->query("SELECT password FROM utilisateurs WHERE email = 'moustapha@test.sn'");
        $newHash = $stmt->fetchColumn();

        $this->assertTrue(password_verify('NouveauSuperPass2026!', $newHash));
        $this->assertFalse(password_verify('AncienPass123', $newHash));

        // Vérifier que le code a été supprimé
        $check = $this->pdo->query("SELECT COUNT(*) FROM password_resets WHERE email = 'moustapha@test.sn'")->fetchColumn();
        $this->assertEquals(0, $check);
    }

    public function testResetPasswordThrowsOnInvalidCode(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('invalide');

        $this->service->resetPassword('moustapha@test.sn', '999999', 'NouveauPass123');
    }

    public function testResetPasswordThrowsOnShortPassword(): void
    {
        $code = $this->service->createResetCode('moustapha@test.sn');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('au moins 6 caractères');

        $this->service->resetPassword('moustapha@test.sn', $code, '12345');
    }
}
