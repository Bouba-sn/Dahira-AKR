<?php
// pages/adhesion_action.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $telephone = $_POST['telephone'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    
    if (!empty($telephone) && !empty($adresse)) {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE utilisateurs SET telephone = ?, adresse = ?, statut_adhesion = 'en_attente' WHERE id = ?");
        $stmt->execute([$telephone, $adresse, $_SESSION['user_id']]);
    }
}
header('Location: /pages/accueil.php');
exit;
