<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use InvalidArgumentException;

class CotisationService
{
    public const OBJECTIF_BUREAU = 35000.0;
    public const OBJECTIF_SIMPLE = 30000.0;
    public const OBJECTIF_ENFANT = 15000.0;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retourne l'objectif annuel de cotisation selon la catégorie du membre.
     */
    public function getObjectif(string $categorie): float
    {
        return match (strtolower(trim($categorie))) {
            'bureau' => self::OBJECTIF_BUREAU,
            'enfant' => self::OBJECTIF_ENFANT,
            default  => self::OBJECTIF_SIMPLE,
        };
    }

    /**
     * Calcule la situation financière précise d'un membre pour une campagne :
     * - Reste à payer (jamais négatif !)
     * - Avance éventuelle
     * - Pourcentage de progression
     * - Statut ('avance', 'termine', 'en_cours', 'aucune')
     * - Priorité de tri (1: avance, 2: termine, 3: en_cours, 4: aucune)
     *
     * @return array{
     *   total_paye: float,
     *   objectif: float,
     *   reste: float,
     *   avance: float,
     *   progression: float,
     *   statut: string,
     *   priorite: int
     * }
     */
    public function calculateSituation(float $objectif, float $totalPaye): array
    {
        $totalPaye = round((float)$totalPaye, 2);
        $objectif  = round((float)$objectif, 2);

        if ($totalPaye > $objectif) {
            $reste = 0.0;
            $avance = round($totalPaye - $objectif, 2);
            $progression = $objectif > 0 ? round(($totalPaye / $objectif) * 100, 1) : 100.0;
            $statut = 'avance';
            $priorite = 1;
        } elseif ($totalPaye == $objectif && $objectif > 0) {
            $reste = 0.0;
            $avance = 0.0;
            $progression = 100.0;
            $statut = 'termine';
            $priorite = 2;
        } elseif ($totalPaye > 0) {
            $reste = round($objectif - $totalPaye, 2);
            $avance = 0.0;
            $progression = $objectif > 0 ? round(($totalPaye / $objectif) * 100, 1) : 0.0;
            $statut = 'en_cours';
            $priorite = 3;
        } else {
            $reste = $objectif;
            $avance = 0.0;
            $progression = 0.0;
            $statut = 'aucune';
            $priorite = 4;
        }

        return [
            'total_paye'  => $totalPaye,
            'objectif'    => $objectif,
            'reste'       => $reste,
            'avance'      => $avance,
            'progression' => $progression,
            'statut'      => $statut,
            'priorite'    => $priorite,
        ];
    }

    /**
     * Classe intelligemment la liste des membres selon la règle stricte du Dahira :
     * 1. Priorité 1 : EN AVANCE
     * 2. Priorité 2 : COTISATION TERMINÉE
     * 3. Priorité 3 : EN COURS (classés par progression décroissante)
     * 4. Priorité 4 : AUCUNE COTISATION
     */
    public function sortMembers(array $members): array
    {
        usort($members, function ($a, $b) {
            $sitA = $a['situation'];
            $sitB = $b['situation'];

            // 1. Ordre par priorité de groupe (1 à 4)
            if ($sitA['priorite'] !== $sitB['priorite']) {
                return $sitA['priorite'] <=> $sitB['priorite'];
            }

            // 2. À l'intérieur du groupe, par pourcentage de progression décroissant
            if ($sitA['progression'] !== $sitB['progression']) {
                return $sitB['progression'] <=> $sitA['progression'];
            }

            // 3. Par montant total payé décroissant
            if ($sitA['total_paye'] !== $sitB['total_paye']) {
                return $sitB['total_paye'] <=> $sitA['total_paye'];
            }

            // 4. Par nom alphabétique
            return strcasecmp($a['nom'] ?? '', $b['nom'] ?? '');
        });

        return $members;
    }

    /**
     * Valide un montant de paiement et une date.
     */
    public function validatePaiement(float $montant, string $date): void
    {
        if ($montant <= 0) {
            throw new InvalidArgumentException('Le montant versé doit être strictement supérieur à 0 FCFA.');
        }

        if (empty(trim($date))) {
            throw new InvalidArgumentException('La date du versement est obligatoire.');
        }
    }

