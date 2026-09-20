<?php
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$email = 'bouba@gmail.com';
$password = password_hash('Boubacar1712', PASSWORD_BCRYPT, ['cost' => 12]);

// Check if user exists
$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $stmt = $pdo->prepare("UPDATE utilisateurs SET role = 'admin', password = ? WHERE email = ?");
    $stmt->execute([$password, $email]);
    echo "Admin mis à jour !";
} else {
    // Create new user with admin role
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
    $stmt->execute(['Admin', $email, $password]);
    echo "Compte admin créé !";
}