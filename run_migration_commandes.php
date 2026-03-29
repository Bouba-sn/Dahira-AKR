<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE commandes ADD COLUMN telephone_client VARCHAR(20) DEFAULT NULL;");
    echo "Migration OK : Colonne telephone_client ajoutée.";
} catch (PDOException $e) {
    echo "Erreur ou dejà ajoutée: " . $e->getMessage();
}