    /**
     * Formate un montant en devise locale (ex: 35 000 FCFA).
     */
    public function formatFcfa(float|int $montant): string
    {
        return number_format((float)$montant, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Récupère la campagne active actuelle.
     */
    public function getActiveCampagne(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM cotisation_campagnes WHERE active = 1 ORDER BY date_debut DESC LIMIT 1");
        $res = $stmt->fetch();
        if (!$res) {
            // Fallback : première campagne existante
            $res = $this->pdo->query("SELECT * FROM cotisation_campagnes ORDER BY date_debut DESC LIMIT 1")->fetch();
        }
        return $res ?: null;
    }

    /**
     * Récupère toutes les campagnes disponibles.
     */
    public function getAllCampagnes(): array
    {
        return $this->pdo->query("SELECT * FROM cotisation_campagnes ORDER BY date_debut DESC")->fetchAll();
    }

    /**
     * Récupère une campagne par son ID.
     */
    public function getCampagneById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cotisation_campagnes WHERE id = ?");
        $stmt->execute([$id]);
        $campagne = $stmt->fetch();
        return $campagne ?: null;
    }

    /**
     * Crée une nouvelle campagne annuelle.
     */
    public function createCampagne(string $nom, string $dateDebut, string $dateFin, ?string $description = null, bool $setAsActive = true): int
    {
        if (empty(trim($nom))) {
            throw new InvalidArgumentException('Le nom de la campagne est obligatoire.');
        }

        if ($setAsActive) {
            $this->pdo->exec("UPDATE cotisation_campagnes SET active = 0");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO cotisation_campagnes (nom, date_debut, date_fin, active, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($nom),
            $dateDebut,
            $dateFin,
            $setAsActive ? 1 : 0,
            $description
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Définit la campagne active.
     */
    public function setActiveCampagne(int $id): bool
    {
        $this->pdo->exec("UPDATE cotisation_campagnes SET active = 0");
        $stmt = $this->pdo->prepare("UPDATE cotisation_campagnes SET active = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les statistiques financières globales pour une campagne donnée.
     */
    public function getGlobalStats(int $campagneId): array
    {
        $members = $this->getMembersWithSituation($campagneId);

        $totalMembres = count($members);
        $totalBureau = 0;
        $totalSimples = 0;
        $totalEnfants = 0;

        $montantAttendu = 0.0;
        $montantCollecte = 0.0;

        $nbAvance = 0;
        $nbTermine = 0;
        $nbEnCours = 0;
        $nbAucune = 0;

        foreach ($members as $m) {
            $cat = $m['categorie_membre'] ?? 'simple';
            match ($cat) {
                'bureau' => $totalBureau++,
                'enfant' => $totalEnfants++,
                default  => $totalSimples++,
            };

            $sit = $m['situation'];
            $montantAttendu += $sit['objectif'];
            $montantCollecte += $sit['total_paye'];

            match ($sit['statut']) {
                'avance'   => $nbAvance++,
                'termine'  => $nbTermine++,
                'en_cours' => $nbEnCours++,
                default    => $nbAucune++,
            };
        }

        $montantRestant = max(0.0, $montantAttendu - $montantCollecte);
        $tauxGlobal = $montantAttendu > 0 ? round(($montantCollecte / $montantAttendu) * 100, 1) : 0.0;

        return [
            'total_membres'    => $totalMembres,
            'total_bureau'     => $totalBureau,
            'total_simples'    => $totalSimples,
            'total_enfants'    => $totalEnfants,
            'montant_attendu'  => $montantAttendu,
            'montant_collecte' => $montantCollecte,
            'montant_restant'  => $montantRestant,
            'taux_global'      => $tauxGlobal,
            'nb_avance'        => $nbAvance,
            'nb_termine'       => $nbTermine,
            'nb_en_cours'      => $nbEnCours,
            'nb_aucune'        => $nbAucune,
            'nb_ayant_cotise'  => $nbAvance + $nbTermine + $nbEnCours,
        ];
    }

    /**
     * Récupère la liste de tous les membres avec leur situation pour la campagne, avec recherche et filtres.
     */
    public function getMembersWithSituation(
        int $campagneId,
        ?string $search = null,
        ?string $filterCat = null,
        ?string $filterStatut = null
    ): array {
        // 1. Récupération des utilisateurs et de la somme de leurs paiements dans la campagne
        $sql = "
            SELECT u.id, u.nom, u.prenom, u.email, u.telephone, u.role, u.categorie_membre, u.fonction, u.avatar, u.photo_membre, u.statut_adhesion,
                   COALESCE(SUM(p.montant), 0) AS total_paye,
                   COUNT(p.id) AS nb_versements
            FROM utilisateurs u
            LEFT JOIN cotisation_paiements p ON p.user_id = u.id AND p.campagne_id = ?
            WHERE (u.statut_adhesion = 'membre' OR u.role = 'admin')
        ";

        $params = [$campagneId];

        if (!empty($search)) {
            $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " GROUP BY u.id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $r) {
            $cat = $r['categorie_membre'] ?: 'simple';
            $objectif = $this->getObjectif($cat);
            $situation = $this->calculateSituation($objectif, (float)$r['total_paye']);

            // Filtrage par catégorie
            if (!empty($filterCat) && $filterCat !== 'tous' && $cat !== $filterCat) {
                continue;
            }

            // Filtrage par statut
            if (!empty($filterStatut) && $filterStatut !== 'tous' && $situation['statut'] !== $filterStatut) {
                continue;
            }

            $r['situation'] = $situation;
            $result[] = $r;
        }

        // Tri intelligent
        return $this->sortMembers($result);
    }

    /**
     * Récupère la fiche détaillée d'un membre avec tout l'historique chronologique de ses paiements.
     */
    public function getMemberDetail(int $userId, int $campagneId): ?array
    {
        $stmtUser = $this->pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();

        if (!$user) {
            return null;
        }

        $cat = $user['categorie_membre'] ?: 'simple';
        $objectif = $this->getObjectif($cat);

        // Historique des paiements dans la campagne
        $stmtPaiements = $this->pdo->prepare("
            SELECT p.*, a.nom AS admin_nom
            FROM cotisation_paiements p
            LEFT JOIN utilisateurs a ON a.id = p.admin_id
            WHERE p.user_id = ? AND p.campagne_id = ?
            ORDER BY p.date_paiement ASC, p.id ASC
        ");
        $stmtPaiements->execute([$userId, $campagneId]);
        $paiements = $stmtPaiements->fetchAll();

        // Calcul du cumul progressif
        $cumul = 0.0;
        foreach ($paiements as &$p) {
            $cumul += (float)$p['montant'];
            $p['cumul_progressif'] = $cumul;
        }
        unset($p);

        $situation = $this->calculateSituation($objectif, $cumul);

        return [
            'user'      => $user,
            'situation' => $situation,
            'paiements' => $paiements,
        ];
    }

    /**
     * Enregistre un nouveau versement pour un membre dans une campagne donnée.
     */
    public function addPaiement(
        int $campagneId,
        int $userId,
        float $montant,
        string $datePaiement,
        ?int $adminId = null,
        string $modePaiement = 'especes',
        ?string $commentaire = null
    ): int {
        $this->validatePaiement($montant, $datePaiement);

        // Génération numéro de reçu
        $recuNumero = 'REC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        $stmt = $this->pdo->prepare("
            INSERT INTO cotisation_paiements (campagne_id, user_id, admin_id, montant, date_paiement, mode_paiement, recu_numero, commentaire)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $campagneId,
            $userId,
            $adminId,
            $montant,
            $datePaiement,
            $modePaiement,
            $recuNumero,
            trim($commentaire ?? '')
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Supprime un paiement enregistré (avec protection et recalcul).
     */
    public function deletePaiement(int $paiementId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM cotisation_paiements WHERE id = ?");
        return $stmt->execute([$paiementId]);
    }

    /**
     * Met à jour la catégorie d'un membre (bureau, simple, enfant).
     */
    public function updateMemberCategory(int $userId, string $categorie): bool
    {
        $cat = match (strtolower(trim($categorie))) {
            'bureau' => 'bureau',
            'enfant' => 'enfant',
            default  => 'simple',
        };

        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET categorie_membre = ? WHERE id = ?");
        return $stmt->execute([$cat, $userId]);
    }
}
