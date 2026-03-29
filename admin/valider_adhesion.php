<?php
// admin/valider_adhesion.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    $pdo = db();
    
    // Validate adhesion
    $stmt = $pdo->prepare("UPDATE utilisateurs SET statut_adhesion = 'membre' WHERE id = ? AND statut_adhesion = 'en_attente'");
    $stmt->execute([$userId]);
}

header('Location: /admin/dashboard.php');
exit;
