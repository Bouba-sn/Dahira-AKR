<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\CotisationService;
use InvalidArgumentException;
use PDO;

final class CotisationServiceTest extends TestCase
{
    private CotisationService $service;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        // Use in-memory SQLite PDO for isolated tests
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo = $pdo;
        
        $pdo->exec("
            CREATE TABLE utilisateurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL,
                email TEXT,
                telephone TEXT,
                role TEXT DEFAULT 'user',
                categorie_membre TEXT DEFAULT 'simple',
                photo_membre TEXT,
                statut_adhesion TEXT DEFAULT 'membre'
            );
            CREATE TABLE cotisation_campagnes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL,
                date_debut TEXT NOT NULL,
                date_fin TEXT NOT NULL,
                active INTEGER DEFAULT 1,
                description TEXT
            );
            CREATE TABLE cotisation_paiements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campagne_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                admin_id INTEGER,
                montant REAL NOT NULL,
                date_paiement TEXT NOT NULL,
                mode_paiement TEXT DEFAULT 'especes',
                recu_numero TEXT,
                commentaire TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->service = new CotisationService($pdo);
    }

    public function testGetObjectifForCategories(): void
    {
        $this->assertEquals(35000.0, $this->service->getObjectif('bureau'));
        $this->assertEquals(30000.0, $this->service->getObjectif('simple'));
        $this->assertEquals(15000.0, $this->service->getObjectif('enfant'));
        // Fallback default
        $this->assertEquals(30000.0, $this->service->getObjectif('autre_inconnu'));
    }

    public function testCalculateMemberSituationForPartialPayment(): void
    {
        // Membre simple (objectif 30 000 FCFA), verse 10 000 FCFA
        $situation = $this->service->calculateSituation(30000.0, 10000.0);

        $this->assertEquals(10000.0, $situation['total_paye']);
        $this->assertEquals(30000.0, $situation['objectif']);
        $this->assertEquals(20000.0, $situation['reste']);
        $this->assertEquals(0.0, $situation['avance']);
        $this->assertEquals(33.3, $situation['progression']);
        $this->assertEquals('en_cours', $situation['statut']);
        $this->assertEquals(3, $situation['priorite']);
    }

    public function testCalculateMemberSituationForCompletedPayment(): void
    {
        // Membre du bureau (objectif 35 000 FCFA), verse 35 000 FCFA
        $situation = $this->service->calculateSituation(35000.0, 35000.0);

        $this->assertEquals(35000.0, $situation['total_paye']);
        $this->assertEquals(35000.0, $situation['objectif']);
        $this->assertEquals(0.0, $situation['reste']);
        $this->assertEquals(0.0, $situation['avance']);
        $this->assertEquals(100.0, $situation['progression']);
        $this->assertEquals('termine', $situation['statut']);
        $this->assertEquals(2, $situation['priorite']);
    }

    public function testCalculateMemberSituationForAdvancePaymentNeverReturnsNegativeReste(): void
    {
        // Membre du bureau (objectif 35 000 FCFA), verse 40 000 FCFA
        $situation = $this->service->calculateSituation(35000.0, 40000.0);

        $this->assertEquals(40000.0, $situation['total_paye']);
        $this->assertEquals(35000.0, $situation['objectif']);
        $this->assertEquals(0.0, $situation['reste'], 'Le montant restant à payer ne doit JAMAIS être négatif');
        $this->assertEquals(5000.0, $situation['avance']);
        $this->assertGreaterThanOrEqual(100.0, $situation['progression']);
        $this->assertEquals('avance', $situation['statut']);
        $this->assertEquals(1, $situation['priorite']);
    }

    public function testCalculateMemberSituationForZeroPayment(): void
    {
        // Enfant (objectif 15 000 FCFA), n'a pas encore cotisé
        $situation = $this->service->calculateSituation(15000.0, 0.0);

        $this->assertEquals(0.0, $situation['total_paye']);
        $this->assertEquals(15000.0, $situation['objectif']);
        $this->assertEquals(15000.0, $situation['reste']);
        $this->assertEquals(0.0, $situation['avance']);
        $this->assertEquals(0.0, $situation['progression']);
        $this->assertEquals('aucune', $situation['statut']);
        $this->assertEquals(4, $situation['priorite']);
    }

    public function testSortMembersAppliesIntelligentRankingRules(): void
    {
        $members = [
            [
                'id' => 1,
                'nom' => 'Moustapha (En cours faible)',
                'situation' => $this->service->calculateSituation(30000.0, 5000.0) // 16.7%, prio 3
            ],
            [
                'id' => 2,
                'nom' => 'Ousmane (Aucune cotisation)',
                'situation' => $this->service->calculateSituation(30000.0, 0.0) // 0%, prio 4
            ],
            [
                'id' => 3,
                'nom' => 'Ibrahima (En avance)',
                'situation' => $this->service->calculateSituation(35000.0, 45000.0) // +10k, prio 1
            ],
            [
                'id' => 4,
                'nom' => 'Fatou (Terminé)',
                'situation' => $this->service->calculateSituation(30000.0, 30000.0) // 100%, prio 2
            ],
            [
                'id' => 5,
                'nom' => 'Abdou (En cours fort)',
                'situation' => $this->service->calculateSituation(15000.0, 12000.0) // 80%, prio 3
            ],
        ];

        $sorted = $this->service->sortMembers($members);

        $this->assertEquals(3, $sorted[0]['id'], 'Priorité 1 : Le membre en avance doit être au sommet');
        $this->assertEquals(4, $sorted[1]['id'], 'Priorité 2 : Le membre ayant terminé doit être 2ème');
        $this->assertEquals(5, $sorted[2]['id'], 'Priorité 3 : Le membre en cours à 80% doit devancer celui à 16.7%');
        $this->assertEquals(1, $sorted[3]['id'], 'Priorité 3 : Le membre en cours à 16.7% doit être 4ème');
        $this->assertEquals(2, $sorted[4]['id'], 'Priorité 4 : Le membre sans cotisation doit être tout en bas');
    }

    public function testValidatePaiementThrowsExceptionOnZeroOrNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->validatePaiement(0.0, '2026-09-18');
    }

    public function testValidatePaiementThrowsExceptionOnNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->validatePaiement(-2500.0, '2026-09-18');
    }

    public function testFormatFcfa(): void
    {
        $this->assertEquals('35 000 FCFA', $this->service->formatFcfa(35000.0));
        $this->assertEquals('0 FCFA', $this->service->formatFcfa(0.0));
        $this->assertEquals('1 500 FCFA', $this->service->formatFcfa(1500.0));
    }

    public function testAddPaiementMemberWaveSelfService(): void
    {
        $this->pdo->exec("INSERT INTO utilisateurs (id, nom, email, categorie_membre, statut_adhesion) VALUES (10, 'Fatou', 'fatou@test.sn', 'simple', 'membre')");
        $this->pdo->exec("INSERT INTO cotisation_campagnes (id, nom, date_debut, date_fin, active) VALUES (1, 'Campagne 2026', '2026-01-01', '2026-12-31', 1)");

        $paiementId = $this->service->addPaiement(
            1,
            10,
            10000.0,
            '2026-09-20',
            null, // Self-service by member
            'wave',
            'Cotisation Wave membre (Réf: TX12345)'
        );

        $this->assertGreaterThan(0, $paiementId);

        $detail = $this->service->getMemberDetail(10, 1);
        $this->assertNotNull($detail);
        $this->assertEquals(10000.0, $detail['situation']['total_paye']);
        $this->assertEquals(20000.0, $detail['situation']['reste']);
        $this->assertCount(1, $detail['paiements']);
        $this->assertEquals('wave', $detail['paiements'][0]['mode_paiement']);
        $this->assertNull($detail['paiements'][0]['admin_id']);
    }

    public function testAddPaiementAdminRecorded(): void
    {
        $this->pdo->exec("INSERT INTO utilisateurs (id, nom, role) VALUES (1, 'Admin Principal', 'admin')");
        $this->pdo->exec("INSERT INTO utilisateurs (id, nom, categorie_membre, statut_adhesion) VALUES (20, 'Modou', 'bureau', 'membre')");
        $this->pdo->exec("INSERT INTO cotisation_campagnes (id, nom, date_debut, date_fin, active) VALUES (2, 'Campagne 2026', '2026-01-01', '2026-12-31', 1)");

        $paiementId = $this->service->addPaiement(
            2,
            20,
            15000.0,
            '2026-09-20',
            1, // Enregistré par l'admin
            'especes',
            'En espèces en main propre'
        );

        $this->assertGreaterThan(0, $paiementId);

        $detail = $this->service->getMemberDetail(20, 2);
        $this->assertNotNull($detail);
        $this->assertEquals(15000.0, $detail['situation']['total_paye']);
        $this->assertEquals(20000.0, $detail['situation']['reste']); // Bureau: 35 000 - 15 000 = 20 000
        $this->assertEquals(1, $detail['paiements'][0]['admin_id']);
        $this->assertEquals('Admin Principal', $detail['paiements'][0]['admin_nom']);
    }
}
