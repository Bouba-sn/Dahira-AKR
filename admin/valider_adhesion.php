<?php
// admin/valider_adhesion.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $userId = (int)$_POST['user_id'];
    $pdo = db();
    $action = $_POST['action'] ?? 'valider';

    if ($action === 'refuser') {
        // Refuser l'adhésion
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut_adhesion = 'non_membre', carte_physique = 0 WHERE id = ?");
        $stmt->execute([$userId]);

        addNotification(
            $userId,
            'Demande d\'adhésion non approuvée',
            "Votre demande d'adhésion n'a pas été validée. Pour toute réclamation, veuillez contacter le bureau du Dahira.",
            '/pages/parametres.php',
            'adhesion'
        );

        $msg = 'Adhésion refusée.';
    } else {
        // Valider l'adhésion avec la catégorie spécifiée par l'administrateur
        $categorie = trim($_POST['categorie_membre'] ?? 'simple');
        if (!in_array($categorie, ['bureau', 'simple', 'enfant'])) {
            $categorie = 'simple';
        }

        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut_adhesion = 'membre', categorie_membre = ? WHERE id = ? AND statut_adhesion = 'en_attente'");
        $stmt->execute([$categorie, $userId]);

        $catLabel = match($categorie) {
            'bureau' => 'Membre du Bureau (35 000 F / an)',
            'enfant' => 'Enfant (15 000 F / an)',
            default  => 'Membre Simple (30 000 F / an)'
        };

        // Notifier le membre de la validation de son adhésion
        addNotification(
            $userId,
            'Adhésion approuvée !',
            "Félicitations ! Votre adhésion a été validée avec le statut : {$catLabel}. Votre carte officielle est désormais active dans Paramètres.",
            '/pages/parametres.php',
            'adhesion'
        );

        $msg = "Adhésion validée avec succès en tant que : {$catLabel}.";
    }
}

$redirectTo = $_POST['redirect_to'] ?? '/admin/dashboard.php';
header('Location: ' . $redirectTo . (str_contains($redirectTo, '?') ? '&' : '?') . 'msg=' . urlencode($msg ?? 'Opération effectuée.'));
exit;
