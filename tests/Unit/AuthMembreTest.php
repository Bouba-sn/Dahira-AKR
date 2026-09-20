<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;

final class AuthMembreTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        } else {
            $_SESSION = [];
        }

        require_once __DIR__ . '/../../includes/auth.php';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (function_exists('isMembre')) {
            isMembre(true);
        }
        parent::tearDown();
    }

    public function testIsMembreReturnsFalseWhenNotLoggedIn(): void
    {
        $_SESSION = [];
        $this->assertFalse(isMembre(true));
    }

    public function testIsMembreReturnsTrueForAdmin(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['statut_adhesion'] = 'non_membre'; // Even if non_membre, admin has full access

        $this->assertTrue(isMembre(true));
    }

    public function testIsMembreReturnsTrueForOfficialMembre(): void
    {
        $_SESSION['user_id'] = 2;
        $_SESSION['role'] = 'user';
        $_SESSION['statut_adhesion'] = 'membre';

        $this->assertTrue(isMembre(true));
    }

    public function testIsMembreReturnsFalseForNonMembre(): void
    {
        $_SESSION['user_id'] = 3;
        $_SESSION['role'] = 'user';
        $_SESSION['statut_adhesion'] = 'non_membre';

        $this->assertFalse(isMembre(true));
    }

    public function testIsMembreReturnsFalseForEnAttente(): void
    {
        $_SESSION['user_id'] = 4;
        $_SESSION['role'] = 'user';
        $_SESSION['statut_adhesion'] = 'en_attente';

        $this->assertFalse(isMembre(true));
    }

    public function testCacheInvalidatesAutomaticallyWhenUserChanges(): void
    {
        $_SESSION['user_id'] = 100;
        $_SESSION['role'] = 'user';
        $_SESSION['statut_adhesion'] = 'membre';
        $this->assertTrue(isMembre());

        // Switch to a non-member without passing $resetCache
        $_SESSION['user_id'] = 101;
        $_SESSION['role'] = 'user';
        $_SESSION['statut_adhesion'] = 'non_membre';
        $this->assertFalse(isMembre());
    }
}
