<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\CotisationService;

$pdo = db();
$cotisationService = new CotisationService($pdo);

// 1. Définir Administrateur comme membre du bureau
$pdo->exec("UPDATE utilisateurs SET categorie_membre = 'bureau' WHERE id = 1");

// 2. Récupérer ou créer la campagne active
$campagne = $cotisationService->getActiveCampagne();
if (!$campagne) {
    $campagneId = $cotisationService->createCampagne(
        'Cotisation 2026–2027',
        '2026-09-01',
        '2027-08-31',
        'Campagne annuelle officielle du lendemain du Gamou 2026 au Gamou 2027'
    );
} else {
    $campagneId = (int)$campagne['id'];
}

// 3. Ajouter des membres d'exemple pour illustrer chaque catégorie et chaque statut
$membresData = [
    [
        'nom' => 'Serigne Cheikh Ndiaye',
        'email' => 'cheikh.ndiaye@dahira.sn',
        'tel' => '77 450 12 34',
        'role' => 'user',
        'cat' => 'bureau', // 35 000
        'versements' => [
            ['montant' => 20000, 'date' => '2026-09-05', 'mode' => 'wave', 'note' => 'Premier versement'],
            ['montant' => 20000, 'date' => '2026-09-12', 'mode' => 'especes', 'note' => 'Solde et don avance'],
        ] // Total: 40 000 => EN AVANCE (+5 000)
    ],
    [
        'nom' => 'Fatou Binetou Diop',
        'email' => 'fatou.diop@dahira.sn',
        'tel' => '78 120 44 55',
        'role' => 'user',
        'cat' => 'simple', // 30 000
        'versements' => [
            ['montant' => 15000, 'date' => '2026-09-02', 'mode' => 'orange_money', 'note' => 'Tranche 1'],
            ['montant' => 15000, 'date' => '2026-09-15', 'mode' => 'wave', 'note' => 'Tranche 2 (Solde)'],
        ] // Total: 30 000 => TERMINÉE
    ],
    [
        'nom' => 'Abdourahmane Sow (Enfant)',
        'email' => 'enfant.abdou@dahira.local',
        'tel' => '76 890 11 22',
        'role' => 'user',
        'cat' => 'enfant', // 15 000
        'versements' => [
            ['montant' => 5000, 'date' => '2026-09-08', 'mode' => 'especes', 'note' => 'Donné par son tuteur'],
            ['montant' => 5000, 'date' => '2026-09-16', 'mode' => 'especes', 'note' => 'Deuxième versement'],
        ] // Total: 10 000 / 15 000 => EN COURS (66.7%)
    ],
    [
        'nom' => 'Moussa Ndao',
        'email' => 'moussa.ndao@dahira.sn',
        'tel' => '70 333 44 55',
        'role' => 'user',
        'cat' => 'simple', // 30 000
        'versements' => [
            ['montant' => 5000, 'date' => '2026-09-10', 'mode' => 'wave', 'note' => 'Premier acompte'],
        ] // Total: 5 000 / 30 000 => EN COURS (16.7%)
    ],
    [
        'nom' => 'Aïssatou Fall',
        'email' => 'aissatou.fall@dahira.sn',
        'tel' => '77 654 32 10',
        'role' => 'user',
        'cat' => 'simple', // 30 000
        'versements' => [] // Total: 0 => AUCUNE COTISATION
    ],
    [
        'nom' => 'Khadim Rassoul Sy (Enfant)',
        'email' => 'enfant.khadim@dahira.local',
        'tel' => '77 888 99 00',
        'role' => 'user',
        'cat' => 'enfant', // 15 000
        'versements' => [] // Total: 0 => AUCUNE COTISATION
    ]
];

$pwdHash = password_hash('passer123', PASSWORD_DEFAULT);

foreach ($membresData as $m) {
    // Vérifier si existe par email
    $chk = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $chk->execute([$m['email']]);
    $uId = $chk->fetchColumn();

    if (!$uId) {
        $ins = $pdo->prepare("
            INSERT INTO utilisateurs (nom, email, password, telephone, role, categorie_membre, statut_adhesion)
            VALUES (?, ?, ?, ?, ?, ?, 'membre')
        ");
        $ins->execute([
            $m['nom'],
            $m['email'],
            $pwdHash,
            $m['tel'],
            $m['role'],
            $m['cat']
        ]);
        $uId = (int)$pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE utilisateurs SET categorie_membre = ?, statut_adhesion = 'membre' WHERE id = ?")->execute([$m['cat'], $uId]);
    }

    // Insérer les paiements de test s'il n'y en a pas encore
    $countP = (int)$pdo->query("SELECT COUNT(*) FROM cotisation_paiements WHERE user_id = $uId AND campagne_id = $campagneId")->fetchColumn();
    if ($countP === 0 && !empty($m['versements'])) {
        foreach ($m['versements'] as $v) {
            $cotisationService->addPaiement(
                $campagneId,
                $uId,
                (float)$v['montant'],
                $v['date'],
                1, // Admin id
                $v['mode'],
                $v['note']
            );
        }
    }
}

echo "Seed cotisations effectué avec succès !\n";
