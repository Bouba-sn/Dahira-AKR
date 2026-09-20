<?php
/**
 * Cron Endpoint pour l'Adhan
 * À appeler par un service cron externe (cron-job.org, etc.)
 * toutes les minutes
 * 
 * URL à configurer: https://votre-domaine.com/cron/adhan.php
 */

header('Content-Type: application/json');
error_reporting(0);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/database.php';

$pdo = db();

// Vérifier l'heure actuelle
$now = new DateTime();
$currentTime = $now->format('H:i');

// Récupérer les heures de prière actives
$prieres = $pdo->query("SELECT fajr, dhuhr, asr, maghrib, isha FROM heures_prieres WHERE actif=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$prieres) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'no active schedule']);
    exit;
}

$prieresNames = [
    'fajr' => 'Fajr',
    'dhuhr' => 'Dhuhr', 
    'asr' => 'Asr',
    'maghrib' => 'Maghrib',
    'isha' => 'Isha'
];

$sent = [];

foreach ($prieres as $name => $time) {
    $prayTime = substr($time, 0, 5);
    
    if ($prayTime === $currentTime) {
        $sent[] = [
            'name' => $name,
            'time' => $prayTime
        ];
        
        // Log pour debug
        $logFile = __DIR__ . '/adhan_log.txt';
        $logMsg = "[" . $now->format('Y-m-d H:i:s') . "] Adhan: $name à $prayTime\n";
        @file_put_contents($logFile, $logMsg, FILE_APPEND);
    }
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'time' => $currentTime,
    'prayers_triggered' => $sent
]);
