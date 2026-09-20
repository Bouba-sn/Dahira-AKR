<?php
/**
 * Script Cron pour l'Adhan
 * À exécuter toutes les minutes via un service cron externe (cron-job.org, easycron, etc.)
 * 
 * URL du cron: https://votre-site.com/cron/adhan_cron.php
 * 
 * Configurez le cron pour exécuter cette URL toutes les minutes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dahira.test');

require_once __DIR__ . '/../config/database.php';

$pdo = db();

// Vérifier l'heure actuelle
$now = new DateTime();
$currentTime = $now->format('H:i');
$today = $now->format('Y-m-d');

// Récupérer les heures de prière actives
$prieres = $pdo->query("SELECT fajr, dhuhr, asr, maghrib, isha FROM heures_prieres WHERE actif=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$prieres) {
    // Pas d'horaires actifs
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
$logFile = __DIR__ . '/adhan_log.txt';

foreach ($prieres as $name => $time) {
    $prayTime = substr($time, 0, 5);
    
    if ($prayTime === $currentTime) {
        $logMessage = "[" . $now->format('Y-m-d H:i:s') . "] Adhan pour $name ($prayTime)";
        
        // Envoyer via Web Push si des abonnés
        $subscribers = $pdo->query("SELECT * FROM push_subscriptions WHERE actif = 1")->fetchAll();
        
        foreach ($subscribers as $sub) {
            sendWebPush([
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['p256dh'],
                    'auth' => $sub['auth']
                ]
            ], [
                'title' => 'Dahira AKR - ' . $prieresNames[$name],
                'body' => 'C\'est l\'heure de la prière (' . $prieresNames[$name] . ')',
                'icon' => '/assets/icons/icon-192x192.png',
                'tag' => 'adhan-' . $name
            ]);
        }
        
        $sent[] = $name;
        file_put_contents($logFile, $logMessage . " - " . count($subscribers) . " notifications envoyées\n", FILE_APPEND);
    }
}

if (count($sent) > 0) {
    echo json_encode(['success' => true, 'prayers' => $sent, 'time' => $currentTime]);
} else {
    echo json_encode(['success' => true, 'message' => 'Aucune prière à cette heure', 'time' => $currentTime]);
}

function sendWebPush($subscription, $notification) {
    // Cette fonction nécessite la library web-push-php
    // Installez: composer require minish/web-push-php
    // Pour l'instant, on log seulement
    
    error_log("Would send push to: " . $subscription['endpoint']);
}
