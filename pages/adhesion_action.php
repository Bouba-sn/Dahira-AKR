<?php
// pages/adhesion_action.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $typeAdhesion = ($_POST['type_adhesion'] ?? '') === 'carte_existante' ? 'carte_existante' : 'nouvelle';
    $cartePhysique = ($typeAdhesion === 'carte_existante') ? 1 : 0;
    $numeroCarte = !empty($_POST['numero_carte']) ? trim($_POST['numero_carte']) : null;
    
    if (!empty($telephone) && !empty($adresse)) {
        $pdo = db();
        
        $photoFileName = null;
        if (isset($_FILES['photo_membre']) && $_FILES['photo_membre']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['photo_membre']['tmp_name'];
            $name = basename($_FILES['photo_membre']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $dir = __DIR__ . '/../assets/uploads/membres/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $newName = 'membre_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($tmp, $dir . $newName)) {
                    $photoFileName = $newName;
                }
            }
        }

        if ($photoFileName) {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET telephone = ?, adresse = ?, photo_membre = ?, type_adhesion = ?, carte_physique = ?, numero_carte = ?, statut_adhesion = 'en_attente' WHERE id = ?");
            $stmt->execute([$telephone, $adresse, $photoFileName, $typeAdhesion, $cartePhysique, $numeroCarte, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET telephone = ?, adresse = ?, type_adhesion = ?, carte_physique = ?, numero_carte = ?, statut_adhesion = 'en_attente' WHERE id = ?");
            $stmt->execute([$telephone, $adresse, $typeAdhesion, $cartePhysique, $numeroCarte, $_SESSION['user_id']]);
        }

        // Notifier tous les administrateurs pour vérification et choix de la catégorie (simple, bureau, enfant)
        $admins = $pdo->query("SELECT id FROM utilisateurs WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
        $nomDemandeur = $_SESSION['user_nom'] ?? 'Un membre';

        if ($typeAdhesion === 'carte_existante') {
            $titreNotif = "Adhésion Carte Physique à vérifier";
            $msgNotif = "{$nomDemandeur} a déclaré posséder déjà sa carte physique. Veuillez vérifier son dossier, préciser sa catégorie (simple, bureau ou enfant) et valider.";
        } else {
            $titreNotif = "Nouvelle Adhésion Wave à valider";
            $msgNotif = "{$nomDemandeur} a validé son adhésion Wave. Veuillez préciser sa catégorie (simple, bureau ou enfant) et valider.";
        }

        foreach ($admins as $adminId) {
            addNotification($adminId, $titreNotif, $msgNotif, '/admin/dashboard.php#adhesions', 'adhesion');
        }

        // Notification de confirmation au demandeur
        addNotification(
            $_SESSION['user_id'],
            "Demande d'adhésion enregistrée",
            "Votre demande d'adhésion a été transmise avec succès. L'administrateur va vérifier votre dossier et activer votre carte officielle.",
            '/pages/parametres.php',
            'adhesion'
        );
    }
}
header('Location: /pages/parametres.php');
exit;
