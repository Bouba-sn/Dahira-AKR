<?php
// config/database.php

// Détection environnement local (Laragon / Localhost) vs Production (InfinityFree)
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = empty($httpHost)
    || str_contains($httpHost, 'localhost')
    || str_contains($httpHost, '127.0.0.1')
    || str_ends_with($httpHost, '.test')
    || (php_sapi_name() === 'cli');

if ($isLocal) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'dahira_akr_v2');
    define('DB_USER', 'root');
    define('DB_PASS', 'root');
} else {
    define('DB_HOST', 'sql305.infinityfree.com');
    define('DB_NAME', 'if0_41557146_dahira');
    define('DB_USER', 'if0_41557146');
    define('DB_PASS', 'CYzpwZ6KIgea');
}
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Tentative avec mot de passe vide si root/root échoue sur Laragon
                if (DB_HOST === 'localhost' && DB_USER === 'root') {
                    try {
                        self::$instance = new PDO($dsn, DB_USER, '', $options);
                        return self::$instance;
                    } catch (PDOException $e2) {
                        // Conserver l'erreur initiale
                    }
                }
                die("Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage()));
            }
        }
        return self::$instance;
    }
}

// Fonction helper globale
function db(): PDO {
    return Database::getConnection();
}

