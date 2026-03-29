<?php
require_once __DIR__ . '/config/database.php';
try {
    $pdo = db();
    $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN statut_adhesion ENUM('non_membre','en_attente','membre') DEFAULT 'non_membre'");
    echo "Col 1 added\n";
} catch(Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN telephone VARCHAR(20) DEFAULT NULL");
    echo "Col 2 added\n";
} catch(Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN adresse TEXT DEFAULT NULL");
    echo "Col 3 added\n";
} catch(Exception $e) { echo $e->getMessage() . "\n"; }

echo "Done.\n";
